<?php

namespace App\Api\Logic\OrderWMS;

use App\Api\Logic\OrderWMS\Order as Order;

class Salyut extends Order
{
    private $_host = '';
    private $_uri = array(
        'create_order' => '/Home/Orders/CreateOrder',
        'get_order_info' => '/Home/Orders/GetOrderInfo',
        'cancel_order_by_wms_order_bn' => '/Home/Orders/CancelOrderByWmsOrderBn',
        'cancel_order_by_wms_delivery_bn' => '/Home/Orders/CancelOrderByWmsDeliveryBn',
        'complete_order_by_wms_delivery_bn' => '/Home/Orders/CompleteOrder',
    );


    public function __construct()
    {
        $this->_host = config('neigou.SALYUT_DOMIN');
    }

    /*
     * @todo 创建订单
     */
    public function Create($order_data)
    {
        if (empty($order_data)) {
            return false;
        }
        if ( ! empty($order_data['extend_data']['invoice_info']))
        {
            $invoice_info = $order_data['extend_data']['invoice_info'];
            $key = $order_data['order_id'];
            $json = json_encode($invoice_info);
            $this->setInvoiceInfo($key, $json);
        }
        $result = $this->SendData('create_order', $order_data);
        if ($result['Result'] != 'true' || empty($result['Data']['order_id'])) {
            return false;
        } else {
            return $result['Data']['order_id'];
        }
    }

    private function setInvoiceInfo($key, $json = '', $rand = '', $timeout = 90000)
    {
        $key = 'INVOICE-' . $key . $rand;
        $RedisClient = new \Neigou\RedisClient();
        if (is_object($RedisClient->_redis_connection)) {
            $r = $RedisClient->_redis_connection->setex($key, $timeout, $json);
            if (!$r) {
                return false;
            } else {
                return true;
            }
        }
        return false;
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

    /**
     * 取消订单by履约订单编号
     */
    public function CancelOrderByWmsOrderBn($order_id, &$msg = '')
    {
        $msg = '该订单为salyut订单无法在此处取消，请到salyut后台进行操作';
        return false;

        if (empty($order_id)) {
            return false;
        }
        $send_data = [
            'order_id' => $order_id
        ];
        $result = $this->SendData('cancel_order_by_wms_order_bn', $send_data);
        if ($result['Result'] != 'true') {
            return false;
        } else {
            return true;
        }
    }

    /**
     * 取消订单by履约发货单号
     */
    public function CancelOrderByWmsDeliveryBn($order_id, &$msg = '')
    {
        $msg = '该订单为salyut订单无法在此处取消，请到salyut后台进行操作';
        return false;

        if (empty($order_id)) {
            return false;
        }
        $send_data = [
            'order_id' => $order_id
        ];
        $result = $this->SendData('cancel_order_by_wms_delivery_bn', $send_data);
        if ($result['Result'] != 'true') {
            return false;
        } else {
            return true;
        }
    }

    /**
     * 完成订单by履约发货单号
     */
    public function CompleteOrderByWmsDeliveryBn($wms_delivery_bn, &$msg = '')
    {
        if (empty($wms_delivery_bn)) {
            return false;
        }
        $send_data = [
            'order_id' => $wms_delivery_bn
        ];
        $result = $this->SendData('complete_order_by_wms_delivery_bn', $send_data);
        $msg = $result['ErrorMsg'];
        if ($result['Result'] != 'true') {
            return false;
        } else {
            return true;
        }
    }

    /**
     *
     * @param type $uri
     * @param type $order_data
     * @return boolean
     */
    private function SendData($uri, $order_data)
    {
        if (empty($order_data)) {
            return false;
        }
        if (!isset($this->_uri[$uri])) {
            return false;
        }
        $send_data = array(
            'token' => \App\Api\Common\Common::GetSalyutOrderSign($order_data),
            'data' => json_encode($order_data),
        );
        $curl = new \Neigou\Curl();
        $result_str = $curl->Post($this->_host . $this->_uri[$uri], $send_data);
        $result = trim($result_str, "\xEF\xBB\xBF");
        $result = json_decode($result, true);
        if ($uri == 'create_order') {
            \Neigou\Logger::General('wms_salyut_order',
                array('data' => json_encode($send_data), 'result' => $result_str));
        }
        return $result;
    }


}
