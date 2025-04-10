<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-09-19
 * Time: 15:47
 */

namespace App\Api\V1\Service\NeigouRule;

use App\Api\V1\Service\ServiceTrait;

class NeigouRule
{
    use ServiceTrait;

    public function ruleWithRule($queryData)
    {
        $returnData = array();
        foreach ($queryData['filter_data'] as $filterType => $filterInfo) {
            if ($filterInfo) {
                $className = 'App\\Api\\Logic\\NeigouRule\\RuleAnalysis\\' . ucfirst(camel_case($filterType)) . "RuleAnalysis";
                if (!class_exists($className)) {
                    $returnData[$filterType] = array();
                    continue;
                }
                $transactionObj = new $className();
                $withRuleRes    = $transactionObj->WithRule($queryData['rule_list'], $filterInfo);
                if ($withRuleRes['status']) {
                    $returnData[$filterType] = $withRuleRes['data'];
                }
            } else {
                $returnData[$filterType] = array();
            }
        }

        return $this->Response(true, '成功', $returnData);
    }

}
