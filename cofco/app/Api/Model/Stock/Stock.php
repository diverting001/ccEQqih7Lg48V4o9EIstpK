<?php

namespace App\Api\Model\Stock;

class Stock
{

    //更新货品库存
    public static function UpdateStock($product_bn, $branch_id, $stock)
    {
        $res = false;
        $stock = intval($stock);
        if (empty($product_bn) || empty($branch_id)) {
            return false;
        }
        $product_stock = self::GetProductInfo($product_bn, $branch_id);
        if (empty($product_stock)) {
            $sql = "INSERT INTO `server_stock_branch_product` ( `product_bn`, `stock`, `branch_id`, `create_time`, `last_modified`)VALUES(?,?,?,?,?)";
            $res = app('api_db')->insert($sql, [$product_bn, $stock, $branch_id, time(), time()]);
        } else {
            $sql = "update `server_stock_branch_product` set `stock` = {$stock},last_modified = " . time() . " where product_bn = :product_bn and branch_id = :branch_id";
            $res = app('api_db')->update($sql, ['product_bn' => $product_bn, 'branch_id' => $branch_id]);
//            $res    = true;
        }
        return $res;
    }

    //更新货品冰结库存数
    public static function UpdataFreez($product_bn, $branch_id, $freez, $odl_freez)
    {
        if (empty($product_bn) || empty($branch_id)) {
            return false;
        }
        $sql = "update server_stock_branch_product set freez = " . intval($freez) . ",last_modified=" . time() . " where product_bn = :product_bn and branch_id = :branch_id and freez = :odl_freez";
        $res = app('api_db')->update($sql,
            ['product_bn' => $product_bn, 'branch_id' => $branch_id, 'odl_freez' => intval($odl_freez)]);
        return $res;
    }

    //获取货品库存
    public static function GetProductInfo($product_bn, $branch_id)
    {
        if (empty($product_bn) || empty($branch_id)) {
            return false;
        }
        $sql = "select * from server_stock_branch_product where product_bn = :product_bn and branch_id = :branch_id";
        $product_stock = app('api_db')->selectOne($sql, ['product_bn' => $product_bn, 'branch_id' => $branch_id]);
        return $product_stock;
    }

    //渠道货品库存
    public static function GetProductStockByChannel(array $product_bn, $channel = '')
    {
        $product_stock_list = [];
        if (empty($product_bn)) {
            return false;
        }
        $sql = "select * from server_stock_branch_product  where product_bn in (" . implode(',',
                array_fill(0, count($product_bn), '?')) . ")";
        $product_stock_list_obj = app('api_db')->select($sql, $product_bn);
        if (!empty($product_stock_list_obj)) {
            foreach ($product_stock_list_obj as $item) {
                $item_stock = max(0, ($item->stock - $item->freez));
                $stock = isset($product_stock_list[$item->product_bn]) ? $product_stock_list[$item->product_bn]['stock'] + $item_stock : $item_stock;
                $product_stock_list[$item->product_bn] = array(
                    'bn' => $item->product_bn,
                    'stock' => $stock,
                );
            }
        }
        return $product_stock_list;
    }

    //库存锁定
    public static function Lock($product_bn, $branch_id, $freez)
    {
        if (empty($product_bn) || empty($branch_id)) {
            return false;
        }
        $freez = intval($freez);
        $sql = "update server_stock_branch_product set freez = IFNULL(freez,0)+ {$freez},last_modified = " . time() . " where IFNULL(freez,0)+{$freez} <= stock and product_bn = :product_bn and branch_id = :branch_id";
        $res = app('api_db')->update($sql, ['product_bn' => $product_bn, 'branch_id' => $branch_id]);
        return $res;
    }

}
