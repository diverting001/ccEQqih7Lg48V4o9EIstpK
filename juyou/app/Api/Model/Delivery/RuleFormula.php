<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-10-18
 * Time: 15:25
 */

namespace App\Api\Model\Delivery;


class RuleFormula
{
    public static function Create($formulaInfo)
    {
        $formulaInfo['create_time'] = time();
        $formulaInfo['update_time'] = time();
        try {
            $id = app('api_db')->table('server_delivery_rule_formula')->insertGetId($formulaInfo);
        } catch (\Exception $e) {
            $id = false;
        }
        return $id;
    }

    public static function Delete($formulaId)
    {
        try {
            $status = app('api_db')
                ->table('server_delivery_rule_formula')
                ->where('formula_id', $formulaId)
                ->delete();
        } catch (\Exception $e) {
            $status = false;
        }
        return $status;
    }

    public static function Find($formulaId)
    {
        return app('api_db')
            ->table('server_delivery_rule_formula')
            ->where('formula_id', $formulaId)
            ->first();
    }

    public static function QueryByRuleId($ruleId)
    {
        $where = array(
            'rule_id' => $ruleId
        );
        return app('api_db')
            ->table('server_delivery_rule_formula')
            ->where($where)
            ->first();
    }
}
