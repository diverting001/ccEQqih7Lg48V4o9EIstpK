<?php
namespace App\Api\V1\Service\Message;
use App\Api\Logic\Service;
use App\Api\Model\Message\Channel as ChannelModel;
use App\Api\V1\Service\ServiceTrait;
use Illuminate\Support\Facades\DB;
use Neigou\ApiClient;
use Neigou\Logger;
//use Swoole\Database\MysqliException;


class Channel{
    use ServiceTrait;

    /**
     * Notes: 获取channel列表
     * @param array $params
     * @return \Closure
     * Author: liuming
     * Date: 2021/1/18
     */
    public function getChannelList($channelIds){
        if ($channelIds){
            $exWhere = [
                [
                    'key' =>'channel.id',
                    'value' => $channelIds,
                    'express' => 'in'
                ]
            ];
        }

        $channelModel = new ChannelModel();
        $channelList = $channelModel->findList([],$exWhere);

        if ($channelList->isEmpty()){
            return $this->outputFormat([]);
        }

        foreach ($channelList as $channelV){
            $channelIdList[] = $channelV->id;
        }

        $channelTemplateCountList = $channelModel->findTemplateCountByChannelIdList($channelIdList);
        foreach ($channelList as $k => &$v){

            foreach ($channelTemplateCountList as $k => $tcV){
                if ($v->id == $tcV->channel_id){
                    $v->template_nums = $tcV->count;
                    unset($channelTemplateCountList[$k]);
                }
            }
        }

        // 获取当前发送渠道信息
        $sendParams = [
            'channel_ids' => $channelIdList
        ];
        $serviceLogic = new Service();
        $res = $serviceLogic->ServiceCall('company_message_get_channel_push_base_list',$sendParams);

        // 记录错误日志
        if ($res['error_code'] != 'SUCCESS'){
            Logger::Debug("service.message.channel",array('action' => 'getChannelList','params' => $sendParams,'res' => $res));
            return $this->outputFormat([], '获取服务端channel基本信息错误', 500);
        }
        $channelPushList = $res['data']['list'];
        $returnChannelList = [];
        foreach ($channelList as $k => $v){
            $v->is_push = 2;
            if (!empty($channelPushList)){
                foreach ($channelPushList as $pushKey => $pushV) {
                    if ($v->id == $pushV['channel_id']) {
                        $v->is_push = 1;
                        $v->push_type = $pushV['type'];
                        unset($channelPushList[$pushKey]);
                    }
                }
            }
            $returnChannelList[$v->id] = $v;
        }
        return $this->outputFormat($returnChannelList);
    }


    /**
     * Notes: 获取channel信息
     * @param $id
     * @return \Closure
     * Author: liuming
     * Date: 2021/2/4
     */
    public function getChannel($id){
        $channelModel = new ChannelModel();
        $channelInfo = $channelModel->findChannelRows(['channel.id' => $id]);
        return $this->outputFormat($channelInfo,'成功');

    }

    /**
     * Notes: 获取channel_ids
     * @param $templateId
     * @return \Closure
     * Author: liuming
     * Date: 2021/3/15
     */
    public function getChannelIdListByTemplateId($templateId){
        $channelModel = new ChannelModel();
        $channelIds = $channelModel->findChannelIdListByTemplateId($templateId);
        $ids =[];
        if (!$channelIds->isEmpty()){
            foreach ($channelIds as $v){
                $ids[] = $v->channel_id;
            }
        }
        return $this->outputFormat($ids,'成功');
    }
}
