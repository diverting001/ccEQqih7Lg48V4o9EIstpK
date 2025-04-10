<?php
/**
 * Created by phpstorm.
 * User: xuhaohao
 * Date: 2021/11/3
 * Time: 19:09
 */

namespace App\Api\Logic\Promotion;

use App\Api\Model\AfterSale\AfterSale as AfterSaleModel;
use App\Api\Model\AfterSale\V2\AfterSaleProducts;
use App\Api\Model\Promotion\PromotionModel;

class LimitBuy
{
    /**
     * @param $orderId
     * @return bool
     */
    public static function returnCancelOrderStock($orderId): bool
    {
        $model = new PromotionModel();

        $res = $model->UnLockPromotionStock($orderId);

        return !($res === false);
    }

    /**
     * @param $afterSaleBn
     * @return bool
     */
    public static function returnAfterSaleStock($afterSaleBn): bool
    {
        $afterSaleInfo = AfterSaleModel::GetAfterSaleInfoByBn($afterSaleBn);

        $productLists = (new AfterSaleProducts())->getListByAfterSaleBns($afterSaleBn);

        $productList = current($productLists);

        if (!empty($afterSaleInfo->order_id) && !empty($productList) && $afterSaleInfo->after_type == 1) {
            $returnProductNum = array_column($productList, 'nums', 'product_bn');

            $stockList = PromotionModel::queryPromotionStockByOrderIdAndBn($afterSaleInfo->order_id,
                array_keys($returnProductNum));

            if (!empty($stockList)) {
                app('db')->beginTransaction();

                foreach ($stockList as $item) {
                    if (!PromotionModel::decPromotionStockByOrderIdAndBn($afterSaleInfo->order_id, $item->bn,
                        $returnProductNum[$item->bn])) {
                        app('db')->rollBack();

                        return false;
                    }

                    if (!PromotionModel::recordPromotionStock($afterSaleBn, $afterSaleInfo->order_id,
                        $item->bn, $returnProductNum[$item->bn])) {
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
