<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-10-22
 * Time: 17:10
 */

namespace App\Api\Logic\DeliveryLimit;


use App\Api\Model\DeliveryLimit\RuleRegion as RegionModel;
use App\Api\Model\DeliveryLimit\RuleTime as RuleTimeModel;

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

    public function getAvailableRegion($ruleIdList, $regionInfo)
    {
        $availableRegionList = [];

        $ruleList = RegionModel::QueryByRuleIds($ruleIdList);
        foreach ($ruleList as $regionRule) {
            try {
                $regionAdapter = new RegionAdapter($regionRule->type);
            } catch (\Exception $e) {
                continue;
            }

            $regionList = $regionAdapter->getAvailableRegion($regionRule->region_id, $regionInfo);
            if ($regionList) {
                foreach ($regionList as $regionItem) {

                    $regionItem->type = $regionRule->type;

                    $availableRegionList[$regionRule->rule_id][] = $regionItem;
                }
            }
        }

        return $availableRegionList;
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

    public function getAvailableTime($ruleIdList, $timeInfo)
    {
        $availableTimeList = [];

        $ruleList = RuleTimeModel::QueryByRuleIds($ruleIdList);
        foreach ($ruleList as $timeRule) {
            try {
                $timeAdapter = new TimeAdapter($timeRule->type);
            } catch (\Exception $e) {
                continue;
            }

            $timeList = $timeAdapter->getAvailableTime($timeRule->time_id, $timeInfo);
            if ($timeList) {
                foreach ($timeList as $timeItem) {

                    $timeItem->type = $timeRule->type;

                    $availableTimeList[$timeRule->rule_id][] = $timeItem;
                }
            }
        }

        return $availableTimeList;
    }
}
