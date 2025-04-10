<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-10-22
 * Time: 17:10
 */

namespace App\Api\Logic\Delivery;


use App\Api\Model\Delivery\RuleRegion as RegionModel;
use App\Api\Model\Delivery\RuleTime as RuleTimeModel;

class Rule
{
    public function regionFunnel($ruleIdList, $regionInfo)
    {
        $availableRuleIdList = [];

        $ruleList = RegionModel::QueryByRuleIds($ruleIdList);
        foreach ($ruleList as $regionRule) {
            if (in_array($regionRule->region_id, $availableRuleIdList)) {
                continue;
            }
            try {
                $regionAdapter = new RegionAdapter($regionRule->type);
            } catch (\Exception $e) {
                continue;
            }


            $regionStatus = $regionAdapter->matchingRegion($regionRule->region_id, $regionInfo);
            if ($regionStatus) {
                $availableRuleIdList[] = $regionRule->rule_id;
            }
        }

        return $availableRuleIdList;
    }

    public function timeFunnel($ruleIdList, $timeInfo)
    {
        $availableRuleIdList = [];

        $ruleList = RuleTimeModel::QueryByRuleIds($ruleIdList);
        foreach ($ruleList as $timeRule) {
            if (in_array($timeRule->time_id, $availableRuleIdList)) {
                continue;
            }
            try {
                $timeAdapter = new TimeAdapter($timeRule->type);
            } catch (\Exception $e) {
                continue;
            }

            $timeStatus = $timeAdapter->matchingTime($timeRule->time_id, $timeInfo);
            if ($timeStatus) {
                $availableRuleIdList[] = $timeRule->rule_id;
            }
        }

        return $availableRuleIdList;
    }
}
