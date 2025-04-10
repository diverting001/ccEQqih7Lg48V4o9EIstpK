<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-05-14
 * Time: 21:22
 */

namespace App\Api\Logic\PushMessage;


use App\Api\Model\Order\Order;
use Neigou\Logger;

class OrderMessage extends BaseMessage
{
    /**
     * 订单完成
     */
    public function orderFinishSuccess()
    {
        $queueName = 'order.finish.success';
        if ($this->sonProcess) {
            $queueName = $queueName . '.' . $this->sonProcess;
        }
        $amqp = new \Neigou\AMQP();
        $amqp->ConsumeMessage(
            $this->systemCode . '.' . $queueName,
            'service',
            'order.finish.success',
            function ($data) use ($queueName) {
                Logger::Debug('push_message_log', [
                    'active' => $queueName,
                    'request_params' => $data,
                ]);
                $orderId = isset($data['data']['order_id']) ? $data['data']['order_id'] : '';
                if (!$orderId) {
                    Logger::General('push_message_error', [
                        'sparam1' => '订单号不存在！',
                        'sparam2' => 'orderFinishSuccess',
                        'request_params' => $data,
                    ]);
                    echo '订单号不存在！';
                    return false;
                }

                //获取订单
                $orderInfo = Order::GetOrderInfoById($orderId);
                foreach ($this->message_filter as $key => $val) {
                    if (isset($orderInfo->$key)) {
                        if (is_array($val) && !in_array($orderInfo->$key, $val)) {
                            echo '订单过滤！';
                            return true;
                        } elseif ($val && $orderInfo->$key != $val) {
                            echo '订单过滤！';
                            return true;
                        }
                    }
                }

                $sendData = [
                    'message_type' => $queueName,
                    'message_data' => [
                        'order_id' => $orderId
                    ]
                ];

                $result = $this->pushMessage($sendData);
                if (isset($result['Result']) && $result['Result'] == 'true') {
                    echo '处理成功:order_id:' . $orderId;
                    return true;
                } else {
                    echo '处理失败:order_id:' . $orderId . ',message:' . json_encode($result);
                    $status = $this->attemptRetryPush($sendData);
                    return $status;
                }
            });
    }

    /**
     * 订单发货完成
     */
    public function orderDeliverySuccess()
    {
        $queueName = 'order.delivery.success';
        if ($this->sonProcess) {
            $queueName = $queueName . '.' . $this->sonProcess;
        }
        $amqp = new \Neigou\AMQP();
        $amqp->ConsumeMessage(
            $this->systemCode . '.' . $queueName,
            'service',
            'order.delivery.success',
            function ($data) use ($queueName) {
                Logger::Debug('push_message_log', [
                    'active' => $queueName,
                    'request_params' => $data,
                ]);
                $orderId = isset($data['data']['order_id']) ? $data['data']['order_id'] : '';
                if (!$orderId) {
                    Logger::General('push_message_error', [
                        'sparam1' => '订单号不存在！',
                        'sparam2' => 'orderDeliverySuccess',
                        'request_params' => $data,
                    ]);
                    echo '订单号不存在！';
                    return false;
                }

                //获取订单
                $orderInfo = Order::GetOrderInfoById($orderId);
                foreach ($this->message_filter as $key => $val) {
                    if (isset($orderInfo->$key)) {
                        if (is_array($val) && !in_array($orderInfo->$key, $val)) {
                            echo '订单过滤！';
                            return true;
                        } elseif ($val && $orderInfo->$key != $val) {
                            echo '订单过滤！';
                            return true;
                        }
                    }
                }

                $sendData = [
                    'message_type' => $queueName,
                    'message_data' => [
                        'order_id' => $orderId
                    ]
                ];

                $result = $this->pushMessage($sendData);
                if (isset($result['Result']) && $result['Result'] == 'true') {
                    echo '处理成功:order_id:' . $orderId;
                    return true;
                } else {
                    echo '处理失败:order_id:' . $orderId . ',message:' . json_encode($result);
                    $status = $this->attemptRetryPush($sendData);
                    return $status;
                }
            });
    }

    /**
     * 订单支付完成
     */
    public function orderPaySuccess()
    {
        $queueName = 'order.pay.success';
        if ($this->sonProcess) {
            $queueName = $queueName . '.' . $this->sonProcess;
        }
        $amqp = new \Neigou\AMQP();
        $amqp->ConsumeMessage(
            $this->systemCode . '.' . $queueName,
            'service',
            'order.pay.success',
            function ($data) use ($queueName) {
                Logger::Debug('push_message_log', [
                    'active' => $queueName,
                    'request_params' => $data,
                ]);
                $orderId = isset($data['data']['order_id']) ? $data['data']['order_id'] : '';
                if (!$orderId) {
                    Logger::General('push_message_error', [
                        'sparam1' => '订单号不存在！',
                        'sparam2' => 'orderPaySuccess',
                        'request_params' => $data,
                    ]);
                    echo '订单号不存在！';
                    return false;
                }

                //获取订单
                $orderInfo = Order::GetOrderInfoById($orderId);
                foreach ($this->message_filter as $key => $val) {
                    if (isset($orderInfo->$key)) {
                        if (is_array($val) && !in_array($orderInfo->$key, $val)) {
                            echo '订单过滤！';
                            return true;
                        } elseif ($val && $orderInfo->$key != $val) {
                            echo '订单过滤！';
                            return true;
                        }
                    }
                }

                $sendData = [
                    'message_type' => $queueName,
                    'message_data' => [
                        'order_id' => $orderId
                    ]
                ];

                $result = $this->pushMessage($sendData);
                if (isset($result['Result']) && $result['Result'] == 'true') {
                    echo '处理成功:order_id:' . $orderId;
                    return true;
                } else {
                    echo '处理失败:order_id:' . $orderId . ',message:' . json_encode($result);
                    $status = $this->attemptRetryPush($sendData);
                    return $status;
                }
            });
    }


    /**
     * 订单创建完成
     */
    public function orderCreateSuccess()
    {
        $queueName = 'order.create.success';
        if ($this->sonProcess) {
            $queueName = $queueName . '.' . $this->sonProcess;
        }
        $amqp = new \Neigou\AMQP();
        $amqp->ConsumeMessage(
            $this->systemCode . '.' . $queueName,
            'service',
            'order.create.success',
            function ($data) use ($queueName) {
                Logger::Debug('push_message_log', [
                    'active' => $queueName,
                    'request_params' => $data,
                ]);
                $orderId = isset($data['data']['order_id']) ? $data['data']['order_id'] : '';
                if (!$orderId) {
                    Logger::General('push_message_error', [
                        'sparam1' => '订单号不存在！',
                        'sparam2' => 'orderCreateSuccess',
                        'request_params' => $data,
                    ]);
                    echo '订单号不存在！';
                    return false;
                }

                //获取订单
                $orderInfo = Order::GetOrderInfoById($orderId);
                foreach ($this->message_filter as $key => $val) {
                    if (isset($orderInfo->$key)) {
                        if (is_array($val) && !in_array($orderInfo->$key, $val)) {
                            echo '订单过滤！';
                            return true;
                        } elseif ($val && $orderInfo->$key != $val) {
                            echo '订单过滤！';
                            return true;
                        }
                    }
                }

                $sendData = [
                    'message_type' => $queueName,
                    'message_data' => [
                        'order_id' => $orderId
                    ]
                ];

                $result = $this->pushMessage($sendData);
                if (isset($result['Result']) && $result['Result'] == 'true') {
                    echo '处理成功:order_id:' . $orderId;
                    return true;
                } else {
                    echo '处理失败:order_id:' . $orderId . ',message:' . json_encode($result);
                    $status = $this->attemptRetryPush($sendData);
                    return $status;
                }
            });
    }

    /**
     * 订单取消完成
     */
    public function orderCancelSuccess()
    {
        $queueName = 'order.cancel.success';
        if ($this->sonProcess) {
            $queueName = $queueName . '.' . $this->sonProcess;
        }
        $amqp = new \Neigou\AMQP();
        $amqp->ConsumeMessage(
            $this->systemCode . '.' . $queueName,
            'service',
            'order.cancel.success',
            function ($data) use ($queueName) {
                Logger::Debug('push_message_log', [
                    'active' => $queueName,
                    'request_params' => $data,
                ]);
                $orderId = isset($data['data']['order_id']) ? $data['data']['order_id'] : '';
                if (!$orderId) {
                    Logger::General('push_message_error', [
                        'sparam1' => '订单号不存在！',
                        'sparam2' => 'orderCancelSuccess',
                        'request_params' => $data,
                    ]);
                    echo '订单号不存在！';
                    return false;
                }

                //获取订单
                $orderInfo = Order::GetOrderInfoById($orderId);
                foreach ($this->message_filter as $key => $val) {
                    if (isset($orderInfo->$key)) {
                        if (is_array($val) && !in_array($orderInfo->$key, $val)) {
                            echo '订单过滤！';
                            return true;
                        } elseif ($val && $orderInfo->$key != $val) {
                            echo '订单过滤！';
                            return true;
                        }
                    }
                }

                $sendData = [
                    'message_type' => $queueName,
                    'message_data' => [
                        'order_id' => $orderId
                    ]
                ];

                $result = $this->pushMessage($sendData);
                if (isset($result['Result']) && $result['Result'] == 'true') {
                    echo '处理成功:order_id:' . $orderId;
                    return true;
                } else {
                    echo '处理失败:order_id:' . $orderId . ',message:' . json_encode($result);
                    $status = $this->attemptRetryPush($sendData);
                    return $status;
                }
            });
    }

    /**
     * 订单关闭完成 交易关闭完成
     */
    public function orderPayedcancelSuccess()
    {
        $queueName = 'order.payedcancel.success';
        if ($this->sonProcess) {
            $queueName = $queueName . '.' . $this->sonProcess;
        }

        $amqp = new \Neigou\AMQP();
        $amqp->ConsumeMessage(
            $this->systemCode . '.' . $queueName,
            'service',
            'order.payedcancel.success',
            function ($data) use ($queueName) {
                Logger::Debug('push_message_log', [
                    'active' => $queueName,
                    'request_params' => $data,
                ]);
                $orderId = isset($data['data']['order_id']) ? $data['data']['order_id'] : '';
                if (!$orderId) {
                    Logger::General('push_message_error', [
                        'sparam1' => '订单号不存在！',
                        'sparam2' => 'orderPayedcancelSuccess',
                        'request_params' => $data,
                    ]);
                    echo '订单号不存在！';
                    return false;
                }

                //获取订单
                $orderInfo = Order::GetOrderInfoById($orderId);
                foreach ($this->message_filter as $key => $val) {
                    if (isset($orderInfo->$key)) {
                        if (is_array($val) && !in_array($orderInfo->$key, $val)) {
                            echo '订单号过滤！';
                            return true;
                        } elseif ($val && $orderInfo->$key != $val) {
                            echo '订单号过滤！';
                            return true;
                        }
                    }
                }

                $sendData = [
                    'message_type' => $queueName,
                    'message_data' => [
                        'order_id' => $orderId
                    ]
                ];

                $result = $this->pushMessage($sendData);
                if (isset($result['Result']) && $result['Result'] == 'true') {
                    echo '处理成功:order_id:' . $orderId;
                    return true;
                } else {
                    echo '处理失败:order_id:' . $orderId . ',message:' . json_encode($result);
                    $status = $this->attemptRetryPush($sendData);
                    return $status;
                }
            });
    }
}
