<?php

namespace App\Console\Commands;

use App\Api\Logic\PushMessage\AftersaleMessage;
use App\Api\Logic\PushMessage\OrderMessage;
use App\Api\Model\PushMessage\PushMessageConf;
use App\Api\Model\PushMessage\PushMessageTypeConf;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MessageHandler extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'message {--system_code=} {--message_type=} {--son_process=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "消息推送";

    protected $helpMsg = <<<EOF
    参数：
        --system_code=:
            neigou、
            mvp
        --message_type=:
            order.create.success
            order.pay.success
            order.delivery.success
            order.finish.success
            order.cancel.success
            order.payedcancel.success
            
            aftersale.finish.success
        --son_process=:可选
EOF;


    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $systemCode = $this->option('system_code');
        $messageType = $this->option('message_type');
        $sonProcess = $this->option('son_process');
        if (!$systemCode || !$messageType) {
            $this->error($this->helpMsg);
            exit;
        }

        $confObj = PushMessageConf::getConfBySystemCode($systemCode);
        if (!$confObj) {
            return $this->error('系统配置错误.system_code:' . $systemCode);
        }

        if ($confObj->push_status != 1) {
            return;
        }

        $typeConfObj = PushMessageTypeConf::getConfBySystemCodeAndType($systemCode, $messageType);
        if (!$typeConfObj) {
            return $this->error('消息类型配置错误.system_code:' . $systemCode . ",message_type:" . $messageType);
        }
        if ($typeConfObj->push_status != 1) {
            return;
        }

        $typeArr = explode('.', $messageType);
        $subject = isset($typeArr[0]) ? $typeArr[0] : '';
        $logic = $this->getHandlerLogicBySubject($subject);
        if (!is_object($logic)) {
            return $this->error('消息类型配置错误.subject:' . $subject);
        }

        $funcName = Str::camel(implode('_', $typeArr));

        $existsStatus = method_exists($logic, $funcName);
        if (!$existsStatus) {
            return $this->error('消息类型配置错误.logic:' . get_class($logic) . ',function_name:' . $funcName);
        }

        $logic->setSonProcess($sonProcess ? $sonProcess : '');
        $logic->serSystemCode($systemCode);

        $logic->setUrl($confObj->push_url . $typeConfObj->uri);

        $overTime = is_null($typeConfObj->push_overtime) ? $confObj->push_overtime : $typeConfObj->push_overtime;
        $logic->setOvertime($overTime);

        $retryCount = is_null($typeConfObj->retry_count) ? $confObj->retry_count : $typeConfObj->retry_count;
        $logic->setRetryCount($retryCount);

        $retryWait = is_null($typeConfObj->retry_wait) ? $confObj->retry_wait : $typeConfObj->retry_wait;
        $logic->setRetryWait($retryWait);

        $signType = $confObj->sign_type;
        $logic->setSignType($signType);

        $signConfig = json_decode($confObj->sign_config, true);
        $signConfig = is_array($signConfig) ? $signConfig : [];
        $logic->setSignConfig($signConfig);

        $messageFilter1 = json_decode($confObj->message_filter, true);
        $messageFilter2 = json_decode($typeConfObj->message_filter, true);
        $messageFilter1 = $messageFilter1 ? $messageFilter1 : [];
        $messageFilter2 = $messageFilter2 ? $messageFilter2 : [];
        $messageFilter = array_merge($messageFilter1, $messageFilter2);
        $logic->setMessageFilter($messageFilter);

        $logic->$funcName();
    }

    private function getHandlerLogicBySubject($subject)
    {
        switch (strtolower($subject)) {
            case 'order':
                return new OrderMessage();
            case 'aftersale':
                return new AftersaleMessage();
        }
    }
}
