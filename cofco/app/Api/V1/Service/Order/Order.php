<?php

namespace App\Api\V1\Service\Order;

use App\Api\Model\Order\Order as OrderModel;
use App\Api\Model\Order\OrderPayLog;
use App\Api\Logic\Mq as Mq;

/*
 * @todo 订单列表
 */

class Order
{
    private $payment_mapping = [
        'alipay' => '支付宝',
        'alipayglobal' => '支付宝国际版',
        'baifupay' => '百付宝',
        'beiqioffline' => '北汽代收',
        'malipay' => '手机支付宝',
        'malipayglobal' => '手机支付宝国际版',
        'mallinpay' => '通联钱包',
        'mallinpayglobal' => '通联钱包',
        'mallinpayhtml' => '微信支付',
        'mbaifupay' => '百付宝',
        'mbeifupay' => '北汽代收',
        'munionpay' => '银联支付手机版',
        'offline' => '线下支付',
        'online' => '线上支付',
        'pcwxpay' => '微信支付',
        'unionpay' => '银联支付网页版',
        'wxpay' => '微信支付',
        'wxqypay' => '微信支付',
    ];
    //列表筛选容许字段
    private $list_filter = [
        'member_id',
        'order_id',
        'company_id',
        'create_time',
        'pay_time',
        'ship_name',
        'ship_mobile',
        'system_code',
        'order_category',
        'pay_status',
        'ship_status',
        'status',
        'create_source',
        'confirm_status',
        'warehouse_name',
        'pop_owner_id',
    ];


    /*
     * @todo 获取订单列表
     */
    public function GetOrderList($request_data)
    {
        $order_list = [];
        if (empty($request_data) || empty($request_data['filter'])) {
            return $order_list;
        }
        //获取主订单id
        $main_order_id_list = $this->SelectMainOrderIdList($request_data['filter'], $request_data['page_index'],
            $request_data['page_size'], $request_data['order']);
        if (empty($main_order_id_list)) {
            return $order_list;
        }
        //组织订单数据
        $order_list = $this->GenerateOrderList($main_order_id_list, $request_data['output_format']);
        return $order_list;
    }

    /*
     * @todo 订单
     */
    public function GetOrderTotal($filter_data)
    {
        if (empty($filter_data)) {
            return 0;
        }
        $where = $this->GetWhereByFilter($filter_data);
        if (empty($where)) {
            return 0;
        }
        $where['create_source'] = [
            'type' => 'eq',
            'value' => 'main'
        ];
        $total = OrderModel::GetOrderTotal($where);
        return $total;
    }

    /*
     * @todo 筛选出主订单列表
     */
    protected function SelectMainOrderIdList($filter_data, $page_index = 1, $page_size = 10, $order = array())
    {
        $main_order_id_list = [];
        if (empty($filter_data)) {
            return [];
        }
        $where = $this->GetWhereByFilter($filter_data);
        if (empty($where)) {
            return $main_order_id_list;
        }
        $where['create_source'] = [
            'type' => 'eq',
            'value' => 'main'
        ];
        $limit = max($page_index - 1, 0) * $page_size . ',' . $page_size;
        $order_list = OrderModel::GetOrderList('order_id', $where, $limit);
        if (empty($order_list)) {
            return $main_order_id_list;
        }
        foreach ($order_list as $order) {
            $main_order_id_list[] = $order->order_id;
        }
        return $main_order_id_list;
    }

    /*
     * @todo filter转换为可以使用的where条件
     */
    protected function GetWhereByFilter($filter_data)
    {
        $where = [];
        if (empty($filter_data)) {
            return $where;
        }
        foreach ($filter_data as $field => $value) {
            if (!in_array($field, $this->list_filter)) {
                continue;
//                return $where;
            }
            switch ($field) {
                case 'create_time':
                    $where['create_time'] = [
                        'type' => 'between',
                        'value' => [
                            'egt' => ($value['start_time']),
                            'elt' => empty($value['end_time']) ? time() : ($value['end_time'])
                        ]
                    ];
                    break;
                case 'pay_time':
                    $where['pay_time'] = [
                        'type' => 'between',
                        'value' => [
                            'egt' => ($value['start_time']),
                            'elt' => empty($value['end_time']) ? time() : ($value['end_time'])
                        ]
                    ];
                    break;
                case 'warehouse_name':
                    $where['extend_data'] = [
                        'type' => 'like',
                        'value' => $value
                    ];
                    break;
                default:
                    if (is_array($value)) {
                        $where[$field] = [
                            'type' => 'in',
                            'value' => $value
                        ];
                    } else {
                        $where[$field] = [
                            'type' => 'eq',
                            'value' => $value
                        ];
                    }
            }
        }

        return $where;
    }


    /*
     * @todo 组织订单列表数据
     */
    protected function GenerateOrderList($main_order_id_list, $output_format)
    {
        $order_list = [];
        if (empty($main_order_id_list)) {
            return $order_list;
        }
        //获取主订单
        $main_order_list = OrderModel::GetOrderList('*',
            ['order_id' => ['type' => 'in', 'value' => $main_order_id_list]], count($main_order_id_list));
        $main_order_list = $this->FormatOrderListData($main_order_list);
        //返回拆分子订单方式
        $split_order_list = $this->GetSplitOrdersData($main_order_id_list, $output_format);
        //拆分
        if (!empty($split_order_list)) {
            $split_order_list = $this->FormatOrderListData($split_order_list);
            foreach ($split_order_list as $split_order) {
                $main_order_list[$split_order['root_pid']]['split_orders'][] = $split_order;
            }
        }
        //返回订单数据
        return $main_order_list;
    }

    /*
     * @todo 格式化订单列表返回数据
     */
    protected function FormatOrderListData($order_list, $set_items = true)
    {
        $order_id_list = [];
        $format_order_list = [];
        if (empty($order_list)) {
            return $format_order_list;
        }
        foreach ($order_list as $order) {
            //订单数据
            $format_order_list[$order->order_id] = [
                'order_id' => $order->order_id,
                'pid' => $order->pid,
                'root_pid' => $order->root_pid,
                'final_amount' => $order->final_amount,
                'point_amount' => $order->point_amount,
                'point_channel' => $order->point_channel,
                'cost_item' => $order->cost_item,
                'cost_freight' => $order->cost_freight,
                'cost_tax' => $order->cost_tax,
                'cur_money' => $order->cur_money,
                'create_source' => $order->create_source,
                'member_id' => $order->member_id,
                'company_id' => $order->company_id,
                'weight' => empty($order->weight) ? 0 : $order->weight,
                'business_code' => $order->business_code,
                'business_project_code' => $order->business_project_code,
                'system_code' => $order->system_code,
                'extend_info_code' => $order->extend_info_code,
                'order_category' => $order->order_category,
                'create_time' => $order->create_time,
                'wms_code' => $order->wms_code,
                'wms_delivery_bn' => $order->wms_delivery_bn,
                'wms_order_bn' => $order->wms_order_bn,
                'pay_status' => $order->pay_status,
                'ship_status' => $order->ship_status,
                'split' => $order->split,
                'status' => $order->status,
                'confirm_status' => $order->confirm_status,
                'ship_province' => $order->ship_province,
                'ship_city' => $order->ship_city,
                'ship_county' => $order->ship_county,
                'ship_town' => $order->ship_town,
                'ship_name' => $order->ship_name,
                'payment' => $order->payment,
                'ship_addr' => $order->ship_addr,
                'ship_zip' => $order->ship_zip,
                'ship_tel' => $order->ship_tel,
                'ship_mobile' => $order->ship_mobile,
                'logi_name' => $order->logi_name,
                'logi_no' => $order->logi_no,
                'pop_owner_id' => $order->pop_owner_id,
                'pmt_amount' => $order->pmt_amount,
                'payed' => $order->payed,
                //时间
                'pay_time' => intval($order->pay_time),
                'delivery_time' => intval($order->delivery_time),
                'finish_time' => intval($order->finish_time),
                'cancel_time' => intval($order->cancel_time),

                'extend_data' => !empty($order->extend_data) ? (json_decode($order->extend_data) ? json_decode($order->extend_data,
                    true) : $order->extend_data) : array(),
                'split_order' => [],
                'items' => [],
                // 退货状态
                'refund_status' => $order->refund_status,
                //备注
                'memo' => $order->memo,
                //暂停状态
                'hung_up_status' => $order->hung_up_status,
                'project_code' => $order->project_code,
            ];
            $order_id_list[] = $order->order_id;
        }
        //是否显示订单商品明细
        if ($set_items == true) {
            $order_items_list = OrderModel::GetOrderItems($order_id_list);
            if (empty($order_items_list)) {
                return [];
            }
            foreach ($order_items_list as $order_id => $order_item) {
                foreach ($order_item as $bn => $item) {
                    $format_order_list[$item->order_id]['items'][$item->bn] = [
                        'bn' => $item->bn,
                        'name' => $item->name,
                        'cost' => $item->cost,
                        'price' => $item->price,
                        'mktprice' => $item->mktprice,
                        'amount' => $item->amount,
                        'weight' => $item->weight,
                        'nums' => $item->nums,
                        'cost_tax' => $item->cost_tax,
                        'pmt_amount' => $item->pmt_amount,
                        'cost_freight' => $item->cost_freight,
                        'point_amount' => $item->point_amount,
                    ];
                }
            }
        }
        return $format_order_list;
    }

    /*
     * @todo 订单详情
     */
    public function GetOrderInfo($request_data)
    {
        $order_id = 0;
        $order_info_list = [];
        //筛选订单id
        if (isset($request_data['filter']['order_id']) && !empty($request_data['filter']['order_id'])) {
            $order_id = $request_data['filter']['order_id'];
        } else {
            if (isset($request_data['filter']['wms_order_bn']) && !empty($request_data['filter']['wms_order_bn']) && !empty($request_data['filter']['wms_code'])) {
                $order_data = OrderModel::GetWmsOrder($request_data['filter']['wms_code'],
                    $request_data['filter']['wms_order_bn']);
                $order_id = $order_data->order_id;
            } else {
                if (isset($request_data['filter']['wms_delivery_bn']) && !empty($request_data['filter']['wms_delivery_bn']) && !empty($request_data['filter']['wms_code'])) {
                    $order_data = OrderModel::GetWmsDeliveryOrder($request_data['filter']['wms_code'],
                        $request_data['filter']['wms_delivery_bn']);
                    $order_id = $order_data->order_id;
                } else {
                    if (
                        (isset($request_data['filter']['trade_no']) && !empty($request_data['filter']['trade_no']))
                        ||
                        (isset($request_data['filter']['payment_id']) && !empty($request_data['filter']['payment_id']))
                    ) {
                        $params = [];
                        if (!empty($request_data['filter']['trade_no'])) {
                            $params['trade_no'] = $request_data['filter']['trade_no'];
                        }
                        if (!empty($request_data['filter']['payment_id'])) {
                            $params['payment_id'] = $request_data['filter']['payment_id'];
                        }
                        $pay_log_data = OrderPayLog::GetPayLogByParams($params);
                        $order_id = is_object($pay_log_data) ? $pay_log_data->order_id : null;
                    }
                }
            }
        }
        if (empty($order_id)) {
            return $order_info_list;
        }
        //获取订单详情
        $order_info = OrderModel::GetOrderInfoById($order_id);
        if (empty($order_info)) {
            return $order_info_list;
        }
        $order_info_list = $this->FormatOrderInfoData($order_info);
        //格式订单数据
        $split_order_list = $this->GetSplitOrdersData([$order_id], $request_data['output_format']);
        //获取拆分订单数据
        if (!empty($split_order_list)) {
            foreach ($split_order_list as $split_order) {
                $order_info_list['split_orders'][$split_order->order_id] = $order_info = $this->FormatOrderInfoData($split_order);
            }
        }
        return $order_info_list;
    }

    private function FormatOrderInfoData($order_info)
    {
        if (empty($order_info)) {
            return [];
        }
        $items = [];
        $order_info_list = [
            'order_id' => $order_info->order_id,
            'pid' => $order_info->pid,
            'root_pid' => $order_info->root_pid,
            'create_source' => $order_info->create_source,
            'member_id' => $order_info->member_id,
            'company_id' => $order_info->company_id,
            'ship_name' => $order_info->ship_name,
            'ship_addr' => $order_info->ship_addr,
            'ship_zip' => $order_info->ship_zip,
            'ship_tel' => $order_info->ship_tel,
            'ship_mobile' => $order_info->ship_mobile,
            'ship_province' => $order_info->ship_province,
            'ship_city' => $order_info->ship_city,
            'ship_county' => $order_info->ship_county,
            'ship_town' => $order_info->ship_town,
            'idcardname' => trim($order_info->idcardname),
            'idcardno' => trim($order_info->idcardno),
            'receive_mode' => $order_info->receive_mode,
            //金额
            'final_amount' => $order_info->final_amount,
            'cur_money' => $order_info->cur_money,
            'point_amount' => $order_info->point_amount,
            'point_channel' => $order_info->point_channel,
            'cost_freight' => $order_info->cost_freight,
            'cost_tax' => $order_info->cost_tax,
            'pmt_amount' => $order_info->pmt_amount,
            'payed' => $order_info->payed,
            'cost_item' => $order_info->cost_item,
            //状态
            'pay_status' => $order_info->pay_status,
            'ship_status' => $order_info->ship_status,
            'confirm_status' => $order_info->confirm_status,
            'status' => $order_info->status,
            //类型
            'business_code' => $order_info->business_code,
            'business_project_code' => $order_info->business_project_code,
            'system_code' => $order_info->system_code,
            'extend_info_code' => $order_info->extend_info_code,
            'order_category' => $order_info->order_category,
            //其它
            'anonymous' => $order_info->anonymous,
            'payment_restriction' => $order_info->payment_restriction,
            'payment' => $order_info->payment,
            'memo' => $order_info->memo,
            'pop_owner_id' => $order_info->pop_owner_id,
            'wms_order_bn' => $order_info->wms_order_bn,
            'wms_delivery_bn' => $order_info->wms_delivery_bn,
            'wms_code' => $order_info->wms_code,
            'split' => $order_info->split,
            //时间
            'create_time' => intval($order_info->create_time),
            'pay_time' => intval($order_info->pay_time),
            'delivery_time' => intval($order_info->delivery_time),
            'finish_time' => intval($order_info->finish_time),
            'cancel_time' => intval($order_info->cancel_time),
            'last_modified' => intval($order_info->last_modified),
            //快递
            'logi_name' => $order_info->logi_name,
            'logi_no' => $order_info->logi_no,
            'logi_code' => $order_info->logi_code,
            'items' => [],
            'split_orders' => [],
            'extend_data' => !empty($order_info->extend_data) ? (json_decode($order_info->extend_data) ? json_decode($order_info->extend_data,
                true) : $order_info->extend_data) : array(),
            // 退货状态
            'refund_status' => $order_info->refund_status,
            //暂停状态
            'hung_up_status' => $order_info->hung_up_status,
            'project_code' => $order_info->project_code,
        ];
        //订单商品详细
        $order_item_list = OrderModel::GetOrderItems([$order_info->order_id]);
        if (!empty($order_item_list)) {
            foreach ($order_item_list[$order_info->order_id] as $order_item) {
                $items[] = [
                    'bn' => $order_item->bn,
                    'name' => $order_item->name,
                    'cost' => $order_item->cost,
                    'price' => $order_item->price,
                    'mktprice' => $order_item->mktprice,
                    'nums' => $order_item->nums,
                    'amount' => $order_item->amount,
                    'weight' => $order_item->weight,
                    'cost_tax' => $order_item->cost_tax,
                    'pmt_amount' => $order_item->pmt_amount,
                    'cost_freight' => $order_item->cost_freight,
                    'point_amount' => $order_item->point_amount,
                ];
            }
        }
        $order_info_list['items'] = $items;
        return $order_info_list;
    }


    /*
     * @todo 获取拆分订单数据
     */
    private function GetSplitOrdersData($main_order_id_list, $output_format)
    {
        $split_order_list = [];
        if (empty($main_order_id_list)) {
            return [];
        }
        switch ($output_format) {
            case 'not_split_order':
                $split_order_list = [];
                break;
            case 'all_split_order':
                $split_where = [
                    'root_pid' => [
                        'type' => 'in',
                        'value' => $main_order_id_list
                    ],
                    'create_source' => [
                        'type' => 'in',
                        'value' => ['split_order', 'wms_order']
                    ]
                ];
                $split_order_list = OrderModel::GetOrderList('*', $split_where);
                break;
            case 'valid_split_order':
                $split_where = [
                    'root_pid' => [
                        'type' => 'in',
                        'value' => $main_order_id_list
                    ],
                    'split' => [
                        'type' => 'eq',
                        'value' => 1
                    ],
                    'create_source' => [
                        'type' => 'in',
                        'value' => ['split_order', 'wms_order']
                    ]
                ];
                $split_order_list = OrderModel::GetOrderList('*', $split_where, '');
                break;
        }
        return $split_order_list;
    }

    /*
     * @todo 订单支付查询
     */
    public function GetOrderPayment($order_bn_list)
    {
        $payment_list = [];
        if (empty($order_bn_list)) {
            $payment_list;
        }
        $payment_list_db = OrderPayLog::GetPayLogList($order_bn_list);
        if (!empty($payment_list_db)) {
            foreach ($payment_list_db as $payment) {
                $extend_data = json_decode($payment->extend_data);
                $payment_list[] = [
                    'order_id' => $payment->order_id,
                    'pay_name' => isset($this->payment_mapping[$payment->pay_name]) ? $this->payment_mapping[$payment->pay_name] : $payment->pay_name,
                    'pay_code' => $payment->pay_name,
                    'payment_id' => $payment->payment_id,
                    'trade_no' => $payment->trade_no,
                    'pay_money' => $extend_data->pay_money,
                    'pay_time' => $extend_data->pay_time,
                    'create_time' => $payment->create_time,
                ];
            }
        }
        return $payment_list;
    }

    /*
     * @todo 超时支付重新抛单
     */
    public function TimeoutPayOrderReTry($order_id)
    {
        return OrderModel::TimeoutPayOrderReTry($order_id);
    }

    /**
     * 获取order_id数组 格式['201708161225321115','201708171131533820']
     * @param type $params
     */
    public function GetOrderIdsPayLogByParams($params)
    {
        return OrderPayLog::GetOrderIdsPayLogByParams($params);
    }

    /**
     * 获取订单列表
     * @param type $filter_type
     * @param type $filter
     * @param type $page_index
     * @param type $page_size
     * @param type $need_total
     */
    public function GetOrderListForMis($filter_type, $filter = [], $page_index = 1, $page_size = 20, $need_total = true)
    {
        $results = [
            'order_list' => [],
        ];
        $total = 0;
        switch ($filter_type) {
            case 1://已完成订单
                $where = [];
                $where['status'] = [
                    'type' => 'eq',
                    'value' => 1,
                ];
                $where['pay_status'] = [
                    'type' => 'eq',
                    'value' => 2,
                ];
                $where['confirm_status'] = [
                    'type' => 'eq',
                    'value' => 2,
                ];
                $where['ship_status'] = [
                    'type' => 'in',
                    'value' => [1, 2],
                ];
                $limit = max($page_index - 1, 0) * $page_size . ',' . $page_size;
                $order_list = OrderModel::GetOrderList('*', $where, $limit);
                if (!empty($order_list)) {
                    $results['order_list'] = $this->FormatOrderListData($order_list, false);
                }

                if (!empty($order_list) && $need_total) {
                    $total = OrderModel::GetOrderTotal($where);
                }
                break;
            case 3://拦截订单旧版
                $limit = max($page_index - 1, 0) * $page_size . ',' . $page_size;
                $ret_data = OrderModel::GetInterceptAndHungupSuccessList('*', $filter, $limit, $need_total);
                if (!empty($ret_data)) {
                    $results['order_list'] = $this->FormatOrderListData($ret_data['order_list'], false);
                    $total = $ret_data['total'];
                }
                if ($need_total) {
                    $results['total'] = $total;
                }
                break;
            case 2://拦截订单
                $limit = max($page_index - 1, 0) * $page_size . ',' . $page_size;
                $ret_data = OrderModel::GetInterceptAndHungupSuccessListV2('*', $filter, $limit, $need_total);
                if (!empty($ret_data)) {
                    $results['order_list'] = $this->FormatOrderListData($ret_data['order_list'], false);
                    $total = $ret_data['total'];
                }
                if ($need_total) {
                    $results['total'] = $total;
                }
                break;
            default:
                break;
        }

        if ($need_total) {
            $results['total'] = $total;
        }

        return $results;
    }

    /**
     * 异常订单消息列表
     */
    public function GetWmsOrderMsgList($filter = [], $page_index = 1, $page_size = 20, $need_total = true)
    {
        $results = [
            'order_list' => [],
        ];
        $total = 0;
        $limit = max($page_index - 1, 0) * $page_size . ',' . $page_size;
        $ret_data = OrderModel::GetExceptionOrderMsgListForMis('*', $filter, $limit, $need_total);
        if (!empty($ret_data)) {
            $data = [];
            $order_ids = [];
            foreach ($ret_data['order_list'] as $current_order) {
                $order_ids[] = $current_order->order_id;
            }
            $where = [
                'order_id' => [
                    'type' => 'in',
                    'value' => $order_ids,
                ]
            ];
            $order_list = OrderModel::GetOrderList('*', $where, '');
            $order_list_arr = $this->FormatOrderListData($order_list, false);
            foreach ($ret_data['order_list'] as $current_order) {
                $tmp = $order_list_arr[$current_order->order_id];
                $tmp['msg_status'] = $current_order->status;
                $tmp['msg_wms_order_status'] = $current_order->wms_order_status;
                $tmp['msg_id'] = $current_order->id;
                $tmp['msg_reason'] = $current_order->wms_msg;
                $tmp['msg_create_time'] = $current_order->create_time;
                $tmp['msg_type'] = $current_order->type;
                $tmp['ignore_operator'] = $current_order->operator;
                $tmp['ignore_reason'] = $current_order->reason;
                $tmp['ignore_time'] = $current_order->operator_time;
                $data[] = $tmp;
            }
            $results['order_list'] = $data;
            $total = $ret_data['total'];
        }
        if ($need_total) {
            $results['total'] = $total;
        }

        return $results;
    }


    /**
     * 更新履约订单消息
     */
    public function UpdateWmsOrderMsg($update_data)
    {
        return OrderModel::UpdateWmsOrderMsg($update_data);
    }

    /**
     * 更新履约订单消息状态
     */
    public function UpdateWmsOrderMsgStatusById($id, $status, $operator = '', $reason = '')
    {
        return OrderModel::UpdateWmsOrderMsgStatusById($id, $status, $operator, $reason);
    }

    /**
     * 获取消息统计
     */
    public function GetMsgStats()
    {
        return OrderModel::GetMsgStats();
    }

    /**
     * 申请暂停订单
     *
     */
    public function Pause($order_id, &$msg = '')
    {
        //获取订单信息
        $order_info = OrderModel::GetOrderInfoById($order_id);
        if (empty($order_info)) {
            $msg = '订单信息未找到';
            return false;
        }

        if ($order_info->pay_status != 2) {
            $msg = '订单未支付';
            return false;
        } elseif ($order_info->split == 2) {
            $msg = '订单已分拆不能暂定';
            return false;
        }
        if ($order_info->status == 3) {
            $msg = '订单已完成';
            return false;
        } elseif ($order_info->status == 2) {
            $msg = '订单已取消';
            return false;
        } elseif (in_array($order_info->hung_up_status, [1, 3])) {
            $msg = '订单已挂起或挂起处理中不能暂停';
            return false;
        } else {
            //
        }

        $current_order_id = $order_info->order_id;
        $is_locked = OrderModel::LockOrderByIds([$current_order_id]);
        if (!$is_locked) {
            $msg = '锁定订单失败';
            goto RET_FAIL;
        }

        //过滤可处理状态
        if (!in_array($order_info->hung_up_status, [0, 2])) {
            $msg = '订单所处状态不能暂停';
            goto RET_FAIL_WITH_UNLOCK;
        }

        $apply_pause_record = OrderModel::GetApplyOrderPauseData($order_id);
        if (!empty($apply_pause_record)) {
            $msg = '申请暂停记录已存在';
            goto RET_FAIL_WITH_UNLOCK;
        }

        $old_hung_up_status = $order_info->hung_up_status;
        $hung_up_status = 3;//挂起状态:0->未挂起，1->已挂起，2->解除挂起，3->处理中
        $ret = OrderModel::UpdateOrderHungupStatus($order_id, $hung_up_status, $old_hung_up_status);
        if ($ret == false) {
            $msg = '锁定订单挂起状态失败';
            goto RET_FAIL_WITH_UNLOCK;
        }

        $update_data = [
            'order_id' => $order_id,
        ];
        $ret = OrderModel::SaveApplyOrderPauseData($update_data);
        if ($ret == false) {
            $msg = '保存暂停申请失败';
            goto RET_FAIL_WITH_UNLOCK;
        }

        $success = false;
        //1.未履约订单标志不去履约
        if (empty($order_info->wms_order_bn) && empty($order_info->wms_delivery_bn)) {
            $success = true;
        }
        //2.已履约的尝试取消履约

        //2.1存在发货单号，则尝试取消发货单
        elseif (!empty($order_info->wms_delivery_bn)) {
            $PauseOrderService = new Pause();
            $response = $PauseOrderService->PauseByWmsDeliveryBn($order_id, $order_info->wms_delivery_bn,
                $order_info->wms_code);
            if ($response['error_code'] == 200) {
                $success = true;
            } else {
                $msg = $response['error_msg'];
            }
        } //2.2存在履约单号则,则尝试取消履约单号
        else {
            $PauseOrderService = new Pause();
            $response = $PauseOrderService->PauseByWmsOrderBn($order_id, $order_info->wms_order_bn,
                $order_info->wms_code);
            if ($response['error_code'] == 200) {
                $success = true;
            } else {
                $msg = $response['error_msg'];
            }
        }

        //3.更新暂停订单申请状态
        if ($success) {
            $status = 2;//申请状态:1->申请暂停中、2->暂停成功、3->暂停失败
            $reason = !empty($msg) ? $msg : '';
            $old_hung_up_status = 3;
            $hung_up_status = 1;
            $update_hungup_ret = OrderModel::UpdateOrderHungupStatus($order_id, $hung_up_status, $old_hung_up_status);
        } else {
            $status = 3;
            $reason = !empty($msg) ? $msg : '暂停订单处理失败';
            $old_hung_up_status = 3;
            $hung_up_status = 0;
            $update_hungup_ret = OrderModel::UpdateOrderHungupStatus($order_id, $hung_up_status, $old_hung_up_status);
        }
        $update_apply_order_pause_ret = 0;
        if ($update_hungup_ret) {
            $update_apply_order_pause_ret = OrderModel::UpdateApplyOrderPauseData($order_id, $status, $reason);
        }

        if ($success && $update_apply_order_pause_ret > 0) {
            //暂停成功,添加到拦截视图
            OrderModel::addInterceptOrder($order_id);
            goto RET_SUCC_WITH_UNLOCK;
        } else {
            goto RET_FAIL_WITH_UNLOCK;
        }

        RET_SUCC_WITH_UNLOCK:
        OrderModel::UnLockOrderByIds([$current_order_id]);
        return true;
        RET_FAIL_WITH_UNLOCK:
        OrderModel::UnLockOrderByIds([$current_order_id]);
        return false;
        RET_FAIL:
        return false;
    }

    /**
     * 已支付未完成订单取消
     */
    public function OrderPayedCancel($order_id, &$msg = '')
    {
        //获取订单信息
        $order_info = OrderModel::GetOrderInfoById($order_id);
        if (empty($order_info)) {
            $msg = '订单信息未找到';
            return false;
        }

        if ($order_info->pay_status != 2) {
            $msg = '订单未支付';
            return false;
        } elseif ($order_info->split == 2) {
            $msg = '订单已分拆不能取消';
            return false;
        }
        if ($order_info->status == 3) {
            $msg = '订单已完成';
            return false;
        } elseif ($order_info->status == 2) {
            $msg = '订单已取消';
            return false;
        }
        $success = false;
        //1.未履约订单标志不去履约
        if ($order_info->confirm_status == 1) {
            $success = true;
        } //2.已履约的尝试取消履约
        elseif (!empty($order_info->wms_delivery_bn)) {//2.1存在发货单号，则尝试取消发货单
            $PauseOrderService = new Pause();
            $response = $PauseOrderService->PauseByWmsDeliveryBn($order_id, $order_info->wms_delivery_bn, $order_info->wms_code, false);
            if ($response['error_code'] == 200) {
                $success = true;
            } else {
                $msg = $response['error_msg'];
            }
        } else {//2.2存在履约单号则,则尝试取消履约单号
            $PauseOrderService = new Pause();
            $response = $PauseOrderService->PauseByWmsOrderBn($order_id, $order_info->wms_order_bn, $order_info->wms_code, false);
            if ($response['error_code'] == 200) {
                $success = true;
            } else {
                $msg = $response['error_msg'];
            }
        }

        if (!$success) {
            return false;
        }

        $ret = OrderModel::UpdateOrderToCancelStatus($order_id);
        if ($ret == false) {
            $msg = '撤销订单失败';
            return false;
        }
        //发送消息
        Mq::OrderPayedCancelForRefund($order_id);
        return true;
    }

    /**
     * 撤销订单For Mis
     */
    public function CancelOrderForMis($order_id, &$msg)
    {
        //获取订单信息
        $order_info = OrderModel::GetOrderInfoById($order_id);
        if (empty($order_info)) {
            $msg = '订单信息未找到';
            return false;
        }

        if ($order_info->pay_status != 2) {
            $msg = '订单未支付';
            return false;
        }

        if ($order_info->status == 3) {
            $msg = '订单已完成';
            return false;
        } elseif ($order_info->status == 2) {
            $msg = '订单已取消';
            return false;
        } else {
            //
        }

        //未确认订单即为拦截订单
        if ($order_info->confirm_status == 1) {
        } elseif ($order_info->hung_up_status == 1) {
        } else {
            $msg = '非暂停且非拦截订单,不能发起撤销';
            return false;
        }

        $ret = OrderModel::UpdateOrderToCancelStatus($order_id);
        if ($ret == false) {
            $msg = '撤销订单失败';
            return false;
        }
        //取消拦截成功,从拦截视图清除
        OrderModel::delInterceptOrder($order_id);
        //发送消息
        Mq::OrderCancel($order_id);
        return true;
    }

    /**
     * 拦截订单恢复履约
     */
    public function InterceptRecovery($order_id, &$msg = '')
    {
        //获取订单信息
        $order_info = OrderModel::GetOrderInfoById($order_id);
        if (empty($order_info)) {
            $msg = '订单信息未找到';
            return false;
        }

        if ($order_info->pay_status != 2) {
            $msg = '订单未支付';
            return false;
        }

        if ($order_info->status == 3) {
            $msg = '订单已完成';
            return false;
        } elseif ($order_info->status == 2) {
            $msg = '订单已取消';
            return false;
        } elseif ($order_info->confirm_status != 1 || empty($order_info->memo)) {
            $msg = '非拦截订单';
            return false;
        } else {
            //
        }

        $ret = OrderModel::OrderConfirm(['order_id' => $order_id]);
        if ($ret) {
            Mq::OrderConfirm($order_id);
            //确认成功后，从拦截试图删除
            OrderModel::delInterceptOrder($order_id);
            return true;
        }
        $msg = '拦截订单履约恢复失败';
        return false;
    }

    /**
     * 更新订单信息
     * @param type $order_id
     * @param type $update_data
     * @return type
     */
    public function UpdateById($order_id, $update_data)
    {
        $where = [
            'order_id' => $order_id,
        ];
        return OrderModel::OrderUpdate($where, $update_data);
    }

    /**
     * 获取暂停订单申请列表
     */
    public function GetPauseList($filter = [], $page_index = 1, $page_size = 20, $need_total = true)
    {
        $results = [
            'order_list' => [],
        ];
        $total = 0;
        $limit = max($page_index - 1, 0) * $page_size . ',' . $page_size;
        $ret_data = OrderModel::GetPauseList('*', $filter, $limit, $need_total);
        if (!empty($ret_data)) {
            $data = [];
            $order_ids = [];
            foreach ($ret_data['order_list'] as $current_order) {
                $order_ids[] = $current_order->order_id;
            }
            $where = [
                'order_id' => [
                    'type' => 'in',
                    'value' => $order_ids,
                ]
            ];
            $order_list = OrderModel::GetOrderList('*', $where, '');
            $order_list_arr = $this->FormatOrderListData($order_list, false);
            foreach ($ret_data['order_list'] as $current_order) {
                $tmp = $order_list_arr[$current_order->order_id];
                $tmp['pause_apply_status'] = $current_order->status;
                $tmp['pause_apply_reason'] = $current_order->reason;
                $tmp['pause_apply_id'] = $current_order->id;
                $tmp['pause_apply_create_at'] = $current_order->create_at;
                $tmp['pause_apply_update_at'] = $current_order->update_at;
                $data[] = $tmp;
            }
            $results['order_list'] = $data;
            $total = $ret_data['total'];
        }
        if ($need_total) {
            $results['total'] = $total;
        }

        return $results;
    }

    /** 获取公司订单统计信息
     *
     * @param array $search
     * @return int
     * @author liuming
     */
    public function getCompanyOrderStatistics($search = array(),$column){
        if (empty($search)){
            return 0;
        }

        // 公司id
        $where['company_id'] = [
            'type' => 'eq',
            'value' => $search['company_id'],
        ];

        // 不是拆单的商品
        $where['split'] = [
            'type' => 'eq',
            'value' => 1,
        ];

        // 订单状态
        if (!empty($search['status'])){
            if (is_array($search['status'])){
                $where['status'] = [
                    'type' => 'in',
                    'value' => $search['status'],
                ];
            }else{
                $where['status'] = [
                    'type' => 'eq',
                    'value' => $search['status'],
                ];
            }
        }

        // 支付状态
        if (!empty($search['pay_status'])){
            $where['pay_status'] = [
                'type' => 'eq',
                'value' => $search['pay_status'],
            ];
        }

        // 查询日期
        if (!empty($search['begin_time'])){
            $where['create_time'] = [
                'type' => 'between',
                'value' => [
                    'egt' => ($search['begin_time']),
                    'elt' => empty($search['end_time']) ? time() : ($search['end_time'])
                ]
            ];
        }

        $totalRes = OrderModel::GetOrderTotal($where);
        $sumRes = OrderModel::GetOrderColumnSum($where,$column);
        $total = $totalRes ? $totalRes : 0;
        $sum = $sumRes ? $sumRes : 0;
        return array('total' => $total,'sum' => $sum);

    }

}
