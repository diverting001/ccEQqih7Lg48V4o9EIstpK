<?php

namespace App\Api\Logic;


use App\Api\Dispatcher;

class Freight
{
    private $_host = '';
    private $_uri = array(
        'get_product_delivery_freight' => '/openapi/deliveryFreight/getProductDeliveryFreight',
    );

    public function __construct()
    {
        $this->_host = config('neigou.STORE_DOMIN');
    }

    /*
     * @todo 获取订单详情
     */
    public function getProductDeliveryFreight($pars)
    {
        if (empty($pars)) {
            return false;
        }
        $result = $this->SendData('get_product_delivery_freight', $pars);
        if ($result['Result'] != 'true' || empty($result['Data'])) {
            \Neigou\Logger::Debug('get_goods_delivery_freight.err', array('data' => $pars, 'result' => $result));
            return false;
        } else {
            return $result['Data'];
        }
    }

    private function SendData($uri, $order_data)
    {
        if (empty($order_data)) {
            return false;
        }
        if (!isset($this->_uri[$uri])) {
            return false;
        }
        $send_data = array('data' => json_encode($order_data));
        $token = \App\Api\Common\Common::GetEcStoreSign($send_data);
        $send_data['token'] = $token;

        $curl = new \Neigou\Curl();
        $result_str = $curl->Post($this->_host . $this->_uri[$uri], $send_data);
        $result = trim($result_str, "\xEF\xBB\xBF");
        $result = json_decode($result, true);
        \Neigou\Logger::General('get_product_delivery_freight', array('data' => json_encode($send_data), 'result' => $result_str));
        return $result;
    }
}
