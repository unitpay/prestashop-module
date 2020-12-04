<?php
/**
 * Created by PhpStorm.
 * User: aleksey
 * Date: 04.07.16
 * Time: 14:40
 */

class UnitpayPayment_processModuleFrontController extends ModuleFrontController
{

    public function initContent()
    {
        parent::initContent();

        $cart = $this->context->cart;

        $customer = new Customer($cart->id_customer);

        if (!Validate::isLoadedObject($customer))
            Tools::redirect('index.php?controller=order&step=1');

        $address = new Address($cart->id_address_delivery);

        $deliveries = $cart->getDeliveryOptionList();

        $cartDeliveryOption = isset($cart->delivery_option) ? json_decode($cart->delivery_option, true) : [];
        $selectedOption = isset($cartDeliveryOption[$cart->id_address_delivery]) ? array_shift(array_filter(explode(",", $cartDeliveryOption[$cart->id_address_delivery]))) : false;
        $delivery = isset($deliveries[$cart->id_address_delivery]) ? array_shift($deliveries[$cart->id_address_delivery]) : [];
        $deliveryPrices = $selectedOption !== false && isset($delivery["carrier_list"][$selectedOption]) ? $delivery["carrier_list"][$selectedOption] : [];

        $carrier = $selectedOption !== false ? new Carrier($selectedOption, $this->context->employee->id_lang) : false;

        $currency = $this->context->currency;
        $total = (float)$cart->getOrderTotal(true, Cart::BOTH);

        $this->module->validateOrder((int)$cart->id, Configuration::get('UNIT_OS_NEEDPAY'), $total, $this->module->displayName, NULL, array(), (int)$currency->id, false, $customer->secure_key);

        $domain = Configuration::get('UNIT_DOMAIN');
        $public_key = Configuration::get('UNIT_PUBLIC_KEY');
        $secret_key = Configuration::get('UNIT_SECRET_KEY');

        $desc = 'Оплата заказа из магазина ' . Configuration::get('PS_SHOP_NAME');
        $sum = $this->context->cart->getOrderTotal();
        $id_order = Order::getOrderByCartId($cart->id);

        $data = array(
            'sum' => $sum,
            'currency' => $currency->iso_code,
            'account' => $id_order,
            'desc' => $desc,
            'customerPhone' => isset($address->phone) ? preg_replace('/\D/', '', $address->phone) : '',
            'customerEmail' => $customer->email,
            'cashItems' => $this->getCashItems($cart, $currency, $deliveryPrices, $carrier, $address),
            'signature' => hash('sha256', join('{up}', array(
                $id_order,
                $currency->iso_code,
                $desc,
                $sum,
                $secret_key
            )))
        );


        Tools::redirect("https://{$domain}/pay/" . $public_key . '?' . http_build_query($data));
    }

    private function getCashItems($cart, $currency = null, $deliveryPrices = [], $carrier = false, $address = false)
    {
        $items = array_map(function ($item) use ($currency) {
            //reduction Скидка
            //reduction_without_tax Скидка без ндс
            //price_without_reduction Цена без скидок без учета кол-ва
            //price_without_reduction_without_tax Цена без скидок и ндс без учета кол-ва
            //price_wt Цена со скидками без учета кол-ва
            //total_wt Цена со скидками с учетом кол-ва

            return array(
                'name' => $item['name'],
                'count' => $item['quantity'],
                'price' => $item['price'],
                'nds' => isset($item["rate"]) ? $this->getTaxRates($item["rate"]) : "none",
                'currency' => $currency->iso_code,
                'type' => 'commodity',
            );
        }, $cart->getProducts());

        if(isset($deliveryPrices["price_with_tax"]) && floatval($deliveryPrices["price_with_tax"]) > 0) {
            //price_without_tax

            $items[] = array(
                'name' => "Услуги доставки",
                'count' => 1,
                'price' => $deliveryPrices["price_with_tax"],
                'nds' => $carrier != false && $address != false ? $this->getTaxRates($carrier->getTaxesRate($address)) : "none",
                'currency' => $currency->iso_code,
                'type' => 'service',
            );
        }

        return base64_encode(json_encode($items));
    }

    private function getTaxRates($rate){
        switch (intval($rate)){
            case 10:
                $vat = 'vat10';
                break;
            case 20:
                $vat = 'vat20';
                break;
            case 0:
                $vat = 'vat0';
                break;
            default:
                $vat = 'none';
        }

        return $vat;
    }

}