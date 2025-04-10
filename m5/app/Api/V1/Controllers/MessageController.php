<?php

namespace App\Api\V1\Controllers;

use App\Api\Common\Controllers\BaseController;
use App\Api\Logic\CompanyTemplate as CompanyTemplateLogic;
use App\Api\V1\Service\Message\Center;
use App\Api\V1\Service\Message\Channel;
use App\Api\V1\Service\Message\MessageHandler;
use App\Api\V1\Service\Message\MessageLog;
use App\Api\V1\Service\Message\MessageProducer;
use App\Api\V1\Service\Message\Template;
use Illuminate\Http\Request;

class MessageController extends BaseController
{

    /**
     * 发送通知接口
     * @param  Request  $request
     * @return array
     */
    public function sendMessage(Request $request)
    {

        $param = $this->getContentArray($request);
        if (empty($param['channel'])) {
            $this->setErrorMsg('缺少渠道');
            return $this->outputFormat($param, MessageHandler::CODE_FIELD_FAIL);
        }

        $messageService = new MessageProducer();
        return $messageService->messageProcessing($param)($this);
    }

    /**
     * 添加模板接口
     * @param  Request  $request
     * @return array
     */
    public function createTemplate(Request $request)
    {
        $param = $this->getContentArray($request);
        if (empty($param['name']) ||
            //empty($param['param']) ||
            empty($param['description'])) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat($param, MessageHandler::CODE_FIELD_FAIL);
        }

        $templateService = new Template();
        return $templateService->createTemplate($param)($this);
    }

    /**
     * 修改模板接口
     * @param  Request  $request
     * @return array
     */
    public function editTemplate(Request $request)
    {
        $param = $this->getContentArray($request);
        $id = $param['id'];
        unset($param['id']);
        if (empty($id) || empty($param)) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat($param, MessageHandler::CODE_FIELD_FAIL);
        }
        $templateService = new Template();
        return $templateService->editTemplate($id,$param)($this);
    }

    /**
     * 获取模板列表
     * @param  Request  $request name,page,limit
     * @return array
     */
    public function getTemplateList(Request $request)
    {
        $param = $this->getContentArray($request);

        $templateService = new Template();
        $where = [];
        if (isset($param['name']) && !empty($param['name'])){
            $where['name'] = $param['name'];
        }

        if (isset($param['is_delete']) && !empty($param['is_delete'])){
            $where['is_delete'] = $param['is_delete'];
        }

        if (isset($param['channel_id']) && !empty($param['channel_id'])){
            $where['channel_id'] = $param['channel_id'];
        }

        if (isset($param['template_ids']) && !empty($param['template_ids'])){
            $where[] = [function($query) use ($param){
                $query->whereIn('id', $param['template_ids']);
            }];
        }

        $page  = (isset($param['page']) && $param['page'] > 0) ? (int)$param['page'] : 1;
        $limit  = (isset($param['limit']) && $param['limit'] > 0) ? (int)$param['limit'] : 20;

        $offset = ($page-1)*$limit;
        return $templateService->getTemplateList($where,$offset,$limit)($this);
    }

    /**
     * 通知处理进度查询
     * @param  Request  $request
     * @return array
     */
    public function getMessageProgress(Request $request)
    {
        $param = $this->getContentArray($request);
        if (empty($param['batch_id'])) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat($param, MessageHandler::CODE_FIELD_FAIL);
        }

        $messageLog = new MessageLog();
        return $this->outputFormat($messageLog->progress($param['batch_id']));
    }

    /**
     * Notes:模版和渠道绑定
     * @param Request $request
     * @return mixed
     * Author: liuming
     * Date: 2021/1/15
     */
    public function bindTemplateChannel(Request $request){
        $params = $this->getContentArray($request);
        if (empty($params['template_id']) || empty($params['channel_ids'])){
            $this->setErrorMsg('基础参数错误');
            return $this->outputFormat([], MessageHandler::CODE_FIELD_FAIL);
        }

        $templateService = new Template();
        return $templateService->bindTemplateChannel($params['template_id'],$params['channel_ids'])($this);
    }

    /**
     * Notes: 获取channel列表
     * @param Request $request
     * @return mixed
     * Author: liuming
     * Date: 2021/1/18
     */
    public function getChannelList(Request $request){
        $params = $this->getContentArray($request);
        $ids = $params['ids'] ? $params['ids'] : [];
        $channelService = new Channel();
        return $channelService->getChannelList($ids)($this);
    }

    /**
     * Notes: 获取模板信息
     * @param Request $request
     * @return array|mixed
     * Author: liuming
     * Date: 2021/1/19
     */
    public function getTemplate(Request $request)
    {
        $param = $this->getContentArray($request);

        if (!isset($param['id']) || empty($param['id'])){
            $this->setErrorMsg('基础参数错误');
            return $this->outputFormat([], MessageHandler::CODE_FIELD_FAIL);
        }
        $templateService = new Template();
        $channelId = isset($param['channel_id']) ? $param['channel_id'] : 0;
        return $templateService->getTemplateById($param['id'],$channelId)($this);
    }

    /**
     * Notes:获取模板绑定的渠道信息
     * @param Request $request
     * @return array|mixed
     * Author: liuming
     * Date: 2021/1/29
     */
    public function getTemplateBindChannels(Request $request){
        $params = $this->getContentArray($request);
        if (empty($params['id'])){
            $this->setErrorMsg('基础参数错误');
            return $this->outputFormat([], MessageHandler::CODE_FIELD_FAIL);
        }

        $templateService = new Template();
        return $templateService->getTemplateBindChannels($params['id'])($this);
    }

    /**
     * Notes: 消息推送
     * @param Request $request
     * @return array|mixed
     * Author: liuming
     * Date: 2021/2/4
     */
    public function getChannel(Request $request){
        $params = $this->getContentArray($request);
        if (empty($params['id'])){
            $this->setErrorMsg('id不能为空');
            return $this->outputFormat([], MessageHandler::CODE_FIELD_FAIL);
        }

        $channelService = new Channel();
        return $channelService->getChannel($params['id'])($this);
    }

    /**
     * Notes: 获取channel_ids
     * @param Request $request
     * @return array|mixed
     * Author: liuming
     * Date: 2021/3/15
     */
    public function getChannelIdListByTemplateId(Request $request){
        $params = $this->getContentArray($request);
        if (empty($params['template_id'])){
            $this->setErrorMsg('id不能为空');
            return $this->outputFormat([], MessageHandler::CODE_FIELD_FAIL);
        }
        $channelService = new Channel();
        return $channelService->getChannelIdListByTemplateId($params['template_id'])($this);
    }

    /**
     * Notes:获取公司短信模板配置
     * User: mazhenkang
     * Date: 2024/8/26 上午9:52
     */
    public function getBusinessTemplateByCompany(Request $request)
    {
        $params = $this->getContentArray($request);
        if (!isset($params['company_id']) || empty($params['company_id'])) {
            $this->setErrorMsg('基础参数错误,company_id不能为空');
            return $this->outputFormat([], 400);
        }
        //order_delivery,order_pay,order_finish,order_cancel,point_send,point_recovery,point_refund,point_lock,point_deadline
        if (empty($params['business_key']) || !is_string($params['business_key'])) {
            $this->setErrorMsg('基础参数错误,business_key不能为空');
            return $this->outputFormat([], 400);
        }
        $businessKey = explode(',', $params['business_key']); //业务key
        $isScopeFill = $params['is_scope_fill'] == 1 ? 1 : 0;

        $clubScopeConfig = new CompanyTemplateLogic();
        $res = $clubScopeConfig->getBusinessTemplateByCompany($params['company_id'],
            $businessKey, $isScopeFill);

        $this->setErrorMsg('请求成功');
        return $this->outputFormat($res);
    }

    public function getBasicTemplateByBusinessKey(Request $request)
    {
        $params = $this->getContentArray($request);
        //order_delivery,order_pay,order_finish,order_cancel,point_send,point_recovery,point_refund,point_lock,point_deadline
        if (empty($params['business_key']) || !is_string($params['business_key'])) {
            $this->setErrorMsg('基础参数错误,business_key不能为空');
            return $this->outputFormat([], 400);
        }
        $businessKey = explode(',', $params['business_key']); //业务key

        $clubScopeConfig = new CompanyTemplateLogic();
        $res = $clubScopeConfig->getBasicTemplateByBusinessKey($businessKey);

        $this->setErrorMsg('请求成功');
        return $this->outputFormat($res);
    }
}
