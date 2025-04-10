<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-10-18
 * Time: 15:25
 */

namespace App\Api\Model\Delivery;


class Rule
{
    public static function Create($ruleInfo)
    {
        $ruleInfo['create_time'] = time();
        $ruleInfo['update_time'] = time();
        try {
            $id = app('api_db')->table('server_delivery_rule')->insertGetId($ruleInfo);
        } catch (\Exception $e) {
            $id = false;
        }
        return $id;
    }

    public static function Delete($ruleId)
    {
        try {
            $status = app('api_db')
                ->table('server_delivery_rule')
                ->where('id', $ruleId)
                ->delete();
        } catch (\Exception $e) {
            $status = false;
        }
        return $status;
    }

    public static function Find($ruleId)
    {
        $where = array(
            'id' => $ruleId
        );
        return app('api_db')
            ->table('server_delivery_rule')
            ->where($where)
            ->first();
    }

    public static function getRuleIdByTempBn($tempBn)
    {
        $where = array(
            'template_bn' => $tempBn
        );
        $list  = app('api_db')
            ->table('server_delivery_rule')
            ->select('id')
            ->where($where)
            ->get();
        return $list->count() > 0 ? array_column($list->toArray(), 'id') : [];
    }

    public static function getFirstSortRule($ruleIds)
    {
        return app('api_db')
            ->table('server_delivery_rule')
            ->whereIn('id', $ruleIds)
            ->orderBy('rule_sort', 'desc')
            ->first();
    }
}
