<?php


namespace App\Api\Logic\AssetOrder;

#商品信息补全
class GoodsItem
{
    // 根据goodsId 获取 商品的 mall_list_id
    public static  function getMallIdList($goodsIdList)  {

        $goodsIdList = array_filter($goodsIdList) ;
        $storeDB          = app('api_db')->connection('neigou_store');
        $goodsMallList    = $storeDB->table('mall_module_mall_goods')
            ->select('mall_id', 'goods_id', 'bn')
            ->whereIn('goods_id',$goodsIdList )
            ->get();
        $goodsMallListArr = [];
        foreach ($goodsMallList as $item) {
            $goodsMallListArr[$item->bn][] = $item->mall_id;
        }

        $goodsExtList    = $storeDB->table('sdb_b2c_goods as goods')
            ->leftJoin('sdb_b2c_mall_goods_cat as cat', 'goods.mall_goods_cat', '=', 'cat.cat_id')
            ->select('goods.bn as goods_bn', 'goods.ziti', 'goods.jifen_pay', 'goods.brand_id','goods.goods_id', 'cat.cat_path')
            ->whereIn('goods.goods_id', $goodsIdList)
            ->get();

        $goodsExtListArr = [];
        foreach ($goodsExtList as $item) {
            $item->cat_path     = trim($item->cat_path, ',');
            $item->mall_list_id = isset($goodsMallListArr[$item->goods_bn]) ? implode(',', $goodsMallListArr[$item->goods_bn]) : '';
            $goodsExtListArr[$item->goods_id] = get_object_vars($item);
        }
        return $goodsExtListArr ;
    }



    // 获取全的商品
    public static function getProductInfo($product_list) {

        $productBnList = [] ;
        $goodsIdList = [] ;
        foreach ($product_list as $item) {
            $productBnList[] = $item['product_bn'] ;
            $goodsIdList[] = $item['goods_id'] ;
        }
        if(empty($productBnList)) {
            return false ;
        }

        $popwmsObj = new Popwms() ;
        $wms_data =   $popwmsObj->get_goods_pop_wms($productBnList) ;

        $goods_info = self::getMallIdList($goodsIdList) ;
        $data= [] ;
        foreach ($product_list as $item) {
            $goods_id = $item['goods_id'] ;
            $product_bn = $item['product_bn'] ;
            $origin_item = $item ;
            if(isset($goods_info[$goods_id]) && !empty($goods_info[$goods_id])) {
                $item = array_merge($item ,$goods_info[$goods_id]) ;
            }
            if(isset($wms_data[$product_bn]) && !empty($wms_data[$product_bn])) {
                $item = array_merge($item ,$wms_data[$product_bn]) ;
            }
            $item['cost'] = $item['price']['cost'] ;
            $item['price'] = $item['price']['price'] ;
            // 如果 原来有税费 则用原来的
            if(isset($origin_item['cost_tax']) && !empty($origin_item['cost_tax']) ) {
                $item['cost_tax'] = $origin_item['cost_tax'] ;
            }
            if($origin_item['jifen_pay']) {
                $item['jifen_pay'] = $origin_item['jifen_pay'] ;
            }
            $data[$product_bn] = $item ;
        }
        return $data ;
    }


    // 获取o2o免邮列表
    public static function getO2OProductList($productBnList) {
        if(empty($productBnList)) {
            return [] ;
        }
        $storeDB          = app('api_db')->connection('neigou_store');
        $o2oProductList   = $storeDB->table('o2o_products')
            ->select('bn')
            ->whereIn('bn', array_filter($productBnList))
            ->get();
        return $o2oProductList->count() ? array_column($o2oProductList->toArray(), 'bn') : [];
    }


}
