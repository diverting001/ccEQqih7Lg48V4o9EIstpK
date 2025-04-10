<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-10-18
 * Time: 15:25
 */

namespace App\Api\Model\DeliveryLimit;


class Rule
{
    public static function Create($ruleInfo)
    {
        $ruleInfo['create_time'] = time();
        $ruleInfo['update_time'] = time();
        try {
            $id = app('api_db')->table('server_delivery_limit_rule')->insertGetId($ruleInfo);
        } catch (\Exception $e) {
            $id = false;
        }
        return $id;
    }

    public static function Delete($ruleId)
    {
        try {
            $status = app('api_db')
                ->table('server_delivery_limit_rule')
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
            ->table('server_delivery_limit_rule')
            ->where($where)
            ->first();
    }

    public static function getRuleInfoByTempBn($tempBn)
    {
        return app('api_db')
            ->table('server_delivery_limit_rule')
            ->where('template_bn', $tempBn)
            ->orderBy('rule_sort' , 'desc')
            ->get();
    }

    public static function getRuleIdByTempBn($tempBn)
    {
        $where = array(
            'template_bn' => $tempBn
        );
        $list  = app('api_db')
            ->table('server_delivery_limit_rule')
            ->select('id')
            ->where($where)
            ->get();
        return $list->count() > 0 ? array_column($list->toArray(), 'id') : [];
    }


}
