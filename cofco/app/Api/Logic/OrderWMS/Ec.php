<?php

namespace App\Api\Logic\OrderWMS;

use App\Api\Logic\OrderWMS\Order as Order;

class Ec extends Order
{
    private $_host = '';
    private $_ocs_host = '';
    private $_uri = array(
        'create_order' => '/openapi/orderV3/CreateOrder',
        'get_order_info' => '/openapi/orderV3/GetOrderInfo',
        'get_order_info_from_ocs' => '/openapi/orders/GetOrderInfo',
        'cancel_order_from_ocs' => '/openapi/orders/CancelOrderByService',
    );

    public function __construct()
    {
        $this->_host = config('neigou.STORE_DOMIN');
        $this->_ocs_host = config('neigou.OCS_DOMIN');
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
        $send_data = [
            'order_id' => $order_id
        ];
        //$result = $this->SendData('get_order_info',$send_data);
        $result = $this->SendDataToOCS('get_order_info_from_ocs', $send_data);
        if ($result['Result'] != 'true' || empty($result['Data'])) {
            return false;
        } else {
            return $result['Data'];
        }
    }

    private function SendDataToOCS($uri, $order_data)
    {
        if (empty($order_data)) {
            return false;
        }
        if (!isset($this->_uri[$uri])) {
            return false;
        }
        $send_data = array('data' => base64_encode(json_encode($order_data)));
        //$token= \App\Api\Common\Common::GetEcStoreSign($send_data);
        //$send_data['token'] = $token;

        $curl = new \Neigou\Curl();
        $result_str = $curl->Post($this->_ocs_host . $this->_uri[$uri], $send_data);
        $result = trim($result_str, "\xEF\xBB\xBF");
        $result = json_decode($result, true);

        $tmp = array(
            '$send_data' => $send_data,
            '$result' => $result,
            'url' => $this->_ocs_host . $this->_uri[$uri]
        );
        \Neigou\Logger::General('wms_ocs_order', array('data' => json_encode($send_data), 'result' => $result_str));
        return $result;
    }

    /**
     * 取消订单
     */
    public function CancelOrder($order_id)
    {
        if (empty($order_id)) {
            return false;
        }
        $send_data = [
            'order_id' => $order_id
        ];
        $result = $this->SendDataToOCS('cancel_order_from_ocs', $send_data);
        if ($result['Result'] != 'true') {
            return false;
        } else {
            return true;
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
        $send_data = [
            'order_id' => $order_id
        ];
        $result = $this->SendDataToOCS('cancel_order_from_ocs', $send_data);
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
        $send_data = [
            'order_id' => $order_id
        ];
        $result = $this->SendDataToOCS('cancel_order_from_ocs', $send_data);
        if ($result['Result'] != 'true') {
            return false;
        } else {
            return true;
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
