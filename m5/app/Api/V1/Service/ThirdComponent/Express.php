<?php

namespace App\Api\V1\Service\ThirdComponent;
use App\Api\Logic\Service as Service;

/**
 * 物流组件
 */
class Express
{
    /**
     * @param $orderId
     * @param $extendData
     * @return array|mixed
     */
    public function getExpressComponentInfo($orderId, $extendData = array()) {
        $result = array();

        if (empty($orderId)) {
            return $result;
        }

        //获取订单
        $serviceLogic = new Service();
        $orderData = $serviceLogic->ServiceCall('order_info', ['order_id' => $orderId]);
        if ('SUCCESS' !== $orderData['error_code'] || empty($orderData['data'])) {
            return $result;
        }

        //获取商品所属shop
        $shopData = $serviceLogic->ServiceCall('get_shop_list', ['filter' => ['pop_owner_id_list' => [$orderData['data']['pop_owner_id']]]]);
        if ('SUCCESS' !== $shopData['error_code']  || empty($shopData['data'])) {
            return $result;
        }

        $shop = current($shopData['data']);

        //根据不同的平台获取数据
        $className = 'App\\Api\\Logic\\ThirdComponent\\' . ucfirst(strtolower($shop['pop_wms_code']));
        if (!class_exists($className)) {
            return $result;
        }

        $classObj = new $className;
        if (!method_exists($classObj, 'getComponentInfo')) {
            return $result;
        }
        $extendData['order_id'] = $orderData['data']['wms_delivery_bn'];
        $result = $classObj->getComponentInfo('express', $extendData);

        return $result;
    }

}
