<?php

namespace App\Console\Commands;

use App\Api\Model\Credit\CreditLimit;
use Illuminate\Console\Command;

class CreditRemind extends Command
{
    protected $signature = 'CreditRemind';
    protected $description = '信用额度过低提醒';
    protected $creditModel = null;

    public function __construct()
    {
        $this->creditModel = new CreditLimit();
        parent::__construct();
    }

    public function handle()
    {
        $mdl = new CreditLimit();

        $list = $mdl->getRemind(['disabled' => 0]);

        $currHour = date('H');

        foreach ($list as $item) {
            echo "remind,id:{$item->id}" . PHP_EOL;

            $account = $mdl->getAccount(['id' => $item->account_id]);
            if (!$account) {
                echo "continue" . PHP_EOL;
                continue;
            }

            if ($account->balance > 0) {
                if ($item->remind_times > 0) {
                    $this->clearRemind($item->id);
                    echo "clearRemind" . PHP_EOL;
                }
            } else {
                if ($account->credit_limit - abs($account->balance) >= $item->remind_credit_limit) {
                    if ($item->remind_times > 0) {
                        $this->clearRemind($item->id);
                        echo "clearRemind" . PHP_EOL;
                    }
                } else {
                    if ($item->remind_times < $item->remind_max_times && $currHour >= $item->start_hour && $currHour < $item->end_hour) {
                        $res = $this->sendRemindSms($item->mobile);
                        echo "send_sms,res:$res" . PHP_EOL;

                        $res = $this->addRemindTimes($item->id);
                        echo "addRemindTimes,res:$res" . PHP_EOL;
                    }
                }
            }
            echo "end,id:{$item->id}" . PHP_EOL;
        }
        echo "over." . PHP_EOL;
    }

    /**
     * @param $mobile
     *
     * @return bool
     */
    private function sendRemindSms($mobile)
    {
        $sendData = [
            'mobile' => $mobile,
            'content' => '您好，由于您的积分授信余额不足，商城随时可能关闭，请尽快联系相关人员。',
            'type' => 'NORMAL',
            'com' => 'DIANDI',
        ];
        $ret = \Neigou\ApiClient::doServiceCall('tools', 'Sms/Send', 'v3', null, $sendData, []);

        if ('OK' == $ret['service_status'] && 'SUCCESS' == $ret['service_data']['error_code']) {
            return true;
        } else {
            $error_code = $ret['service_data']['error_code'];
            \Neigou\Logger::Debug("CreditRemind", array('func' => 'sendRemindSms', 'error_code' => $error_code));
        }

        return false;
    }

    /**
     * @param $id
     *
     * @return mixed
     */
    private function clearRemind($id)
    {
        return $this->creditModel->editRemind(['id' => $id], ['remind_times' => 0, 'update_time' => time()]);
    }

    /**
     * @param $id
     *
     * @return bool
     */
    private function addRemindTimes($id)
    {
        return $this->creditModel->addRemindTimes($id);
    }
}
