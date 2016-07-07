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


        $public_key = Configuration::get('UNIT_PUBLIC_KEY');
        $desc = 'Оплата заказа из магазина ' . Configuration::get('PS_SHOP_NAME');
        $sum = $this->context->cart->getOrderTotal();
        $order_id = Order::getOrderByCartId($cart->id);
        $account = $order_id;

        Tools::redirect('https://unitpay.ru/pay/' . $public_key . '?sum=' . $sum . '&account=' . $account . '&desc=' . $desc);

    }

}