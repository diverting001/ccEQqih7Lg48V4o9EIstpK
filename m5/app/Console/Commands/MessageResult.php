<?php

namespace App\Console\Commands;

use App\Api\Model\Message\MessageLog;
use App\Api\V1\Service\Message\MessageProducer;
use Illuminate\Console\Command;
use Neigou\RedisNeigou;

class MessageResult extends Command
{
    public const LOCK_EXPIRE_TIME = 600;
    protected $signature = 'MessageResult {--action=}';
    protected $description = '消息服务结果';

    public function handle()
    {
        $action = $this->option('action');
        echo '消息中心: '.$action.PHP_EOL;

        switch ($action) {
            case 'query':
                $this->queryMessage();
                break;
        }
    }

    private function queryMessage()
    {
        $time = time();
        $key = 'service_message_result_send_query_lock';
        if (!$this->lock($key, $time, self::LOCK_EXPIRE_TIME)) {
            echo '获取锁失败'.PHP_EOL;
            return false;
        }

        $messageLogModel = new MessageLog();
        $list = $messageLogModel->getBatchByStatus(MessageLog::SEND_STATUS_SYNC_RESULT_ING);

        if (empty($list)) {
            $this->deleteLock($key, $time);
            return false;
        }
        $classList = [];
        foreach ($list as $messageLog) {
            //单批次发送全部逻辑
            $messageService = new MessageProducer();
            $platform = $messageService->findPlatformByChannel($messageLog->channel);
            if (isset($classList[$messageLog->channel])) {
                $classObj = $classList[$messageLog->channel];
            } else {
                $classObj = new $platform['class'](
                    [
                        'channel' => $messageLog->channel,
                        'data' => []
                    ]);
                $classList[$messageLog->channel] = $classObj;
            }
            $batchInfoList = $messageLogModel->getSendBatchInfo($messageLog->id);
            $flag = false;
            foreach ($batchInfoList as $batchInfo) {
                $result = $classObj->getResult($batchInfo);
                if (isset($result['result']) && ($result['result'])) {
                    $messageLogModel->updateInfoLogByBatch(
                        $messageLog->id,
                        $batchInfo->send_batch,
                        MessageLog::SEND_STATUS_SUCCESS,
                        $result['data']
                    );
                    $flag = true;
                }
            }
            if ($flag) {
                $messageLogModel->updateBatchLog($messageLog->id, MessageLog::SEND_STATUS_SUCCESS);
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

}
