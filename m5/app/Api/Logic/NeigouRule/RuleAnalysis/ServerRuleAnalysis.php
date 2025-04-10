<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-01-31
 * Time: 15:32
 */

namespace App\Api\Logic\NeigouRule\RuleAnalysis;


class ServerRuleAnalysis extends ARuleAnalysis
{
    public function WithRule($ruleIdList, $filterData)
    {
        return $this->Response('false', '暂不支持服务类型积分结算');
    }
}
