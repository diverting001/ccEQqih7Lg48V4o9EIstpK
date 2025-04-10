<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-10-18
 * Time: 15:25
 */

namespace App\Api\Model\Delivery;

class RuleTimeSectionTime
{
    public static function Create($timeInfo)
    {
        try {
            $id = app('api_db')->table('server_delivery_rule_time_section_time')->insertGetId($timeInfo);
        } catch (\Exception $e) {
            $id = false;
        }
        return $id;
    }

    public static function DeleteByTimeId($timeId)
    {
        try {
            $status = app('api_db')
                ->table('server_delivery_rule_time_section_time')
                ->where('time_id', $timeId)
                ->delete();
        } catch (\Exception $e) {
            $status = false;
        }
        return $status;
    }

    public static function matchingTimeList($timeId, $deliveryNum = 0)
    {
        $list = app('api_db')
            ->table('server_delivery_rule_time_section_time')
            ->where('time_id', $timeId)
            ->when($deliveryNum, function ($query) use ($deliveryNum) {
                $query->where('start', '<=', $deliveryNum)
                    ->where('end', '>', $deliveryNum);
            })
            ->get();

        return $list;
    }

}
