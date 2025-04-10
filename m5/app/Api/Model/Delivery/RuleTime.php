<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-10-18
 * Time: 15:25
 */

namespace App\Api\Model\Delivery;


class RuleTime
{
    public static function Create($timeInfo)
    {
        $timeInfo['create_time'] = time();
        $timeInfo['update_time'] = time();
        try {
            $id = app('api_db')->table('server_delivery_rule_time')->insertGetId($timeInfo);
        } catch (\Exception $e) {
            $id = false;
        }
        return $id;
    }

    public static function Delete($timeId)
    {
        try {
            $status = app('api_db')
                ->table('server_delivery_rule_time')
                ->where('time_id', $timeId)
                ->delete();
        } catch (\Exception $e) {
            $status = false;
        }
        return $status;
    }

    public static function QueryByRuleId($ruleId)
    {
        $where = array(
            'rule_id' => $ruleId
        );
        return app('api_db')
            ->table('server_delivery_rule_time')
            ->where($where)
            ->get();
    }

    public static function QueryByRuleIds($ruleIds)
    {
        return app('api_db')
            ->table('server_delivery_rule_time')
            ->whereIn('rule_id', $ruleIds)
            ->get();
    }

}
