<?php

namespace App\Api\V1\Service\ThirdComponent;
use App\Api\Logic\Service as Service;

/**
 * 商品组件
 */
class Goods
{
    /**
     * 获取评价
     * @param $type
     * @param $extendData
     * @return array|mixed
     */
    public function getEvaluateComponentInfo($productBn, $extendData = array()) {
        $result = array();

        if (empty($productBn)) {
            return $result;
        }

        //获取商品
        $serviceLogic = new Service();
        $productData = $serviceLogic->ServiceCall('get_product_info', ['filter' => ['product_bn' => $productBn]]);
        if ('SUCCESS' !== $productData['error_code'] || empty($productData['data'])) {
            return $result;
        }

        //获取商品所属shop
        $shopData = $serviceLogic->ServiceCall('get_shop_list', ['filter' => ['shop_id_list' => [$productData['data']['shop_id']]]]);
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

        $extendData['product_bn'] = $productBn;
        $result = $classObj->getComponentInfo('evaluate', $extendData);

        return $result;
    }

}
