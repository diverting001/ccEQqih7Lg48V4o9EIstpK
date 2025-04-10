<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-01-16
 * Time: 17:05
 */

namespace App\Api\V1\Service\PointScene;

use App\Api\Model\PointScene\Rule as RuleModel;
use App\Api\Model\PointScene\RuleInfo as RuleInfoModel;

class Rule
{
    public function QueryList($whereArr, $page, $pageSize)
    {
        $count = RuleModel::QueryCount($whereArr);
        if ($count > 0) {
            $ruleList = RuleModel::QueryList($whereArr, $page, $pageSize);
        } else {
            $ruleList = array();
        }
        return $this->Response(true, "", array(
            'total_num' => $count,
            'total_page' => ceil($count / $pageSize),
            'rule_list' => $ruleList,
        ));

    }

    public function Create($ruleInfo)
    {
        $ruleData = array(
            "name" => $ruleInfo['name'],
            "desc" => $ruleInfo['desc'] ? $ruleInfo['desc'] : "",
            "op_id" => $ruleInfo['op_id'] ? $ruleInfo['op_id'] : "",
            "op_name" => $ruleInfo['op_name'] ? $ruleInfo['op_name'] : "",
        );
        app('db')->beginTransaction();

        $ruleId = RuleModel::Create($ruleData);
        if (!$ruleId) {
            app('db')->rollBack();
            return $this->Response(false, "规则创建失败");
        }

        $createInfoStatus = $this->CreateRuleInfo($ruleId, $ruleInfo);
        if (!$createInfoStatus['status']) {
            app('db')->rollBack();
            return $createInfoStatus;
        }

        app('db')->commit();

        return $this->Response(true, "创建成功", array('rule_id' => $ruleId));
    }

    public function Update($ruleInfo)
    {
        $ruleId = $ruleInfo['rule_id'];
        $ruleData = array(
            "name" => $ruleInfo['name'],
            "desc" => $ruleInfo['desc'] ? $ruleInfo['desc'] : "",
            "op_id" => $ruleInfo['op_id'] ? $ruleInfo['op_id'] : "",
            "op_name" => $ruleInfo['op_name'] ? $ruleInfo['op_name'] : "",
        );

        app('db')->beginTransaction();
        $saveStatus = RuleModel::Update($ruleId, $ruleData);
        if (!$saveStatus) {
            app('db')->rollBack();
            return $this->Response(false, "规则修改失败");
        }

        $delStatus = RuleInfoModel::DeleteAll($ruleId);
        if (!$delStatus) {
            app('db')->rollBack();
            return $this->Response(false, "规则详情删除失败");
        }

        $createInfoStatus = $this->CreateRuleInfo($ruleId, $ruleInfo);
        if (!$createInfoStatus['status']) {
            app('db')->rollBack();
            return $createInfoStatus;
        }

        app('db')->commit();

        return $this->Response(true, "创建成功", array('rule_id' => $ruleId));
    }

    private function CreateRuleInfo($ruleId, $ruleInfo)
    {
        $ruleInfoData = array();
        foreach ($ruleInfo['filter_bn'] as $bnInfo) {
            $ruleInfoData[] = array(
                "rule_id" => $ruleId,
                "consume_type" => $ruleInfo['consume_type'],
                "filter_key" => $bnInfo['filter_type'],
                "filter_val" => $bnInfo['val'],
                "filter_val1" => isset($bnInfo['val1']) && $bnInfo['val1'] ? $bnInfo['val1'] : "all",
                "filter_val2" => isset($bnInfo['val2']) && $bnInfo['val2'] ? $bnInfo['val2'] : "all",
                "filter_val3" => isset($bnInfo['val3']) && $bnInfo['val3'] ? $bnInfo['val3'] : "all",
                'created_at' => time(),
                'act' => $bnInfo['act'] ?: 'in',
                'group_id' => $bnInfo['group_id'],
            );
        }

        $infoStatus = RuleInfoModel::Create($ruleInfoData);
        if (!$infoStatus) {
            app('db')->rollBack();
            return $this->Response(false, "规则详情创建失败");
        }
        return $this->Response(true, "规则详情创建失败");
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
