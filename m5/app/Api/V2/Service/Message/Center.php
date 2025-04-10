<?php
namespace App\Api\V2\Service\Message;

use App\Api\Model\Message\Center as MessageCenterModel;

class Center
{

    const MESSAGE_CENTER_DEFAULT_SEND_STATUS = 0;// 默认是未发送
    const MESSAGE_CENTER_STAY_SEND_STATUS = 1; //待发送
    const MESSAGE_CENTER_DEFAULT_TYPE = 1;// 默认是通知类

    /**
     * Notes:批量创建消息（单条用户支持多个）
     * User: mazhenkang
     * Date: 2024/8/27 下午5:47
     * @param $params
     * @return mixed
     */
    public function batchCreate($params)
    {
        if (empty($params['channel_id']) || empty($params['template_id']) || empty($params['company_id']) || empty($params['data']) || !is_array($params['data'])) {
            return false;
        }

        $CenterModel = new MessageCenterModel();

        foreach ($params['data'] as $k=>$data) {
            $return[$k] = [
                'message_center_id'    => 0,
                'message_center_title' => $data['title'] ?: '',
                'status' => 'fail',
            ];
            //1、创建消息中心消息
            $createCenterData = [
                'title'       => $data['title'],
                'content'     => $data['content'] ?: '',
                'params'      => $data['params'] ?? [],
                'channel_id'  => $params['channel_id'],
                'send_status' => self::MESSAGE_CENTER_DEFAULT_SEND_STATUS,
                'type'        => $data['type'] ?? self::MESSAGE_CENTER_DEFAULT_TYPE,
                'member_id'   => $data['member_id'] ? $params['member_id'] : 0,
                'company_id'  => $params['company_id'],
                'template_id' => $params['template_id'],
                'total_num'   => 0
            ];
            $centerId = $CenterModel->createCenter($createCenterData);
            if (empty($centerId)) {
                continue;
            }

            //2、追加发送用户
            $itemsList = [];
            foreach ($data['recv_data'] as $recv_data) {
                $itemsList[] = [
                    'message_center_id' => $centerId,
                    'user_id' => $recv_data['user_id'],
                    'recv_obj' => $recv_data['recv_obj']
                ];
            }
            $res = $CenterModel->createCenterItems($itemsList);
            if (empty($res)) {
               continue;
            }
            //3、订单消息状态更新为待发送
            $res = $CenterModel->updateSendStatusById($centerId,self::MESSAGE_CENTER_STAY_SEND_STATUS);
            if(!$res){
                continue;
            }

            $return[$k]['message_center_id'] = $centerId;
            $return[$k]['status'] = 'succ';
        }

        return $return;
    }
}
