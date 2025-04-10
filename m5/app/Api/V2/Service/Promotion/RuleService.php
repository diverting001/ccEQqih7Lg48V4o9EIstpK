<?php
/**
 * Created by PhpStorm.
 * User: chuanbin
 * Date: 2020-02-26
 * Time: 17:42
 */

namespace App\Api\V2\Service\Promotion;


use App\Api\Logic\Promotion\CreatorAdapter;
use App\Api\Model\Promotion\PromotionRuleModel;

class RuleService
{
    public function GenConditionData($condition){
        $adapter = new CreatorAdapter($condition['operator_class']);
        return $adapter->generate($condition);
    }

    private function _format_create_data($ruleInfo){
        $gen_ret = $this->GenConditionData($ruleInfo['condition']);
        return array(
            "name" => $ruleInfo['name'],
            "description" => $ruleInfo['description'] ? $ruleInfo['description'] : "",
            "sort" => $ruleInfo['sort'] ? $ruleInfo['sort'] : "100",
            "start_time" => $ruleInfo['start_time'] ? $ruleInfo['start_time'] : time(),
            "end_time" => $ruleInfo['end_time'] ? $ruleInfo['end_time'] : time(),
            "status" => $ruleInfo['status'] ? $ruleInfo['status'] : 2,
            "condition" => $gen_ret,
            "type" => $ruleInfo['type'] ? $ruleInfo['type'] : 'goods',
        );
    }

    public function Create($ruleInfo)
    {
        //处理数据
        $ruleData = $this->_format_create_data($ruleInfo);
        app('db')->beginTransaction();

        $ruleId = PromotionRuleModel::Create($ruleData);
        if (!$ruleId) {
            app('db')->rollBack();
            return $this->Response(false, "规则创建失败");
        }
        $createInfoStatus = $this->CreateRuleRel($ruleId, $ruleInfo['rel_rule_id']);
        if (!$createInfoStatus['status']) {
            app('db')->rollBack();
            return $createInfoStatus;
        }

        app('db')->commit();

        return $this->Response(true, "创建成功", array('rule_id' => $ruleId));
    }
    public function QueryList($whereArr, $page, $pageSize)
    {
        $count = PromotionRuleModel::QueryCount($whereArr);
        if ($count > 0) {
            $ruleList = PromotionRuleModel::QueryList($whereArr, $page, $pageSize);
        } else {
            $ruleList = array();
        }
        return $this->Response(true, "", array(
            'total_num' => $count,
            'total_page' => ceil($count / $pageSize),
            'rule_list' => $ruleList,
        ));

    }

    public function QueryRelList($whereArr, $page, $pageSize)
    {
        $count = PromotionRuleModel::QueryRelCount($whereArr);
        if ($count > 0) {
            $ruleList = PromotionRuleModel::QueryRelList($whereArr, $page, $pageSize);
        } else {
            $ruleList = array();
        }
        return $this->Response(true, "", array(
            'total_num' => $count,
            'total_page' => ceil($count / $pageSize),
            'rule_list' => $ruleList,
        ));

    }

    public function CreateScopeRel($relationData)
    {
        $scopeInfo = PromotionRuleModel::Find($relationData['rule_id']);
        if (!$scopeInfo ) {
            return $this->Response(false, "关联失败,规则不存在或已失效");
        }
        app('db')->beginTransaction();
        //delete old scope
        PromotionRuleModel::DeleteScopeByPid($relationData['rule_id']);
        foreach ($relationData['company_list'] as $companyId) {
            $scope = array(
                "pid"   => $relationData['rule_id'],
                "scope" => 'company',
                "scope_value" => $companyId
            );
            //create scope
            $createRes = PromotionRuleModel::CreateScope($scope);
            if (!$createRes) {
                app('db')->rollBack();
                return $this->Response(false, $createRes['msg']);
            }
        }
        $res = PromotionRuleModel::Update($relationData,['status'=>1]);
        if(!$res){
            app('db')->rollBack();
            return $this->Response(false, $res['msg']);
        }
        app('db')->commit();
        return $this->Response(true, "关联成功");
    }



    public function Update($ruleInfo)
    {
        $ruleId = $ruleInfo['rule_id'];
        $ruleData = $this->_format_create_data($ruleInfo);

        app('db')->beginTransaction();
        $saveStatus = PromotionRuleModel::Update($ruleId, $ruleData);
        if (!$saveStatus) {
            app('db')->rollBack();
            return $this->Response(false, "规则修改失败");
        }

        $delStatus = PromotionRuleModel::DeleteAll($ruleId);
        if (!$delStatus) {
            app('db')->rollBack();
            return $this->Response(false, "规则关联删除失败");
        }

        $createInfoStatus = $this->CreateRuleRel($ruleId, $ruleInfo['rel_rule_id']);
        if (!$createInfoStatus['status']) {
            app('db')->rollBack();
            return $createInfoStatus;
        }

        app('db')->commit();

        return $this->Response(true, "创建成功", array('rule_id' => $ruleId));
    }

    public function Delete($ruleInfo){
        $rule_id = $ruleInfo['rule_id'];
        //delete action scope
        PromotionRuleModel::DeleteScopeByPid($rule_id);
        //delete rule rel
        PromotionRuleModel::DeleteAll($rule_id);
        //delete rule
        $del_rule_res = PromotionRuleModel::DeleteRule($rule_id);
        if(!$del_rule_res){
            return $this->Response(false, "规则删除失败");
        }
        return $this->Response(true, "删除成功", []);
    }

    public function StatusEdit($ruleInfo)
    {
        $ruleId = $ruleInfo['rule_id'];
        $ruleData = array(
            "status" => $ruleInfo['status'] ? $ruleInfo['status'] : 2,
        );
        $saveStatus = PromotionRuleModel::Update($ruleId, $ruleData);
        if (!$saveStatus) {
            return $this->Response(false, "修改失败");
        }
        return $this->Response(true, "修改成功", array('rule_id' => $ruleId));
    }

    private function CreateRuleRel($ruleId, $rel_rule_id)
    {
        $infoStatus = PromotionRuleModel::CreateRel($ruleId,$rel_rule_id);
        if (!$infoStatus) {
            app('db')->rollBack();
            return $this->Response(false, "规则关联失败");
        }
        return $this->Response(true, "规则关联成功");
    }

    public function QueryCompanyRuleList($filterData){
        $company_id = is_array($filterData['company_id']) ? $filterData['company_id'] : [$filterData['company_id']];

        $status = $filterData['status'] ?: 0;

        $time_available = $filterData['time_available'] ?: 0;

        $query_list = PromotionRuleModel::QueryRuleListByCompanyId($company_id, $status, $time_available);

        return $this->Response(true, '', $query_list);
    }

    private function Response($status = true, $msg = '成功', $data = [])
    {
        return [
            'status' => $status,
            'msg' => $msg,
            'data' => $data,
        ];
    }

}
