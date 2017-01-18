<?php
/**
 * Created by PhpStorm.
 * User: aleksey
 * Date: 04.07.16
 * Time: 14:40
 */

class UnitpayCallbackModuleFrontController extends ModuleFrontController
{

    public $display_header = false;
    public $display_column_left = false;
    public $display_column_right = false;
    public $display_footer = false;

    public function postProcess()
    {
        header('Content-type:application/json;  charset=utf-8');

        $method = '';
        $params = [];

        if ((isset($_GET['params'])) && (isset($_GET['method'])) && (isset($_GET['params']['signature']))){
            $params = $_GET['params'];
            $method = $_GET['method'];
            $signature = $params['signature'];

            if (empty($signature)){
                $status_sign = false;
            }else{
                $status_sign = $this->verifySignature($params, $method);
            }

        }else{
            $status_sign = false;
        }

        if ($status_sign){
            switch ($method) {
                case 'check':
                    $result = $this->check( $params );
                    break;
                case 'pay':
                    $result = $this->payment( $params );
                    break;
                case 'error':
                    $result = $this->error( $params );
                    break;
                default:
                    $result = array('error' =>
                        array('message' => 'неверный метод')
                    );
                    break;
            }
        }else{
            $result = array('error' =>
                array('message' => 'неверная сигнатура')
            );
        }

        echo json_encode($result);

    }

    function verifySignature($params, $method)
    {
        $secret = Configuration::get('UNIT_SECRET_KEY');
        return $params['signature'] == $this->getSignature($method, $params, $secret);
    }


    function getSignature($method, array $params, $secretKey)
    {
        ksort($params);
        unset($params['sign']);
        unset($params['signature']);
        array_push($params, $secretKey);
        array_unshift($params, $method);

        return hash('sha256', join('{up}', $params));
    }

    function check( $params )
    {
        $order_id = $params['account'];
        $order = new Order($order_id);
        $currency = new Currency($order->id_currency);

        if (is_null($order->id)){
            $result = array('error' =>
                array('message' => 'заказа не существует')
            );
        }elseif ((float)$order->total_paid != (float)$params['orderSum']) {

            PrestaShopLogger::addLog(date('H:i:s', time()) . '| check method | Не совпадает сумма заказа| сумма в заказе -> '
                . $order->total_paid . '| сумма в параметрах ->' . $params['orderSum'], 3);

            $result = array('error' =>
                array('message' => 'не совпадает сумма заказа')
            );
        }elseif ($currency->iso_code != $params['orderCurrency']) {

            PrestaShopLogger::addLog(date('H:i:s', time()) . '| check method | Не совпадает валюта заказа| валюта в заказе -> '
                . $currency->iso_code . '| валюта в параметрах ->' . $params['orderCurrency'], 3);

            $result = array('error' =>
                array('message' => 'не совпадает валюта заказа')
            );
        }
        else{
            $result = array('result' =>
                array('message' => 'Запрос успешно обработан')
            );
        }

        return $result;

    }

    function payment( $params )
    {
        $id_order = $params['account'];
        $order = new Order($id_order);
        $currency = new Currency($order->id_currency);

        if (is_null($order->id)){
            $result = array('error' =>
                array('message' => 'заказа не существует')
            );
        }elseif ((float)$order->total_paid != (float)$params['orderSum']) {

            PrestaShopLogger::addLog(date('H:i:s', time()) . '| pay method | Не совпадает сумма заказа| сумма в заказе -> '
                . $order->total_paid . '| сумма в параметрах ->' . $params['orderSum'], 3);

            $result = array('error' =>
                array('message' => 'не совпадает сумма заказа')
            );
        }elseif ($currency->iso_code != $params['orderCurrency']) {

            PrestaShopLogger::addLog(date('H:i:s', time()) . '| pay method | Не совпадает валюта заказа| валюта в заказе -> '
                . $currency->iso_code . '| валюта в параметрах ->' . $params['orderCurrency'], 3);

            $result = array('error' =>
                array('message' => 'не совпадает валюта заказа')
            );
        }
        else{

            $history = new OrderHistory();
            $history->id_order = $id_order;
            $history->changeIdOrderState((int)Configuration::get('UNIT_OS_PAYED'), (int)($id_order));
            $history->addWithemail(true);

            $result = array('result' =>
                array('message' => 'Запрос успешно обработан')
            );

        }

        return $result;
    }
    function error( $params )
    {
        $id_order = $params['account'];
        $order = new Order($id_order);

        if (is_null($order->id)){
            $result = array('error' =>
                array('message' => 'заказа не существует')
            );
        }
        else{

            $history = new OrderHistory();
            $history->id_order = $id_order;
            $history->changeIdOrderState((int)Configuration::get('UNIT_OS_ERROR_PAY'), (int)($id_order));
            $history->addWithemail(true);

            $result = array('result' =>
                array('message' => 'Запрос успешно обработан')
            );

        }

        return $result;
    }

}
