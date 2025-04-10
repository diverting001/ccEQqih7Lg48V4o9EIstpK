<?php

namespace App\Api\V1\Controllers;

use App\Api\Common\Controllers\BaseController;
use App\Api\V1\Service\Order\Concurrent as Concurrent;
use App\Api\V1\Service\Order\Create as OrderCreate;
use App\Api\V1\Service\Order\Confirm as OrderConfirm;
use App\Api\V1\Service\Order\Push as OrderPush;
use App\Api\V1\Service\Order\Pay as OrderPay;
use App\Api\V1\Service\Order\Order as OrderOrder;
use App\Api\V1\Service\Order\Cancel as OrderCancel;
use App\Api\V1\Service\Order\Refund as OrderRefund;
use App\Api\V1\Service\Order\Complete as OrderComplete;
use App\Api\V1\Service\Order\Pause as OrderPause;
use App\Api\V1\Service\Order\SplitOrder as SplitOrderService;
use App\Api\Model\Order\Order as OrderModel;
use App\Api\Logic\Mq as Mq;
use Illuminate\Http\Request;

class OrderController extends BaseController
{

    /*
     * @todo 创建订单
     */
    public function Create(Request $request)
    {
        $order_data = $this->getContentArray($request);
        //订单号发配
        $server_concurrent = new Concurrent();
        //临时订单号
        $temp_order_id = $order_data['temp_order_id'];
        if (!empty($temp_order_id)) {
            //检查临时订单号是否可用
            if (!$server_concurrent->CherckOrderId($temp_order_id)) {
                $this->setErrorMsg('临时订单号错误');
                return $this->outputFormat([], 400);
            }
        } else {
            $temp_order_id = $server_concurrent->GetOrderId();
        }
        //是否已进行预拆单
        if (!isset($order_data['split_id'])) {
            $split_id = '';
        } else {
            $split_id = $order_data['split_id'];
        }
//        $split_id   = 1;
        //创建订单
        $create_order_data = array(
            'order_id' => $temp_order_id,
            //订单号
            'member_id' => $order_data['member_id'],
            //用户id
            'company_id' => $order_data['company_id'],
            //公司id
            'pmt_amount' => $order_data['pmt_amount'],
            // 优惠金额(满减券+免税券)
            'point_amount' => $order_data['point_amount'],
            // 积分支付金额
            'ship_name' => $order_data['ship_name'],
            //收货人姓名
            'ship_addr' => $order_data['ship_addr'],
            //收货人详情地址
            'ship_zip' => $order_data['ship_zip'],
            //收货人邮编
            'ship_tel' => $order_data['ship_tel'],
            //收货人电话
            'ship_mobile' => $order_data['ship_mobile'],
            //收货人手机号
            'ship_province' => $order_data['ship_province'],
            //收货人所在省
            'ship_city' => $order_data['ship_city'],
            //收货人所在市
            'ship_county' => $order_data['ship_county'],
            //收货人所在县
            'ship_town' => $order_data['ship_town'],
            //收货人所在镇
            'idcardname' => $order_data['idcardname'],
            //收货人证件类型 (身份证)
            'idcardno' => $order_data['idcardno'],
            //收货人证件号 (身份证号)
            'terminal' => $order_data['terminal'],
            //平台来源PC|手机
            'point_channel' => $order_data['point_channel'],
            //使用积分类型
            'anonymous' => $order_data['anonymous'],
            //匿名下单 yes|no
            'receive_mode' => empty($order_data['receive_mode']) ? 1 : intval($order_data['receive_mode']),
            //收货方式
            'memo' => empty($order_data['memo']) ? '' : $order_data['memo'],
            //订单附言
            'payment_restriction' => $order_data['payment_restriction'],
            //支付方式限制
            'business_code' => empty($order_data['business_code']) ? '' : $order_data['business_code'],
            //内购业务关系编码
            'extend_info_code' => empty($order_data['extend_info_code']) ? '' : $order_data['extend_info_code'],
            //业务扩展类型
            'order_category' => empty($order_data['order_category']) ? '' : $order_data['order_category'],
            //功能列表划分  (电商订单、福利、体检等）
            'business_project_code' => empty($order_data['business_project_code']) ? '' : $order_data['business_project_code'],
            //内购业务项目编码
            'system_code' => empty($order_data['system_code']) ? '' : $order_data['system_code'],
            //业务系统（内购会、积分宝等）
            'extend_data' => is_array($order_data['extend_data']) ? json_encode($order_data['extend_data']) : $order_data['extend_data'],
            //扩展数据（json）
            'split_id' => $split_id,
            //拆单结果
            'channel' => $order_data['channel'],
            //下单渠道
            'lock_source' => $order_data['lock_source'],
            //库存锁定来源
            'preorder_order' => isset($order_data['preorder_order']) ? $order_data['preorder_order'] : array(),
            //复用预下订单
            'project_code' => $order_data['project_code'],
            'freight_price' => $order_data['feright_amount'],
        );
        if (isset($order_data['extend_deliver_area'])) {
            $create_order_data['extend_deliver_area'] = $order_data['extend_deliver_area']; //mryx详细地址
        }

        if (isset($order_data['extend_send_time'])) {
            $create_order_data['extend_send_time'] = $order_data['extend_send_time']; //mryx配送时间
        }
        $order_service = new OrderCreate();
        $res = $order_service->Create($create_order_data);
        if ($res['error_code'] != 200) {
            $this->setErrorMsg($res['error_msg']);
            return $this->outputFormat($res['data'], $res['error_code']);
        } else {
            $this->setErrorMsg('创建成功');
            return $this->outputFormat(['order_id' => $temp_order_id]);
        }
    }


    /*
     * @todo 订单支付
     */
    public function DoPay(Request $request)
    {
        $order_data = $this->getContentArray($request);
        if (empty($order_data)) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }
        if (empty($order_data['order_id'])) {
            $this->setErrorMsg('订单号不能为空');
            return $this->outputFormat([], 400);
        }
        $extend_data = empty($order_data['extend_data']) ? [] : (!is_array($order_data['extend_data']) ? [$order_data['extend_data']] : $order_data['extend_data']);
        $pay_order_data = [
            'order_id' => $order_data['order_id'],
            'pay_name' => $order_data['pay_name'],
            'trade_no' => $order_data['trade_no'],
            'pay_time' => $order_data['pay_time'],
            'payment_id' => $order_data['payment_id'],
            'payment_system' => $order_data['payment_system'],
            'pay_money' => $order_data['pay_money'],
            'extend_data' => json_encode($extend_data),
        ];
        $order_service = new OrderPay();
        $res = $order_service->DoPay($pay_order_data);
        if ($res['error_code'] != 200) {
            $this->setErrorMsg($res['error_msg']);
            return $this->outputFormat([], 500);
        } else {
            //非拼图订单正常确认发货
            $order_info = OrderModel::GetOrderInfoById($pay_order_data['order_id']);
            if ($order_info->order_category != 'pintuan') {
                //支付方式为credit/mcredit,跳过自动确认
                if (in_array($order_info->payment, ['credit', 'mcredit'])) {
                    $this->setErrorMsg('支付成功');
                    return $this->outputFormat([]);
                }

                //主单支付时,若有子单将子单的confirm_status置为已确认
                $split_order_list = OrderModel::GetSplitOrderByRootPId($order_info->root_pid);
                if (!empty($split_order_list)) {
                    foreach ($split_order_list as $split_order) {
                        if ($split_order->memo != '' && PSR_PAUSE_REMARK_ORDER == 1) {
                            OrderModel::addInterceptOrder($split_order->order_id);
                            continue;
                        } else {
                            $res = OrderModel::OrderConfirm(['order_id' => $split_order->order_id]);
                            if (!$res) {
                                \Neigou\Logger::Debug('pay_order_confirm_split_order',
                                    array('bn' => json_encode($split_order->order_id)));
                            }
                        }
                    }
                }

                //如果主单直接是拦截订单,跳过确认
                if ($order_info->memo != '' && PSR_PAUSE_REMARK_ORDER == 1) {
                    OrderModel::addInterceptOrder($order_info->order_id);
                } else {
                    $order_confirm_service = new OrderConfirm();
                    $confirm_order_data = [
                        'order_id' => $order_data['order_id']
                    ];
                    $order_confirm_service->Confirm($confirm_order_data);
                    \Neigou\Logger::Debug('pay_order_confirm', array('bn' => json_encode($order_info)));
                }
            }
            $this->setErrorMsg('支付成功');
            return $this->outputFormat([]);
        }
    }

    /*
     * @todo 取消订单
     */
    public function Cancel(Request $request)
    {
        $order_data = $this->getContentArray($request);
        if (empty($order_data)) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }
        if (empty($order_data['order_id'])) {
            $this->setErrorMsg('订单号不能为空');
            return $this->outputFormat([], 400);
        }
        $order_service = new OrderCancel();
        $cancel_order_data = [
            'order_id' => $order_data['order_id']
        ];
        $res = $order_service->Cancel($cancel_order_data);
        if ($res['error_code'] != 200) {
            $this->setErrorMsg($res['error_msg']);
            return $this->outputFormat([], 500);
        } else {
            $this->setErrorMsg('取消成功');
            return $this->outputFormat([]);
        }
    }

    /*
     * @todo 订单详情
     */
    public function GetOrderInfo(Request $request)
    {
        $order_data = $this->getContentArray($request);
        $request_data = [];
        $order_data['order_id'] = (string)$order_data['order_id'];
        if (!empty($order_data['order_id'])) {
            $request_data['filter'] = array(
                'order_id' => $order_data['order_id']
            );
        }
        if (!empty($order_data['wms_order_bn']) && !empty($order_data['wms_code'])) {
            $request_data['filter'] = array(
                'wms_order_bn' => $order_data['wms_order_bn'],
                'wms_code' => $order_data['wms_code']
            );
        }
        if (!empty($order_data['wms_delivery_bn']) && !empty($order_data['wms_code'])) {
            $request_data['filter'] = array(
                'wms_delivery_bn' => $order_data['wms_delivery_bn'],
                'wms_code' => $order_data['wms_code']
            );
        }
        //支付单
        $filter_data = [];
        if (!empty($order_data['payment_id'])) {
            $filter_data['payment_id'] = $order_data['payment_id'];
        }
        //外部交易号查询订单信息
        if (!empty($order_data['trade_no'])) {
            $filter_data['trade_no'] = $order_data['trade_no'];
        }

        if (!empty($filter_data)) {
            $request_data['filter'] = $filter_data;
        }

        if (empty($request_data)) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }
        $output_format = empty($order_data['output_format']) ? 'valid_split_order' : trim($order_data['output_format']);
        $request_data['output_format'] = $output_format;
        $order_service = new OrderOrder();
        $order_info = $order_service->GetOrderInfo($request_data);
        if (empty($order_info)) {
            $this->setErrorMsg('订单不存在');
            return $this->outputFormat([], 401);
        } else {
            $this->setErrorMsg('获取成功');
            return $this->outputFormat($order_info);
        }
    }

    /**
     * 订单列表
     */
    public function GetOrderListForMis(Request $request)
    {
        $where_data = $this->getContentArray($request);
        //limit限制
        $page_size = empty($where_data['page_size']) ? 20 : intval($where_data['page_size']);
        $page_index = $where_data['page_index'] < 1 ? 1 : intval($where_data['page_index']);
        //1:已完成订单,2:拦截订单
        $filter_type = empty($where_data['filter_type']) ? 1 : intval($where_data['filter_type']);

        $filter = [];
        if (isset($where_data['order_id'])) {
            $filter['order_id'] = $where_data['order_id'];
        }

        $order_list = [];
        $total = 0;
        $order_service = new OrderOrder();
        $results = $order_service->GetOrderListForMis($filter_type, $filter, $page_index, $page_size, true);

        if (!empty($results)) {
            $order_list = $results['order_list'];
            $total = $results['total'];
        }

        if ($total > 0) {
            $this->setErrorMsg('获取成功');
            return $this->outputFormat(['order_list' => $order_list, 'total' => $total]);
        } else {
            $this->setErrorMsg('未获取订单');
            return $this->outputFormat([], 400);
        }
    }

    /**
     * 获取订单履约消息列表
     */
    public function GetWmsOrderMsgList(Request $request)
    {
        $where_data = $this->getContentArray($request);
        //limit限制
        $page_size = empty($where_data['page_size']) ? 20 : intval($where_data['page_size']);
        $page_index = $where_data['page_index'] < 1 ? 1 : intval($where_data['page_index']);


        $filter = [];
        if (isset($where_data['order_id'])) {
            $filter['order_id'] = $where_data['order_id'];
        }

        if (isset($where_data['msg_status'])) {
            $filter['msg_status'] = $where_data['msg_status'];
        } else {
            $filter['msg_status'] = 0;
        }
        if (isset($where_data['msg_type'])) {
            $filter['msg_type'] = $where_data['msg_type'];
        }
        if (isset($where_data['msg_wms_order_status'])) {
            $filter['msg_wms_order_status'] = $where_data['msg_wms_order_status'];
        }

        $order_list = [];
        $total = 0;
        $order_service = new OrderOrder();
        $results = $order_service->GetWmsOrderMsgList($filter, $page_index, $page_size, true);

        if (!empty($results)) {
            $order_list = $results['order_list'];
            $total = $results['total'];
        }

        $this->setErrorMsg('获取成功');
        return $this->outputFormat(['order_list' => $order_list, 'total' => $total]);
    }

    /**
     * 获取消息统计
     */
    public function GetMsgStats()
    {
        $order_service = new OrderOrder();
        $result = $order_service->GetMsgStats();
        $intercept_order_count = $result['intercept_order_count'];
        $exception_order_msg_count = $result['exception_order_msg_count'];

        $this->setErrorMsg('获取成功');
        return $this->outputFormat([
            'exception_order_msg_count' => $exception_order_msg_count,
            'intercept_order_count' => $intercept_order_count
        ]);
    }


    /*
     * @todo 订单列表
     */
    public function GetOrderList(Request $request)
    {
        $where_data = $this->getContentArray($request);
        if (empty($where_data)) {
            $this->setErrorMsg('请选择筛选条件');
            return $this->outputFormat([], 400);
        }
        $where = [];
        //公司
        if (!empty($where_data['company_id'])) {
            $where['company_id'] = ($where_data['company_id']);
        }
        //用户
        if (!empty($where_data['member_id'])) {
            $where['member_id'] = ($where_data['member_id']);
        }
        //订单平台
        if (!empty($where_data['system_code'])) {
            $where['system_code'] = trim($where_data['system_code']);
        }
        //收货人手机号
        if (!empty($where_data['ship_mobile'])) {
            $where['ship_mobile'] = trim($where_data['ship_mobile']);
        }
        //收货人姓名
        if (!empty($where_data['ship_name'])) {
            $where['ship_name'] = trim($where_data['ship_name']);
        }
        //订单状态
        if (!empty($where_data['status']) && in_array($where_data['status'], array(1, 2, 3))) {
            $where['status'] = intval($where_data['status']);
        }
        if (!empty($where_data['pay_status'])) {
            $where['pay_status'] = intval($where_data['pay_status']);
        }
        if (!empty($where_data['confirm_status'])) {
            $where['confirm_status'] = intval($where_data['confirm_status']);
        }
        if (!empty($where_data['ship_status'])) {
            $where['ship_status'] = intval($where_data['ship_status']);
        }
        if (!empty($where_data['create_time'])) {
            $where['create_time'] = ($where_data['create_time']);
        }
        if (!empty($where_data['order_id'])) {
            $where['order_id'] = ($where_data['order_id']);
        }
        if (!empty($where_data['pay_time'])) {
            $where['pay_time'] = ($where_data['pay_time']);
        }
        if (!empty($where_data['create_source'])) {
            $where['create_source'] = $where_data['create_source'];
        }
        // 店铺
        if (!empty($where_data['pop_owner_id'])) {
            $where['pop_owner_id'] = $where_data['pop_owner_id'];
        }

        //仓库
        if (!empty($where_data['warehouse_name'])) {
            $where['warehouse_name'] = $where_data['warehouse_name'];
        }

        //支付单
        $filter_data = [];
        if (!empty($where_data['payment_id'])) {
            $filter_data['payment_id'] = $where_data['payment_id'];
        }
        //外部交易号查询订单信息
        if (!empty($where_data['trade_no'])) {
            $filter_data['trade_no'] = $where_data['trade_no'];
        }

        $order_service = new OrderOrder();
        if (!empty($filter_data)) {
            $order_ids = $order_service->GetOrderIdsPayLogByParams($filter_data);
            if (!empty($order_ids)) {
                $where['order_id'] = $order_ids;
            }
        }

        //limit限制
        $page_size = empty($where_data['page_size']) ? 20 : intval($where_data['page_size']);
        $page_index = $where_data['page_index'] < 1 ? 1 : intval($where_data['page_index']);

        //输出格式
        $output_format = empty($where_data['output_format']) ? 'valid_split_order' : trim($where_data['output_format']);
        $request_data = [
            'filter' => $where,
            'page_index' => $page_index,
            'page_size' => $page_size,
            'output_format' => $output_format
        ];

        //print_r($request_data);die;
        //订单
        $order_list = $order_service->GetOrderList($request_data);
        $total = $order_service->GetOrderTotal($request_data['filter']);
        if (empty($total)) {
            $this->setErrorMsg('未获取订单');
            return $this->outputFormat([], 400);
        } else {
            $this->setErrorMsg('获取成功');
            return $this->outputFormat(['order_list' => $order_list, 'total' => $total]);
        }
    }

    /*
     * @todo 分配一个订单号
     */
    public function GenOrderId()
    {
        //订单号发配
        $server_concurrent = new Concurrent();
        $order_id = $server_concurrent->GetOrderId();
        $this->setErrorMsg('成功');
        return $this->outputFormat(['order_id' => $order_id]);
    }


    public function PushOrder(Request $request)
    {
        $order_data_list = $this->getContentArray($request);
        if (empty($order_data_list)) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }
        $return_data = [];
        foreach ($order_data_list as $order_data) {
            $order_info = OrderModel::GetOrderInfoById($order_data['order_id']);
            $order_service = new OrderPush();
            if ($order_info) {
                $res = $order_service->UpdateOrder($order_data['order_id']);
            } else {
                $res = $order_service->Push($order_data);
            }
            if ($res) {
                $return_data['success'][] = array(
                    'order_id' => $order_data['order_id'],
                    'msg' => '成功'
                );
            } else {
                $errormsg = empty($order_service->error) ? $order_data['order_id'] . '==订单推送失败' : $order_data['order_id'] . '==' . $order_service->error;
                $return_data['fail'][] = [
                    'order_id' => $order_data['order_id'],
                    'msg' => $errormsg
                ];
            }
        }
        $this->setErrorMsg('获取成功');
        return $this->outputFormat($return_data);

    }


    /*
     * @todo 履约平台订单查询
     */
    public function GetWmsOrder(Request $request)
    {
        $where_data = $this->getContentArray($request);
        if (empty($where_data['wms_code']) || empty($where_data['wms_order_bn'])) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }
        $order_info = OrderModel::GetWmsOrder($where_data['wms_code'], $where_data['wms_order_bn']);
        if (empty($order_info)) {
            $this->setErrorMsg('订单不存在');
            return $this->outputFormat([], 401);
        }
        $this->setErrorMsg('请求成功');
        return $this->outputFormat(['order_id' => $order_info->order_id, 'root_pid' => $order_info->root_pid]);
    }

    /*
     * todo 订单支付日志查询
     */
    public function GetOrderPaymentList(Request $request)
    {
        $where_data = $this->getContentArray($request);
        if (empty($where_data['order_id_list'])) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }
        $order_service = new OrderOrder();
        $order_payment_list = $order_service->GetOrderPayment($where_data['order_id_list']);
        if (empty($order_payment_list)) {
            $this->setErrorMsg('未获取订单支付记录');
            return $this->outputFormat([], 400);
        } else {
            $this->setErrorMsg('获取成功');
            return $this->outputFormat($order_payment_list);
        }
    }

    /*
     * @todo 订单确认向履约平台下单
     */
    public function OrderConfirm(Request $request)
    {
        $order_data = $this->getContentArray($request);
        if (empty($order_data['order_id'])) {
            $this->setErrorMsg('订单号不能为空');
            return $this->outputFormat([], 400);
        }
        $order_service = new OrderConfirm();
        $confirm_order_data = [
            'order_id' => $order_data['order_id']
        ];
        $res = $order_service->Confirm($confirm_order_data);
        if ($res['error_code'] != 200) {
            $this->setErrorMsg($res['error_msg']);
            return $this->outputFormat([], 500);
        } else {
            $this->setErrorMsg('确认成功');
            return $this->outputFormat([]);
        }
    }

    /*
     * @todo 订单更新消息
     */
    public function OrderUpdateMessage(Request $request)
    {
        $order_data = $this->getContentArray($request);
        if (empty($order_data['wms_code']) || empty($order_data['wms_order_bn'])) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }
        Mq::OrderUpdate($order_data['wms_order_bn'], $order_data['wms_code']);
        $this->setErrorMsg('更新成功');
        return $this->outputFormat([]);
    }

    /*
     * @todo 更新履约订单消息到订单服务
     */
    public function UpdateWmsOrderMsg(Request $request)
    {
        $order_data = $this->getContentArray($request);
        if (empty($order_data['wms_code']) || (empty($order_data['wms_order_bn']) && empty($order_data['wms_delivery_bn']))) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }

        $order_service = new OrderOrder();
        if (isset($order_data['wms_delivery_bn'])) {
            $request_data['filter'] = array(
                'wms_delivery_bn' => $order_data['wms_delivery_bn'],
                'wms_code' => $order_data['wms_code']
            );
            $order_info = $order_service->GetOrderInfo($request_data);
            if (empty($order_info)) {
                $request_data['filter'] = array(
                    'wms_order_bn' => $order_data['wms_order_bn'],
                    'wms_code' => $order_data['wms_code']
                );
                $order_info = $order_service->GetOrderInfo($request_data);
                if (empty($order_info)) {
                    $this->setErrorMsg('发货单不存在，履约信息未找到!');
                    return $this->outputFormat([], 403);
                }
            }
        } else {
            $request_data['filter'] = array(
                'wms_order_bn' => $order_data['wms_order_bn'],
                'wms_code' => $order_data['wms_code']
            );
            $order_info = $order_service->GetOrderInfo($request_data);
            if (empty($order_info)) {
                $this->setErrorMsg('履约信息未找到');
                return $this->outputFormat([], 401);
            }
        }

        $update_data = [
            'order_id' => $order_info['order_id'],
            'pid' => $order_info['pid'],
            'root_pid' => $order_info['root_pid'],
            'wms_code' => $order_data['wms_code'],
            'wms_order_bn' => isset($order_data['wms_order_bn']) ? $order_data['wms_order_bn'] : '',
            'wms_delivery_bn' => isset($order_data['wms_delivery_bn']) ? $order_data['wms_delivery_bn'] : '',
            'wms_order_status' => isset($order_data['wms_order_status']) ? $order_data['wms_order_status'] : '',
            'wms_msg' => isset($order_data['wms_msg']) ? $order_data['wms_msg'] : '',
            'type' => isset($order_data['type']) ? $order_data['type'] : '1',
        ];

        $order_service->UpdateWmsOrderMsg($update_data);
        $this->setErrorMsg('更新成功');
        return $this->outputFormat([]);
    }

    /**
     * 更新订单履约消息状态
     */
    public function UpdateWmsOrderMsgStatusById(Request $request)
    {
        $req_data = $this->getContentArray($request);
        if (empty($req_data['id'])) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }

        $status = isset($req_data['status']) ? $req_data['status'] : 1;//消息状态:0->未读，1->已忽略，2->已处理

        $operator = isset($req_data['operator']) ? $req_data['operator'] : '';
        $reason = isset($req_data['reason']) ? $req_data['reason'] : '';

        $order_service = new OrderOrder();
        $order_service->UpdateWmsOrderMsgStatusById($req_data['id'], $status, $operator, $reason);
        $this->setErrorMsg('更新成功');
        return $this->outputFormat([]);
    }

    /**
     * 申请订单暂停
     */
    public function Pause(Request $request)
    {
        $req_data = $this->getContentArray($request);
        if (empty($req_data['order_id'])) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }

        $order_id = $req_data['order_id'];//'申请状态:1->申请暂停中、2->暂停成功、3->暂停失败'

        $order_service = new OrderOrder();
        $msg = '';
        $process_result = $order_service->Pause($order_id, $msg);
        if ($process_result) {
            $this->setErrorMsg('暂停成功');
            return $this->outputFormat([]);
        } else {
            $msg = empty($msg) ? '暂停失败' : $msg;
            $this->setErrorMsg($msg);
            return $this->outputFormat([], 501);
        }
    }

    /**
     * 已支付未完成订单取消
     */
    public function OrderPayedCancel(Request $request)
    {
        $req_data = $this->getContentArray($request);
        if (empty($req_data['order_id'])) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }

        $order_id = $req_data['order_id'];

        $order_service = new OrderOrder();
        $msg = '';
        $process_result = $order_service->OrderPayedCancel($order_id, $msg);
        if (!$process_result) {
            $msg = empty($msg) ? '取消订单失败' : $msg;
            $this->setErrorMsg($msg);
            return $this->outputFormat([], 501);
        }
        $this->setErrorMsg('取消订单成功');
        return $this->outputFormat([]);
    }

    /**
     * 撤销订单For Mis
     */
    public function CancelOrderForMis(Request $request)
    {
        $req_data = $this->getContentArray($request);
        if (empty($req_data['order_id'])) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }

        $order_id = $req_data['order_id'];

        $order_service = new OrderOrder();
        $msg = '';
        $process_result = $order_service->CancelOrderForMis($order_id, $msg);

        if ($process_result) {
            $this->setErrorMsg('撤销成功');
            return $this->outputFormat([]);
        } else {
            $msg = empty($msg) ? '撤销失败' : $msg;
            $this->setErrorMsg($msg);
            return $this->outputFormat([], 501);
        }
    }

    /**
     * 获取暂停订单申请
     */
    public function GetPauseList(Request $request)
    {
        $where_data = $this->getContentArray($request);
        //limit限制
        $page_size = empty($where_data['page_size']) ? 20 : intval($where_data['page_size']);
        $page_index = $where_data['page_index'] < 1 ? 1 : intval($where_data['page_index']);


        $filter = [];
        if (isset($where_data['order_id'])) {
            $filter['order_id'] = $where_data['order_id'];
        }

        $order_list = [];
        $total = 0;
        $order_service = new OrderOrder();
        $results = $order_service->GetPauseList($filter, $page_index, $page_size, true);

        if (!empty($results)) {
            $order_list = $results['order_list'];
            $total = $results['total'];
        }

        if ($total > 0) {
            $this->setErrorMsg('获取成功');
            return $this->outputFormat(['order_list' => $order_list, 'total' => $total]);
        } else {
            $this->setErrorMsg('无数据');
            return $this->outputFormat([], 400);
        }
    }

    /**
     * 暂停订单重新履约
     */
    public function RetryWms(Request $request)
    {
        $req_data = $this->getContentArray($request);
        if (empty($req_data['order_id'])) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }

        $order_id = $req_data['order_id'];

        //1.确认订单是否为暂停订单
        $OrderPauseService = new OrderPause();
        $msg = '';
        $process_result = $OrderPauseService->RetryWms($order_id, $msg);
        if ($process_result) {
            $this->setErrorMsg('处理成功');
            return $this->outputFormat([]);
        } else {
            $msg = empty($msg) ? '处理失败' : $msg;
            $this->setErrorMsg($msg);
            return $this->outputFormat([], 501);
        }
    }

    /**
     * 订单分拆(余单发货)
     */
    public function SplitOrder(Request $request)
    {
        $req_data = $this->getContentArray($request);
        if (empty($req_data['order_id'])) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }

        $order_id = $req_data['order_id'];
        $product_list = $req_data['product_list'];


        //检查订单状态、订单信息
        $order_info = OrderModel::GetOrderInfoById($order_id);
        if ($order_info->hung_up_status != 1 && $order_info->confirm_status != 1) {
            $this->setErrorMsg('非暂停订单且非拦截订单不能拆分');
            return $this->outputFormat([], 403);
        } elseif ($order_info->split != 1) {
            $this->setErrorMsg('已拆分订单不能重复拆分');
            return $this->outputFormat([], 503);
        } else {
            //
        }

        //检查分拆商品有效性
        $order_items = OrderModel::GetOrderItemsByOrderId($order_id);

        $SplitOrderService = new SplitOrderService();

        $response = $SplitOrderService->checkProductList($product_list, $order_items);
        if ($response['error_code'] != 200) {
            $this->setErrorMsg($response['error_msg']);
            return $this->outputFormat([], $response['error_code']);
        }
        $response = $SplitOrderService->saveSplitOrders($order_info, $order_items, $product_list);

        if ($response['error_code'] != 200) {
            $this->setErrorMsg($response['error_msg']);
            return $this->outputFormat([], $response['error_code']);
        }

        //拆分成功，原始订单从拦截列表清除
        OrderModel::delInterceptOrder($order_id);
        //拆分成功,生效的子单显示到拦截列表
        if (!empty($response['data'])) {
            //生效的订单需要确认,取消的订单直接取消
            if (!empty($response['data']['valid'])) {
                foreach ($response['data']['valid'] as $new_order_id) {
                    OrderModel::addInterceptOrder($new_order_id);
                }
            }
        }

        return $this->outputFormat($response['data']);
    }

    /**
     * 拦截订单确认履约
     */
    public function InterceptRecovery(Request $request)
    {
        $req_data = $this->getContentArray($request);
        if (empty($req_data['order_id'])) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }

        $order_id = $req_data['order_id'];

        $order_service = new OrderOrder();
        $msg = '';
        $process_result = $order_service->InterceptRecovery($order_id, $msg);
        if ($process_result) {
            $this->setErrorMsg('处理成功');
            return $this->outputFormat([]);
        } else {
            $msg = empty($msg) ? '处理失败' : $msg;
            $this->setErrorMsg($msg);
            return $this->outputFormat([], 501);
        }
    }

    /**
     * 订单信息更新
     */
    public function Update(Request $request)
    {
        $req_data = $this->getContentArray($request);
        if (empty($req_data['order_id'])) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }

        $update_data = [];
        if (isset($req_data['logi_code'])) {
            $update_data['logi_code'] = $req_data['logi_code'];
        }
        if (isset($req_data['logi_no'])) {
            $update_data['logi_no'] = $req_data['logi_no'];
        }

        if (isset($req_data['ship_mobile'])) {
            $update_data['ship_mobile'] = $req_data['ship_mobile'];
        }

        if (isset($req_data['ship_name'])) {
            $update_data['ship_name'] = $req_data['ship_name'];
        }

        if (isset($req_data['ship_province'])) {
            $update_data['ship_province'] = $req_data['ship_province'];
        }
        if (isset($req_data['ship_city'])) {
            $update_data['ship_city'] = $req_data['ship_city'];
        }
        if (isset($req_data['ship_county'])) {
            $update_data['ship_county'] = $req_data['ship_county'];
        }
        if (isset($req_data['ship_town'])) {
            $update_data['ship_town'] = $req_data['ship_town'];
        }
        if (isset($req_data['ship_addr'])) {
            $update_data['ship_addr'] = $req_data['ship_addr'];
        }

        if (!empty($update_data)) {
            $order_service = new OrderOrder();
            $order_service->UpdateById($req_data['order_id'], $update_data);
        }
        $this->setErrorMsg('更新成功');
        return $this->outputFormat([]);
    }

    /*
     * @todo 订单退款申请
     */
    public function RefundApply()
    {

    }

    /*
     * @todo 订单退款确认
     */
    public function RefundConfirm(Request $request)
    {
        $order_data = $this->getContentArray($request);
        if (empty($order_data)) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }
        if (empty($order_data['order_id'])) {
            $this->setErrorMsg('订单号不能为空');
            return $this->outputFormat([], 400);
        }
        $extend_data = empty($order_data['extend_data']) ? [] : (!is_array($order_data['extend_data']) ? [$order_data['extend_data']] : $order_data['extend_data']);
        $refund_order_data = [
            'order_id' => $order_data['order_id'],
            'refund_name' => trim($order_data['refund_name']),
            'trade_no' => trim($order_data['trade_no']),
            'refund_time' => trim($order_data['refund_time']),
            'refund_id' => trim($order_data['refund_id']),
            'refund_system' => trim($order_data['refund_system']),
            'refund_money' => $order_data['refund_money'],
            'extend_data' => json_encode($extend_data),
        ];
        $order_service = new OrderRefund();
        $res = $order_service->RefundConfirm($refund_order_data);
        if ($res['error_code'] != 200) {
            $this->setErrorMsg($res['error_msg']);
            return $this->outputFormat([], 500);
        } else {
            $this->setErrorMsg('退款成功');
            return $this->outputFormat([]);
        }
    }

    /*
     * @todo 超时支付重新抛单
     */
    public function TimeoutPayOrderReTry(Request $request)
    {
        $request_data = $this->getContentArray($request);
        if (!isset($request_data['order_id'])) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }
        $order_id = $request_data['order_id'];
        if (!empty($order_id)) {
            //检查临时订单号是否可用
            $order_service = new OrderOrder();
            $res = $order_service->TimeoutPayOrderReTry($order_id);
            if ($res) {
                Mq::OrderConfirm($order_id);
                $this->setErrorMsg('成功');
                return $this->outputFormat([]);
            } else {
                $this->setErrorMsg('处理失败');
                return $this->outputFormat([], 503);
            }
        }
        $this->setErrorMsg('订单不存在');
        return $this->outputFormat([], 401);
    }


    /*
     * @todo 订单完成
     */
    public function PayOrderCompleteByOrderId(Request $request)
    {
        $request_data = $this->getContentArray($request);
        if (!isset($request_data['order_id'])) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }
        $order_id = $request_data['order_id'];

        if (!empty($order_id)) {
            //检查临时订单号是否可用
            $order_service = new OrderComplete();
            $msg = '';
            $res = $order_service->CompleteOrderByOrderId($order_id, $msg);
            if ($res) {
                $this->setErrorMsg('成功');
                return $this->outputFormat([]);
            } else {
                $this->setErrorMsg($msg);
                return $this->outputFormat([], 503);
            }
        }
        $this->setErrorMsg('订单不存在');
        return $this->outputFormat([], 401);
    }

    /** 获取公司订单统计信息
     *
     * @param Request $request
     * @return array
     * @author liuming
     */
    public function getCompanyOrderStatistics(Request $request){
        $requestData = $this->getContentArray($request);

        // 参数检查
        if (empty($requestData['company_id'])){
            $this->setErrorMsg('参数错误 : company_id不能为空');
            return $this->outputFormat([], 400);
        }

        /** 设置搜索参数 -- begin */
        // 公司id
        $search['company_id'] = $requestData['company_id'];

        // 订单状态,可以是数组或字符串
        if ($requestData['status']){
            $search['status'] = $requestData['status'];
        }
        // 订单支付状态
        if ($requestData['pay_status']){
            $search['pay_status'] = $requestData['pay_status'];
        }
        // 订单统计日期
        if ($requestData['begin_time']){
            $search['begin_time'] = $requestData['begin_time'];
            $search['end_time'] = $requestData['end_time'] ? $requestData['end_time'] : time();
        }
        /** 设置搜索参数 -- end */

        // 获取订单数据
        $orderService = new OrderOrder();
        $orderRes = $orderService->getCompanyOrderStatistics($search,'final_amount');
        $this->setErrorMsg('请求成功');
        return $this->outputFormat($orderRes);

    }
}
