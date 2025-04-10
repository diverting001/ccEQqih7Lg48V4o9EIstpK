<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Api\Model\Order\Order as OrderModel;
use App\Api\V1\Service\Order\Concurrent as Concurrent;
use App\Api\Logic\Service as Service;
use App\Api\Logic\Salyut\Orders as SalyutOrder;
use App\Api\Logic\Shop\Orders as ShopOrder;
use App\Api\Model\Order\OrderPayLog as OrderPayLogModel;
use Exception;
use App\Api\Logic\Mq as Mq;
use App\Api\Model\Order\OrderPayLog;

class OrderMessage extends Command
{
    protected $force = '';
    protected $signature = 'order-message {action}';
    protected $description = '订单消息处理';


    public function handle()
    {
        set_time_limit(0);
        $action = $this->argument('action');
        switch ($action) {
            case 'order_pay':   //订单支付
                $this->OrderPay();
                break;
            case 'order_cancel':    //订单取消
                $this->OrderCancel();
                break;
            case 'order_update':    //订单更新
                $this->UpdateOrder();
                break;
            case 'order_finish':    //订单完成
                $this->OrderFinish();
                break;
            case 'order_delivery':    //订单发货
                $this->OrderDelivery();
                break;
            case 'order_confirm':    //订单确认 向履约平台下单
                $this->OrderConfirm();
                break;
            case 'order_payment':
                $this->OrderPayment();
                break;
            case "sync_paylog":
                $this->SyncPayLog();
                break;
            case "order_payed_cancel":
                $this->OrderPayedCancelForSalyut();
                break;
            default:
                throw new Exception('～_～不能进行处理   啊哈哈哈哈哈哈～～～～ (^傻^)');
        }

    }

    /*
     * @todo 获取需要更新的货品信息
     */
    public function OrderPay()
    {
        $function = function ($data) {
//            $data   = $data['data'];
//            //日志记录
//            $funcgion_log   = function($method_name,$success,$msg) use ($data){
//                $log_array = array(
//                    'action' => $method_name,
//                    'success' => $success ? 1 : 0,
//                    'data' => json_encode($data),
//                    'bn' => $data['order_id'],
//                    'remark' => $msg,
//                );
//                \Neigou\Logger::General('service_message_order_dopay',$log_array);
//            };
////            sleep(5);
//            if (empty($data['order_id'])) {
//                echo '订单号不能为空' . "\n";
//                $funcgion_log('order_id_null',false,'订单号为空');
//                return false;
//            }
//            //获取订单
//            $order_info = OrderModel::GetOrderInfoById($data['order_id']);
//            if ($order_info->pay_status != 2 || $order_info->status != 1) {
//                $funcgion_log('order_status_error',false,'订单号状态错误');
//                echo $data['order_id'] . '===订单号状态错误' . "\n";
//                return false;
//            }
//            if($order_info->order_category != 'pintuan'){
//
//            }

            return true;
        };
        $amqp = new \Neigou\AMQP();
        $amqp->ConsumeMessage('service_order_pay', 'service', 'order.pay.success', $function);
    }

    /*
     * @todo 订单取消
     */
    protected function OrderPayedCancelForSalyut()
    {
        $function = function ($data) {
            //日志记录
            \Neigou\Logger::General('service_message_order_cancel_for_salyut', $data);
            $data = $data['data'];
            if (empty($data['order_id'])) {
                echo '订单号不能为空===' . "\n";
                return false;
            }
            //获取订单
            $order_info = OrderModel::GetOrderInfoById($data['order_id']);

            //取消salyut预下订单
            $salyut_order_logic = new SalyutOrder();
            $salyut_order_logic->OrderCancel(['order_id' => $order_info->order_id]);
            return true;
        };
        $amqp = new \Neigou\AMQP();
        $amqp->ConsumeMessage('service_order_payed_cancel', 'service', 'order.payed_cancel_for_refund.success', $function);
    }
    /*
     * @todo 订单取消
     */
    protected function OrderCancel()
    {
        $function = function ($data) {
            //日志记录
            $funcgion_log = function ($method_name, $success, $msg) use ($data) {
                $log_array = array(
                    'action' => $method_name,
                    'success' => $success ? 1 : 0,
                    'data' => json_encode($data),
                    'bn' => $data['order_id'],
                    'remark' => $msg,
                );
                \Neigou\Logger::General('service_message_order_cancel', $log_array);
            };
            $data = $data['data'];
            if (empty($data['order_id'])) {
                echo '订单号不能为空===' . "\n";
                return false;
            }
            //获取订单
            $order_info = OrderModel::GetOrderInfoById($data['order_id']);
            if ($order_info->status != 2) {
                $funcgion_log('order_stauts_error', false, '订单未取消');
                echo $order_info->order_id . '订单未取消' . "\n";
                return true;
            }
            //取消库存锁定
            $service_logic = new Service();
            $stock_temp_lock_data = [
                'channel' => $order_info->channel,
                'lock_obj' => $data['order_id'],
                'lock_type' => 'service_order',
            ];
            $stock_response = $service_logic->ServiceCall('stock_temp_lock_cancel', $stock_temp_lock_data);
            if ($stock_response['error_code'] != 'SUCCESS') {
                $funcgion_log('order_cancel_fail', false, '订单取消失败:' . $stock_response['service_data']['error_msg'][0]);
                echo $data['order_id'] . '订单取消失败===' . "\n";
                return true;
            }
            //取消salyut预下订单
            $salyut_order_logic = new SalyutOrder();
            $salyut_order_logic->OrderCancel(['order_id' => $order_info->order_id]);

            // 获取拆单数据
            $shop_order_logic = new ShopOrder();
            $orderSplitInfo = OrderModel::GetSplitOrderByRootPId($order_info->order_id);
            if (empty($orderSplitInfo)) {
                $shop_order_logic->OrderCancel([
                    'order_id' => $order_info->order_id,
                    'wms_code' => $order_info->wms_code
                ]);
            } else {
                foreach ($orderSplitInfo as $order) {
                    $shop_order_logic->OrderCancel(['order_id' => $order->order_id, 'wms_code' => $order->wms_code]);
                }
            }

            //消息广播
            Mq::OrderLockCancel($order_info->order_id);
            return true;
        };
        $amqp = new \Neigou\AMQP();
        $amqp->ConsumeMessage('service_order_cancel', 'service', 'order.cancel.success', $function);
    }

    /*
     * @todo 更新订单信息
     */
    public function UpdateOrder()
    {
        $function = function ($data) {
            //日志记录
            $funcgion_log = function ($method_name, $success, $msg) use ($data) {
                $log_array = array(
                    'action' => $method_name,
                    'success' => $success ? 1 : 0,
                    'data' => json_encode($data),
                    'bn' => $data['wms_order_bn'],
                    'remark' => $msg,
                );
                \Neigou\Logger::General('service_message_order_update', $log_array);
            };
            $data = $data['data'];
            if (empty($data['wms_order_bn'])) {
                $funcgion_log('wms_order_bn_is_null', false, '履约平台订单号不能为空');
                echo '履约平台订单号不能为空===' . "\n";
                return false;
            }
            //获取订单
            $order_info = OrderModel::GetWmsOrder($data['wms_code'], $data['wms_order_bn']);
            if (empty($order_info)) {
                $funcgion_log('order_is_null', false, '订单不存在');
                echo $data['wms_code'] . '====' . $data['wms_order_bn'] . '订单不存在=======' . "\n";
                return true;
            }
            if (empty($order_info->wms_order_bn)) {
                $funcgion_log('order_error', false, '订单未同步到履约平台');
                echo $order_info->order_id . '订单未同步到履约平台=======' . "\n";
                return false;
            }
            $class_name = 'App\\Api\\Logic\\OrderWMS\\' . ucfirst(strtolower($order_info->wms_code));
            if (!class_exists($class_name)) {
                $funcgion_log('order_file', false, '订单未同步到履约平台');
                echo '未到可处理订单类=======' . "\n";
                return false;
            }
            $class_obj = new $class_name();
            $wms_order_info = $class_obj->GetInfo($order_info->wms_order_bn);
            if (!$wms_order_info) {
                $funcgion_log('wms_order_error', false, '在履约平台未到订单');
                echo '在履约平台未到订单=======' . "\n";
            }
            $res = $this->OrderSplit($order_info, $wms_order_info);
            if (!$res) {
                $funcgion_log('order_update_file', false, '更新失败');
                echo $order_info->order_id . '更新失败========' . "\n";
            } else {
                echo $order_info->order_id . '更新成功========' . "\n";
            }
            return $res;
        };
        $amqp = new \Neigou\AMQP();
        $amqp->ConsumeMessage('service_order_update', 'service', 'order.update.*', $function, true);
    }


    /*
     * @todo 更新主订单完成
     */
    public function OrderFinish()
    {
        $function = function ($data) {
            $data = $data['data'];
            if (empty($data['order_id'])) {
                echo '订单号不能为空===' . "\n";
                return false;
            }
            //主单完成无需处理
            if ($data['create_source'] == 'main') {
                return true;
            }
            $split_order_info = OrderModel::GetOrderInfoById($data['order_id']);
            $order_info = OrderModel::GetOrderInfoById($split_order_info->root_pid);
            if ($order_info->status == 3) {
                echo $order_info->order_id . '订单已完成' . "\n";
                return true;
            }
            //获取所有拆分订单
            $split_order_list = OrderModel::GetSplitOrderByRootPId($order_info->order_id);
            if (!empty($split_order_list)) {
                foreach ($split_order_list as $split_order) {
                    if ($split_order->status != 3) {
                        echo $order_info->order_id . '有部分拆分订单未完成' . "\n";
                        return true;
                    }
                }
            }
            //更新主订单完成
            $where = [
                'order_id' => $order_info->order_id,
                'status' => $order_info->status,
            ];
            $save_data = [
                'status' => 3,
                'finish_time' => time()
            ];
            $res = OrderModel::OrderUpdate($where, $save_data);
            Mq::OrderFinish($order_info->order_id);
            return $res;
        };
        $amqp = new \Neigou\AMQP();
        $amqp->ConsumeMessage('service_order_finish', 'service', 'order.finish.success', $function);
    }

    /*
     * @todo 更新主订单取消
     */
    protected function UpdateMainOrderCancel($order_id)
    {
        $split_order_info = OrderModel::GetOrderInfoById($order_id);
        $order_info = OrderModel::GetOrderInfoById($split_order_info->root_pid);
        if ($order_info->status == 2) {
            echo $order_info->order_id . '订单已取消' . "\n";
            return true;
        }
        //获取所有拆分订单
        $split_order_list = OrderModel::GetSplitOrderByRootPId($order_info->order_id);
        if (!empty($split_order_list)) {
            foreach ($split_order_list as $split_order) {
                if ($split_order->status != 2) {
                    echo $order_info->order_id . '有部分拆分订单未取消' . "\n";
                    return true;
                }
            }
        }
        //更新主订单完成
        $where = [
            'order_id' => $order_info->order_id,
            'status' => $order_info->status,
        ];
        $save_data = [
            'status' => 2,
            'cancel_time' => time()
        ];
        $res = OrderModel::OrderUpdate($where, $save_data);
        return $res;
    }

    /*
     * @todo 更新主订单发货
     */
    public function OrderDelivery()
    {
        $function = function ($data) {
            $data = $data['data'];
            if (empty($data['order_id'])) {
                echo '订单号不能为空===' . "\n";
                return false;
            }
            //主单完成无需处理
            if ($data['create_source'] == 'main') {
                return true;
            }
            $split_order_info = OrderModel::GetOrderInfoById($data['order_id']);
            $order_info = OrderModel::GetOrderInfoById($split_order_info->root_pid);
            if ($order_info->ship_status == 2) {
                echo $order_info->order_id . '订单已发货' . "\n";
                return true;
            }
            //获取所有拆分订单
            $split_order_list = OrderModel::GetSplitOrderByRootPId($order_info->order_id);
            if (!empty($split_order_list)) {
                foreach ($split_order_list as $split_order) {
                    if ($split_order->ship_status != 2) {
                        echo $order_info->order_id . '有部分拆分订单未发货' . "\n";
                        return true;
                    }
                }
            }
            //更新主订单完成
            $where = [
                'order_id' => $order_info->order_id,
                'ship_status' => $order_info->ship_status,
            ];
            $save_data = [
                'ship_status' => 2,
                'delivery_time' => time()
            ];
            $res = OrderModel::OrderUpdate($where, $save_data);
            Mq::OrderDelivery($order_info->order_id);
            return $res;
        };
        $amqp = new \Neigou\AMQP();
        $amqp->ConsumeMessage('service_order_delivery', 'service', 'order.delivery.success', $function);
    }


    private function OrderConfirm()
    {
        $function = function ($data) {
            $data = $data['data'];
            //日志记录
            $funcgion_log = function ($method_name, $success, $msg) use ($data) {
                $log_array = array(
                    'action' => $method_name,
                    'success' => $success ? 1 : 0,
                    'data' => json_encode($data),
                    'bn' => $data['order_id'],
                    'remark' => $msg,
                );
                \Neigou\Logger::General('service_message_order_confirm', $log_array);
            };
//            sleep(5);
            if (empty($data['order_id'])) {
                echo '订单号不能为空' . "\n";
                $funcgion_log('order_id_null', false, '订单号为空');
                return false;
            }
            //需要履约的订单
            $perform_order = [];
            //获取订单
            $order_info = OrderModel::GetOrderInfoById($data['order_id']);
            if ($order_info->pay_status != 2 || $order_info->status != 1) {
                $funcgion_log('order_status_error', false, '订单号状态错误');
                echo $data['order_id'] . '===订单号状态错误' . "\n";
                return false;
            }

            //锁定成功的订单
            $locked_order_ids = [];
            //获取拆分订单
            $split_order_list = OrderModel::GetSplitOrderByRootPId($data['order_id']);
            if (!empty($split_order_list)) {
                foreach ($split_order_list as $split_order) {
                    $current_order_id = $split_order->order_id;
                    $is_locked = OrderModel::LockOrderByIds([$current_order_id]);
                    if ($is_locked) {
                        $current_order_info = OrderModel::GetOrderInfoById($current_order_id);
                        if ($current_order_info->pay_status == 2 &&
                            $current_order_info->status == 1 &&
                            $current_order_info->confirm_status == 2 &&
                            !in_array($current_order_info->hung_up_status, [1, 3])) {
                            if (!empty($current_order_info->wms_code) && empty($current_order_info->wms_order_bn)) {
                                $perform_order[] = $current_order_info->order_id;
                            }
                        }
                        $locked_order_ids[] = $current_order_id;
                    }
                }
            } else {
                $current_order_id = $order_info->order_id;
                $is_locked = OrderModel::LockOrderByIds([$current_order_id]);
                if ($is_locked) {
                    $current_order_info = OrderModel::GetOrderInfoById($current_order_id);
                    if ($current_order_info->pay_status == 2 &&
                        $current_order_info->status == 1 &&
                        $current_order_info->confirm_status == 2 &&
                        !in_array($current_order_info->hung_up_status, [1, 3])) {
                        if (!empty($current_order_info->wms_code) && empty($current_order_info->wms_order_bn)) {
                            $perform_order[] = $current_order_info->order_id;
                        }
                    }
                    $locked_order_ids[] = $current_order_id;
                }
            }

            //对订单进行履约平台下单
            if (empty($perform_order)) {
                $funcgion_log('order_split_error', false, '订单拆分错误');
                echo $data['order_id'] . '===订单拆分错误' . "\n";

                //返回之前取消锁定的订单
                $is_unlocked = OrderModel::UnLockOrderByIds($locked_order_ids);
                if (!$is_unlocked) {
                    $funcgion_log('unlock_order_by_ids', false, '解锁订单号失败');
                    print_r($locked_order_ids);
                    echo '===解锁订单号失败' . "\n";
                }
                return false;
            }
            //组织履约平台下单数据
            $order_data = $this->PerformOrderGenerate($perform_order);
            if (empty($order_data)) {
                $funcgion_log('preform_order_generate_error', false, '订单号数据错误');
                echo $data['order_id'] . '===订单号数据错误' . "\n";
                //返回之前取消锁定的订单
                $is_unlocked = OrderModel::UnLockOrderByIds($locked_order_ids);
                if (!$is_unlocked) {
                    $funcgion_log('unlock_order_by_ids', false, '解锁订单号失败');
                    print_r($locked_order_ids);
                    echo '===解锁订单号失败' . "\n";
                }
                return false;
            }
            $msg = '';
            //创建履约平台订单
            $res = $this->CreateWmsOrder($order_info, $order_data, $msg);

            //返回之前取消锁定的订单
            $is_unlocked = OrderModel::UnLockOrderByIds($locked_order_ids);
            if (!$is_unlocked) {
                $funcgion_log('unlock_order_by_ids', false, '解锁订单号失败');
                print_r($locked_order_ids);
                echo '===解锁订单号失败' . "\n";
            }
            if ($res) {
                echo '============成功' . "\n";
                return true;
            } else {
                $funcgion_log('order_pull_wms_order_fail', false, '履约平台创建订单错误:' . $msg);
                echo '============失败' . "\n";
                return false;
            }
        };

        $amqp = new \Neigou\AMQP();
        $amqp->ConsumeMessage('service_order_confirm', 'service', 'order.confirm.success', $function);
    }

    /*
     * @todo 组织履约平台下单数据
     */
    private function PerformOrderGenerate($perform_order)
    {
        $perform_order_data = [];
        if (empty($perform_order)) {
            return $perform_order_data;
        }
        //组织数据
        foreach ($perform_order as $order_id) {
            $order_info = OrderModel::GetOrderInfoById($order_id);
            $order_item_list = OrderModel::GetOrderItems([$order_id]);
            //获取支付日志
            $pay_log = OrderPayLogModel::GetPayLog($order_info->root_pid);
            $items = [];
            if (isset($order_item_list[$order_id])) {
                foreach ($order_item_list[$order_id] as $order_item) {
                    $items[] = [
                        'bn' => $order_item->bn,
                        'name' => $order_item->name,
                        'cost' => $order_item->cost,
                        'price' => $order_item->price,
                        'mktprice' => $order_item->mktprice,
                        'nums' => $order_item->nums,
                        'weight' => $order_item->weight,
                        'cost_freight' => $order_item->cost_freight,
                        'pmt_amount' => $order_item->pmt_amount,
                        'point_amount' => $order_item->point_amount,
                        'cost_tax' => $order_item->cost_tax,
                    ];
                }
            }
            $perform_order_data[] = array(
                'order_id' => $order_info->order_id,
                'wms_code' => $order_info->wms_code,
                'root_pid' => $order_info->root_pid,
                'member_id' => $order_info->member_id,
                'company_id' => $order_info->company_id,
                'ship_name' => $order_info->ship_name,
                'ship_addr' => $order_info->ship_addr,
                'ship_zip' => $order_info->ship_zip,
                'ship_mobile' => $order_info->ship_mobile,
                'ship_province' => $order_info->ship_province,
                'ship_city' => $order_info->ship_city,
                'ship_county' => $order_info->ship_county,
                'ship_town' => $order_info->ship_town,
                'idcardname' => $order_info->idcardname,
                'idcardno' => $order_info->idcardno,
                'point_amount' => $order_info->point_amount,
                'cost_freight' => $order_info->cost_freight,
                'payed' => $order_info->payed,
                'pmt_amount' => $order_info->pmt_amount,
                'anonymous' => $order_info->anonymous,
                'final_amount' => $order_info->final_amount,
                'weight' => $order_info->weight,
                'cost_item' => $order_info->cost_item,
                'cost_tax' => $order_info->cost_tax,
                'memo' => $order_info->memo,
                'pay_info' => [
                    'pay_name' => 'service_order_pay',
                    'trade_no' => $pay_log->trade_no,
                    'pay_money' => $order_info->payed,
                    'pay_time' => $order_info->pay_time
                ],
                'items' => $items,
                'extend_data' => !empty($order_info->extend_data) ? (json_decode($order_info->extend_data) ? json_decode($order_info->extend_data,
                    true) : $order_info->extend_data) : [],
            );
        }
        return $perform_order_data;
    }

    /*
     * @todo 创建履约平台订单
     */
    protected function CreateWmsOrder($order_info, $order_data, &$msg)
    {
        $res = true;
        if (empty($order_info) || empty($order_data)) {
            return false;
        }
        foreach ($order_data as $order) {
            $class_name = 'App\\Api\\Logic\\OrderWMS\\' . ucfirst(strtolower($order['wms_code']));
            if (!class_exists($class_name)) {
                $msg = '处理类不存在';
                echo $order['order_id'] . '===处理类不存在' . "\n";
                continue;
//                return false;
            }
            $class_obj = new $class_name();
            $order_id = $class_obj->Create($order);
            if (!$order_id) {
                $msg = '履约平台创建订单失败';
                echo $order['order_id'] . '===履约平台创建订单失败' . "\n";
                $res = false;
                continue;
//                return false;
            }
            //更新记录
            $update_order_data = [
                'wms_order_bn' => $order_id,
                'last_modified' => time()
            ];
            $where = [
                'order_id' => $order['order_id'],
                'status'    => 1,
            ];
            $up_res = OrderModel::OrderUpdate($where, $update_order_data);
            if (!$up_res) {
                $msg = '订单更新失败';
                echo $order['order_id'] . '===订单更新失败' . "\n";
                $res = false;
                //订单更新失败取消已确认订单
                if(method_exists($class_obj,'CancelOrderByWmsOrderBn')){
                    $class_obj->CancelOrderByWmsOrderBn($order_id);
                }
                continue;
//                return false;
            }
            //对商品锁定进行转换
            $product_list = [];
            foreach ($order['items'] as $item) {
                $product_list[] = [
                    'product_bn' => $item['bn'],
                    'count' => $item['nums']
                ];
            }
            $service_logic = new Service();
            $stock_temp_lock_change_data = [
                'channel' => $order_info->channel,
                'change_data' => [
                    'lock_obj' => $order_info->order_id,
                    'lock_type' => 'service_order',
                    'to_lock_obj' => $order_id,
                    'to_lock_type' => 'order',
                    'product_list' => $product_list
                ]
            ];
            $stock_response = $service_logic->ServiceCall('stock_temp_lock_change', $stock_temp_lock_change_data);
            if ($stock_response['error_code'] != 'SUCCESS') {
                $msg = '订单商品锁定转换失败';
                echo $order_info->order_id . '===订单商品锁定转换失败' . "\n";
                continue;
//                return true;
            }
        }
        return $res;
    }

    /**
     * 检查并重置子单总金额、支付金额由于分摊累加造成精度问题
     *
     * @param type $order_info 待拆分订单
     * @param type $new_split_order 拆分子订单
     */
    private function checkAndResetPrecision($order_info, &$new_split_order, $valid_split_order)
    {
        $parent_final_amount = $order_info->final_amount;

        $surplus_final_amount = $parent_final_amount;
        foreach ($valid_split_order as $v_split_order) {
            $surplus_final_amount -= $v_split_order->final_amount;
        }

        $use_final_amount = 0;
        $max_final_amount_index = 0;
        $max_final_amount = 0;
        foreach ($new_split_order as $key => $split_order) {
            $use_final_amount += $split_order['final_amount'];
            if ($max_final_amount < $split_order['final_amount']) {
                $max_final_amount = $split_order['final_amount'];
                $max_final_amount_index = $key;
            }
        }

        $x = ($surplus_final_amount - $use_final_amount);
        $abs = abs($x);
        if ($abs > 0 && $abs < 1) {
            $new_split_order[$max_final_amount_index]['final_amount'] += $x;
            $new_split_order[$max_final_amount_index]['cur_money'] += $x;

            $new_split_order[$max_final_amount_index]['final_amount'] = round($new_split_order[$max_final_amount_index]['final_amount'],
                2);
            $new_split_order[$max_final_amount_index]['cur_money'] = round($new_split_order[$max_final_amount_index]['cur_money'],
                2);

            //把偏差转移明细
            //$cost_item+$cost_freight+$cost_tax-$pmt_amount
            //$cost_item = $new_split_order[$max_final_amount_index]['cost_item'];

            $cost_freight = $new_split_order[$max_final_amount_index]['cost_freight'];
            $cost_tax = $new_split_order[$max_final_amount_index]['cost_tax'];
            $pmt_amount = $new_split_order[$max_final_amount_index]['pmt_amount'];
            //$ponit_amount = $new_split_order[$max_final_amount_index]['ponit_amount'];

            $modify_key = $cost_freight > 0 ? 'cost_freight' : ($pmt_amount > 0 ? 'pmt_amount' : ($cost_tax > 0 ? 'cost_tax' : 'ponit_amount'));
            if (isset($new_split_order[$max_final_amount_index][$modify_key]) && $new_split_order[$max_final_amount_index][$modify_key] > 0) {
                $new_split_order[$max_final_amount_index][$modify_key] += $x;
                $max_item_key = 0;
                $max_item_amount = 0;
                $max_item_bn = '';
                foreach ($new_split_order[$max_final_amount_index]['items'] as $item_key => $item) {
                    if ($item["$modify_key"] > 0 && $item['amount'] > $max_item_amount) {
                        $max_item_key = $item_key;
                        $max_item_amount = $item['amount'];
                        $max_item_bn = $item['bn'];
                    }
                }

                $old_val = $new_split_order[$max_final_amount_index]['items']["$max_item_key"]["$modify_key"];
                $new_split_order[$max_final_amount_index]['items']["$max_item_key"]["$modify_key"] += $x;
                if ($new_split_order[$max_final_amount_index]['items']["$max_item_key"]["$modify_key"] < 0) {
                    $new_split_order[$max_final_amount_index]['items']["$max_item_key"]["$modify_key"] = $old_val;
                } else {
                    $new_val = round($new_split_order[$max_final_amount_index]['items']["$max_item_key"]["$modify_key"],
                        2);
                    $new_split_order[$max_final_amount_index]['items']["$max_item_key"]["$modify_key"] = $new_val;
                }
            }

        }

        $log_array = [
            'order_info' => $order_info,
            'surplus_final_amount' => $surplus_final_amount,
            'use_final_amount' => $use_final_amount,
            'x' => $x,
            'modify_key' => $modify_key,
            'max_item_bn' => $max_item_bn,
            'old_val' => $old_val,
            'new_val' => $new_val,
            'max_final_amount_index' => $max_final_amount_index,
            'new_split_order' => $new_split_order,
        ];
        \Neigou\Logger::General('checkAndResetPrecision', $log_array);
    }

    /*
     * @todo 订单拆单
     */
    protected function OrderSplit($order_info, $wms_order_info)
    {
        echo $order_info->order_id . '======';
        $new_split_order = [];   //新建拆分订单
        $cancel_split_order = [];   //取消拆分订单
        $update_order = [];   //更新订单
        $send_mq_list = [];   //需要发送消息列表
        if (empty($order_info) || empty($wms_order_info)) {
            echo '履约平台数据为空';
            return false;
        }
        //查询订单items
        $order_items = [];
        $order_item_list = OrderModel::GetOrderItemsByOrderId($order_info->order_id);
        if (empty($order_item_list)) {
            echo '未找到订单商品明细';
            return false;
        }
        foreach ($order_item_list as $item) {
            $order_items[$item->bn] = $item;
        }
        $order_info->items = $order_items;
        if (empty($wms_order_info['son_orders'])) {
            //检查商品是否一致
            foreach ($wms_order_info['items'] as $item) {
                if (!isset($order_items[$item['product_bn']])) {
                    echo '履约商品和原订单商品不一致';
                    return false;
                }
                if ($order_items[$item['product_bn']]->nums != $item['nums']) {
                    //$wms_order_info['son_orders']   = $wms_order_info;
                    //break;
                    echo '履约商品和原订单商品不一致';
                    return false;
                }
            }
        }


        //获取订单下面的所有拆分订单
        $split_delivery_bn = [];
        $split_order_list = OrderModel::GetSplitOrders($order_info->order_id, $order_info->wms_code);
        if (!empty($split_order_list)) {
            foreach ($split_order_list as $split_order) {
                $split_delivery_bn[$split_order->wms_delivery_bn] = $split_order;
            }
        }

        //新的拆分 数据
        if (!in_array($wms_order_info['delivery_status'], array(1, 2, 3, 4))) {
            echo '主订单发货状态错误';
            return false;
        }
        if (!in_array($wms_order_info['status'], array(1, 2, 3))) {
            echo '主订单状态错误';
            return false;
        }
        if (!empty($wms_order_info['son_orders'])) {
            $valid_split_order = [];
            //已拆分子订单
            foreach ($wms_order_info['son_orders'] as $son_order) {
                if (!in_array($son_order['delivery_status'], array(1, 2, 3, 4))) {
                    echo '子订单发货状态错误';
                    return false;
                }
                if (!in_array($son_order['status'], array(1, 2, 3))) {
                    echo '子订单状态错误';
                    return false;
                }
                //验证商品是否正确
                if (empty($son_order['items'])) {
                    return false;
                }
                foreach ($son_order['items'] as $item) {
                    if (!isset($order_items[$item['product_bn']])) {
                        echo '子订商品不存在';
                        return false;
                    }
                    if ($order_items[$item['product_bn']]->nums < $item['nums']) {
                        echo '子订单商品数据错误';
                        return false;
                    }
                    $order_items[$item['product_bn']]->nums = $order_items[$item['product_bn']]->nums - $item['nums'];
                }
                //已存在的订单进行状态更新
                if (isset($split_delivery_bn[$son_order['delivery_id']])) {
                    //检查订单消息是否需要更新
                    $save_data = $this->CheckDeliveryUpdate($split_delivery_bn[$son_order['delivery_id']], $son_order,
                        $send_mq_list);
                    if (!empty($save_data)) {
                        $update_order[] = [
                            'where' => [
                                'order_id' => $split_delivery_bn[$son_order['delivery_id']]->order_id,
                                'status' => $split_delivery_bn[$son_order['delivery_id']]->status,
                                'ship_status' => $split_delivery_bn[$son_order['delivery_id']]->ship_status,
                            ],
                            'save_data' => $save_data
                        ];
                    }
                    unset($split_delivery_bn[$son_order['delivery_id']]);

                    $valid_split_order[] = $split_delivery_bn[$son_order['delivery_id']];
                } else {
                    //不存在的商品进行新建拆分订单
                    $new_split_order[] = $this->SplitOrderGenerate($order_info, $son_order, $send_mq_list);
                }
            }
            //有拆分子订单,原订单设置为已拆分取消
            if ($order_info->status == 1) {
                $cancel_split_order[] = $order_info->order_id;
            }

            //矫正子单总金额、支付金额精度问题
            if (!empty($new_split_order)) {
                $this->checkAndResetPrecision($order_info, $new_split_order, $valid_split_order);
            }
        } else {
            //检查订单消息是否需要更新
            $save_data = $this->CheckDeliveryUpdate($order_info, $wms_order_info, $send_mq_list);
            if (!empty($save_data)) {
                $update_order[] = [
                    'where' => [
                        'order_id' => $order_info->order_id,
                        'status' => $order_info->status,
                        'ship_status' => $order_info->ship_status,
                    ],
                    'save_data' => $save_data
                ];
            }
        }
        //所有需要取消的订单
        if (!empty($split_delivery_bn)) {
            foreach ($split_delivery_bn as $delivery) {
                $cancel_split_order[] = $delivery->order_id;
            }
        }
        //履约平台订单未完全拆分 不生成新订单，并不取消旧订单
        if ($wms_order_info['is_full_split'] == 2) {
            unset($new_split_order, $cancel_split_order);
        }
        //无需更新
        if (empty($new_split_order) && empty($cancel_split_order) && empty($update_order)) {
            return true;
        }
        $res = OrderModel::SaveSplitOrders($new_split_order, $cancel_split_order, $update_order);
        if ($res) {
            //发送消息
            if (!empty($send_mq_list)) {
                foreach ($send_mq_list as $value) {
                    switch ($value['type']) {
                        case 'delivery':
                            Mq::OrderDelivery($value['order_id']);
                            break;
                        case 'finish':
                            Mq::OrderFinish($value['order_id']);
                            break;
                        case 'cancel':
                            $this->UpdateMainOrderCancel($value['order_id']);
                            Mq::OrderPayedCancel($value['order_id']);
                            break;
                    }
                }
            }
        }
        return $res;
    }


    /*
     * @todo 创建一个新的拆分订单
     */
    protected function SplitOrderGenerate($order_info, $son_order, &$send_mq_list)
    {
        $server_concurrent = new Concurrent();
        if (empty($order_info) || empty($son_order)) {
            return [];
        }
        $cost_item = 0;    //商品总金额
        $cost_freight = 0;    //快递总金额
        $point_amount = 0;    //积分支付金额
        $cost_tax = 0;    //税费金额
        $pmt_amount = 0;    //订单优惠金额
        $weight = 0;    //重量
        $new_split_order_items = [];
        foreach ($son_order['items'] as $item) {
            $order_info_itme = $order_info->items[$item['product_bn']];
            if (empty($order_info_itme)) {
                return false;
            }
            $item_cost_item = $order_info_itme->price * $item['nums'];

            //For保存明细累加后与计算值保留两位精度相等
            if ($order_info_itme->amount > 0) {
                $item_pmt_amount = round(($item_cost_item / $order_info_itme->amount) * $order_info_itme->pmt_amount,
                    2);
                $item_point_amount = round(($item_cost_item / $order_info_itme->amount) * $order_info_itme->point_amount,
                    2);
                $item_cost_freight = round(($item_cost_item / $order_info_itme->amount) * $order_info_itme->cost_freight,
                    2);
                $item_cost_tax = round(($item_cost_item / $order_info_itme->amount) * $order_info_itme->cost_tax, 2);
            } else {
                $item_pmt_amount = 0;
                $item_point_amount = 0;
                $item_cost_freight = 0;
                $item_cost_tax = 0;
            }

            $new_split_order_items[] = [
                'bn' => $item['product_bn'],
                'p_bn' => '',
                'name' => $order_info_itme->name,
                'cost' => $order_info_itme->cost,
                'price' => $order_info_itme->price,
                'mktprice' => $order_info_itme->mktprice,
                'amount' => $item_cost_item,
                'weight' => $order_info_itme->weight * $item['nums'],
                'nums' => $item['nums'],
                'pmt_amount' => $item_pmt_amount,
                'cost_freight' => $item_cost_freight,
                'cost_tax' => $item_cost_tax,
                'point_amount' => $item_point_amount,
                'item_type' => $order_info_itme->item_type,
            ];
            $cost_item += $item_cost_item;
            $cost_freight += $item_cost_freight;
            $cost_tax += $item_cost_tax;
            $point_amount += $item_point_amount;
            $pmt_amount += $item_pmt_amount;
            $weight += $order_info_itme->weight * $item['nums'];
        }
        $new_split_order = [
            'order_id' => $server_concurrent->GetOrderId(),
            'pid' => $order_info->order_id,
            'root_pid' => $order_info->root_pid,
            'create_source' => 'split_order',
            'final_amount' => ($cost_item + $cost_freight + $cost_tax - $pmt_amount),
            //订单总金额(商品总金额+快递总金额 + 税费 -优惠金额 )
            'cur_money' => ($cost_item + $cost_freight + $cost_tax - $pmt_amount) - $point_amount,
            //现金需支付总额 (商品总金额+快递总金额 + 税费 -优惠金额 -积分抵扣 )
            'member_id' => $order_info->member_id,
            'company_id' => $order_info->company_id,
            'create_time' => $order_info->create_time,
            'pay_time' => $order_info->pay_time,
            'last_modified' => time(),
            'stplit_create_time' => time(),
            'ship_province' => $order_info->ship_province,
            'ship_city' => $order_info->ship_city,
            'ship_county' => $order_info->ship_county,
            'ship_town' => $order_info->ship_town,
            'ship_name' => $order_info->ship_name,
            'receive_mode' => $order_info->receive_mode,
            'idcardname' => $order_info->idcardname,
            'idcardno' => $order_info->idcardno,
            'weight' => $weight,
            'ship_addr' => $order_info->ship_addr,
            'ship_zip' => $order_info->ship_zip,
            'ship_tel' => $order_info->ship_tel,
            'ship_mobile' => $order_info->ship_mobile,
            'cost_item' => $cost_item,
            'cost_freight' => $cost_freight,
            'cost_tax' => $cost_tax,
            'point_amount' => $point_amount,
            'point_channel' => $order_info->point_channel,
            'pmt_amount' => $pmt_amount,
            'payed' => ($cost_item + $cost_freight + $cost_tax - $pmt_amount) - $point_amount,
            'terminal' => $order_info->terminal,
            'anonymous' => $order_info->anonymous,
            'business_code' => $order_info->business_code,
            'business_project_code' => $order_info->business_project_code,
            'system_code' => $order_info->system_code,
            'extend_info_code' => $order_info->extend_info_code,
            'order_category' => $order_info->order_category,
            'payment_restriction' => $order_info->payment_restriction,
            'pay_status' => 2,
            'payment' => $order_info->payment,
            'wms_order_bn' => $order_info->wms_order_bn,
            'wms_code' => $order_info->wms_code,
            'memo' => $order_info->memo,
            'channel' => $order_info->channel,
            'pop_owner_id' => $order_info->pop_owner_id,
            //发货信息
            'wms_delivery_bn' => $son_order['delivery_id'],
            'ship_status' => $son_order['delivery_status'],
            'status' => $son_order['status'],
            'confirm_status' => 2,
            'items' => $new_split_order_items
        ];
        //订单已完成
        if ($new_split_order['status'] == 3) {
            $new_split_order['finish_time'] = time();
            //订单完成消息
            $send_mq_list[] = [
                'type' => 'finish',
                'order_id' => $new_split_order['order_id'],
            ];
        }
        //已发货
        if ($new_split_order['ship_status'] == 2) {
            $new_split_order['delivery_time'] = time();
            $new_split_order['logi_name'] = $son_order['logi_name'];
            $new_split_order['logi_no'] = $son_order['logi_no'];
            //订单发货消息
            $send_mq_list[] = [
                'type' => 'delivery',
                'order_id' => $new_split_order['order_id'],
            ];
        }
        return $new_split_order;
    }


    /*
     * @todo 对比较是否需要更新
     */
    public function CheckDeliveryUpdate($old_order, $new_order, &$send_mq_list)
    {
        $update_order = [];
        if (empty($old_order) || empty($new_order)) {
            return $update_order;
        }
        //订单状态
        switch ($old_order->status) {
            case 1: //正常订单
                if ($new_order['status'] == 2) {
                    $update_order['status'] = 2;
                    $update_order['cancel_time'] = time();
                    $send_mq_list[] = [
                        'type' => 'cancel',
                        'order_id' => $old_order->order_id,
                    ];
                } else {
                    if ($new_order['status'] == 3) {
                        $update_order['status'] = 3;
                        $update_order['finish_time'] = time();
                        //订单完成消息
                        $send_mq_list[] = [
                            'type' => 'finish',
                            'order_id' => $old_order->order_id,
                        ];
                    }
                }
                break;
            case 2: //订单取消
                break;
            case 3: //订单完成
                break;
        }
        //发货状态
        switch ($old_order->ship_status) {
            case 1: //未发货
                if (in_array($new_order['delivery_status'], [2, 3])) {
//                    if ( !empty($new_order['logi_name']) && !empty($new_order['logi_no'])){
                    $send_mq_list[] = [];
                    $update_order['ship_status'] = $new_order['delivery_status'];
                    $update_order['delivery_time'] = time();
                    $update_order['logi_name'] = $new_order['logi_name'];
                    $update_order['logi_no'] = $new_order['logi_no'];
                    $update_order['logi_code'] = $new_order['logi_code'];
                    $update_order['wms_delivery_bn'] = $new_order['delivery_id'];
                    //订单发货消息
                    $send_mq_list[] = [
                        'type' => 'delivery',
                        'order_id' => $old_order->order_id,
                    ];
//                    }
                }
                break;
            case 2: //已发货
                if ($new_order['logi_no']) {
                    $update_order['logi_name'] = $new_order['logi_name'];
                    $update_order['logi_no'] = $new_order['logi_no'];
                    $update_order['logi_code'] = $new_order['logi_code'];
                }
                break;
            case 3: //已完成
                break;
        }
        return $update_order;
    }


    /*
     * @todo 订单支付记录更新
     */
    public function SyncPayLog()
    {
        $i = 0;
        $size = 10;
        while (true) {
            $limit = ($i * $size) . ',' . $size;
            $sql = "select o.order_id from server_orders as o left join server_order_pay_log as pl on pl.order_id = o.order_id where create_source = 'main' and pay_status = 2 and pl.id is null limit {$limit}";
            $order_list = app('api_db')->select($sql);
            if (empty($order_list)) {
                die('同步完成^__^!');
            }
            $order_id_list = array();
            foreach ($order_list as $order) {
                $order_id_list[] = $order->order_id;
            }
            if (empty($order_id_list)) {
                die('获取订单号为空*__*!');
            }
            //获取es同中的支付信息
            $sql = "select p.payment_id,p.pay_app_id,p.trade_no,p.cur_money,p.t_payed,b.rel_id from sdb_ectools_order_bills as b 
            inner join sdb_ectools_payments as p on b.bill_id =p.payment_id where b.rel_id in (" . implode(',',
                    $order_id_list) . ")  and pay_object  = 'order' and status = 'succ'";
            $pay_list = app('db')->connection('neigou_store')->select($sql);
            if (empty($pay_list)) {
                die('怎么没有es的支付单呢-__-!');
            }
            foreach ($pay_list as $pay_info) {
                $extend_data = [
                    'pay_money' => $pay_info->cur_money,
                    'pay_time' => $pay_info->t_payed,
                ];
                $log_data[] = [
                    'order_id' => $pay_info->rel_id,
                    'pay_name' => $pay_info->pay_app_id,
                    'trade_no' => $pay_info->trade_no,
                    'payment_id' => $pay_info->payment_id,
                    'payment_system' => 'EC',
                    'create_time' => time(),
                    'extend_data' => json_encode($extend_data),
                ];
            }
            app('db')->table('server_order_pay_log')->insert($log_data);
            echo $i . "\n";
            $i++;
        }
    }

}
