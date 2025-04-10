<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-01-22
 * Time: 11:02
 */

namespace App\Api\V1\Service\PointScene;

use App\Api\Logic\Service;
use App\Api\Model\PointScene\SceneCompanyRel;
use App\Api\Model\PointScene\SceneRuleRel as SceneRuleRelModel;
use App\Api\V1\Service\PointServer\Account as AccountServer;
use App\Api\Model\PointScene\Scene as SceneModel;
use App\Api\Model\PointScene\Rule as RuleModel;
use App\Api\Model\PointScene\SceneCompanyRel as SceneCompanyRelModel;
use Illuminate\Support\Facades\DB;

class Scene
{
    public function QueryList($whereArr, $page, $pageSize)
    {
        $count = SceneModel::QueryCount($whereArr);
        if ($count > 0) {
            $sceneList = SceneModel::QueryList($whereArr, $page, $pageSize);
        } else {
            $sceneList = array();
        }
        return $this->Response(true, "", array(
            'total_num'  => $count,
            'total_page' => ceil($count / $pageSize),
            'rule_list'  => $sceneList,
        ));
    }

    /**
     * 创建场景
     */
    public function Create($sceneData)
    {
        app('api_db')->beginTransaction();
        $saveData = array(
            "name"    => $sceneData['name'],
            "desc"    => $sceneData['desc'] ? $sceneData['desc'] : "",
            "op_id"   => $sceneData['op_id'] ? $sceneData['op_id'] : "",
            "op_name" => $sceneData['op_name'] ? $sceneData['op_name'] : "",
        );
        $sceneId  = SceneModel::Create($saveData);
        if (!$sceneId) {
            app('api_db')->rollBack();
            return $this->Response(false, "创建失败");
        }

        $sceneRuleRel = [];
        foreach ($sceneData['rule_bns'] as $ruleBn) {
            $sceneRuleRel[] = [
                'scene_id' => $sceneId,
                'rule_bn'  => $ruleBn
            ];
        }

        $addStatus = SceneRuleRelModel::Create($sceneRuleRel);
        if (!$addStatus) {
            app('api_db')->rollBack();
            return $this->Response(false, "创建失败");
        }

        app('api_db')->commit();

        return $this->Response(true, "创建成功", array("scene_id" => $sceneId));
    }

    public function Update($sceneId, $sceneData)
    {
        app('api_db')->beginTransaction();
        $saveData = [
            'name' => $sceneData['name'],
            'desc' => $sceneData['desc'],
        ];
        if (isset($sceneData['op_id'])) {
            $saveData['op_id'] = $sceneData['op_id'];
        }
        if (isset($sceneData['op_name'])) {
            $saveData['op_name'] = $sceneData['op_name'];
        }
        $upStatus = SceneModel::Update($sceneId, $saveData);
        if (!$upStatus) {
            app('api_db')->rollBack();
            return $this->Response(false, "修改失败");
        }

        SceneRuleRelModel::DeleteAll($sceneId);

        foreach ($sceneData['rule_bns'] as $ruleBn) {
            $sceneRuleRel[] = [
                'scene_id' => $sceneId,
                'rule_bn'  => $ruleBn
            ];
        }

        $addStatus = SceneRuleRelModel::Create($sceneRuleRel);
        if (!$addStatus) {
            app('api_db')->rollBack();
            return $this->Response(false, "创建失败");
        }

        app('api_db')->commit();

        return $this->Response(true, "创建成功");
    }

    /**
     * 场景关联公司
     */
    public function RelationCompany($relationData)
    {
        $sceneInfo = SceneModel::Find($relationData['scene_id']);
        if (!$sceneInfo || $sceneInfo->disabled != 0) {
            return $this->Response(false, "关联失败,场景不存在或已失效");
        }

        $accountServer = new AccountServer();
        app('db')->beginTransaction();
        foreach ($relationData['company_list'] as $companyId) {
            $accountInfo = array(
                "scene_id"   => $relationData['scene_id'],
                "company_id" => $companyId
            );
            $relId       = SceneCompanyRelModel::Create($accountInfo);
            if (!$relId) {
                app('db')->rollBack();
                return $this->Response(false, "关联失败");
            }
            $createRes = $accountServer->Create();
            if (!$createRes['status']) {
                app('db')->rollBack();
                return $this->Response(false, $createRes['msg']);
            }
            $account = $createRes['data']['account'];
            $bindRes = SceneCompanyRelModel::BindAccount($relId, $account);
            if (!$bindRes) {
                app('db')->rollBack();
                return $this->Response(false, "公司场景账户绑定失败");
            }
        }
        app('db')->commit();
        return $this->Response(true, "关联成功");
    }

    public function GetCompanyRel($queryData)
    {
        $companyId = $queryData['company_id'];
        $sceneList = SceneCompanyRel::FindByCompany($companyId);
        return $this->Response(true, "成功", $sceneList->count() ? $sceneList : array());
    }

    /**
     * 查看可使用积分的实体
     */
    public function WithRule($queryData)
    {
        $companyId    = $queryData['company_id'];
        $memberId     = $queryData['member_id'];
        $pointChannel = $queryData['channel'];
        $filterData   = $queryData['filter_data'];

        $sceneIdArr = [];
        $ruleBnArr  = [];

        $serviceLogic = new Service();
        $res          = $serviceLogic->ServiceCall(
            'get_member_point',
            [
                'member_id'  => $memberId,
                'company_id' => $companyId,
                'channel'    => $pointChannel
            ],
            'v2'
        );
        if ('SUCCESS' == $res['error_code']) {
            foreach ($res['data'] as $account) {
                $sceneIdArr[$account['account']] = $account['rule_bns'];

                $ruleBnArr = array_merge($ruleBnArr, $account['rule_bns']);
            }
        }

        $sendData = [
            'channel'  => "NEIGOU_SHOPING",
            'rule_bns' => $ruleBnArr
        ];

        $neigouRuleRes = $serviceLogic->ServiceCall('rule_bn_to_neigou_rule_id', $sendData);

        if ('SUCCESS' != $neigouRuleRes['error_code'] || !$neigouRuleRes['data']) {
            return $this->Response();
        }

        $ruleIdArr = [];
        foreach ($neigouRuleRes['data'] as $neigouRule) {
            $ruleIdArr[$neigouRule['rule_bn']] = $neigouRule['channel_rule_bn'];
        }

        $returnData = array();
        foreach ($filterData as $filterType => $filterInfo) {
            if ($filterInfo) {
                $className = 'App\\Api\\Logic\\PointScene\\RuleAnalysis\\' . ucfirst(camel_case($filterType)) . "RuleAnalysis";
                if (!class_exists($className)) {
                    $returnData[$filterType] = array();
                    continue;
                }
                $transactionObj = new $className();
                $withRuleRes    = $transactionObj->WithRule($ruleIdArr, $sceneIdArr, $filterInfo);
                if ($withRuleRes['status']) {
                    $returnData[$filterType] = $withRuleRes['data'];
                }
            } else {
                $returnData[$filterType] = array();
            }
        }
        return $this->Response(true, '', $returnData);
    }

    private function Response($status = true, $msg = '成功', $data = [])
    {
        return [
            'status' => $status,
            'msg'    => $msg,
            'data'   => $data,
        ];
    }
}
