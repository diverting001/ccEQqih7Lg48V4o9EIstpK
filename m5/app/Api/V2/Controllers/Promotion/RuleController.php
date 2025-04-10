<?php
/**
 * Created by PhpStorm.
 * User: chuanbin
 * Date: 2020-02-25
 * Time: 11:14
 */

namespace App\Api\V2\Controllers\Promotion;


use App\Api\Common\Controllers\BaseController;
use App\Api\Model\Promotion\PromotionRuleModel;
use App\Api\V2\Service\Promotion\RuleService;
use Illuminate\Http\Request;

class RuleController extends BaseController
{

    public function CreateRule(Request $request){
        $ruleData = $this->getContentArray($request);
        if (
            empty($ruleData['name']) ||
            empty($ruleData['rel_rule_id']) ||
            empty($ruleData['description']) ||
            empty($ruleData['start_time']) ||
            empty($ruleData['end_time']) ||
            empty($ruleData['type']) ||
            !is_array($ruleData['condition'])
        ) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }
        $ruleServer = new RuleService();
        $res = $ruleServer->Create($ruleData);

        $this->setErrorMsg($res['msg']);
        if ($res['status']) {
            return $this->outputFormat($res['data'], 0);
        } else {
            return $this->outputFormat(array(), 400);
        }
    }

    public function SaveRule(Request $request){
        $ruleData = $this->getContentArray($request);
        if (
            empty($ruleData['name']) ||
            empty($ruleData['rule_id']) ||
            empty($ruleData['rel_rule_id']) ||
            empty($ruleData['description']) ||
            empty($ruleData['start_time']) ||
            empty($ruleData['end_time']) ||
            empty($ruleData['type']) ||
            !is_array($ruleData['condition'])
        ) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }
        $ruleServer = new RuleService();
        $res = $ruleServer->Update($ruleData);

        $this->setErrorMsg($res['msg']);
        if ($res['status']) {
            return $this->outputFormat($res['data'], 0);
        } else {
            return $this->outputFormat(array(), 400);
        }
    }

    public function RuleList(Request $request){
        $ruleData = $this->getContentArray($request);
        $page = isset($ruleData['page']) ? $ruleData['page'] : 1;
        $pageSize = isset($ruleData['page_size']) ? $ruleData['page_size'] : 10;
        $type = isset($ruleData['type']) ? $ruleData['type'] : 'goods';
        $rel_id = isset($ruleData['rel_id']) ? $ruleData['rel_id'] : ''; //商品规则id
        $company_id = isset($ruleData['company_id']) ? $ruleData['company_id'] : '';
        $whereArr = array();
        if (isset($ruleData['name']) && $ruleData['name']) {
            $whereArr['name'] = $ruleData['name'];
        }

        if (isset($ruleData['rule_ids']) && $ruleData['rule_ids']) {
            $whereArr['rule_ids'] = $ruleData['rule_ids'];
        }

        if (isset($ruleData['status']) && $ruleData['status']) {
            $whereArr['status'] = $ruleData['status'];
        }
        if ($rel_id) {
            $ruleList = PromotionRuleModel::QueryRuleIdsByRelRuleId($rel_id);
            if (empty($ruleList)){
                $this->setErrorMsg('查询成功');
                return $this->outputFormat(array(), 400);
            }
            $rule_ids = array_column($ruleList,'pid');

            $whereArr['rule_ids'] = $whereArr['rule_ids'] ? array_intersect($rule_ids,$whereArr['rule_ids']) : $rule_ids;
            if (empty($whereArr['rule_ids'])){
                $this->setErrorMsg('查询成功');
                return $this->outputFormat(array(), 400);
            }
        }

        if ($company_id){
            $relCompanyList = PromotionRuleModel::QueryRelListByCompanyIdWithAll($company_id);
            if (empty($relCompanyList)){
                $this->setErrorMsg('查询成功');
                return $this->outputFormat(array(), 400);
            }
            $rule_ids = array_column($relCompanyList,'pid');

            $whereArr['rule_ids'] = $whereArr['rule_ids'] ? array_intersect($rule_ids,$whereArr['rule_ids']) : $rule_ids;
            if (empty($whereArr['rule_ids'])){
                $this->setErrorMsg('查询成功');
                return $this->outputFormat(array(), 400);
            }
        }

        $whereArr['type'] = $type;

        $ruleServer = new RuleService();
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
        $data = PromotionRuleModel::Find($rule_id);
        if(!empty($data)){
            $data->rel_rules = PromotionRuleModel::FindAllRel($rule_id);
            return $this->outputFormat($data, 0);
        } else {
            return $this->outputFormat(array(), 400);
        }
    }

    public function StatusEdit(Request $request){
        $ruleData = $this->getContentArray($request);
        if (
            empty($ruleData['status'])
        ) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }

        $ruleServer = new RuleService();
        //检查是否有已经推送的列表 如果没有不允许直接上线
        if($ruleData['status']==1){
            $whereArr['pid'] = [$ruleData['rule_id']];
            $res = $ruleServer->QueryRelList($whereArr, 1, 1);
            if($res['data']['total_num']<=0){
                $this->setErrorMsg('未推送过的规则不允许直接上线');
                return $this->outputFormat([], 403);
            }
        }

        $res = $ruleServer->StatusEdit($ruleData);

        $this->setErrorMsg($res['msg']);
        if ($res['status']) {
            return $this->outputFormat($res['data'], 0);
        } else {
            return $this->outputFormat(array(), 400);
        }
    }

    public function PubRule(Request $request){
        $relationData = $this->getContentArray($request);
        if (empty($relationData['rule_id']) || empty($relationData['company_list'])) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }

        $ruleServer = new RuleService();
        $res         = $ruleServer->CreateScopeRel($relationData);
        if ($res['status']) {
            $this->setErrorMsg('请求成功');
            return $this->outputFormat($res['data'], 0);
        } else {
            $this->setErrorMsg($res['msg']);
            return $this->outputFormat(array(), 400);
        }
    }

    public function PubRuleRelList(Request $request){
        $ruleData = $this->getContentArray($request);
        $page = isset($ruleData['page']) ? $ruleData['page'] : 1;
        $pageSize = isset($ruleData['page_size']) ? $ruleData['page_size'] : 10;

        $whereArr = array();
        if (isset($ruleData['pid']) && $ruleData['pid']) {
            $whereArr['pid'] = $ruleData['pid'];
        }

        if (isset($ruleData['scope']) && $ruleData['scope']) {
            $whereArr['scope'] = $ruleData['scope'];
        }
        $ruleServer = new RuleService();
        $res = $ruleServer->QueryRelList($whereArr, $page, $pageSize);
        $this->setErrorMsg($res['msg']);
        if ($res['status']) {
            return $this->outputFormat($res['data'], 0);
        } else {
            return $this->outputFormat(array(), 400);
        }
    }

    public function DeleteRule(Request $request){
        $ruleData = $this->getContentArray($request);
        if (
        empty($ruleData['rule_id'])
        ) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }

        $ruleServer = new RuleService();
        $res = $ruleServer->Delete($ruleData);

        $this->setErrorMsg($res['msg']);
        if ($res['status']) {
            return $this->outputFormat($res['data'], 0);
        } else {
            return $this->outputFormat(array(), 400);
        }
    }

    /**
     * Notes  : 根据公司ID查询符合条件的商品限购规则
     * @param Request $request
     * @param Request $request - company_id 公司ID
     * @param Request $request - status 规则状态 0-全部 1-生效 2-不生效
     * @param Request $request - time_available 时间范围可用 0-全部 1-在可用时间范围
     * @return array
     */
    public function GetCompanyRuleList(Request $request){
        $filterData = $this->getContentArray($request);

        if (empty($filterData['company_id'])){
            $this->setErrorMsg('公司ID不可为空');
            return $this->outputFormat([], 400);
        }

        $ruleService = new RuleService();
        $res = $ruleService->QueryCompanyRuleList($filterData);

        $this->setErrorMsg($res['msg']);

        if ($res['status']){
            return $this->outputFormat($res['data'], 0);
        }else{
            return $this->outputFormat([], 400);
        }
    }
}
