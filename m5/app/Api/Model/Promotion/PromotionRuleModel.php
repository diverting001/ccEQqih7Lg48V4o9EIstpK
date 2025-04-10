<?php
/**
 * Created by PhpStorm.
 * User: chuanbin
 * Date: 2020-02-26
 * Time: 17:48
 */

namespace App\Api\Model\Promotion;


class PromotionRuleModel
{
    public static function QueryCount($whereArr)
    {
        $ruleDb = app('api_db')->table('server_promotion_v2');
        if ($whereArr['name']) {
            $ruleDb = $ruleDb->where('name', 'like', '%' . $whereArr['name'] . '%');
        }
        if ($whereArr['rule_ids']) {
            $ruleDb = $ruleDb->whereIn('id', $whereArr['rule_ids']);
        }
        if ($whereArr['status']) {
            $ruleDb = $ruleDb->where('status', $whereArr['disabled']);
        }
        if ($whereArr['type']) {
            $ruleDb = $ruleDb->where('type', $whereArr['type']);
        }
        $count = $ruleDb->count();
        return $count ? $count : 0;
    }

    public static function QueryList($whereArr, $page = 1, $pageSize = 10)
    {
        $ruleDb = app('api_db')->table('server_promotion_v2');
        if ($whereArr['name']) {
            $ruleDb = $ruleDb->where('name', 'like', '%' . $whereArr['name'] . '%');
        }
        if ($whereArr['rule_ids']) {
            $ruleDb = $ruleDb->whereIn('id', $whereArr['rule_ids']);
        }
        if ($whereArr['updated_at']) {
            $ruleDb = $ruleDb->where('updated_at', '>', $whereArr['updated_at']);
        }
        if ($whereArr['status']) {
            $ruleDb = $ruleDb->where('status', $whereArr['status']);
        }
        if ($whereArr['type']) {
            $ruleDb = $ruleDb->where('type', $whereArr['type']);
        }
        $ruleDb = $ruleDb->orderBy('id', 'desc');
        return $ruleDb->forPage($page, $pageSize)->get();
    }

    public static function QueryRelCount($whereArr)
    {
        $ruleDb = app('api_db')->table('server_promotion_v2_action_scope');

        if ($whereArr['pid']) {
            $ruleDb = $ruleDb->whereIn('pid', $whereArr['pid']);
        }
        if ($whereArr['scope']) {
            $ruleDb = $ruleDb->where('scope', $whereArr['scope']);
        }
        $count = $ruleDb->count();
        return $count ? $count : 0;
    }

    public static function QueryRelList($whereArr, $page = 1, $pageSize = 10)
    {
        $ruleDb = app('api_db')->table('server_promotion_v2_action_scope');
        if ($whereArr['pid']) {
            $ruleDb = $ruleDb->whereIn('pid', $whereArr['pid']);
        }
        if ($whereArr['scope']) {
            $ruleDb = $ruleDb->where('scope', $whereArr['scope']);
        }
        $ruleDb = $ruleDb->orderBy('id', 'desc');
        return $ruleDb->forPage($page, $pageSize)->get();
    }

    /**
     * 创建积分使用规则
     * @param $ruleInfo
     * @return bool
     */
    public static function Create($ruleInfo)
    {
        $ruleInfo = array(
            'name'       => $ruleInfo['name'],
            'description'       => $ruleInfo['description'],
            'sort'   => $ruleInfo['sort'],
            'start_time'      => $ruleInfo['start_time'],
            'end_time'    => $ruleInfo['end_time'],
            'status'    => 2,
            'condition'    => $ruleInfo['condition'],
            'type'    => $ruleInfo['type'],
            'updated_at'    => time(),
        );
        try {
            $lastId = app('api_db')->table('server_promotion_v2')->insertGetId($ruleInfo);
        } catch (\Exception $e) {
            $lastId = false;
        }
        return $lastId;
    }

    public static function CreateRel($pid,$rule_ids){
        if(is_array($rule_ids)){
            foreach ($rule_ids as $key=> $rid){
                $insert[$key]['rule_id'] = $rid;
                $insert[$key]['pid'] = $pid;
            }
        } else {
            $insert['pid'] = $pid;
            $insert['rule_id'] = $rule_ids;
        }
        try {
            $ret = app('api_db')->table('server_promotion_v2_rule_rel')->insert($insert);
        } catch (\Exception $e) {
            $ret = false;
        }
        return $ret;
    }

    public static function DeleteAll($pid)
    {
        try {
            $status = app('api_db')->table('server_promotion_v2_rule_rel')->where('pid', $pid)->delete();
        } catch (\Exception $e) {
            $status = false;
        }
        return $status;
    }

    public static function DeleteRule($rule_id)
    {
        try {
            $status = app('api_db')->table('server_promotion_v2')->where('id', $rule_id)->delete();
        } catch (\Exception $e) {
            $status = false;
        }
        return $status;
    }

    /**
     * 修改规则
     */
    public static function Update($ruleId, $ruleInfoData)
    {
        try {
            $ruleInfoData['updated_at'] = time();
            $status                     = app('api_db')->table('server_promotion_v2')->where(array('id' => $ruleId))->update($ruleInfoData);
        } catch (\Exception $e) {
            $status = false;
        }
        return $status;
    }

    /**
     * 查询促销规则
     * @param $ruleId
     * @return mixed
     */
    public static function Find($ruleId)
    {
        $where = array(
            'id' => $ruleId
        );
        return app('api_db')->table('server_promotion_v2')->where($where)->first();
    }

    public static function FindAllRel($ruleId)
    {
        $where = array(
            'rel.pid' => $ruleId
        );
        return app('api_db')->table('server_promotion_v2_rule_rel as rel')
            ->leftJoin('server_new_point_rule as rule','rel.rule_id','=','rule.rule_id')
            ->where($where)->get();
    }

    //创建scope关联
    public static function CreateScope($data){
        try {
            $lastId = app('api_db')->table('server_promotion_v2_action_scope')->insertGetId($data);
        } catch (\Exception $e) {
            $lastId = false;
        }
        return $lastId;
    }

    //删除scope关联
    public static function DeleteScopeByPid($pid){
        try {
            $status = app('api_db')->table('server_promotion_v2_action_scope')->where('pid', $pid)->delete();
        } catch (\Exception $e) {
            $status = false;
        }
        return $status;
    }

    public static function QueryRuleListByCompanyId($companyId, $status = 0, $time_available = 0){
        $resQuery = app('api_db')->table('server_promotion_v2_action_scope as action')
            ->leftJoin('server_promotion_v2 as promotion', 'action.pid', '=', 'promotion.id')
            ->where('action.scope', 'company')
            ->whereIn('action.scope_value', $companyId);

        if($status > 0){
            $resQuery = $resQuery->where('promotion.status', $status);
        }

        if ($time_available > 0){
            $resQuery = $resQuery->where('promotion.start_time', '<', time())->where('promotion.end_time', '>', time());
        }

        $resQuery = $resQuery->where('promotion.type', 'company')
            ->select('action.scope_value as company_id', 'promotion.id as promotion_id', 'promotion.name', 'promotion.status', 'promotion.sort', 'promotion.start_time', 'promotion.end_time')
            ->get()
            ->all();

        return is_array($resQuery) ? $resQuery : [];
    }

    /**
     * @desc 通过rel_id 查询 ruleId
     *
     * @param $relRuleId
     *
     * @return array
     */
    public static  function QueryRuleIdsByRelRuleId($relRuleId){
        $resQuery = app('api_db')->table('server_promotion_v2_rule_rel')
            ->where('rule_id',$relRuleId)
            ->select('pid','rule_id')
            ->get()->all();
        return is_array($resQuery)?$resQuery:[];
    }

    public static function QueryRelListByCompanyIdWithAll($company_id)
    {
        $ruleDb = app('api_db')->table('server_promotion_v2_action_scope');
        $scope = array('all');
        if ($company_id){
            $scope[] = $company_id;
        }
        $ruleDb = $ruleDb->whereIn('scope_value', $scope);
        $ruleDb = $ruleDb->orderBy('id', 'desc');
        $resQuery =  $ruleDb->get()->all();
        return is_array($resQuery)?$resQuery:[];
    }

}
