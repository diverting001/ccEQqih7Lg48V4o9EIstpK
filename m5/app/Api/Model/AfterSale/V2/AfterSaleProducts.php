<?php
namespace App\Api\Model\AfterSale\V2;

class AfterSaleProducts
{

    private static $_channel_name = 'service';

    public function __construct()
    {
        $this->_db = app('api_db');
    }

    public function getListByAfterSaleBns($after_sale_bns){
        $return = [];
        if(!$after_sale_bns){
            return $return;
        }

        $after_sale_bns = is_array($after_sale_bns)?$after_sale_bns:(array)$after_sale_bns;

        $product_data = $this->_db->table('server_after_sales_products')->whereIn('after_sale_bn', $after_sale_bns)->get()->toArray();
        if(!$product_data){
            return $return;
        }

        foreach ($product_data as $product_obj) {
            $return[$product_obj->after_sale_bn][] = array(
                'product_bn' => $product_obj->product_bn,
                'product_id' => $product_obj->product_id,
                'nums' => $product_obj->nums,
                'refundable_money' => $product_obj->refundable_money,
                'refundable_point' => $product_obj->refundable_point,
                'refundable_voucher' => $product_obj->refundable_voucher,
                'refundable_cost_freight' => $product_obj->refundable_cost_freight,
                'real_money' => $product_obj->real_money,
                'real_point' => $product_obj->real_point,
                'real_voucher' => $product_obj->real_voucher,
                'item_type'=>$product_obj->item_type,
                'p_bn'=>$product_obj->p_bn,
            );
        }

        return $return;
    }

    /*
    * 写入数据
    */
    public function create($data)
    {
        return $this->_db->table('server_after_sales_products')->insert($data);
    }

    /**
     * 更新货品
     * @param $after_sale_bn
     * @param $product_bn
     * @param $update
     * @return mixed
     */
    public function updateRefund($where,$update){
        if(!$where || !$update){
            return false;
        }
        return $this->_db->table('server_after_sales_products')->where($where)->update($update);
    }
}