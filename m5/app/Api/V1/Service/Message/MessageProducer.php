<?php
namespace App\Api\V1\Service\Message;

use App\Api\Model\Message\Channel;
use App\Api\Model\Message\Platform;
use App\Api\V1\Service\ServiceTrait;
use App\Jobs\Queueable\SendMessageQueue;
use Carbon\Carbon;

/**
 * 消息模板配置
 */
class MessageProducer
{
    use ServiceTrait;

    /**
     * 处理消息
     * @param $param
     * @return \Closure|string
     */
    public function messageProcessing($param)
    {
        $classObj = $this->platformCheck($param['channel'], $param);
        #调用平台验证规则
        $checkResult = $classObj->checkParam();

        if ($checkResult !== true) {
            return $this->outputFormat($checkResult, $checkResult, MessageHandler::CODE_FIELD_FAIL);
        }

        #加入队列
        $batchId = $this->messageAddQueue($param);
        return $this->outputFormat(['batchId' => $batchId]);
    }

    /**
     * 获取指定渠道处理类
     * @param $channel
     * @param $param
     * @return MessageHandler | false
     */
    public function platformCheck($channel, $param)
    {
        $platform = $this->findPlatformByChannel($channel);
        if (empty($platform)) {
            return false;
        }

        return new $platform['class']($param);
    }

    /**
     * 查找渠道对应信息
     * @param $channel string 渠道名称
     * @return array
     */
    public function findPlatformByChannel(string $channel)
    {
        $channelModel = new Channel();
        $platform = $channelModel->findPlatform($channel);
        if (!isset($platform->platform_id)) {
            return [];
        }
        $platformModel = new Platform();
        return $platformModel->findPlatformById($platform->platform_id);
    }

    /**
     * 加入消息队列
     * @param $param
     * @return int 批次id
     */
    public function messageAddQueue($param)
    {
        #生成日志
        $batchId = MessageLog::batchStart($param);
        #设置批次id
        $param['batch_id'] = $batchId;
        $messageQueue = new SendMessageQueue($param);

        #加延迟队列
        if (isset($param['send_at'])) {
            $messageQueue->delay(Carbon::createFromFormat('Y-m-d H:i:s', $param['send_at']));
        }
        $dispatch = dispatch($messageQueue);
        if (isset($param['aging']) && $param['aging'] === 2) {
            $dispatch->onQueue('message.slow');
        } else {
            $dispatch->onQueue('message.quick');
        }
        return $batchId;
    }
}
