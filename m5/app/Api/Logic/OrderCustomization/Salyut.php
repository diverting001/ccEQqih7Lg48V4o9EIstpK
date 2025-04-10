<?php

namespace App\Api\Logic\OrderCustomization;

class Salyut
{
    private $_host = '';

    public function __construct()
    {
        $this->_host = config('neigou.SALYUT_DOMIN');
    }

    /*
     * @todo 创建订单
     */
    public function getOrderCustomizationInfo($orderData, & $errMsg = '')
    {
        if (empty($orderData)) {
            return false;
        }
        $sendData = [
            'class_obj' => 'OrderCustomization',
            'method' => 'getOrderCustomizationInfo',
            'data' => array('order_data' => $orderData),
        ];
        $result = $this->SendData($sendData);
        if ($result['Result'] != 'true' || empty($result['Data'])) {
            $errMsg = $result['ErrorMsg'];
            return false;
        } else {
            return $result['Data'];
        }
    }

    /*
     * @todo 获取订单详情
     */
    public function getOrderCustomizationResult($orderData, $customizationData, & $errMsg = '')
    {
        if (empty($orderData) OR empty($customizationData)) {
            return false;
        }
        $sendData = [
            'class_obj' => 'OrderCustomization',
            'method' => 'getOrderCustomizationResult',
            'data' => array('order_data' => $orderData, 'customization_data' => $customizationData),
        ];
        $result = $this->SendData($sendData);
        if ($result['Result'] != 'true' || empty($result['Data'])) {
            $errMsg = $result['ErrorMsg'];
            return false;
        } else {
            return $result['Data'];
        }
    }

    /**
     *
     * @params  $uri
     * @params  $order_data
     * @return boolean
     */
    private function SendData($data)
    {
        if (empty($data)) {
            return false;
        }
        $data['data'] = json_encode($data['data']);
        $data['token'] = \App\Api\Common\Common::GetSalyutSign($data);
        $curl = new \Neigou\Curl();
        $result_str = $curl->Post($this->_host. '/OpenApi/apirun/', $data);
        $result = trim($result_str, "\xEF\xBB\xBF");
        $result = json_decode($result, true);
        \Neigou\Logger::General('wms_salyut_order_customization', array('data' => json_encode($data), 'result' => $result_str));
        return $result;
    }

}
