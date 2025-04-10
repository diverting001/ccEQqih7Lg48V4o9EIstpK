<?php
/**
 * php artisan DaemonTaskConsumeMessage
 * @author zhangjian 18604419839@163.com
 */
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Api\Model\DaemonTask\DaemonTask;

class DaemonTaskConsumeMessage extends Command
{

    protected $signature   = 'DaemonTaskConsumeMessage';
    protected $description = '后台脚本:消费消息队列中的消息';

    public function handle()
    {
        try {
            $callback = function($msg) {
                \Neigou\Logger::Debug('DaemonTaskConsumeMessage.msg', array('msg' => $msg));
                if (empty($msg['id']) || !is_int($msg['id'])) {
                    return true;
                }
                $daemonTaskModel = new DaemonTask();
                $daemonTaskModel->findData(array(array('id', '=', $msg['id'])));
                $data = $daemonTaskModel->getData();
                $time = time();
                if (
                    empty($data['id']) || empty($data['task_name']) || empty($data['task_address']) ||
                    $data['status'] !== $daemonTaskModel->getStatusWaitingRun() ||
                    $data['run_time'] > $time || $data['run_number'] >= $data['run_max_number']
                ) {
                    \Neigou\Logger::Debug('DaemonTaskConsumeMessage.data', array('data' => $data));
                    return true;
                }
                // 唯一消费者校验
                $res = $daemonTaskModel->uniqueConsumerCheck();
                if (!$res) {
                    return true;
                }
                $daemonTaskModel->recordOutput('开始');
                $res = $daemonTaskModel->updateStatusRunning();
                if (!$res) {
                    $daemonTaskModel->recordOutput('异常结束:updateStatusRunning', array('res' => $res));
                    return true;
                }
                $address = explode('->', $data['task_address']);
                if (count($address) !== 2) {
                    $daemonTaskModel->recordOutput('异常结束:task_address', array('task_address' => $data['task_address']));
                    return true;
                }
                $result = $this->_hook($address[0], $address[1], $data['task_parameters'], $daemonTaskModel);
                // 数据初始化
                $daemonTaskModel->dataInit();
                $daemonTaskModel->findData(array(array('id', '=', $msg['id'])));
                $daemonTaskModel->recordOutput('处理结果', array('result' => $result));
                // 记录结果
                $res = $daemonTaskModel->pushResultset(array('result' => $result));
                if (!$res) {
                    $daemonTaskModel->recordOutput('异常结束:pushResultset', array('res' => $res));
                    return true;
                }
                if ($result === true) {
                    // 成功
                    $res = $daemonTaskModel->updateStatusSuccess();
                    if (!$res) {
                        $daemonTaskModel->recordOutput('异常结束:updateStatusSuccess', array('res' => $res));
                        return true;
                    }
                }
                else {
                    // 失败
                    $data = $daemonTaskModel->getData();
                    if ($data['run_number'] < $data['run_max_number']) {
                        // 重试
                        $res = $daemonTaskModel->tryAgain();
                        if (!$res) {
                            $daemonTaskModel->recordOutput('异常结束:tryAgain', array('res' => $res));
                            return true;
                        }
                    }
                    else {
                        $res = $daemonTaskModel->updateStatusFailed();
                        if (!$res) {
                            $daemonTaskModel->recordOutput('异常结束:updateStatusFailed', array('res' => $res));
                            return true;
                        }
                    }
                }
                $daemonTaskModel->recordOutput('结束');
                return true;
            };

            $amqp = new \Neigou\AMQP();
            $amqp->ConsumeMessage(
                DaemonTask::MQ_QUEUE_NAME,
                DaemonTask::MQ_EXCHANGE_NAME,
                DaemonTask::MQ_ROUTING_KEY,
                $callback
            );
        } catch (Exception $e) {
            echo 'Caught exception: ', $e->getMessage(), "\n";
        }
    }

    /**
     * @return bool|null
     */
    private function _hook(string $class, string $action, array $params = array(), DaemonTask $daemonTaskModel)
    {
        if (class_exists($class)) {
            $result = new $class();
            if (method_exists($result, $action)) {
                return $result->$action($daemonTaskModel, $params);
            }
            else {
                return null;
            }
        } else {
            return null;
        }
    }

}
