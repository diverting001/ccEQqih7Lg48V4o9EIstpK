<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-01-31
 * Time: 15:32
 */

namespace App\Api\Logic\PointScene\RuleAnalysis;


class ServerRuleAnalysis extends ARuleAnalysis
{
    public function WithRule($ruleIdList,$sceneIdList, $filterData)
    {
        return $this->Response('false', '暂不支持服务类型积分结算');
    }
}
