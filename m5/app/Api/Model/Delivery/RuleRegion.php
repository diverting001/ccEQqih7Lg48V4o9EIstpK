<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-10-18
 * Time: 15:25
 */

namespace App\Api\Model\Delivery;


class RuleRegion
{
    public static function Create($regionInfo)
    {
        $regionInfo['create_time'] = time();
        $regionInfo['update_time'] = time();
        try {
            $id = app('api_db')->table('server_delivery_rule_region')->insertGetId($regionInfo);
        } catch (\Exception $e) {
            $id = false;
        }
        return $id;
    }

    public static function Delete($regionId)
    {
        try {
            $status = app('api_db')
                ->table('server_delivery_rule_region')
                ->where('region_id', $regionId)
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
            ->table('server_delivery_rule_region')
            ->where($where)
            ->get();
    }

    public static function QueryByRuleIds($ruleIds)
    {
        return app('api_db')
            ->table('server_delivery_rule_region')
            ->whereIn('rule_id', $ruleIds)
            ->get();
    }
}
