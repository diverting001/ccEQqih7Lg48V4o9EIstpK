<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-05-14
 * Time: 21:23
 */

namespace App\Api\Logic\PushMessage;


use App\Api\Model\AfterSale\AfterSale;
use Neigou\Logger;

class AftersaleMessage extends BaseMessage
{
    public function aftersaleFinishSuccess()
    {
        $queueName = 'aftersale.finish.success';
        if ($this->sonProcess) {
            $queueName = $queueName . '.' . $this->sonProcess;
        }

        $amqp = new \Neigou\AMQP();
        $amqp->ConsumeMessage(
            $this->systemCode . '.' . $queueName,
            'service',
            'aftersale.finish.success',
            function ($data) use ($queueName) {
                Logger::Debug('push_message_log', [
                    'active' => $queueName,
                    'request_params' => $data,
                ]);
                $afterSaleBn = isset($data['data']['after_sale_bn']) ? $data['data']['after_sale_bn'] : '';
                if (!$afterSaleBn) {
                    Logger::General('push_message_error', [
                        'sparam1' => '售后单号不存在！',
                        'sparam2' => 'aftersaleFinishSuccess',
                        'request_params' => $data,
                    ]);
                    echo '售后单号不存在！';
                    return false;
                }

                //获取订单
                $afterSaleInfo = AfterSale::GetAfterSaleInfoByBn($afterSaleBn);
                foreach ($this->message_filter as $key => $val) {
                    if (isset($afterSaleInfo->$key)) {
                        if (is_array($val) && !in_array($afterSaleInfo->$key, $val)) {
                            echo '售后单过滤！';
                            return true;
                        } elseif ($val && $afterSaleInfo->$key != $val) {
                            echo '售后单过滤！';
                            return true;
                        }
                    }
                }

                $sendData = [
                    'message_type' => $queueName,
                    'message_data' => [
                        'after_sale_bn' => $afterSaleBn
                    ]
                ];

                $result = $this->pushMessage($sendData);
                if (isset($result['Result']) && $result['Result'] == 'true') {
                    echo '处理成功:after_sale_bn:' . $afterSaleBn;
                    return true;
                } else {
                    echo '处理失败:after_sale_bn:' . $afterSaleBn . ',message:' . json_encode($result);
                    $status = $this->attemptRetryPush($sendData);
                    return $status;
                }
            });
    }
}
