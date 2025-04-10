<?php

namespace App\Console\Commands;

use App\Api\Logic\Express\Pickup as PickupLogic;
use App\Api\V4\Service\Express\Pickup as PickupService;
use App\Api\Logic\Service as Service;
use App\Api\Model\Express\PickupOrder as PickupOrderModel;
use App\Api\Model\Express\V2\Express as ExpressModel;
use App\Api\Model\Express\PickupOrderItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;


class ExpressPickup extends Command
{
    protected $force = '';
    protected $signature = 'ExpressPickup {channel} {type} {--id=}';
    protected $description = '上门取件下单、状态更新、物流轨迹更新';
    protected $redisClient;
    protected $pickupLogic;
    protected $channel;

    public function handle()
    {

        $channel = $this->argument('channel');
        $type = $this->argument('type');

        $this->channel = $channel;

        $this->pickupLogic = new PickupLogic($channel);

        switch ($type) {
            case 'create':
                $this->orderCreate();
                break;
            case 'detail':
                $this->orderDetailSync();
                break;
            case 'trace':
                $this->orderTraceSync();
                break;
        }
    }

    /**
     * 订单创建
     * @return bool|void
     */
    public function orderCreate() {
        $pickupOrderModel = new PickupOrderModel();
        $pickupOrderItemModel = new PickupOrderItem();
        $i = 0;
        while ($i < 99999) {
            $i ++;
            $where = [
                ['channel', '=', $this->channel],
                ['status', '=', PickupOrderModel::STATUS_READY],
                ['fail_count', '<', 6],
                ['update_time', '<', time() - 60]
            ];

            //获取待下单数据
            $orderList = $pickupOrderModel->getPickupOrderList('*', $where, [],100, 'update_time asc');
            if (empty($orderList)) {
                $this->info('同步完成');
                return true;
            }

            foreach ($orderList as $orderDetail) {
                $update = array(
                    'update_time' => time()
                );

                //调用订单取消接口
                DB::beginTransaction();
                try {
                    //加锁
                    $order = $pickupOrderModel->getOrderLock($orderDetail['id']);
                    if ($order['status'] != PickupOrderModel::STATUS_READY) {
                        continue;
                    }

                    $orderItem = $pickupOrderItemModel->getPickupOrderItemList($order['id']);
                    if (empty($orderItem)) {
                        continue;
                    }

                    //订单下单
                    $order['order_item'] = $orderItem;
                    $result = $this->pickupLogic->createOrder($order);

                    if (!isset($result['Result']) || $result['Result'] != 'true'){
                        $this->error(sprintf("id:%s,订单创建失败", $order['id']));

                        //失败次数增加
                        $update['fail_count'] = DB::raw('fail_count + 1');
                        //6次异常
                        if ($order['fail_count'] >= 5) {
                            $update['status'] = PickupOrderModel::STATUS_BLOCKED;
                            $update['status_reason'] = $result['ErrorMsg'];
                        }
                    } else {
                        $this->info(sprintf("id:%s,订单创建成功", $order['id']));

                        $update['status'] = PickupOrderModel::STATUS_ORDERED;
                        $update['status_reason'] = '订单创建成功';
                        $update['third_order_no'] = $result['Data']['third_order_no'];
                        $update['express_no'] = $result['Data']['express_no'];
                        $update['express_com'] = $result['Data']['express_com'];
                    }

                    $res = $pickupOrderModel->updatePickupOrderById($order['id'], $update);
                    $this->info(sprintf("id:%s,更新订单:%s", $order['id'], $res ? '成功' : '失败'));
                    DB::commit();

                } catch (\Exception $e) {
                    $this->error($e->getMessage());
                    DB::rollBack();break;
                }
            }

        }
    }


    /**
     * 查询订单详情,状态、运费、物流单号等
     * @return bool|void
     */
    public function orderDetailSync() {
        $id = $this->option('id');

        $pickupOrderModel = new PickupOrderModel();
        $pickupOrderService = new PickupService();

        $i = 0;
        while ($i < 99999) {
            $i ++;
            $where = [
                ['channel', '=', $this->channel],
                ['update_time', '<', time() - 300]
            ];
            if ($id) {
                $where = ['id' => $id];
            }
            $whereIn = ['status', array(PickupOrderModel::STATUS_ORDERED, PickupOrderModel::STATUS_DISTRIBUTE, PickupOrderModel::STATUS_COLLECT, PickupOrderModel::STATUS_TRANSPORT, PickupOrderModel::STATUS_DELIVERY)];
            //获取待下单数据
            $orderList = $pickupOrderModel->getPickupOrderList('*', $where, $whereIn, 100, 'update_time asc');
            if (empty($orderList)) {
                $this->info('同步完成');
                return true;
            }

            foreach ($orderList as $order) {
                $result = $this->pickupLogic->queryOrderDetail($order['third_order_no'], $order['order_no']);
                if (!isset($result['Result']) || $result['Result'] != 'true'){
                    $updateData = [
                        'update_time' => time()
                    ];
                    $this->error(sprintf("id:%s,订单查询失败", $order['id']));
                } else {
                    $updateData = [
                        'status' => $result['Data']['status'],
                        'status_reason' => 'order detail sync',
                        'express_no' => $result['Data']['express_no'],
                        'third_order_no' => $result['Data']['third_order_no'],
                        'express_com' => $result['Data']['express_com'],
                        'freight_price' => $result['Data']['order_fee'],
                        'api_data' => json_encode($result['Data']['api_data']),
                        'update_time' => time()
                    ];
                    $this->info(sprintf("id:%s,订单查询成功", $order['id']));
                }
                $res = $pickupOrderService->updatePickupDetail($order, $updateData);
                $this->info(sprintf("id:%s,更新订单:%s", $order['id'], $res['status'] ? '成功' : '失败'));
            }
            if ($id) {
                break;
            }
        }
    }

    /**
     * 查询订单物流轨迹
     * @return bool|void
     */
    public function orderTraceSync() {
        $id = $this->option('id');

        $service_logic = new Service();
        $pickupOrderModel = new PickupOrderModel();

        $i = 0;
        while ($i < 99999) {
            $i ++;

            $where = [
                ['channel', '=', $this->channel],
                ['express_no', '!=', ''],
                ['express_com', '!=', ''],
                ['trace_update_time', '<', time() - 300],
                ['update_time', '>' , time() - 86400]
            ];
            if ($id) {
                $where = ['id' => $id];
            }
            //获取待下单数据
            $orderList = $pickupOrderModel->getPickupOrderList('*', $where, [], 100, 'trace_update_time asc');
            if (empty($orderList)) {
                $this->info('同步完成');
                return true;
            }

            foreach ($orderList as $order) {
                $update = [
                    'trace_update_time' => time()
                ];

                $result = $this->pickupLogic->queryOrderTrace($order['third_order_no'], $order['order_no']);
                if (!isset($result['Result']) || $result['Result'] != 'true'){
                    $this->error(sprintf("id:%s,订单查询失败", $order['id']));
                } else {
                    //遍历获取揽收时间
                    if (!$order['collect_time']) {
                        $collectionTime = 0;
                        foreach ($result['Data'] as $trace) {
                            if ($trace['status'] == PickupOrderModel::STATUS_COLLECT) {
                                $collectionTime = strtotime($trace['time']);
                                break;
                            }
                        }
                        $update['collect_time'] = $collectionTime;
                    }

                    $this->info(sprintf("id:%s,订单查询成功", $order['id']));
                }

                $res = $pickupOrderModel->updatePickupOrderById($order['id'], $update);
                $this->info(sprintf("id:%s,更新订单:%s", $order['id'], $res ? '成功' : '失败'));

                $expressStatus = $this->getExpressStatus($order['status']);

                //同步物流
                $exprsssResult = $service_logic->ServiceCall('express_save', [
                    'express_com' => $order['express_com'],
                    'express_no' => $order['express_no'],
                    'express_mobile' => $order['sender_mobile'],
                    'express_channel' => 'suppplier',
                    'status' => $expressStatus,
                    'express_data' => ['data' => $result['Data']],
                ]);
                if ('SUCCESS' != $exprsssResult['error_code']) {
                    $this->info(sprintf("物流保存失败"));
                }
            }
            if ($id) {
                break;
            }
        }
    }

    /**
     * 上门取件订单状态跟物流状态映射
     * @param $status
     * @return int|mixed
     */
    private function getExpressStatus($status) {
        $map = [
            PickupOrderModel::STATUS_READY => ExpressModel::STATUS_EMPTY,
            PickupOrderModel::STATUS_ORDERED => ExpressModel::STATUS_EMPTY,
            PickupOrderModel::STATUS_DISTRIBUTE => ExpressModel::STATUS_EMPTY,
            PickupOrderModel::STATUS_COLLECT  => ExpressModel::STATUS_COLLECT,
            PickupOrderModel::STATUS_TRANSPORT => ExpressModel::STATUS_UNDERWAY,
            PickupOrderModel::STATUS_DELIVERY => ExpressModel::STATUS_DELIVERY,
            PickupOrderModel::STATUS_RECEIVED => ExpressModel::STATUS_RECEIVED,
            PickupOrderModel::STATUS_REFUSAL => ExpressModel::STATUS_REFUSAL,
            PickupOrderModel::STATUS_INTERCEPT => ExpressModel::STATUS_BLOCKED,
            PickupOrderModel::STATUS_CANCEL => ExpressModel::STATUS_BLOCKED
        ];

        return $map[$status] ?? ExpressModel::STATUS_EMPTY;
    }
}
