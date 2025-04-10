<?php

namespace App\Api\Model\Goods;

use Illuminate\Database\Eloquent\Model;

class GoodsPaymentTaxFeeProductModel extends Model
{
    public $table = "sdb_b2c_goods_payment_tax_fee_product";

    public $connection = 'neigou_store';

    public $timestamps = false;

    public function getTaxFeeIdByProductIds($product_bns, $tax_fee_ids)
    {
        return $this->query()
            ->whereIn('product_bn', $product_bns)
            ->whereIn('tax_fee_id', $tax_fee_ids)
            ->select(['tax_fee_id', 'product_bn'])->get()->toArray();
    }

    public function getProductInfoByIds($where, $field = ['id'])
    {
        if (empty($where) || empty($field)) {
            return false;
        }
        return $this->query()
            ->where($where)
            ->select($field)->first();
    }

    public function addData($data)
    {
        if (empty($data)) {
            return false;
        }
        return $this->insert($data);
    }

    public function delData($where)
    {
        if (empty($where)) {
            return false;
        }
        return $this->where($where)->delete();
    }

    public function getListCount($where, $whereIn,$product_name = '')
    {
        $model = $this->query();
        if ($where) {
            $model->where($where);
        }
        if (!empty($whereIn)) {
            $model->whereIn($whereIn[0], $whereIn[1]);
        }
        if($product_name){
            $model->join('sdb_b2c_products as p','sdb_b2c_goods_payment_tax_fee_product.product_bn','=','p.bn','left');
        }
        return $model->count();
    }

    public function getList($where,$whereIn, $field = ['id'], $page = 1, $limit = 10,$product_name = '')
    {
        $model = $this->query();
        if ($where) {
            $model->where($where);
        }
        if (!empty($whereIn)) {
            $model->whereIn($whereIn[0], $whereIn[1]);
        }
        if($product_name){
            $model->join('sdb_b2c_products as p','sdb_b2c_goods_payment_tax_fee_product.product_bn','=','p.bn','left');
        }
        $offset = ($page - 1) * $limit;
        return $model->select($field)
            ->offset($offset)
            ->limit($limit)
            ->orderBy('id', 'desc')
            ->get()->toArray();
    }

}
