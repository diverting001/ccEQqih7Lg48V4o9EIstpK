<?php

namespace App\Api\Logic\AssetOrder;
use App\Api\Model\Goods\Product ;
use App\Api\Model\Goods\Shop ;
/**
 * Class Popwms
 * @package App\Api\Logic\Product
 *  查询商品所属履约平台信息
 */
#查询商品所属履约平台信息
class Popwms
{
    /**
     * @ 商品所属pop_shop->pop_owner->pop_wms信息
     * @ has_one方式实现：shop has_one owner, owner has_one wms
     */
    public function get_goods_pop_wms($product_bns_list) {
        $productModel = new Product ;
        $bn_pop_shop_ids =  $productModel->GetProductList(array('bn' => array("in" ,$product_bns_list)) ,['product_id', 'pop_shop_id', 'bn',"goods_id","taxfees" ,'name as product_name']) ;
        if(empty($bn_pop_shop_ids)){
            return array();
        }
        $new_bns = array();
        foreach($bn_pop_shop_ids as $key=>$item) {
            $item = get_object_vars($item) ;
            // pop_shop_id为空时设置默认值 1 防止其它异常
            if(!isset($item['pop_shop_id']) || empty($item['pop_shop_id'])){
                $item['pop_shop_id'] = 1;
            }
            $new_bns[$item['pop_shop_id']][$item['bn']] = $item['pop_shop_id'];
            $bn_pop_shop_ids[$key] = $item ;
        }
        unset($item);
        $popShopMdl = new Shop ;
        $new_popshops = $popShopMdl->getPosShops(array_keys($new_bns));
        //从pop_owner获取pop_wms_id
        $pop_owner_ids_key = array();
        foreach($new_popshops as $item)   {
            $pop_owner_ids_key[$item['pop_owner_id']] = $item['pop_owner_id'];
        }
        $new_owners = $popShopMdl->getPosOwners(array_keys($pop_owner_ids_key));
        //从pop_wms获取pop_wms_code
        $pop_wms_ids_key = array();
        foreach($new_owners as $item) {
            $pop_wms_ids_key[$item['pop_wms_id']] = $item['pop_wms_id'];
        }
        unset($item);

        $new_wmss = $popShopMdl->getPosWmss(array_keys($pop_wms_ids_key));
        //合并
        $result_bns =  array();
        foreach($bn_pop_shop_ids as $item)
        {
            $result_bns[$item['bn']] = $item;
            foreach($new_popshops as $popshops_item)
            {
                if($item['pop_shop_id'] == $popshops_item['pop_shop_id'])
                {
                    $result_bns[$item['bn']]['pop_shop_name'] = $popshops_item['pop_shop_name'];
                    $result_bns[$item['bn']]['pop_owner_id'] = $popshops_item['pop_owner_id'];
                }
            }
        }
        unset($item);

        foreach($result_bns as &$item)
        {
            foreach($new_owners as $pop_owners_item)
            {
                if($item['pop_owner_id'] == $pop_owners_item['pop_owner_id'])
                {
                    $item['pop_owner_name'] = $pop_owners_item['pop_owner_name'];
                    $item['pop_wms_id'] = $pop_owners_item['pop_wms_id'];
                }
            }
        }
        unset($item);
        foreach($result_bns as &$item)
        {
            foreach($new_wmss as $pop_wmss_item)
            {
                if($item['pop_wms_id'] == $pop_wmss_item['pop_wms_id'])
                {
                    $item['pop_wms_code'] = $pop_wmss_item['pop_wms_code'];
                }
            }
        }
        return $result_bns;
    }

    /**
     * 先按运营实体拆单，然后按履约平台组织数据，
     * @todo maojz 获取商品对于的履约平台
     */
    public function get_goods_wms($product_bns_list)
    {
        if (empty($product_bns_list)) {
            return false;
        }
        $result = $this->get_goods_pop_wms($product_bns_list);
        $wms_codes = array();
        $owners = array();
        $owner_items = array();
        $wms = array();
        foreach ($result as $item) {
            $owners[$item['pop_owner_id']] = $item['pop_owner_id'];
            $owner_items[$item['pop_owner_id']][] = array('bn' => $item['product_bn']);
            $wms_owner_items[$item['pop_wms_id']][$item['pop_owner_id']][$item['bn']] = array(
                'bn' => $item['bn'],
                'pop_owner_name' => $item['pop_owner_name'],
                'pop_owner_id' => $item['pop_owner_id'],
                'pop_shop_id' => $item['pop_shop_id'],
            );

            $wms[] = $item['pop_wms_id'];
            $wms_codes[$item['pop_wms_id']] = $item['pop_wms_code'];
        }
        unset($item);
        $owners = array_unique($owners);
        $order_main_count = count($owners);
        $wms = array_unique($wms);
        $ret = array();
        foreach ($wms as $item) {
            $ret[$item] = array(
                'pack'=>array('code'=> $wms_codes[$item] ),
                'orders'=> $wms_owner_items[$item],
            );
        }
        return array('main_order_count' => $order_main_count, 'pop_wms' => $ret);
    }

}
