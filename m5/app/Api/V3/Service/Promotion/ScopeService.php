<?php


namespace App\Api\V3\Service\Promotion;


use App\Api\Model\Promotion\PromotionModel;

class ScopeService
{
    private $_mdl_promotion;
    public function __construct()
    {
        $this->_mdl_promotion = new PromotionModel();
    }

    public function GetActivePromotion($company_id,$member_id,$channel){
        $rule_list = $this->_mdl_promotion->getNormalMatchRuleId($company_id,$member_id,$channel);
        $return['rule_ids'] = [];
        $return['pid'] = [];
        $return['relation'] = [];
        if(!empty($rule_list)){
            foreach ($rule_list as $key=>$value){
                $return['relation'][$value->rule_id][] = $value->pid;
                $return['rule_ids'][] = $value->rule_id;
                $return['pid'][] = $value->pid;
            }
            $return['rule_ids'] = array_unique($return['rule_ids']);
            $return['pid'] = array_unique($return['pid']);
        }
        return $return;
    }

}
