<?php

namespace App\Api\Model\Goods;

use Illuminate\Database\Eloquent\Model;

class GoodsPaymentTaxFeePushModel extends Model
{
    public $table = "sdb_b2c_goods_payment_tax_fee_push";

    public $connection = 'neigou_store';

    public $timestamps = false;

    public function getTaxFeeIdsByCompanyTaxId(array $company_tax_fee_ids)
    {
        //获取公司绑定的推送信息
        return $this->query()->where(['status' => 0])->where(function ($query) use ($company_tax_fee_ids) {
            $query->where('visible', '=', 1);
            if (!empty($company_tax_fee_ids)) {
                $query->orWhere(function ($query) use ($company_tax_fee_ids) {
                    $query->where('visible', '=', 2)->whereIn('tax_fee_id', $company_tax_fee_ids);
                });
            };
        })->select(['tax_fee_id'])->get()->toArray();
    }

    public function getTaxInfoByIds($where, $field = ['id'])
    {
        if (empty($where) || empty($field)) {
            return false;
        }
        return $this->query()
            ->where($where)
            ->select($field)->first();
    }

    public function addInfo($data)
    {
        if(empty($data)){
            return false;
        }
        return $this->insertGetId($data);
    }

    public function editInfo($where, $data)
    {
        if (empty($where) || empty($data)) {
            return false;
        }
        return $this->where($where)->update($data);
    }
}
