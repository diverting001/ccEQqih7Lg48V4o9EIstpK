<?php

namespace App\Console\Commands;

use App\Api\Logic\Openapi;
use App\Api\Logic\Service;
use App\Api\Model\Company\ClubCompany;
use App\Api\Model\Message\Center;
use App\Api\Model\Message\Channel;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use App\Api\Model\Account\MemberCompany;
use Neigou\Logger;
use Neigou\RedisNeigou;

class MessageCenter extends Command
{
    public const LOCK_EXPIRE_TIME = 600;
    protected $signature = 'MessageCenter {--action=}';
    protected $description = '消息中心';
    protected $channelKeyList = [
        1 => 'mobile',
        2 => 'email',
        3 => 'member_key_raw'
    ];

    public function handle()
    {
        $action = $this->option('action');
        echo '消息中心: '.$action.PHP_EOL;

        switch ($action) {
            case 'create':
                $this->createMessage();
                break;
            case 'query':
                $this->queryMessage();
                break;
        }
    }

    /**
     * Notes: 创建消息
     * Author: liuming
     * Date: 2021/1/25
     */
    private function createMessage()
    {
        $time = time();
        $key = 'service_message_center_send_lock';
        if (!$this->lock($key, $time, self::LOCK_EXPIRE_TIME)) {
            echo '获取锁失败'.PHP_EOL;
            return false;
        }
        $centerModel = new Center();
        $where = array(
            'send_status' => 1,
        );
        $list = $centerModel->findCenterList($where, [], 0, 500);
        foreach ($list as $v) {
            $channelIds[] = $v->channel_id;
        }
        $sendParams = [
            'ids' => $channelIds
        ];

        $serviceLogic = new Service();
        $res = $serviceLogic->ServiceCall('message_get_channel_list', $sendParams);

        if ($res['error_code'] !== 'SUCCESS') {
            echo '获取channel错误'.PHP_EOL;
            $this->deleteLock($key, $time);
            return false;
        }

        $channelList = $res['data'];

        foreach ($list as $v) {
            // 获取详情
            $lastId = 0;
            $where = [
                'message_center_id' => $v->id
            ];
            $batchIdList = [];
            do {
                $exWhere = [
                    [
                        'key' => 'id',
                        'express' => '>',
                        'value' => $lastId
                    ],
                ];
                $itemsList = $centerModel->findItemsList($where, $exWhere, 200);
                if ($itemsList->isEmpty()) {
                    break;
                }
                $count = count($itemsList);
                $lastId = $itemsList[$count - 1]->id;

                $receiverList = [];
                foreach ($itemsList as $itemV) {
                    $receiverList[] = $itemV->recv_obj;
                }
                if ($channelList[$v->channel_id]['type'] === Channel::TYPE_QYWX) {
                    $sendObj = $receiverList;
                } elseif ($channelList[$v->channel_id]['type'] === Channel::TYPE_QYWXTEXT) {
                    $clubCompanyModel = new ClubCompany();
                    $gcorpIdRes = $clubCompanyModel->getGcorpIdByCompanyId($v->company_id);
                    $sendObj = [json_encode(['guids' => $receiverList, 'gcorp_id' => $gcorpIdRes['gcorp_id']])];
                } elseif ($channelList[$v->channel_id]['type'] === Channel::TYPE_MEMBER_KEY) {
                    $clubCompanyModel = new ClubCompany();
                    $thirdCompany = $clubCompanyModel->getChannelByCompanyId($v->company_id);

                    $memberCompany = new MemberCompany();
                    $memberCompanyList = $memberCompany->getByCompanyAndMemberId($v->company_id, $receiverList);
                    $memberKeys = array_column($memberCompanyList, 'member_key');
                    if (empty($memberKeys)) $sendObj = [];
                    $sendObj = [json_encode(['receiver' => $memberKeys, 'company_id' => $thirdCompany['external_bn']])];
                } else {
                    $sendObj = $this->getSendObjData(
                        $v->company_id,
                        $receiverList,
                        $channelList[$v->channel_id]['type']
                    );
                }
                if (empty($sendObj)) {
                    echo '未匹配用户数据'.PHP_EOL;
                    continue;
                }
                // 组合发送信息数据
                $sendParams = [
                    'channel' => $channelList[$v->channel_id]['channel'],
                    'data' => [
                        [
                            'receiver' => $sendObj,
                            'template_param' => json_decode($v->params, true),
                            'template_id' => $v->template_id
                        ]
                    ]
                ];
                $serviceLogic = new Service();
                $res = $serviceLogic->ServiceCall('message_send', $sendParams);
                Logger::Debug('service.message.send.log', array('send_params' => $sendParams, 'res' => $res));
                if ($res['error_code'] === 'SUCCESS') {
                    echo 'batchId: '.$res['data']['batchId'].PHP_EOL;
                    $batchIdList[] = [
                        'message_center_id' => $v->id,
                        'batch_id' => $res['data']['batchId']
                    ];
                } else {
                    $errMsg = $v->id.' : '.implode(',', $res['error_msg']);
                    Logger::Debug(
                        'service.message_center.send.error',
                        array('send_params' => $sendParams, 'err_msg' => $errMsg, 'id' => $v->id)
                    );
                }
            } while (true);

            $totalNum = $centerModel->findItemsCount(array('message_center_id' => $v->id));
            if (isset($batchIdList) && !empty($batchIdList)) {
                $centerModel->addBatchItems($batchIdList);
                $where = [
                    'id' => $v->id
                ];
                $update = [
                    'batch_id' => $res['data']['batchId'],
                    'send_status' => 2,
                    'total_num' => $totalNum,
                ];
                $centerModel->updateCenter($where, $update);
                echo $v->id.' : 发送消息成功'.PHP_EOL;
            } else {
                $where = [
                    'id' => $v->id
                ];
                $update = [
                    'batch_id' => 0,
                    'send_status' => 4,
                    'total_num' => $totalNum,
                    'failed_num' => $totalNum
                ];
                $centerModel->updateCenter($where, $update);
                echo $errMsg.PHP_EOL;
            }
        }
        $this->deleteLock($key, $time);
    }

    private function queryMessage()
    {
        $time = time();
        $key = 'service_message_center_send__query_lock';
        if (!$this->lock($key, $time, self::LOCK_EXPIRE_TIME)) {
            echo '获取锁失败'.PHP_EOL;
            return false;
        }

        $centerModel = new Center();
        $where = array(
            'send_status' => 2,

        );
        $list = $centerModel->findCenterList($where);

        if (empty($list)) {
            $this->deleteLock($key, $time);
            return false;
        }
        $channelModel = new Channel();
        $channelWx = $channelModel->findChannelQywxChannelId();
        $platform = $channelModel->findPlatform($channelWx->channel);
        $config = json_decode($platform->platform_config, true);
        foreach ($list as $v) {
            $successNum = 0;
            $batchIds = $centerModel->findBatchIds($v->id);
            $isUpdate = false;
            foreach ($batchIds as $batchV) {
                $sendParams = [
                    'batch_id' => $batchV->batch_id
                ];
                $serviceLogic = new Service();
                $res = $serviceLogic->ServiceCall('message_get_progress', $sendParams);
                if ($res['error_code'] === 'SUCCESS' && $res['data']['result']) {
                    $data = $res['data'];
                    $successNum += $data['success'];
                    $total = $data['total'] ?? null;
                    $isUpdate = true;
                }
            }
            if ($isUpdate) {
                // 更改状态
                $where = [
                    'id' => $v->id
                ];
                if ($successNum > 0) {
                    $update['send_status'] = 3;
                    echo $v->id.' : 发送成功'.PHP_EOL;
                } else {
                    $update['send_status'] = 4;
                    echo $v->id.' : 发送失败'.PHP_EOL;
                }

                $total = $total ?? $v->total_num;
                $failedNum = $total - $successNum;
                if (isset($total)) {
                    $update['total_num'] = $total;
                }
                $update['failed_num'] = ($failedNum > 0) ? $failedNum : 0;
                $update['success_num'] = $successNum;
                $centerModel->updateCenter($where, $update);
            }
        }
        $this->deleteLock($key, $time);
    }

    /**
     * Notes: 枷锁
     * @param $key
     * @param $value
     * @param  int  $timeOut
     * @return bool
     * Author: liuming
     * Date: 2021/4/22
     */
    public function lock($key, $value, $timeOut = 10)
    {

        $redisConn = new RedisNeigou();
        $redis = $redisConn->_redis_connection;
        $res = $redis->setnx($key, $value);
        if ($res) {
            $redis->expire($key, $timeOut);
            return true;
        }

        if ($redis->ttl($key) == -1) {
            $redis->expire($key, $timeOut);
        }
        return false;
    }

    /**
     * Notes: 释放锁
     * @param $key
     * @param $value
     * Author: liuming
     * Date: 2021/4/22
     */
    public function deleteLock($key, $value)
    {
        $redisConn = new RedisNeigou();
        $redis = $redisConn->_redis_connection;

        $res = $redis->get($key);
        if ($res == $value && $redis->ttl($key) >= 1) {
            echo '释放锁成功'.PHP_EOL;
            $redis->del($key);
        } else {
            echo '释放锁失败'.PHP_EOL;
        }
    }

    public function getSendObjData($companyId, $guids, $type)
    {
        $userData = $this->getUserData($companyId, $guids);
        if (empty($userData)) {
            return false;
        }

        \Neigou\Logger::Debug('message_center_get_user_by_guids_type', array(
            'sender' => json_encode($userData),
            'type' => json_encode($this->channelKeyList[$type]),
        ));

        $key = $this->channelKeyList[$type];
        $sendObj = [];
        foreach ($userData as $v) {
            if (empty($v[$key])) {
                continue;
            }
            $sendObj[] = $v[$key];
        }
        $sendObj = array_filter($sendObj);
        return $sendObj;
    }


    private function getUserData($companyId = 0, $guids = [])
    {
        if (empty($guids) || empty($companyId)) {
            return false;
        }

        $clubCompanyModel = new ClubCompany();
        $gcorpIdRes = $clubCompanyModel->getGcorpIdByCompanyId($companyId);
        $requestData = array(
            'gcorp_id' => $gcorpIdRes['gcorp_id'],
            'guids' => $guids
        );

        $path = '/ChannelInterop/V1/Standard/User/getUserInfoByGuids';
        $openapi_logic = new Openapi();
        $result = $openapi_logic->Query($path, $requestData);

        \Neigou\Logger::Debug('message_center_get_user_by_guids', array(
            'path' => $path,
            'sender' => json_encode($requestData),
            'reason' => json_encode($result),
        ));

        if ($result['Result'] !== 'true') {
            \Neigou\Logger::Debug('message_center_get_user_by_guids', array(
                'path' => $path,
                'sender' => json_encode($requestData),
                'reason' => json_encode($result),
            ));
        }

        return $result['Data'];
    }
}
