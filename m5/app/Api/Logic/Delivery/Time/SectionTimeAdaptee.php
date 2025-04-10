<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-10-18
 * Time: 18:40
 */

namespace App\Api\Logic\Delivery\Time;


use App\Api\Logic\Delivery\TimeTarget;
use App\Api\Model\Delivery\RuleTime as RuleTimeModel;
use App\Api\Model\Delivery\RuleTimeSectionTime as RuleTimeSectionTimeModel;

class SectionTimeAdaptee extends TimeTarget
{
    private $type;

    public function __construct($type)
    {
        $this->type = $type;
    }

    public function createRuleTime($ruleId, $timeList)
    {
        app('db')->beginTransaction();
        $timeId = RuleTimeModel::Create([
            'rule_id' => $ruleId,
            'type'    => $this->type,
        ]);
        if (!$timeId) {
            app('db')->rollBack();
            return $this->Response(false, '时间创建失败【1】');
        }

        foreach ($timeList as $timeInfo) {
            $status = RuleTimeSectionTimeModel::Create([
                'time_id' => $timeId,
                'start'   => $timeInfo['start'],
                'end'     => $timeInfo['end']
            ]);
            if (!$status) {
                app('db')->rollBack();
                return $this->Response(false, '时间创建失败【2】');
            }
        }
        app('db')->commit();
        return $this->Response();
    }

    public function deleteRuleTime($timeId)
    {
        app('db')->beginTransaction();
        $regionStatus = RuleTimeModel::Delete($timeId);
        if (!$regionStatus) {
            app('db')->rollBack();
            return $this->Response(false, '时间删除失败【1】');
        }
        $fourLevelStatus = RuleTimeSectionTimeModel::DeleteByTimeId($timeId);
        if (!$fourLevelStatus) {
            app('db')->rollBack();
            return $this->Response(false, '时间删除失败【2】');
        }
        app('db')->commit();
        return $this->Response();
    }

    public function matchingTime($timeId, $timeInfo)
    {
        $deliveryTime = $timeInfo['delivery_time'];

        $deliveryNum = date("H", $deliveryTime) * 3600 + date("i", $deliveryTime) * 60 + date("s", $deliveryTime);

        $timeRuleList = RuleTimeSectionTimeModel::matchingTimeList($timeId, $deliveryNum);

        if ($timeRuleList->count() > 0) {
            return 1;
        }

        return 0;
    }
}
