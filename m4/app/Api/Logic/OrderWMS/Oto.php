<?php

namespace App\Api\Logic\OrderWMS;

use App\Api\Logic\OrderWMS\Order as Order;

class Oto extends Order
{
    private $_host = '';
    private $_uri = array(
        'create_order' => '/openapi/cpsOrder/BindOrder',
        'get_order_info' => '/openapi/cpsOrder/GetInfo',
    );

    public function __construct()
    {
        $this->_host = config('neigou.STORE_DOMIN');
    }

    /*
     * @todo 创建订单
     */
    public function Create($order_data)
    {
        if (empty($order_data)) {
            return false;
        }
        $result = $this->SendData('create_order', $order_data);
        if ($result['Result'] != 'true' || empty($result['Data'])) {
            return false;
        } else {
            return $result['Data'];
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
        $send_data = [
            'order_id' => $order_id
        ];
        $result = $this->SendData('get_order_info', $send_data);
        if ($result['Result'] != 'true' || empty($result['Data'])) {
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
        $send_data = array('data' => base64_encode(json_encode($order_data)));
        $token = \App\Api\Common\Common::GetEcStoreSign($send_data);
        $send_data['token'] = $token;

        $curl = new \Neigou\Curl();
        $result_str = $curl->Post($this->_host . $this->_uri[$uri], $send_data);
        $result = trim($result_str, "\xEF\xBB\xBF");
        $result = json_decode($result, true);
        \Neigou\Logger::General('wms_ec_order', array('data' => json_encode($send_data), 'result' => $result_str));
        return $result;
    }
}
