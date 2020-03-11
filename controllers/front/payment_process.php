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

        $currency = $this->context->currency;
        $total = (float)$cart->getOrderTotal(true, Cart::BOTH);

        $this->module->validateOrder((int)$cart->id, Configuration::get('UNIT_OS_NEEDPAY'), $total, $this->module->displayName, NULL, array(), (int)$currency->id, false, $customer->secure_key);


        $domain = Configuration::get('UNIT_DOMAIN');
        $public_key = Configuration::get('UNIT_PUBLIC_KEY');
        $desc = 'Оплата заказа из магазина ' . Configuration::get('PS_SHOP_NAME');
        $sum = $this->context->cart->getOrderTotal();
        $id_order = Order::getOrderByCartId($cart->id);
        $account = $id_order;

        Tools::redirect("https://$domain/pay/" . $public_key . '?' .
            http_build_query(array(
                'sum' => $sum,
                'currency' => $this->context->currency->iso_code,
                'account' => $account,
                'desc' => $desc,
                'customerEmail' => $customer->email,
                'cashItems' => $this->getCashItems($cart),
                'signature' => hash('sha256', join('{up}', array(
                    $account,
                    $this->context->currency->iso_code,
                    $desc,
                    $sum,
                    Configuration::get('UNIT_SECRET_KEY')
                )))
            )));

    }

    private function getCashItems($cart)
    {
        return base64_encode(
            json_encode(
                array_map(function ($item) {
                    return array(
                        'name' => $item['name'],
                        'count' => $item['quantity'],
                        'price' => $item['price']
                    );
                }, $cart->getProducts())));
    }

}