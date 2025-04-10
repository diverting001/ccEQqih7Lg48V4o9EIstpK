<?php
namespace App\Api\V2\Controllers;

use App\Api\Common\Controllers\BaseController;
use App\Api\V2\Service\Message\Center as MessageCenterService;
use Illuminate\Http\Request;
use App\Api\Model\Message\Channel as MessageChannelModel;


class MessageCenterController extends BaseController
{
    /**
     * Notes:批量消息（消息中心创建、追加明细、状态变更）
     * User: mazhenkang
     * @param Request $request
     */
    public function batchCreate(Request $request)
    {
        $params = $this->getContentArray($request);

        if (empty($params['channel_id']) || empty($params['template_id']) || empty($params['company_id']) || empty($params['data']) || !is_array($params['data'])) {
            $this->setErrorMsg('参数不正确');
            return $this->outputFormat([], 400);
        }

        $messageChannelModel = new MessageChannelModel();
        $channelInfo = $messageChannelModel->findChannelRows(['channel.id' => $params['channel_id']]);
        if (empty($channelInfo)) {
            $this->setErrorMsg('消息渠道不存在');
            return $this->outputFormat([], 400);
        }

        foreach ($params['data'] as $data) {
            if (empty($data['title']) || empty($data['content']) || empty($data['params'])
                || !is_array($data['params']) || empty($data['recv_data']) || !is_array
                ($data['recv_data'])) {
                $this->setErrorMsg('参数不正确');
                return $this->outputFormat([], 400);
            }

            foreach($data['recv_data'] as $recv_data){
                if (empty($recv_data['user_id']) || empty($recv_data['recv_obj'])) {
                    $this->setErrorMsg('用户id/发送对象不能为空');
                    return $this->outputFormat([], 400);
                }
            }
        }

        $centreService = new MessageCenterService();
        $res = $centreService->batchCreate($params);
        if ($res == false) {
            $this->setErrorMsg('提交失败');
            return $this->outputFormat($params, 10002);
        }

        $this->setErrorMsg('success');
        return $this->outputFormat($res, 0);
    }
}
