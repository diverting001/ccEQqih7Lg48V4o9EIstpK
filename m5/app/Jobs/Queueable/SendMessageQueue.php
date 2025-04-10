<?php

namespace App\Jobs\Queueable;

use App\Api\V1\Service\Message\MessageProducer;
use App\Jobs\Job;

class SendMessageQueue extends Job
{
    protected $param;

    public function __construct(array $param)
    {
        $this->param = $param;
    }

    /**
     * 处理消息并发送
     *
     * @return void
     */
    public function handle()
    {
        $messageProducer = new MessageProducer();
        $classObj = $messageProducer->platformCheck($this->param['channel'], $this->param);
        $classObj->setBatchId($this->param['batch_id']);
        $classObj->sendMessage();
    }

    public function failed()
    {
        //失败超过重试次数
    }
}
