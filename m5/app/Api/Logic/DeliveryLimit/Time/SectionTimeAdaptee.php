<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-10-18
 * Time: 18:40
 */

namespace App\Api\Logic\DeliveryLimit\Time;


use App\Api\Logic\DeliveryLimit\TimeTarget;
use App\Api\Model\DeliveryLimit\RuleTime as RuleTimeModel;
use App\Api\Model\DeliveryLimit\RuleTimeSectionTime as RuleTimeSectionTimeModel;

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
                'time_id'  => $timeId,
                'start'    => $timeInfo['start'],
                'end'      => $timeInfo['end'],
                'advance'  => $timeInfo['advance'],
                'interval' => $timeInfo['interval'],
                'open'     => $timeInfo['open'],
                'stop'     => $timeInfo['stop'],
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
        $orderTime    = $timeInfo['order_time'];
        $deliveryTime = $timeInfo['delivery_time'];

        $deliveryNum = date("H", $deliveryTime) * 3600 + date("i", $deliveryTime) * 60 + date("s", $deliveryTime);

        $timeRuleList = RuleTimeSectionTimeModel::matchingTimeList($timeId, $deliveryNum);

        if ($timeRuleList->count() <= 0) {
            return 0;
        }

        foreach ($timeRuleList as $timeRule) {
            if ($timeRule->interval == 0) {
                return 1;
            }

            if (!$timeRule->advance && !$timeRule->stop) {
                return 1;
            }
            //如果截单时间为0则只算提前时间
            if ($timeRule->stop <= 0) {
                if ($orderTime + $timeRule->advance < $deliveryTime) {
                    return 1;
                } else {
                    return 0;
                }
            } else {
                //下单当天开始的时间
                $orderStartTime = strtotime(date('Y-m-d', $orderTime));
                $nextStartTime  = strtotime('+1 day', $orderStartTime);
                //下单当天截单时间
                $orderStopTime = $orderStartTime + $timeRule->stop;

                if ($orderTime >= $orderStopTime || $orderTime < ($timeRule->open + $orderStartTime)) {
                    $orderTimeNum = date("H", $orderTime) * 3600 + date("i", $orderTime) * 60 + date("s", $orderTime);
                    //当天能预约之前下的单
                    if ($timeRule->start >= $orderTimeNum) {
                        $earliestAllowTime = $orderStartTime + $timeRule->start + $timeRule->advance;
                    } else {
                        $earliestAllowTime = $nextStartTime + $timeRule->start + $timeRule->advance;
                    }
                    if ($earliestAllowTime <= $deliveryTime) {
                        return 1;
                    } else {
                        return 0;
                    }
                } else {
                    //存在于第几个区间
                    $intervalNum       = floor(($orderTime + $timeRule->advance - $orderStartTime - $timeRule->start) / $timeRule->interval);
                    $earliestAllowTime = $intervalNum * $timeRule->interval + $orderStartTime + $timeRule->start;
                    if ($earliestAllowTime <= $deliveryTime) {
                        return 1;
                    } else {
                        return 0;
                    }
                }
            }
        }
    }

    public function getAvailableTime($timeId, $timeInfo)
    {
        $timeRuleList = RuleTimeSectionTimeModel::matchingTimeList($timeId);
        return $timeRuleList;
    }
}
