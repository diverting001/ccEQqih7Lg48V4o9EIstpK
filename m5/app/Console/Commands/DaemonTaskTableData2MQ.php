<?php
/**
 * php artisan DaemonTaskTableData2MQ
 * @author zhangjian 18604419839@163.com
 */
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Api\Model\DaemonTask\DaemonTask;

class DaemonTaskTableData2MQ extends Command
{

    protected $signature   = 'DaemonTaskTableData2MQ';
    protected $description = '后台脚本:轮询符合条件的表数据进入消息队列';

    public function handle()
    {
        $amqp = new \Neigou\AMQP();

        $daemonTaskModel = new DaemonTask();
        $result = $daemonTaskModel->tableData2MQ();

        if (!empty($result)) {
            foreach ($result as $key => $value) {
                $daemonTaskModel->dataInit();
                $daemonTaskModel->dataOverwrite($value);
                // 正常加入消息队列
                if ($daemonTaskModel->getStatus() === DaemonTask::STATUS_WAITING_JOIN_MQ) {
                    $res = $daemonTaskModel->updateStatusWaitingRun();
                    if ($res) {
                        $msg = array(
                            'id' => $value['id']
                        );
                        $amqp->PublishMessage(
                            DaemonTask::MQ_EXCHANGE_NAME,
                            DaemonTask::MQ_ROUTING_KEY,
                            $msg
                        );
                    }
                }
                // 由于PublishMessage方法没有返回值，无从判定加入消息队列是否成功，故将超时数据再次加入消息队列
                else if ($daemonTaskModel->getStatus() === DaemonTask::STATUS_WAITING_RUN) {
                    $data = $daemonTaskModel->getData();
                    if ($data['run_number'] < $data['run_max_number']) {
                        $res = $daemonTaskModel->updateRunTime();
                        if ($res) {
                            $msg = array(
                                'id' => (int)$value['id']
                            );
                            $amqp->PublishMessage(
                                DaemonTask::MQ_EXCHANGE_NAME,
                                DaemonTask::MQ_ROUTING_KEY,
                                $msg
                            );
                        }
                    }
                    else {
                        $res = $daemonTaskModel->updateStatusFailed();
                    }
                }
            }
        }
    }

}
