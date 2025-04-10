<?php

namespace App\Api\Model\Stock;

class Product
{

    //获取需要更新货品列表
    public static function GetUpdateProductList($source, $type, $limit = 50)
    {
        $sql = "select * from server_stock_product where `source` = :source  and `type` = :type order by last_modified asc limit " . intval($limit);
        $product_list = app('api_db')->select($sql, ['source' => $source, 'type' => $type]);
        return $product_list;
    }

    //获取货品列表
    public static function GetProductList(array $products_bn)
    {
        if (empty($products_bn)) {
            return [];
        }
        $sql = "select * from server_stock_product where product_bn in (" . implode(',',
                array_fill(0, count($products_bn), '?')) . ")";
        $product_list = app('api_db')->select($sql, $products_bn);
        return $product_list;
    }

    //更新货品属性
    public static function UpdateProductbyIds($id, $up_date)
    {
        if (empty($id) || empty($up_date)) {
            return false;
        }
        $res = app('api_db')->table('server_stock_product')->whereIn('id', $id)->update($up_date);
        return true;
    }

    //更新货品属性
    public static function UpdateProduct($where, $up_date)
    {
        if (empty($where) || empty($up_date)) {
            return false;
        }
        $res = app('api_db')->table('server_stock_product')->where($where)->update($up_date);
        return true;
    }

    //保存货品
    public static function AddProduct($save_data)
    {
        if (empty($save_data)) {
            return false;
        }
        $sql = "INSERT INTO `server_stock_product` ( `product_bn`, `type`, `source`, `update_level`, `create_time`, `last_modified`)VALUES(:product_bn,:type,:source,:update_level,:create_time,:last_modified)";
        $res = app('api_db')->insert($sql, $save_data);
        return $res;
    }

}
