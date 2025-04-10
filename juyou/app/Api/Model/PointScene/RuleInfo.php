<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-01-16
 * Time: 18:11
 */

namespace App\Api\Model\PointScene;


class RuleInfo
{
    /**
     * 创建积分使用规则
     */
    public static function Create($ruleInfoData)
    {
        try {
            $status = app('api_db')->table('server_new_point_rule_info')->insert($ruleInfoData);
        } catch (\Exception $e) {
            $status = false;
        }
        return $status;
    }

    /**
     * 修改规则
     */
    public static function DeleteAll($ruleId)
    {
        try {
            $status = app('api_db')->table('server_new_point_rule_info')->where('rule_id', $ruleId)->delete();
        } catch (\Exception $e) {
            $status = false;
        }
        return $status;
    }

    public static function Query($consumeType, $filterKey, $ruleId, $filterVal, $filterVal1 = null, $filterVal2 = null, $filterVal3 = null)
    {
        $dbObj = app('api_db')->table('server_new_point_rule_info')
            ->where('consume_type', $consumeType)
            ->where('filter_key', $filterKey);
        if ($ruleId) {
            if (is_array($ruleId)) {
                $dbObj = $dbObj->whereIn('rule_id', $ruleId);
            } else {
                $dbObj = $dbObj->where('rule_id', $ruleId);
            }
        }
        if ($filterVal) {
            if (is_array($filterVal)) {
                $dbObj = $dbObj->whereIn('filter_val', $filterVal);
            } else {
                $dbObj = $dbObj->where('filter_val', $filterVal);
            }
        }

        if ($filterVal1) {
            if (is_array($filterVal1)) {
                $dbObj = $dbObj->whereIn('filter_val1', $filterVal1);
            } else {
                $dbObj = $dbObj->where('filter_val1', $filterVal1);
            }
        }

        if ($filterVal2) {
            if (is_array($filterVal2)) {
                $dbObj = $dbObj->whereIn('filter_val2', $filterVal2);
            } else {
                $dbObj = $dbObj->where('filter_val2', $filterVal2);
            }
        }

        if ($filterVal3) {
            if (is_array($filterVal3)) {
                $dbObj = $dbObj->whereIn('filter_val3', $filterVal3);
            } else {
                $dbObj = $dbObj->where('filter_val3', $filterVal3);
            }
        }
        return $dbObj->get();
    }

    public static function FindAll($ruleId)
    {
        $where = array(
            'rule_id' => $ruleId
        );
        return app('api_db')->table('server_new_point_rule_info')->where($where)->get();
    }

}
