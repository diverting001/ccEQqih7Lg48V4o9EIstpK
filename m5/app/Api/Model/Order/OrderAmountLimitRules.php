<?php

namespace App\Api\Model\Order;

use \App\Api\Model\BaseModel;

class OrderAmountLimitRules extends BaseModel
{
    protected $table = 'sdb_b2c_order_amount_limit_rules' ;

    protected $primaryKey = 'id' ;

    public function getLimitRuleRelatedInfo($limitRuleIds): array
    {
       if (empty($limitRuleIds) || !is_array($limitRuleIds)) {
           return array();
       }

       $result = $this->query()->whereIn('limit_rule_id', $limitRuleIds)
           ->select('goods_rule_id','limit_rule_id')
           ->get();

       if ($result) {
           return $result->toArray();
       }

       return array();
    }
}
