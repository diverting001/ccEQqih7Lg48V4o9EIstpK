<?php

namespace App\Api\V3\Service\Stock;

use App\Api\V3\Service\Stock\Stock as StockService;
use App\Api\Model\Stock\Restrict as ProductRestrict;

class StockRestrict extends StockService
{

    /*
     * @todo 锁定活动限制库存
     */
    public function Lock($product_list, $parameter, &$response_products)
    {
        $lock_status = true;
        $channel = $parameter['channel'];
        if (empty($product_list) || empty($channel)) {
            return false;
        }
        foreach ($product_list as $product) {
            $restrict_res = true;
            //检查货品是否有活动限制
            $product_restrict = ProductRestrict::GetProductRestrict([$product['product_bn']], $channel, time());
            //锁定活动限制库存
            if (!empty($product_restrict)) {
                $restrict_res = ProductRestrict::Lock($product_restrict[$product['product_bn']]['id'],
                    $product['count']);
                //创建冻结记录
            }
            if (empty($restrict_res)) {
                $response_products['fail_product'][$product['product_bn']] = $product['product_bn'];
            }
            $lock_status = $lock_status && $restrict_res;
        }
        return $lock_status;
    }

    /*
     *@todo 取消锁定
     */
    public function CancelLock($channel, $lock_type, $lock_obj)
    {
        return true;
        // TODO: Implement CancelLock() method.
    }


    /*
     * @todo 获取货品库存
     */
    public function GetStock($product_list, $filter, $product_num_list = array())
    {//货品库存列表
        $product_stock_list = [];
        if (empty($product_list)) {
            return $product_stock_list;
        }
        $product_restrict_list = ProductRestrict::GetProductRestrict($product_list, $filter['channel'], time());
        foreach ($product_list as $bn) {
            $product_stock_list[$bn] = [
                'bn' => $bn,
                'main_stock' => isset($product_restrict_list[$bn]) ? intval($product_restrict_list[$bn]['stock']) : 999999,
                'stock' => isset($product_restrict_list[$bn]) ? intval($product_restrict_list[$bn]['stock']) : 999999,
            ];
        }
        return $product_stock_list;
    }

}
