<?php

namespace App\Api\Model\Goods;

use Illuminate\Database\Eloquent\Model;

class GoodsPaymentTaxFeeRateModel extends Model
{
    public $table = "sdb_b2c_goods_payment_tax_fee_rate";

    public $connection = 'neigou_store';

    public $timestamps = false;

    public function getTaxRateByTaxIds(array $taxFeeIds)
    {
        //获取组合分组对应的税率
        return $this->query()->whereIn('tax_fee_id', $taxFeeIds)->select(['tax_fee_id', 'rate_type', 'rate'])
            ->get()->toArray();
    }

    public function addRateData(array $rate_data)
    {
        if(empty($rate_data)){
            return false;
        }
        return $this->insert($rate_data);
    }

    public function getDataByTaxFeeid($where)
    {
        return $this->query()->where($where)->get()->toArray();
    }

    public function updateRateData($where,$data)
    {
        if (empty($where) || empty($data)) {
            return false;
        }
        return $this->where($where)->update($data);
    }
}
