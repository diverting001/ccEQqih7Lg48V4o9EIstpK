<?php

namespace App\Api\Model\Goods;

use Illuminate\Database\Eloquent\Model;

class GoodsPaymentTaxFeePushCompanyModel extends Model
{
    public $table = "sdb_b2c_goods_payment_tax_fee_push_company";

    public $connection = 'neigou_store';

    public $timestamps = false;

    public function getTaxFeeIdsByCompanyIds($company_id)
    {
        return $this->query()
            ->where('company_id', '=', $company_id)
            ->select(['tax_fee_id'])->get()->toArray();
    }

    public function getCompanyData($where, $field = ['id'])
    {
        if (empty($where)) {
            return false;
        }
        return $this->query()
            ->where($where)
            ->select($field)->get()->toArray();
    }

    public function addData($data)
    {
        if (empty($data)) {
            return false;
        }
        return $this->insert($data);
    }

    public function delData($where,$del_whereIn)
    {
        if (empty($where) || empty($del_whereIn)) {
            return false;
        }
        return $this->where($where)->whereIn($del_whereIn[0],$del_whereIn[1])->delete();
    }
}
