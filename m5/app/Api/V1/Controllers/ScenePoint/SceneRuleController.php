<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-01-16
 * Time: 16:43
 */

namespace App\Api\V1\Controllers\ScenePoint;

use App\Api\Common\Controllers\BaseController;
use App\Api\Model\PointScene\Rule;
use App\Api\Model\PointScene\RuleInfo;
use App\Api\V1\Service\PointScene\Rule as RuleServer;
use Illuminate\Http\Request;

class SceneRuleController extends BaseController
{
    public function QueryList(Request $request)
    {
        $ruleData = $this->getContentArray($request);
        $page = isset($ruleData['page']) ? $ruleData['page'] : 1;
        $pageSize = isset($ruleData['page_size']) ? $ruleData['page_size'] : 10;

        $whereArr = array();
        if (isset($ruleData['name']) && $ruleData['name']) {
            $whereArr['name'] = $ruleData['name'];
        }

        if (isset($ruleData['rule_ids']) && $ruleData['rule_ids']) {
            $whereArr['rule_ids'] = $ruleData['rule_ids'];
        }

        if (isset($ruleData['disabled']) && $ruleData['disabled']) {
            $whereArr['disabled'] = $ruleData['disabled'];
        }
        if (!empty($ruleData['keyword'])) {
            $whereArr['keyword'] = $ruleData['keyword'];
        }

        $ruleServer = new RuleServer();
        $res = $ruleServer->QueryList($whereArr, $page, $pageSize);
        $this->setErrorMsg($res['msg']);
        if ($res['status']) {
            return $this->outputFormat($res['data'], 0);
        } else {
            return $this->outputFormat(array(), 400);
        }
    }

    //查询规则信息
    public function Query(Request $request){
        $param = $this->getContentArray($request);
        $rule_id = $param['rule_id'];
        $data = Rule::Find($rule_id);
        if(!empty($data)){
            $data->info = RuleInfo::FindAll($rule_id);
            return $this->outputFormat($data, 0);
        } else {
            return $this->outputFormat(array(), 400);
        }
    }

    /**
     * 创建积分场景规则
     */
    public function Create(Request $request)
    {
        $ruleData = $this->getContentArray($request);
        if (
            empty($ruleData['name']) ||
            empty($ruleData['consume_type']) ||
//            empty($ruleData['filter_type']) ||
            !is_array($ruleData['filter_bn']) ||
            count($ruleData['filter_bn']) < 1
        ) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }

        $ruleServer = new RuleServer();
        $res = $ruleServer->Create($ruleData);

        $this->setErrorMsg($res['msg']);
        if ($res['status']) {
            return $this->outputFormat($res['data'], 0);
        } else {
            return $this->outputFormat(array(), 400);
        }
    }

    /**
     * 创建积分场景规则
     */
    public function Update(Request $request)
    {
        $ruleData = $this->getContentArray($request);
        if (
            empty($ruleData['rule_id']) ||
            empty($ruleData['name']) ||
            empty($ruleData['consume_type']) ||
//            empty($ruleData['filter_type']) ||
            !is_array($ruleData['filter_bn']) ||
            count($ruleData['filter_bn']) < 1
        ) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }

        $ruleServer = new RuleServer();
        $res = $ruleServer->Update($ruleData);

        $this->setErrorMsg($res['msg']);
        if ($res['status']) {
            return $this->outputFormat($res['data'], 0);
        } else {
            return $this->outputFormat(array(), 400);
        }
    }
}
