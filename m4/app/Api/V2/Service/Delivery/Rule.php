<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-10-18
 * Time: 15:16
 */

namespace App\Api\V2\Service\Delivery;

use App\Api\Logic\Delivery\RegionAdapter;
use App\Api\Logic\Delivery\TimeAdapter;
use App\Api\Logic\Delivery\FormulaAdapter;
use App\Api\Logic\Delivery\Rule as RuleLogic;

use App\Api\Model\Delivery\Rule as RuleModel;
use App\Api\Model\Delivery\RuleFormula;
use App\Api\Model\Delivery\Template as TemplateModel;
use App\Api\Model\Delivery\RuleRegion as RuleRegionModel;
use App\Api\Model\Delivery\RuleTime as RuleTimeModel;
use App\Api\Model\Delivery\RuleFormula as RuleFormulaModel;

class Rule
{
    private $templageList = [];

    public function BatchCreate($templageBn, $ruleList)
    {
        $ruleIdList = [];
        app('db')->beginTransaction();
        foreach ($ruleList as $ruleInfo) {
            $res = $this->Create($templageBn, $ruleInfo);
            if (!$res['status']) {
                app('db')->rollBack();
                return $res;
            }
            $ruleIdList[] = $res['data']['rule_id'];
        }
        app('db')->commit();

        return $this->Response(true, '成功', ['rule_ids' => $ruleIdList]);
    }

    public function Create($templageBn, $ruleInfo)
    {
        //运费模板验证
        if (!in_array($templageBn, $this->templageList)) {
            $tempInfo = TemplateModel::Find($templageBn);
            if ($tempInfo) {
                $this->templageList[] = $templageBn;
            } else {
                return $this->Response(false, '快递模板不存在');
            }
        }

        //规则验证
        if (
            !$ruleInfo['rule_name'] ||
            !$ruleInfo['rule_desc'] ||
            !$ruleInfo['formula_info'] ||
            !isset($ruleInfo['region_info']) ||
            !$ruleInfo['region_info'] ||
            !isset($ruleInfo['time_info']) ||
            !$ruleInfo['time_info']
        ) {
            return $this->Response(false, '参数错误');
        }


        app('db')->beginTransaction();

        //规则创建
        $ruleId = RuleModel::Create([
            'template_bn' => $templageBn,
            'rule_name'   => $ruleInfo['rule_name'],
            'rule_desc'   => $ruleInfo['rule_desc'],
            'rule_sort'   => isset($ruleInfo['rule_sort']) ? $ruleInfo['rule_sort'] : 0,
        ]);

        if (!$ruleId) {
            app('db')->rollBack();
            return $this->Response(false, '创建失败【1】');
        }

        //地址创建
        try {
            $regionAdapter = new RegionAdapter($ruleInfo['region_info']['type']);
        } catch (\Exception $e) {
            app('db')->rollBack();
            return $this->Response(false, $e->getMessage());
        }


        $regionRes = $regionAdapter->createRuleRegion($ruleId, $ruleInfo['region_info']['value']);
        if (!$regionRes['status']) {
            app('db')->rollBack();
            return $this->Response(false, $regionRes['msg']);
        }

        //时间创建
        try {
            $timeAdapter = new TimeAdapter($ruleInfo['time_info']['type']);
        } catch (\Exception $e) {
            app('db')->rollBack();
            return $this->Response(false, $e->getMessage());
        }


        $timeRes = $timeAdapter->createRuleTime($ruleId, $ruleInfo['time_info']['value']);
        if (!$timeRes['status']) {
            app('db')->rollBack();
            return $this->Response(false, $timeRes['msg']);
        }

        //运费创建
        try {
            $formulaAdapter = new FormulaAdapter($ruleInfo['formula_info']['type']);
        } catch (\Exception $e) {
            app('db')->rollBack();
            return $this->Response(false, $e->getMessage());
        }


        $formulaRes = $formulaAdapter->createRuleFormula($ruleId, $ruleInfo['formula_info']['value']);
        if (!$formulaRes['status']) {
            app('db')->rollBack();
            return $this->Response(false, $formulaRes['msg']);
        }

        app('db')->commit();

        return $this->Response(true, '成功', ['rule_id' => $ruleId]);
    }

    public function BatchDelete($ruleIds)
    {
        $ruleIdList = [];
        app('db')->beginTransaction();
        foreach ($ruleIds as $ruleId) {
            $res = $this->Delete($ruleId);
            if (!$res['status']) {
                app('db')->rollBack();
                return $res;
            }
            $ruleIdList[] = $res['data']['rule_id'];
        }
        app('db')->commit();

        return $this->Response(true, '成功', ['rule_ids' => $ruleIdList]);
    }

    public function Delete($ruleId)
    {
        $ruleInfo = RuleModel::Find($ruleId);
        if (!$ruleInfo) {
            return $this->Response(true, '', ['rule_id' => $ruleId]);
        }

        app('db')->beginTransaction();

        $ruleStatus = RuleModel::Delete($ruleId);
        if (!$ruleStatus) {
            app('db')->rollBack();
            return $this->Response(false, '规则删除失败');
        }

        $regionList = RuleRegionModel::QueryByRuleId($ruleId);
        if ($regionList->count() > 0) {
            foreach ($regionList as $regionInfo) {
                try {
                    $regionAdapter = new RegionAdapter($regionInfo->type);
                } catch (\Exception $e) {
                    app('db')->rollBack();
                    return $this->Response(false, $e->getMessage());
                }


                $regionRes = $regionAdapter->deleteRuleRegion($regionInfo->region_id);
                if (!$regionRes['status']) {
                    app('db')->rollBack();
                    return $this->Response(false, $regionRes['msg']);
                }
            }
        }

        $timeList = RuleTimeModel::QueryByRuleId($ruleId);
        if ($timeList->count() > 0) {
            foreach ($timeList as $timeInfo) {
                try {
                    $timeAdapter = new TimeAdapter($timeInfo->type);
                } catch (\Exception $e) {
                    app('db')->rollBack();
                    return $this->Response(false, $e->getMessage());
                }


                $timeRes = $timeAdapter->deleteRuleTime($timeInfo->time_id);
                if (!$timeRes['status']) {
                    app('db')->rollBack();
                    return $this->Response(false, $timeRes['msg']);
                }
            }
        }

        $formulaInfo = RuleFormulaModel::QueryByRuleId($ruleId);
        if ($formulaInfo) {
            try {
                $formulaAdapter = new FormulaAdapter($formulaInfo->type);
            } catch (\Exception $e) {
                app('db')->rollBack();
                return $this->Response(false, $e->getMessage());
            }


            $formulaRes = $formulaAdapter->deleteRuleFormula($formulaInfo->formula_id);
            if (!$formulaRes['status']) {
                app('db')->rollBack();
                return $this->Response(false, $formulaRes['msg']);
            }
        }

        app('db')->commit();

        return $this->Response(true, '成功', ['rule_id' => $ruleId]);
    }

    public function BatchQueryFreight($deliveryList)
    {
        $returnData = [];
        foreach ($deliveryList as $deliveryInfo) {
            if (
                !$deliveryInfo['template_bn'] ||
                !$deliveryInfo['region_info'] ||
                !$deliveryInfo['time_info']
            ) {
                return $this->Response(false, '参数错误');
            }

            $res = $this->QueryFreight(
                $deliveryInfo['template_bn'],
                $deliveryInfo['region_info'],
                $deliveryInfo['time_info'],
                [
                    'region_info' => $deliveryInfo['region_info'],
                    'time_info'   => $deliveryInfo['time_info'],
                    'subtotal'    => $deliveryInfo['subtotal'],
                    'weight'      => $deliveryInfo['weight']
                ]
            );
            if (!$res['status']) {
                return $res;
            }

            $returnData[$deliveryInfo['template_bn']] = $res['data'];
        }

        return $this->Response(true, '成功', $returnData);
    }

    public function QueryFreight($tempBn, $regionInfo, $timeInfo, $elementData)
    {
        //获取模板下所有规则
        $ruleIdList = RuleModel::getRuleIdByTempBn($tempBn);
        if (!$ruleIdList) {
            return $this->Response(false, '无可用规则');
        }

        $ruleLogic  = new RuleLogic();
        $ruleIdList = $ruleLogic->regionFunnel($ruleIdList, $regionInfo);
        if (!$ruleIdList) {
            return $this->Response(false, '无可用配送地址');
        }

        $ruleIdList = $ruleLogic->timeFunnel($ruleIdList, $timeInfo);

        if (!$ruleIdList) {
            return $this->Response(false, '无可用配送时间');
        }

        if (count($ruleIdList) > 1) {
            $ruleInfo  = RuleModel::getFirstSortRule($ruleIdList);
            $curRuleId = $ruleInfo->id;
        } else {
            $curRuleId = current($ruleIdList);
        }

        if (!$curRuleId) {
            return $this->Response(false, '运费计算异常【1】');
        }

        $formulaInfo = RuleFormula::QueryByRuleId($curRuleId);
        if (!$formulaInfo) {
            return $this->Response(false, '运费计算异常【2】');
        }

        try {
            $formulaAdapter = new FormulaAdapter($formulaInfo->type);
        } catch (\Exception $e) {
            return $this->Response(false, $e->getMessage());
        }

        $freightRes = $formulaAdapter->runFormula($formulaInfo->formula_id, $elementData);

        if (!$freightRes['status']) {
            return $freightRes;
        }

        return $this->Response(true, '验证通过', $freightRes['data']);
    }

    protected function Response($status = true, $msg = '成功', $data = [])
    {
        return [
            'status' => $status,
            'msg'    => $msg,
            'data'   => $data,
        ];
    }
}
