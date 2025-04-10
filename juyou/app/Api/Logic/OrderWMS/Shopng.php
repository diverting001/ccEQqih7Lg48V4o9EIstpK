<?php

namespace App\Api\Logic\OrderWMS;

use App\Api\Logic\OrderWMS\Order as Order;

class Shopng extends Order
{
    private $_host = '';


    public function __construct()
    {
        $this->_host = config('neigou.SHOP_DOMIN');
    }

    /*
     * @todo 创建订单
     */
    public function Create($order_data)
    {
        if (empty($order_data)) {
            return false;
        }
        $result = $this->SendData('V1/Order/Order/createOrder', $order_data);
        if ($result['Result'] != 'true' || empty($result['Data']['order_id'])) {
            return false;
        } else {
            return $result['Data']['order_id'];
        }
    }

    /*
     * @todo 获取订单详情
     */
    public function GetInfo($order_id)
    {
        if (empty($order_id)) {
            return false;
        }
        $post_data = array(
            'order_id' => $order_id
        );
        $result = $this->SendData('V1/Order/Order/getOrderInfo', $post_data);
        if ($result['Result'] != 'true' || empty($result['Data'])) {
            return false;
        } else {
            return $result['Data'];
        }
    }

    /**
     * 取消订单by履约订单编号
     */
    public function CancelOrderByWmsOrderBn($order_id)
    {
        if (empty($order_id)) {
            return false;
        }
        $post_data = array(
            'order_id' => $order_id
        );
        $result = $this->SendData('V1/Order/Order/cancelOrder', $post_data);
        if ($result['Result'] != 'true') {
            return false;
        } else {
            return true;
        }
    }

    /**
     * 取消订单by履约发货单号
     */
    public function CancelOrderByWmsDeliveryBn($order_id)
    {
        if (empty($order_id)) {
            return false;
        }
        $post_data = array(
            'order_id' => $order_id
        );
        $result = $this->SendData('V1/Order/Order/cancelOrder', $post_data);
        if ($result['Result'] != 'true') {
            return false;
        } else {
            return true;
        }
    }

    private function SendData($path, $send_data)
    {
        if (empty($send_data)) {
            return false;
        }
        $http_url = $this->_host . '/Shop/OpenApi/Channel/' . $path;

        $token = \App\Api\Common\Common::GetShopV2Sign($send_data, config('neigou.SHOPNG_APPSECRET'));

        $data = array(
            'appkey' => config('neigou.SHOPNG_APPKEY'),
            'data' => urlencode(json_encode($send_data)),
            'sign' => $token,
            'time' => date('Y-m-d H:i:s'),
        );

        $curl = new \Neigou\Curl();
        $result_str = $curl->Post($http_url, $data);
        $result = trim($result_str, "\xEF\xBB\xBF");
        $result = json_decode($result, true);
        \Neigou\Logger::General('wms_shopng_order', array('data' => json_encode($send_data), 'result' => $result_str));
        return $result;
    }


}
