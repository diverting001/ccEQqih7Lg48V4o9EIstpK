<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-09-19
 * Time: 15:44
 */

namespace App\Api\V1\Controllers;

use App\Api\Model\Rule\RuleChannel as RuleChannelModel;
use App\Api\Common\Controllers\BaseController;
use App\Api\V1\Service\Rule\Rule;
use Illuminate\Http\Request;

/**
 * 限制规则服务
 * Class RuleController
 * @package App\Api\V1\Controllers
 */
class RuleController extends BaseController
{
    public function getRuleChannel()
    {
        $ruleChannelModel = new RuleChannelModel();
        $ruleChannelList  = $ruleChannelModel->getRuleChannel();
        if ($ruleChannelList) {
            $this->setErrorMsg('请求成功');
            return $this->outputFormat($ruleChannelList, 0);
        } else {
            $this->setErrorMsg('获取失败');
            return $this->outputFormat(array(), 400);
        }
    }

    /**
     * 统一获取限制规则
     * @param Request $request
     * @return array
     */
    public function getRule(Request $request)
    {
        $requestData = $this->getContentArray($request);

        $channel   = $requestData['channel'] ?? "";
        $filterArr = $requestData['filter'] ?? [];
        $page      = $requestData['page'] ?? 1;
        $pageSize  = $requestData['pageSize'] ?? 20;

        $filter = [];
        if (isset($filterArr['searchName']) && $filterArr['searchName']) {
            $filter['searchName'] = $filterArr['searchName'];
        }

        $ruleService = new Rule();

        $res = $ruleService->getRule($channel, $filter, $page, $pageSize);
        if ($res['status']) {
            $this->setErrorMsg('请求成功');
            return $this->outputFormat($res['data'], 0);
        } else {
            $this->setErrorMsg($res['msg']);
            return $this->outputFormat(array(), 400);
        }
    }

    /**
     * 根据规则ID获取渠道规则标识
     * @param Request $request
     * @return array
     */
    public function getChannelRuleBn(Request $request)
    {
        $requestData = $this->getContentArray($request);

        $channel = $requestData['channel'] ?? "";
        $ruleBns = $requestData['rule_bns'] ?? [];

        $ruleService = new Rule();

        $res = $ruleService->getChannelRuleBn($channel, $ruleBns);
        if ($res['status']) {
            $this->setErrorMsg('请求成功');
            return $this->outputFormat($res['data'], 0);
        } else {
            $this->setErrorMsg($res['msg']);
            return $this->outputFormat(array(), 400);
        }
    }

    public function queryRule(Request $request)
    {
        $requestData = $this->getContentArray($request);
        $ruleBns = $requestData['rule_bns'] ?? [];

        $ruleService = new Rule();

        $res = $ruleService->queryRule([
            'rule_bns' =>$ruleBns
        ]);
        if ($res['status']) {
            $this->setErrorMsg('请求成功');
            return $this->outputFormat($res['data'], 0);
        } else {
            $this->setErrorMsg($res['msg']);
            return $this->outputFormat(array(), 400);
        }
    }
}
