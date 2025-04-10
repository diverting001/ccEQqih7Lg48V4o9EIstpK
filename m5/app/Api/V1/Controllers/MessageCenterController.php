<?php

namespace App\Api\V1\Controllers;

use App\Api\Common\Controllers\BaseController;
use App\Api\V1\Service\Message\Center;
use App\Api\V1\Service\Message\MessageHandler;
use Illuminate\Http\Request;

class MessageCenterController extends BaseController
{
    /**
     * Notes: 创建消息中心
     * @param Request $request
     * @return array
     * Author: liuming
     * Date: 2021/1/21
     */
    public function create(Request $request)
    {
        $params = $this->getContentArray($request);
        $mustKey = [
            'title', 'content', 'channel_id', 'company_id', 'template_id'
        ];
        $errKey = [];
        foreach ($mustKey as $v) {
            if (isset($params[$mustKey]) || empty($params[$v])) {
                $errKey[] = $v;
            }
        }
        if ($errKey) {
            $this->setErrorMsg('基础参数错误,' . implode(',', $errKey) . '不存在');
            return $this->outputFormat([], 400);
        }

        $centreService = new Center();
        return $centreService->create($params)($this);

    }

    /**
     * Notes: 获取消息中心详情
     * @param Request $request
     * Author: liuming
     * Date: 2021/1/21
     */
    public function getDetail(Request $request)
    {
        $params = $this->getContentArray($request);
        if (empty($params['id']) && empty($params['title'])) {
            $this->setErrorMsg('id或title不能同时为空');
            return $this->outputFormat([], MessageHandler::CODE_FIELD_FAIL);
        }
        $where = [];
        if ($params['title']){
            $where['title'] = $params['title'];
        }

        if ($params['company_id']){
            $where['company_id'] = $params['company_id'];
        }

        if ($params['id']){
            $where['id'] = $params['id'];
        }
        $centreService = new Center();
        return $centreService->getCenter($where)($this);
    }

    /**
     * Notes:获取消息中心列表
     * @param Request $request
     * Author: liuming
     * Date: 2021/1/21
     */
    public function getList(Request $request)
    {
        $params = $this->getContentArray($request);
        $page = (isset($params['page']) && $params['page'] > 0) ? (int)$params['page'] : 1;
        $limit = (isset($params['limit']) && $params['limit'] > 0) ? (int)$params['limit'] : 20;

        $offset = ($page - 1) * $limit;
        $where = [];
        if ($params['company_id']){
            $where = [
                'company_id' => $params['company_id']
            ];
        }
        $centreService = new Center();
        return $centreService->getCenterList($where, $offset, $limit)($this);

    }

    /**
     * Notes: 追加明细
     * @param Request $request
     * @return array|mixed
     * Author: liuming
     * Date: 2021/4/22
     */
    public function appendItems(Request $request){
        $params = $this->getContentArray($request);
        if (empty($params)){
            $this->setErrorMsg('基础参数错误,消息明细不能为空');
            return $this->outputFormat([], 400);
        }
        if (!isset($params['message_center_id']) || empty($params['message_center_id'])){
            $this->setErrorMsg('基础参数错误,message_center_id不能为空');
            return $this->outputFormat([], 400);
        }

        $messageCenterId = $params['message_center_id'];

        $itemsList = [];
        foreach ($params['recv_data'] as $v){
            $userId = isset($v['user_id']) ? $v['user_id'] : 0;
            $recvObj = isset($v['recv_obj']) ? $v['recv_obj'] : '';
            if (empty($recvObj)){
                continue;
            }
            $itemsList[] = [
                'message_center_id' => $messageCenterId,
                'user_id' => $userId,
                'recv_obj' => $recvObj
            ];
        }

        if (empty($params)){
            $this->setErrorMsg('基础参数错误,recv_obj值不能为空');
            return $this->outputFormat([], 400);
        }

        $centreService = new Center();
        return $centreService->addItems($messageCenterId,$itemsList)($this);
    }


    /**
     * Notes: 状态变更
     * @param Request $request
     * @return array|mixed
     * Author: liuming
     * Date: 2021/4/22
     */
    public function updateSendStatus(Request $request){
        $params = $this->getContentArray($request);

        if (empty($params)){
            $this->setErrorMsg('参数不能为空');
            return $this->outputFormat([], 400);
        }
        if (!isset($params['id']) || empty($params['id'])){
            $this->setErrorMsg('基础参数错误,id不能为空');
            return $this->outputFormat([], 400);
        }

        if (!isset($params['send_status'])){
            $this->setErrorMsg('基础参数错误,send_status不能为空');
            return $this->outputFormat([], 400);
        }

        $centreService = new Center();
        return $centreService->updateSendStatus($params['id'],$params['send_status'])($this);
    }

    /**
     * Notes:批量消息 三合一 （消息中心创建、追加明细、状态变更）
     * User: mazhenkang
     * Date: 2023/1/4 10:39
     * @param Request $request
     */
    public function batchCreate(Request $request)
    {
        $params = $this->getContentArray($request);

        if(empty($params)){
            $this->setErrorMsg('请求参数不能为空');
            return $this->outputFormat([], 400);
        }

        if (!isset($params['channel_id']) || empty($params['channel_id'])){
            $this->setErrorMsg('基础参数错误,channel_id不能为空');
            return $this->outputFormat([], 400);
        }

        if (!isset($params['company_id']) || empty($params['company_id'])){
            $this->setErrorMsg('基础参数错误,company_id不能为空');
            return $this->outputFormat([], 400);
        }

        $centreService = new Center();

        return $centreService->batchCreate($params)($this);
    }
}
