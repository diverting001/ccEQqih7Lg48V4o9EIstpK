<?php

namespace App\Api\V1\Controllers;

use App\Api\Common\Controllers\BaseController;
use App\Api\V1\Service\Message\ChannelPush;
use App\Api\V1\Service\Message\MessageHandler;
use Illuminate\Http\Request;

class CompanyMessageController extends BaseController
{

    /**
     * Notes: 创建渠道推送
     * @param Request $request
     * @return array
     * Author: liuming
     * Date: 2021/1/20
     */
    public function channelPush(Request $request)
    {
        $params = $this->getContentArray($request);

        // 参数检查
        if (empty($params['channel_id']) || empty($params['type'])) {
            $this->setErrorMsg('channel_id或type不能为空');
            return $this->outputFormat([], MessageHandler::CODE_FIELD_FAIL);
        }

        if (empty($params['company_ids']) && $params['type'] != 1) {
            $this->setErrorMsg('company_ids不能为空');
            return $this->outputFormat([], MessageHandler::CODE_FIELD_FAIL);
        }

        // 创建推送处理
        $channelService = new ChannelPush();
        return $channelService->channelPush($params['channel_id'], $params['type'], $params['company_ids'])($this);
    }

    /**
     * Notes: 取消推送
     * @param Request $request
     * @return array|mixed
     * @throws \Exception
     * Author: liuming
     * Date: 2021/2/3
     */
    public function cancelChannelPush(Request $request)
    {
        $params = $this->getContentArray($request);
        if (empty($params['channel_id'])) {
            $this->setErrorMsg('channel_id不能为空');
            return $this->outputFormat([], MessageHandler::CODE_FIELD_FAIL);
        }

        $channelService = new ChannelPush();
        return $channelService->cancelChannelPush($params['channel_id'])($this);
    }

    /**
     * Notes: 获取公司推送了哪些渠道
     * @param Request $request
     * @return array
     * Author: liuming
     * Date: 2021/1/21
     */
    public function getCompanyChannelPushList(Request $request)
    {
        $params = $this->getContentArray($request);
        if (empty($params['company_id'])) {
            $this->setErrorMsg('company_id不能为空');
            return $this->outputFormat([], MessageHandler::CODE_FIELD_FAIL);
        }

        $channelPushService = new ChannelPush();
        return $channelPushService->getCompanyChannelPushListV3($params['company_id'])($this);
    }

    /**
     * Notes: 获取渠道绑定的公司ids
     * @param Request $request
     * @return array|mixed
     * Author: liuming
     * Date: 2021/1/29
     */
    public function getChannelBindCompanyList(Request $request)
    {
        $params = $this->getContentArray($request);
        if (empty($params['channel_id'])) {
            $this->setErrorMsg('channel_id不能为空');
            return $this->outputFormat([], MessageHandler::CODE_FIELD_FAIL);
        }

        $templateService = new ChannelPush();
        return $templateService->getChannelBindCompanyList($params['channel_id'])($this);
    }

    /**
     * Notes: 获取渠道推送的基础信息
     * @param Request $request
     * @return array
     * Author: liuming
     * Date: 2021/2/8
     */
    public function getChannelPushBaseList(Request $request){

        $chanelIds = [];
        $params = $this->getContentArray($request);
        if ($params['channel_ids']){
            $chanelIds = $params['channel_ids'];
        }
        $channelPushService = new ChannelPush();
        return $channelPushService->getChannelPushBaseList($chanelIds)($this);
    }
}
