<?php

namespace App\Api\V2\Service\Delivery;
use App\Api\Logic\Service as Service;

/**
 * 获取货品预计送达时间
 */

class Promise
{
    public function getProductPromiseInfo($product, $area, $extendData = array()) {
        $result = array();

        if (empty($product['product_bn']) || empty($product['num']) || empty($area['province']) || empty($area['city']) || empty($area['county'])) {
            return $result;
        }

        //获取商品
        $serviceLogic = new Service();
        $productData = $serviceLogic->ServiceCall('get_product_info', ['filter' => ['product_bn' => $product['product_bn']]]);
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
        $className = 'App\\Api\\Logic\\Promise\\' . ucfirst(strtolower($shop['pop_wms_code']));
        if (!class_exists($className)) {
            return $result;
        }

        $classObj = new $className;
        if (!method_exists($classObj, 'getProductPromiseInfo')) {
            return $result;
        }
        $result = $classObj->getProductPromiseInfo($product, $area, $extendData);

        return $result;
    }
}
