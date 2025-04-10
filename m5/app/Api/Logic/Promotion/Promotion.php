<?php

namespace App\Api\Logic\Promotion;

use App\Api\Logic\Service;
use App\Api\Model\AfterSale\AfterSale as AfterSaleModel;
use App\Api\Model\AfterSale\V2\AfterSaleProducts;
use App\Api\Model\Promotion\PromotionModel;

class Promotion
{
    public static function returnPayedCancelOrderStock($orderId, &$msg =''):bool
    {
        $res = (new Service())->ServiceCall('order_info', ['order_id' => $orderId]);
        $orderData = array();
        if($res['error_code'] == 'SUCCESS' && !empty($res['data'])) {
            $orderData = $res['data'];
        }
        if (!$orderData) {
            $msg = '订单信息获取失败，订单号：'.$orderId;
            return false;
        }
        $productInfo = array();
        foreach ($orderData['items'] as $item) {
            $productInfo[$item['bn']] = array(
                'nums' => $item['nums'],
                'amount' => bcmul($item['amount'], 100)
            );
        }

        $stockList = PromotionModel::queryPromotionStockByOrderIdAndBn($orderData['root_pid'],
            array_keys($productInfo));

        if (empty($stockList)) {
            return true;
        }

        app('db')->beginTransaction();
        foreach ($productInfo as $bn => $item) {
            if (!PromotionModel::decPromotionByOrderIdAndBn($orderData['root_pid'], $bn, $item['nums'],  $item['amount'])) {
                app('db')->rollBack();
                $msg = '运营活动库存回退失败，订单号：'.$orderId;
                return false;
            }
            if (!PromotionModel::recordPromotion('', $orderId,
                $bn, $item['nums'],  $item['amount'])) {
                app('db')->rollBack();
                $msg = '运营活动库存回退记录失败，订单号：'.$orderId;
                return false;
            }
        }
        app('db')->commit();
        return true;
    }

    /**
     * @param $afterSaleBn
     * @return bool
     */
    public static function returnAfterSaleStock($afterSaleBn)
    {
        $afterSaleInfo = AfterSaleModel::GetAfterSaleInfoByBn($afterSaleBn);
        $productLists = (new AfterSaleProducts())->getListByAfterSaleBns($afterSaleBn);
        $productList = current($productLists);
        if (!empty($afterSaleInfo->order_id) && !empty($productList) && $afterSaleInfo->after_type == 1) {
            /** 获取主单ID */
            $res = (new Service())->ServiceCall('order_info', ['order_id' => $afterSaleInfo->order_id]);
            $orderData = array();
            if($res['error_code'] == 'SUCCESS' && !empty($res['data'])) {
                $orderData = $res['data'];
            }
            if (!$orderData) return false;
            $orderId = $orderData['root_pid'];
            $productInfo = array();
            foreach ($productList as $item) {
                $productInfo[$item['product_bn']] = array(
                    'nums' => $item['nums'],
                    'amount' => bcmul($item['real_money'], 100) + bcmul($item['real_point'], 100)
                );
            }

            $stockList = PromotionModel::queryPromotionStockByOrderIdAndBn($orderId,
                array_keys($productInfo));
            if (!empty($stockList)) {
                app('db')->beginTransaction();
                foreach ($stockList as $item) {
                    if (!PromotionModel::decPromotionByOrderIdAndBn($orderId, $item->bn,
                        $productInfo[$item->bn]['nums'], $productInfo[$item->bn]['amount'])) {
                        app('db')->rollBack();
                        return false;
                    }
                    if (!PromotionModel::recordPromotion($afterSaleBn, $afterSaleInfo->order_id,
                        $item->bn, $productInfo[$item->bn]['nums'], $productInfo[$item->bn]['amount'])) {
                        app('db')->rollBack();
                        return false;
                    }
                }
                app('db')->commit();
            }
        }
        return true;
    }
}
