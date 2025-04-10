<?php
/*
 * 发票平台逻辑层
 */

namespace App\Api\Logic\Invoice;
use app\Api\Model\Goods\Product;

class  Repeater
{


    /*
     * 聚合公司和商品发票信息
     */
    public function AggregationCompanyAndProductInvoiceData($product_ids = '',$company_id = '',$company_channel = '',$payments = ''){

        //获取公司或者渠道发票平台信息
        $data = $this->getCompanyInvoiceInfoByRedis($company_id,$company_channel,$payments);
        if($data['is_make_invoice'] == false){
            return false;
        }

        //聚合商品和发票类型
        $goods_invoice_data = $this->getGoodsInvoiceData($product_ids,$data['invoice_data']['invoice_type']);

        $return['is_make_invoice'] = empty($goods_invoice_data['invoice_type'])?false:true;
        $return['invoice_type'] = $goods_invoice_data['invoice_type'];
        $return['goods_invoice_info'] = $goods_invoice_data['goods_invoice_info'];
        $return['platform_payments'] = $data['invoice_data']['platform_payments'];
        $return['company_is_make_invoice'] = true;
        $return['goods_is_make_invoice'] = empty($goods_invoice_data['invoice_type'])?false:true;

        return $return;
    }

    /*
     * 缓存公司/渠道是否可以开票
     */
    public function getCompanyInvoiceInfoByRedis($company_id = 0,$company_channel = '',$payments = ''){
        $redis = new \Neigou\BaseRedis();

        $redis_key = 'invoice_company_id_'.$company_id.'channel_'.$company_channel.'payments_'.json_encode($payments);

        $data = $redis->fetch($redis_key);
        $data = json_decode($data,true);
        if(!$data){
            $data = $this->getCompanyInvoiceInfo($company_id,$company_channel,$payments);
            $redis->store($redis_key,json_encode($data),300);
        }

        return $data;
    }

    /*
     * 获取商品的发票信息
     */
    public function getGoodsInvoiceData($product_ids = array(),$invoice_type = array()){

        $return = array();
        if(!$product_ids || !$invoice_type){
            return $return;
        }

        $invoice_last_data = array();
        $all_invoice_last_data = array();

        $fileds = array('goods_id', 'product_id', 'invoice_type', 'invoice_tax', 'invoice_tax_code');

        $productModel = new Product() ;
        $where = [ 'product_id' => [ 'in' => $product_ids ] ] ;
        $product_data = $productModel->GetProductList($where,$fileds);
        $product_list = array();
        foreach ($product_data as $product){
            $product_list[$product['product_id']] = $product;
        }

        foreach ($product_list as $k=>&$goods_item){
            switch ($goods_item['invoice_type']){
                //商品开普票
                case 'ordinary':
                    $invoice_type_data = array_intersect($invoice_type,array('ORDINARY','ELECTRONIC'));
                    break;
                //商品开专票
                case 'special':
                    $invoice_type_data = array_intersect($invoice_type,array('SPECIAL','ORDINARY','ELECTRONIC'));
                    break;
                default:
                    $invoice_type_data = array();
            }
            if(!$invoice_type_data){
                unset($product_list[$k]);continue;
            }
            $goods_item['invoice_data'] = $invoice_type_data;
            $all_invoice_last_data = array_merge($all_invoice_last_data,$invoice_type_data);
            $invoice_last_data[] = $invoice_type_data;
        }

        //发票降级
        $is_special = 1;
        foreach ($invoice_last_data as $type){
            if(!in_array('SPECIAL',$type)){
                $is_special = 0;
            }
        }

        $all_invoice_last_data = array_unique($all_invoice_last_data);
        foreach ($all_invoice_last_data as $item){
            if($is_special){
                $new_invoice_last_data[] = $item;
            }else{
                if($item != 'SPECIAL'){
                    $new_invoice_last_data[] = $item;
                }
            }
        }
        $return['invoice_type'] = $new_invoice_last_data;
        $return['goods_invoice_info'] = $product_list;
        return $return;
    }
}
