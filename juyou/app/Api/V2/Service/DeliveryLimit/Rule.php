<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-10-18
 * Time: 15:16
 */

namespace App\Api\V2\Service\DeliveryLimit;

use App\Api\Logic\DeliveryLimit\RegionAdapter;
use App\Api\Logic\DeliveryLimit\TimeAdapter;

use App\Api\Logic\DeliveryLimit\Rule as RuleLogic;

use App\Api\Model\DeliveryLimit\Rule as RuleModel;
use App\Api\Model\DeliveryLimit\RuleRegion as RuleRegionModel;
use App\Api\Model\DeliveryLimit\RuleTime as RuleTimeModel;
use App\Api\Model\DeliveryLimit\Template as TemplateModel;

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
            'rule_name'   => $ruleInfo['rule_name'],
            'rule_desc'   => $ruleInfo['rule_desc'],
            'template_bn' => $templageBn,
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

        app('db')->commit();

        return $this->Response(true, '成功', ['rule_id' => $ruleId]);
    }

    public function BatchQueryStatus($deliveryList)
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

            $res = $this->QueryStatus(
                $deliveryInfo['template_bn'],
                $deliveryInfo['region_info'],
                $deliveryInfo['time_info']
            );

            $returnData[$deliveryInfo['template_bn']] = $res['data'];
        }

        return $this->Response(true, '成功', $returnData);
    }

    public function QueryStatus($tempBn, $regionInfo, $timeInfo)
    {
        //获取模板下所有规则
        $ruleIdList = RuleModel::getRuleIdByTempBn($tempBn);

        $ruleLogic  = new RuleLogic();
        $ruleIdList = $ruleLogic->regionFunnel($ruleIdList, $regionInfo);
        if (!$ruleIdList) {
            return $this->Response(false, '无可用配送地址', [
                'region_status' => 0,
                'time_status'   => 0
            ]);
        }

        $ruleIdList = $ruleLogic->timeFunnel($ruleIdList, $timeInfo);

        if (!$ruleIdList) {
            return $this->Response(false, '无可用配送时间', [
                'region_status' => 1,
                'time_status'   => 0
            ]);
        }
        return $this->Response(true, '验证通过', [
            'region_status' => 1,
            'time_status'   => 1
        ]);
    }

    public function QueryRule($tempBn, $filterData)
    {
        //获取模板下所有规则
        $ruleList = RuleModel::getRuleInfoByTempBn($tempBn);
        if ($ruleList->count() <= 0) {
            return $this->Response(false, '无规则');
        }

        $ruleIdList = [];
        $ruleArr    = [];
        foreach ($ruleList as $item) {
            $ruleIdList[] = $item->id;

            $ruleArr[$item->id] = $item;
        }

        $ruleLogic  = new RuleLogic();
        $regionList = $ruleLogic->getAvailableRegion($ruleIdList, $filterData['region_info'] ?? []);
        if (count($regionList) <= 0) {
            return $this->Response(false, '无有效地址');
        }

        $ruleIdList = array_keys($regionList);
        $timeList   = $ruleLogic->getAvailableTime($ruleIdList, $filterData['time_info'] ?? []);
        if (count($timeList) <= 0) {
            return $this->Response(false, '无有效时间');
        }

        $returnData = [];

        foreach ($ruleList as $ruleInfo) {
            $ruleId = $ruleInfo->id;
            if (!isset($regionList[$ruleId]) || !isset($timeList[$ruleId])) {
                continue;
            }

            $ruleInfo->regtion_info = $regionList[$ruleId];
            $ruleInfo->time_info    = $timeList[$ruleId];

            $returnData[] = $ruleInfo;
        }

        return $this->Response(true, '成功', ['rule_list' => $returnData]);
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
