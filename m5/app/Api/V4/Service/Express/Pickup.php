<?php


namespace App\Api\V4\Service\Express;
use App\Api\Logic\Express\Pickup as PickupLogic;
use App\Api\Model\Express\PickupChannel as PickupChannelModel;
use App\Api\Model\Express\PickupOrder as PickupOrderModel;
use App\Api\Model\Express\PickupOrderItem as PickupOrderItemModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class Pickup
{

    public function __construct(){
        $this->PickupOrderItemModel = new PickupOrderItemModel();
        $this->PickupOrderModel = new PickupOrderModel();
        $this->PickupChannelModel = new PickupChannelModel();
    }

    /**
     * 上面取件下单
     * @param $params
     * @return array
     */
    public function savePickup($params):array {
        //检测当前订单是否已经生成
        $order = $this->PickupOrderModel->getPickupOrderByApplyNo($params['apply_no']);
        if (!empty($order)) {
            return $this->response(true, '', ['pickup_order_no' => $order['order_no']]);
        }

        //检测可用渠道
        $channelRes = $this->checkChannel($params['express_channel']);
        if (!$channelRes['status']) {
            return $channelRes;
        }

        //组装保存参数
        $orderData = $this->formatPickupOrderSaveData($params);

        //保存订单
        $res = $this->savePickupOrder($orderData);
        if (!$res['status']){
            return $res;
        }

        return $this->response(true, '', ['pickup_order_no' => $orderData['order_no']]);
    }

    /**
     * 获取上门取件详情
     * @param $orderNo
     * @param $applyNo
     * @return array
     */
    public function getPickupOrderInfo($orderNo = '', $applyNo = ''):array {
        if (!empty($orderNo)) {
            $order = $this->PickupOrderModel->getPickupOrderByOrderNo($orderNo);
        } else {
            $order = $this->PickupOrderModel->getPickupOrderByApplyNo($applyNo);
        }
        if (empty($order)) {
            return $this->response(false, '订单不存在');
        }

        //获取订单货品
        $orderItem = $this->PickupOrderItemModel->getPickupOrderItemList($order['id']);

        //组装数据
        $orderData = $this->formatPickupOrderData($order, $orderItem);

        return $this->response(true, '', $orderData);
    }


    /**
     * 取消上门取件
     * @param $orderNo
     * @param $applyNo
     * @return array
     */
    public function cancelPickupOrder($orderNo = '', $applyNo = ''):array {
        if (!empty($orderNo)) {
            $orderDetail = $this->PickupOrderModel->getPickupOrderByOrderNo($orderNo);
        } else {
            $orderDetail = $this->PickupOrderModel->getPickupOrderByApplyNo($applyNo);
        }
        if (empty($orderDetail)) {
            return $this->response(false, '订单不存在');
        }

        //调用订单取消接口
        DB::beginTransaction();
        try {
            //加锁
            $order = $this->PickupOrderModel->getOrderLock($orderDetail['id']);

            //订单已经取消
            if ($order['status'] == PickupOrderModel::STATUS_CANCEL) {
                return $this->response(true, '订单已取消', ['cancel_status' => 0]);
            }

            //订单收货不允许取消
            if ($order['status'] == PickupOrderModel::STATUS_RECEIVED) {
                return $this->response(false, '订单不允许取消');
            }

            if (!empty($order['third_order_no'])) {
                //调用第三方接口取消
                $pickupLogic = new PickupLogic($order['channel']);
                $result = $pickupLogic->cancelOrder($order['third_order_no'], $order['order_no'], '用户主动取消');
                if (!isset($result['Result']) || $result['Result'] != 'true'){
                    throw new \Exception('订单取消失败');
                }

                //订单取消状态
                if (in_array($result['Data']['cancel_status'], array(0, 1))) {
                    //设置订单状态为取消
                    $res = $this->PickupOrderModel->updatePickupOrderById($order['id'], ['status' => PickupOrderModel::STATUS_CANCEL, 'status_reason' => '订单取消', 'update_time' => time()]);
                    if (!$res) {
                        throw new \Exception('订单取消状态更新失败');
                    }
                }
                DB::commit();
                return $this->response(true, '', ['cancel_status' => $result['Data']['cancel_status']]);

            } else {
                //设置订单状态为取消
                $res = $this->PickupOrderModel->updatePickupOrderById($order['id'], ['status' => PickupOrderModel::STATUS_CANCEL, 'status_reason' => '订单取消', 'update_time' => time()]);
                if (!$res) {
                    throw new \Exception('订单取消状态更新失败');
                }

                DB::commit();
                return $this->response(true, '', ['cancel_status' => 0]);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->response(false, $e->getMessage());
        }

    }

    /**
     * 格式化订单保存数据
     * @param $orderParams
     * @return array
     */
    public function formatPickupOrderSaveData($orderParams):array {
        $order = [
            'channel' => $orderParams['express_channel'],
            'order_no' => $this->createOrderNo(),
            'apply_no' => $orderParams['apply_no'],
            'third_order_no' => '',
            'create_time' => time(),
            'business_no' => $orderParams['business_no'],
            'business_source' => $orderParams['business_source'],
            'sender_name' => $orderParams['sender_content']['name'],
            'sender_mobile' => $orderParams['sender_content']['mobile'],
            'sender_address' => $orderParams['sender_content']['address'],
            'receiver_name' => $orderParams['receiver_content']['name'],
            'receiver_mobile' => $orderParams['receiver_content']['mobile'],
            'receiver_address' => $orderParams['receiver_content']['address'],
        ];

        $orderItems = [];
        foreach ($orderParams['cargoes'] as $cargoes) {
            $orderItem = [
                'name' => $cargoes['name'],
                'number' => $cargoes['number'],
                'weight' => $cargoes['weight'] ?? 0.1,
                'volume' => $cargoes['volume'] ?? 0.1
            ];
            $orderItems[] = $orderItem;
        }

        $order['order_item'] = $orderItems;

        return $order;
    }


    /**
     * @param $order
     * @param $orderItem
     * @return array
     */
    public function formatPickupOrderData($order, $orderItem):array {
        $orderData = array(
            'express_channel' => $order['channel'],
            'order_no' => $order['order_no'],
            'status' => $order['status'],
            'status_txt' => PickupOrderModel::$statusMsg[$order['status']],
            'status_reason' => $order['status_reason'],
            'freight_price' => $order['freight_price'],
            'business_no' => $order['business_no'],
            'business_source' => $order['business_source'],
            'express_content' => [
                'express_no' => $order['express_no'],
                'express_com' => $order['express_com']
            ],
            'sender_content' => [
                'name' => $order['sender_name'],
                'mobile' => $order['sender_mobile'],
                'address' => $order['sender_address'],
            ],
            'receiver_content' => [
                'name' => $order['receiver_name'],
                'mobile' => $order['receiver_mobile'],
                'address' => $order['receiver_address'],
            ]
        );

        foreach ($orderItem as $item) {
            $orderData['cargoes'][] = [
                'name' => $item['name'],
                'number' => $item['number'],
                'weight' => $item['weight'],
                'volume' => $item['volume'],
            ];
        }

        return $orderData;
    }

    /**
     * @param $orderData
     * @return array
     */
    public function savePickupOrder($orderData) {

        $orderItemData = $orderData['order_item'];
        unset($orderData['order_item']);

        //开启事务
        Db::beginTransaction();

        $id = $this->PickupOrderModel->savePickupOrder($orderData);
        if (!$id) {
            Db::rollback();
            return $this->response(false, '保存订单失败');
        }

        foreach ($orderItemData as &$v) {
            $v['pickup_order_id'] = $id;
        }

        $res = $this->PickupOrderItemModel->savePickupItem($orderItemData);
        if (!$res) {
            Db::rollback();
            return $this->response(false, '保存订单货品失败');
        }

        Db::commit();
        return $this->response();
    }

    /**
     * 生成订单号
     * @return string
     */
    public function createOrderNo(){
        return  date('YmdHis') . rand(1000, 9999);
    }

    /**
     * 检测渠道
     * @param $channel
     * @return array
     */
    public function checkChannel($channel = ''):array {

        if (empty($channel)) {
            $res = $this->PickupChannelModel->getDefaultChannel();
        } else {
            $res = $this->PickupChannelModel->getChannel($channel);
        }

        if (empty($res)) {
            return $this->response(false, '不存在可用渠道');
        }

        return $this->response(true, '', array('channel' => $res['channel']));
    }

    /**
     * @param $order
     * @param $updateData
     * @return array
     */
    public function updatePickupDetail($order, $updateData) {

        //更新字段
        $updateFields = array('third_order_no', 'express_no', 'express_com', 'status', 'status_reason','freight_price', 'api_data', 'update_time');
        foreach ($updateFields as $field) {
            if(isset($updateData[$field]) && !empty($updateData[$field]) && $order[$field] != $updateData[$field]) {
                $update[$field] = $updateData[$field];
            }
        }

        if (empty($update)) {
            return $this->response();
        }

        //状态变更
        if (isset($update['status'])) {
            //检测状态变更是否合法
            $res = $this->checkOrderStatusChange($order['status'], $update['status'], $updateData);
            if (!$res['status']) {
                return $res;
            }
        }

        $pickupOrderModel = new PickupOrderModel();
        $res = $pickupOrderModel->updatePickupOrderById($order['id'], $update);
        if (!$res) {
            return $this->response(false, '更新订单失败');
        }
        return $this->response();
    }

    /**
     * 检测订单状态变更是否合法
     * @param $status
     * @param $newStatus
     * @param $data
     * @return array
     */
    public function checkOrderStatusChange($status, $newStatus, $validData = array()) {
        $transitions = [
            PickupOrderModel::STATUS_ORDERED => [PickupOrderModel::STATUS_DISTRIBUTE, PickupOrderModel::STATUS_COLLECT, PickupOrderModel::STATUS_TRANSPORT, PickupOrderModel::STATUS_DELIVERY, PickupOrderModel::STATUS_RECEIVED, PickupOrderModel::STATUS_REFUSAL, PickupOrderModel::STATUS_INTERCEPT, PickupOrderModel::STATUS_CANCEL, PickupOrderModel::STATUS_BLOCKED],
            PickupOrderModel::STATUS_DISTRIBUTE => [PickupOrderModel::STATUS_COLLECT, PickupOrderModel::STATUS_TRANSPORT, PickupOrderModel::STATUS_DELIVERY, PickupOrderModel::STATUS_RECEIVED, PickupOrderModel::STATUS_REFUSAL, PickupOrderModel::STATUS_INTERCEPT, PickupOrderModel::STATUS_CANCEL, PickupOrderModel::STATUS_BLOCKED],
            PickupOrderModel::STATUS_COLLECT => [PickupOrderModel::STATUS_TRANSPORT, PickupOrderModel::STATUS_DELIVERY, PickupOrderModel::STATUS_RECEIVED, PickupOrderModel::STATUS_REFUSAL, PickupOrderModel::STATUS_INTERCEPT, PickupOrderModel::STATUS_CANCEL, PickupOrderModel::STATUS_BLOCKED],
            PickupOrderModel::STATUS_TRANSPORT => [PickupOrderModel::STATUS_DELIVERY, PickupOrderModel::STATUS_RECEIVED, PickupOrderModel::STATUS_REFUSAL, PickupOrderModel::STATUS_INTERCEPT, PickupOrderModel::STATUS_CANCEL, PickupOrderModel::STATUS_BLOCKED],
            PickupOrderModel::STATUS_DELIVERY => [PickupOrderModel::STATUS_RECEIVED, PickupOrderModel::STATUS_REFUSAL, PickupOrderModel::STATUS_INTERCEPT, PickupOrderModel::STATUS_CANCEL, PickupOrderModel::STATUS_BLOCKED],
            PickupOrderModel::STATUS_RECEIVED => [],
            PickupOrderModel::STATUS_REFUSAL => [],
            PickupOrderModel::STATUS_INTERCEPT => [],
            PickupOrderModel::STATUS_CANCEL => [],
            PickupOrderModel::STATUS_BLOCKED => [],
        ];
        if (!isset($transitions[$status]) || !in_array($newStatus, $transitions[$status])) {
            return $this->response(false, '状态变更不合法');
        }
        //校验参数
        switch ($newStatus) {
            case PickupOrderModel::STATUS_COLLECT:
            case PickupOrderModel::STATUS_TRANSPORT:
            case PickupOrderModel::STATUS_DELIVERY:
            case PickupOrderModel::STATUS_RECEIVED:
            $validator = Validator::make((array)$validData, [
                'third_order_no' => 'required|string',
                'express_no' => 'required|string',
                'express_com' => 'required|string',
                'freight_price' => 'required|numeric|gt:0',
            ]);

            if ($validator->fails()) {
               return $this->response(false, $validator->errors());
            }
            break;
        }
        return $this->response();
    }
    /**
     * @param $status
     * @param $msg
     * @param $data
     * @return array
     */
    public function response($status = true, $msg = '', $data = array()) :array {
        return array(
            'status' => $status,
            'msg' => $msg,
            'data' => $data
        );
    }
}
