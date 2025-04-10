<?php

namespace App\Api\Model\Order;

class Order
{

    /*
     * @todo 检查订单是否已存在
     */
    public static function CheckOrderId($order_id)
    {
        if (empty($order_id)) {
            return false;
        }
        $sql = "select count(1) as total from server_orders where order_id = :order_id";
        $order_total = app('api_db')->selectOne($sql, ['order_id' => $order_id]);
        if (empty($order_total) || empty($order_total->total)) {
            return true;
        } else {
            return false;
        }
    }

    /*
     * @todo 获取订单详情 (单独一条订单)
     */
    public static function GetOrderInfoById($order_id)
    {
        $sql = "select * from server_orders where order_id = :order_id";
        $order_info = app('api_db')->selectOne($sql, ['order_id' => $order_id]);
        return $order_info;
    }

    /*
     * @todo 获取订单 (获取一组订单，包含拆分订单)
     */
    public static function GetSplitOrderByRootPId($root_pid)
    {
        if (empty($root_pid)) {
            return [];
        }
        $order_list = self::GetSplitOrdeListrByRootPId([$root_pid]);
        return $order_list;
    }

    /*
     * @todo 获取订单列表 (获取一组订单，包含拆分订单)
     */
    public static function GetSplitOrdeListrByRootPId($root_pid)
    {
        if (empty($root_pid)) {
            return [];
        }
        $sql = "select * from server_orders where root_pid in(" . implode(',',
                array_fill(0, count($root_pid), '?')) . ") and create_source != 'main' and split = '1'";
        $order_list = app('api_db')->select($sql, array_values($root_pid));
        return $order_list;
    }

    public static function GetOrderItemsByOrderId($order_id)
    {
        if (empty($order_id)) {
            return [];
        }
        $item_list = self::GetOrderItems([$order_id]);
        if (!isset($item_list[$order_id])) {
            return [];
        }
        return $item_list[$order_id];
    }

    /*
     * @todo 查询订单商品说细 (批量)
     */
    public static function GetOrderItems($order_id_list)
    {
        $order_item_list = [];
        if (empty($order_id_list) || !is_array($order_id_list)) {
            return $order_item_list;
        }
        $sql = "select * from server_order_items where order_id in(" . implode(',',
                array_fill(0, count($order_id_list), '?')) . ")";
        $item_list = app('api_db')->select($sql, array_values($order_id_list));
        if (empty($item_list)) {
            return $order_item_list;
        }
        foreach ($item_list as $item) {
            $order_item_list[$item->order_id][] = $item;
        }
        return $order_item_list;
    }

    /*
     * @todo 保存订单
     */
    public static function SaveOrder($save_data)
    {
        if (empty($save_data)) {
            return false;
        }
        $split_orders = $save_data['split_orders'];
        unset($save_data['split_orders']);
        //开启事务
        app('db')->beginTransaction();
        //保存主订单
        $res = self::AddOrder($save_data);
        if (!$res) {
            app('db')->rollBack();
            return false;
        }
        //保存子订单
        if (!empty($split_orders) && count($split_orders) > 1) {
            foreach ($split_orders as $split_order) {
                $split_order['pid'] = $save_data['order_id'];
                $split_order['root_pid'] = $save_data['order_id'];
                $split_order['member_id'] = $save_data['member_id'];
                $split_order['company_id'] = $save_data['company_id'];
                $split_order['create_time'] = time();
                $split_order['last_modified'] = time();
                $split_order['ship_province'] = $save_data['ship_province'];
                $split_order['ship_city'] = $save_data['ship_city'];
                $split_order['ship_county'] = $save_data['ship_county'];
                $split_order['ship_town'] = $save_data['ship_town'];
                $split_order['ship_name'] = $save_data['ship_name'];
                $split_order['idcardname'] = $save_data['idcardname'];
                $split_order['idcardno'] = $save_data['idcardno'];
                $split_order['ship_addr'] = $save_data['ship_addr'];
                $split_order['ship_zip'] = $save_data['ship_zip'];
                $split_order['ship_tel'] = $save_data['ship_tel'];
                $split_order['ship_mobile'] = $save_data['ship_mobile'];
                $split_order['receive_mode'] = $save_data['receive_mode'];
                $split_order['terminal'] = $save_data['terminal'];
                $split_order['anonymous'] = $save_data['anonymous'];
                $split_order['business_code'] = $save_data['business_code'];
                $split_order['extend_info_code'] = $save_data['extend_info_code'];
                $split_order['order_category'] = $save_data['order_category'];
                $split_order['business_project_code'] = $save_data['business_project_code'];
                $split_order['system_code'] = $save_data['system_code'];
                $split_order['extend_data'] = $save_data['extend_data'];
                $split_order['point_channel'] = $save_data['point_channel'];
                $split_order['channel'] = $save_data['channel'];
                $split_order['payment_restriction'] = $save_data['payment_restriction'];
                $split_order['pay_status'] = 1;
                $split_order['status'] = 1;
                $split_order['project_code'] = $save_data['project_code'];
                //保存订单
                $res = self::AddOrder($split_order);
                if (!$res) {
                    app('db')->rollBack();
                    return false;
                }
            }
        }
        //提交订单
        app('db')->commit();
        return true;
    }


    /*
     * @todo 订单支付
     */
    public static function OrderPay($pay_order_data)
    {
        if (empty($pay_order_data)) {
            return false;
        }
        app('db')->beginTransaction();
        //主单进行支付
        $update_order_data = [
            'pay_status' => 2,
            'payment' => $pay_order_data['payment'],
            'payed' => $pay_order_data['pay_money'],
            'pay_time' => time(),
            'last_modified' => time()
        ];
        $where = [
            'order_id' => $pay_order_data['order_id'],
            'pay_status' => 1,
        ];
        $res = self::OrderUpdate($where, $update_order_data);
        if (!$res) {
            app('db')->rollBack();
            return false;
        }
        //获取订单下有所折分订单进行支付
        $split_order_list = self::GetSplitOrderByRootPId($pay_order_data['order_id']);
        if (!empty($split_order_list)) {
            foreach ($split_order_list as $split_order) {
                $update_order_data = [
                    'pay_status' => 2,
                    'payment' => $pay_order_data['payment'],
                    'payed' => $split_order->cur_money,
                    'pay_time' => time(),
                    'last_modified' => time()
                ];
                $where = [
                    'order_id' => $split_order->order_id,
                    'pay_status' => 1,
                ];
                $res = self::OrderUpdate($where, $update_order_data);
                if (!$res) {
                    app('db')->rollBack();
                    return false;
                }
            }
        }
        app('db')->commit();
        return true;
    }

    /*
     * @todo 订单取消
     */
    public static function OrderCancel($order_data)
    {
        if (empty($order_data)) {
            return false;
        }
        $where = [
            'status' => 1,
//            'pay_status'   => 1,
            'root_pid' => $order_data['order_id'],
        ];
        $update_order_data = [
            'status' => 2,
            'cancel_time' => time()
        ];
        $res = self::OrderUpdate($where, $update_order_data);
        return $res;
    }


    /*
     * @todo 订单更新
     */
    public static function OrderUpdate($where, $update_data)
    {
        if (empty($where) || empty($update_data)) {
            return false;
        }
        if (!isset($update_data['last_modified'])) {
            $update_data['last_modified'] = time();
        }
        $res = app('api_db')->table('server_orders')->where($where)->update($update_data);
        return $res;
    }


    /*
     * @todo 获取履约平台拆单
     */
    public static function GetSplitOrders($order_id, $wms_code)
    {
        if (empty($order_id)) {
            return [];
        }
        $sql = "select * from server_orders where pid = :order_id and wms_code = :wms_code and  `create_source` = 'split_order' and `split` = 1";
        $order_list = app('api_db')->select($sql, ['order_id' => $order_id, 'wms_code' => $wms_code]);
        return $order_list;
    }

    /*
     * @todo 订单订单拆分
     */
    public static function SaveSplitOrders($new_split_order, $cancel_split_order, $update_order)
    {
        if (empty($new_split_order) && empty($cancel_split_order) && empty($update_order)) {
            return true;
        }
        app('db')->beginTransaction();
        //保存新拆分订单
        if (!empty($new_split_order)) {
            foreach ($new_split_order as $split_order) {
                $res = self::AddOrder($split_order);
                if (!$res) {
                    app('db')->rollBack();
                    return false;
                }
            }
        }
        //保存取消订单
        if (!empty($cancel_split_order)) {
            foreach ($cancel_split_order as $cancel_order) {
                $where = [
//                    'status'    => 1,
                    'order_id' => $cancel_order
                ];
                $update_order_data = [
                    'split' => 2
                ];
                $res = self::OrderUpdate($where, $update_order_data);
                if (!$res) {
                    app('db')->rollBack();
                    return false;
                }
            }
        }
        //更新订单状态
        if (!empty($update_order)) {
            foreach ($update_order as $order) {
                $res = self::OrderUpdate($order['where'], $order['save_data']);
                if (!$res) {
                    app('db')->rollBack();
                    return false;
                }
            }
        }
        app('db')->commit();
        return true;
    }

    /*
     * @todo 批量保存订单
     */
    public static function SaveOrderAll($order_list)
    {
        if (empty($order_list)) {
            return false;
        }
        app('db')->beginTransaction();
        foreach ($order_list as $order) {
            $res = self::AddOrder($order);
            if (!$res) {
                app('db')->rollBack();
                return false;
            }
        }
        app('db')->commit();
        return true;
    }


    /*
     * @todo 保存订单
     */
    private static function AddOrder($order_data)
    {
        if (empty($order_data)) {
            return false;
        }
        $items = $order_data['items'];
        unset($order_data['items']);
        $sql = "INSERT INTO `server_orders` (`" . implode('`,`', array_keys($order_data)) . "`)VALUES(" . implode(',',
                array_fill(0, count($order_data), '?')) . ")";
        $res = app('api_db')->insert($sql, array_values($order_data));
        if (!$res) {
            return false;
        }
        //保存订单明细
        foreach ($items as $item) {
            $item['order_id'] = $order_data['order_id'];
            $sql = "INSERT INTO `server_order_items` (`" . implode('`,`',
                    array_keys($item)) . "`)VALUES(" . implode(',', array_fill(0, count($item), '?')) . ")";
            $res = app('api_db')->insert($sql, array_values($item));
            if (!$res) {
                return false;
            }
        }
        return true;
    }


    /*
     * @todo 获取履约平台订单
     */
    public static function GetWmsOrder($wms_code, $wms_order_bn)
    {
        if (empty($wms_code) || empty($wms_order_bn)) {
            return [];
        }
        $sql = "select * from server_orders where wms_code = :wms_code and wms_order_bn = :wms_order_bn and create_source in('main','wms_order')";
        $order_info = app('api_db')->selectOne($sql, ['wms_code' => $wms_code, 'wms_order_bn' => $wms_order_bn]);
        return $order_info;
    }

    /*
     * @todo 获取履约平台发货单订单
     */
    public static function GetWmsDeliveryOrder($wms_code, $wms_delivery_bn)
    {
        if (empty($wms_code) || empty($wms_delivery_bn)) {
            return [];
        }
        $sql = "select * from server_orders where wms_code = :wms_code and wms_delivery_bn = :wms_delivery_bn ";
        $order_info = app('api_db')->selectOne($sql, ['wms_code' => $wms_code, 'wms_delivery_bn' => $wms_delivery_bn]);
        return $order_info;
    }

    /*
     * @todo 获取订单列表
     */
    public static function GetOrderList($field = '*', $where = [], $limit = 20, $order = 'create_time desc')
    {
        $field = empty($field) ? '*' : $field;
        $where_data = self::WhereAnalysis($where);
        $limit = empty($limit) ? '' : ' limit ' . $limit;
        $sql = "select $field from server_orders where 1 {$where_data['str']} order by {$order} {$limit}";
        $order_list = app('api_db')->select($sql, $where_data['bindings']);
        return $order_list;
    }

    /*
     * @todo 获取订单列表
     */
    public static function GetOrderTotal($where = [])
    {
        $where_data = self::WhereAnalysis($where);
        $sql = "select count(1) as total from server_orders where 1 {$where_data['str']}  ";
        $order_total = app('api_db')->selectOne($sql, $where_data['bindings']);
        return empty($order_total) ? 0 : $order_total->total;
    }

    /** 获取指定列的count值
     *
     * @param array $where
     * @param string $column
     * @return int
     * @author liuming
     */
    public static function GetOrderColumnSum($where = [],$column = '')
    {
        if (empty($column)){
            return 0;
        }
        $where_data = self::WhereAnalysis($where);
        $sql = "select SUM({$column}) as sum from server_orders where 1 {$where_data['str']}  ";


        $order_total = app('api_db')->selectOne($sql, $where_data['bindings']);
        return empty($order_total) ? 0 : $order_total->sum;
    }


    /*
     * @todo 解析where条件
     */
    protected static function WhereAnalysis($where)
    {
        $where_data = [
            'str' => '',
            'bindings' => []
        ];
        if (empty($where)) {
            return $where_data;
        }
        foreach ($where as $field => $value) {
            switch ($value['type']) {
                case 'eq':
                    $where_data['str'] .= ' and (' . $field . ' = :' . $field . ')';
                    $where_data['bindings'][$field] = $value['value'];
                    break;
                case 'neq':
                    $where_data['str'] .= ' and (' . $field . ' != :' . $field . ')';
                    $where_data['bindings'][$field] = $value['value'];
                    break;
                case 'between':
                    $where_data['str'] .= ' and (' . $field . ' >= :' . $field . '_egt and ' . $field . ' <= :' . $field . '_elt)';
                    $where_data['bindings'][$field . '_egt'] = $value['value']['egt'];
                    $where_data['bindings'][$field . '_elt'] = $value['value']['elt'];
                    break;
                case 'in':
                    $in_data = [];
                    foreach ($value['value'] as $k => $data) {
                        $in_data[$field . '_' . $k] = "{$data}";
                    }
                    $where_data['str'] .= ' and ( ' . $field . ' in (:' . implode(',:', array_keys($in_data)) . '))';
                    $where_data['bindings'] = array_merge($where_data['bindings'], $in_data);
                    break;
                case 'like':
                    $where_data['str'] .= ' and ('.$field.' LIKE :'.$field.')';
                     $fieldWord = "%".$value['value']."%";
                    $where_data['bindings'][$field] = $fieldWord;
                    break;
            }
        }
        return $where_data;
    }

    /*
     * @todo 订单确认
     */
    public static function OrderConfirm($order_data)
    {
        if (empty($order_data)) {
            return false;
        }
        $where = [
            'confirm_status' => 1,
            'order_id' => $order_data['order_id']
        ];
        $update_order_data = [
            'confirm_status' => 2,
        ];
        $res = self::OrderUpdate($where, $update_order_data);
        return $res;
    }

    /*
     * @todo 订单退款确认
     */
    public static function OrderRefundConfirm($refund_order_data)
    {
        if (empty($refund_order_data)) {
            return false;
        }
        app('db')->beginTransaction();
        //主单进行支付
        $update_order_data = [
            'pay_status' => 3,
            'last_modified' => time()
        ];
        $where = [
            'order_id' => $refund_order_data['order_id'],
            'pay_status' => 2,
        ];
        $res = self::OrderUpdate($where, $update_order_data);
        if (!$res) {
            app('db')->rollBack();
            return false;
        }
        //获取订单下有所折分订单进行支付
        $split_order_list = self::GetSplitOrderByRootPId($refund_order_data['order_id']);
        if (!empty($split_order_list)) {
            foreach ($split_order_list as $split_order) {
                $update_order_data = [
                    'pay_status' => 3,
                    'last_modified' => time()
                ];
                $where = [
                    'order_id' => $split_order->order_id,
                    'pay_status' => 2,
                ];
                $res = self::OrderUpdate($where, $update_order_data);
                if (!$res) {
                    app('db')->rollBack();
                    return false;
                }
            }
        }
        app('db')->commit();
        return true;
    }

    /*
    * @todo 超时支付重新抛单
    */
    public static function TimeoutPayOrderReTry($order_id)
    {
        $sql = "update `server_orders` set `status`=1,`last_modified`=:last_modified where `order_id`=:order_id "
            . "and `pay_status`=2 "
            . "and `status`=2 "
            . "and `confirm_status`=2 "
            . "and `wms_order_bn`='' "
            . "and `create_source`='main' "
            . "and `pay_time`>`cancel_time`";

        $res = app('api_db')->update($sql, ['last_modified' => time(), 'order_id' => $order_id]);
        return $res;
    }

    public static function GetTimeoutPayOrders($start_time, $end_time)
    {
        $sql = "select * from `server_orders` where "
            . "`pay_status`=2 "
            . "and `status`=2 "
            . "and `refund_status`=0 "
            . "and `wms_order_bn`='' "
            . "and `create_source`='main' "
            . "and `pay_time`>`cancel_time` "
            . "and `pay_time`>=:start_time and `pay_time`<=:end_time";

        $res = app('api_db')->select($sql, ['start_time' => $start_time, 'end_time' => $end_time]);
        return $res;
    }

    public static function GetTimeoutUncompleteOrders($start_time, $end_time)
    {
        $sql = "select * from `server_orders` where "
            . "`pay_status`=2 "
            . "and `status`=1 "
            . 'and company_id in(2246,2343,16545,16546,16547,16548,20812,30067,37642,37763,42222,43054,43185,43187,43765,34335) '
            . "and `delivery_time`>=:start_time and `delivery_time`<=:end_time "
            . "and `split`=1";

        $res = app('api_db')->select($sql, ['start_time' => $start_time, 'end_time' => $end_time]);
        return $res;
    }

    public static function GetTimeoutShipOrders($create_time, $days = 30)
    {
        $begin_time = $create_time - $days * 24 * 3600;
        $sql = "select * from `server_orders` where "
            . "`pay_status`=2 "
            //. "and `confirm_status`=2 "
            . "and `status`=1 "
            . "and `ship_status`=1 "
            . "and `split`='1' "
            . 'and company_id in(2246,2343,16545,16546,16547,16548,20812,30067,37642,37763,42222,43054,43185,43187,43765,34335) '
            . "and `pay_time`<=:create_time and `pay_time`>=:begin_time";
        $res = app('api_db')->select($sql, ['create_time' => $create_time, 'begin_time' => $begin_time]);
        return $res;
    }

    /**
     * 获取异常订单消息
     */
    public static function GetExceptionOrderMsgListForMis(
        $filed = '*',
        $filter = [],
        $limit = 20,
        $need_total = false,
        $order = 'id desc'
    ) {
        $results = [
            'order_list' => [],
        ];
        $where_data = [];
        if (!empty($filter)) {
            $where_str = '1';
            if (isset($filter['order_id'])) {
                $where_str .= " and `order_id`=:order_id";
                $where_data['order_id'] = $filter['order_id'];
            }
            if (isset($filter['msg_status'])) {
                $where_str .= " and `status`=:msg_status";
                $where_data['msg_status'] = $filter['msg_status'];
            }
            if (isset($filter['msg_type'])) {
                $where_str .= " and `type`=:msg_type";
                $where_data['msg_type'] = $filter['msg_type'];
            }
            if (isset($filter['msg_wms_order_status'])) {
                if (is_array($filter['msg_wms_order_status'])) {
                    $key_name_str = '';
                    foreach ($filter['msg_wms_order_status'] as $key => $val) {
                        $key = 'bind_key_' . $key;
                        $key_name_str .= ',:' . $key;
                        $where_data[$key] = $val;
                    }
                    $msg_wms_order_status_vals = substr($key_name_str, 1);
                    $where_str .= " and `wms_order_status` in ({$msg_wms_order_status_vals})";
                } else {
                    $where_str .= " and `wms_order_status`=:msg_wms_order_status";
                    $where_data['msg_wms_order_status'] = $filter['msg_wms_order_status'];
                }
            }
        } else {
            $where_str = '1';
        }

        $limit = empty($limit) ? '' : ' limit ' . $limit;
        $sql = "select {$filed} from server_order_exception_msg where {$where_str} order by {$order} {$limit}";
        $results['order_list'] = app('api_db')->select($sql, $where_data);
        if ($need_total) {
            $sql = "select count(1) as total from server_order_exception_msg where {$where_str}";
            $order_total = app('api_db')->selectOne($sql, $where_data);
            $results['total'] = empty($order_total) ? 0 : $order_total->total;
        }

        return $results;
    }

    /**
     * 保存订单履约异常消息
     */
    public static function UpdateWmsOrderMsg($update_data)
    {
        $order_info = self::GetOrderInfoById($update_data['order_id']);
        if (is_object($order_info) && in_array($order_info->status, [2, 3] && $update_data['type'] == 3)) {
            return false;
        }

        $update_data['create_time'] = date('Y-m-d H:i:s');
        is_null($update_data['pid']) && ($update_data['pid'] = '');
        $keys = '`' . implode('`,`', array_keys($update_data)) . '`';
        $vals = ':' . implode(',:', array_keys($update_data));

        $sql = "select * from server_order_exception_msg where order_id=:order_id";
        $record = app('api_db')->selectOne($sql, ['order_id' => $update_data['order_id']]);
        if (!empty($record)) {
            if ($update_data['type'] == 3 && $record->type == 3 && in_array($record->status, [1, 2])) {
                //未发货忽略时间与本次未发货上报时间小于两天时,不再上报
                if ((time() - strtotime($record->create_time)) < 172800) {
                    return false;
                }
            }

            //超时未发货消息忽略覆盖未处理的履约消息
            if ($update_data['type'] == 3 && $record->type == 1) {
                if ($record->status == 0) {
                    return false;
                } else {
                    $update_data['wms_order_status'] = '';
                }
            }


            $update_data['status'] = 0;
            return app('api_db')->table('server_order_exception_msg')
                ->where(['id' => $record->id])
                ->update($update_data);
        }
        $sql = "insert into server_order_exception_msg({$keys}) "
            . "values({$vals});";
        return app('api_db')->insert($sql, $update_data);
    }

    /**
     *
     * 更新订单履约消息状态
     *
     * @param string $id 1,2,3,4,
     * @param $status
     * @param string $operator
     * @param string $reason
     *
     * @return mixed
     */
    public static function UpdateWmsOrderMsgStatusById($id, $status, $operator = '', $reason = '')
    {
        $sql = "update server_order_exception_msg set `status`=:status,`operator`=:operator,`reason`=:reason, `operator_time`=:operator_time where `id` in (" . $id . ')';
        return app('api_db')->update($sql,
            ['status' => $status, 'operator' => $operator, 'reason' => $reason, 'operator_time' => time()]);
    }

    /**
     * 获取消息统计
     */
    public static function GetMsgStats()
    {
        $result = [];

        $sql = "select count(1) as total from server_order_exception_msg where `status`=0";
        $rs = app('api_db')->selectOne($sql);
        $result['exception_order_msg_count'] = empty($rs) ? 0 : $rs->total;
        /*
        $sql = "select count(1) as total from server_orders where ((`pay_status`=2 and `confirm_status`=1 and `wms_code`='SALYUT' and `memo` != '') or `hung_up_status`=1)"
                . " and `status`=1 and `split`=1";
         *
         */
        $sql = "select count(1) as total from server_intercept_orders where 1";
        $rs = app('api_db')->selectOne($sql);
        $result['intercept_order_count'] = empty($rs) ? 0 : $rs->total;

        return $result;
    }

    /**
     * 保存申请订单暂停数据
     */
    public static function SaveApplyOrderPauseData($update_data)
    {
        $update_data['create_at'] = isset($update_data['create_at']) ? $update_data['create_at'] : time();
        return app('api_db')->table('server_apply_pause_orders')->insert($update_data);
    }

    /**
     * 更新申请订单暂停数据
     * @param type $order_id
     * @param type $status
     * @param type $reason
     * @return type
     */
    public static function UpdateApplyOrderPauseData($order_id, $status, $reason = '')
    {
        $sql = "update server_apply_pause_orders "
            . "set `status`=:status,`reason`=:reason,`update_at`=:update_at where `order_id`=:order_id";
        return app('api_db')->update($sql, [
            'order_id' => $order_id,
            'status' => $status,
            'reason' => $reason,
            'update_at' => time()
        ]);
    }

    /**
     * 更新订单暂停、恢复暂停状态
     */
    public static function UpdateOrderHungupStatus($order_id, $hung_up_status, $old_hung_up_status)
    {
        $sql = "update server_orders set `hung_up_status`=:hung_up_status,`last_modified`=:last_modified"
            . " where `order_id`=:order_id and `hung_up_status`=:old_hung_up_status";
        return app('api_db')->update($sql, [
            'order_id' => $order_id,
            'hung_up_status' => $hung_up_status,
            'last_modified' => time(),
            'old_hung_up_status' => $old_hung_up_status
        ]);
    }

    /**
     * 更新订单为已取消For Mis
     * @param type $order_id
     * @return type
     */
    public static function UpdateOrderToCancelStatus($order_id)
    {
        $sql = "update server_orders set `status`=2,`last_modified`=:last_modified, `cancel_time`=:cancel_time"
            . " where `order_id`=:order_id and `status`=1";
        return app('api_db')->update(
            $sql,
            [
                'order_id' => $order_id,
                'last_modified' => time(),
                'cancel_time' => time()
            ]
        );
    }

    /**
     * 获取申请订单暂停数据
     */
    public static function GetApplyOrderPauseData($order_id, $status = 0)
    {
        $sql = "select * from server_apply_pause_orders where `order_id`=:order_id and `status`=:status";
        return app('api_db')->selectOne($sql, ['order_id' => $order_id, 'status' => $status]);
    }

    /**
     * 获取暂停订单申请列表
     */
    public static function GetPauseList(
        $filed = '*',
        $filter = [],
        $limit = 20,
        $need_total = false,
        $order = 'create_at desc'
    ) {
        $results = [
            'order_list' => [],
        ];
        $where_data = [];
        if (!empty($filter)) {
            if (isset($filter['order_id'])) {
                $where_str = "`order_id`=:order_id";
                $where_data['order_id'] = $filter['order_id'];
            }
        } else {
            $where_str = 1;
        }

        $limit = empty($limit) ? '' : ' limit ' . $limit;
        $sql = "select {$filed} from server_apply_pause_orders where {$where_str} order by {$order} {$limit}";

        $results['order_list'] = app('api_db')->select($sql, $where_data);
        if ($need_total) {
            $sql = "select count(1) as total from server_apply_pause_orders where {$where_str}";
            $order_total = app('api_db')->selectOne($sql, $where_data);
            $results['total'] = empty($order_total) ? 0 : $order_total->total;
        }

        return $results;
    }

    /**
     * 获取拦截订单和暂停成功订单列表
     */
    public static function GetInterceptAndHungupSuccessList(
        $filed = '*',
        $filter = [],
        $limit = 20,
        $need_total = false,
        $order = 'create_time desc'
    ) {
        $results = [
            'order_list' => [],
        ];
        $where_data = [];
        if (!empty($filter)) {
            $where_str = '1';
            if (isset($filter['order_id'])) {
                $where_str .= " and `order_id`=:order_id";
                $where_data['order_id'] = $filter['order_id'];
            }
            $where_str .= " and ((`pay_status`=2 and `confirm_status`=1 and `wms_code`='SALYUT' and `memo` != '') or `hung_up_status`=1) and `status`=1 and `split`=1";
        } else {
            $where_str = "((`pay_status`=2 and `confirm_status`=1 and `wms_code`='SALYUT' and `memo` != '')  or `hung_up_status`=1 ) and `status`=1 and `split`=1";
        }

        //支付方式为credit/mcredit,跳过筛选
        $where_str .= " and payment not in('credit','mcredit')";

        $limit = empty($limit) ? '' : ' limit ' . $limit;
        $sql = "select {$filed} from server_orders where {$where_str} order by {$order} {$limit}";
        $results['order_list'] = app('api_db')->select($sql, $where_data);
        if ($need_total) {
            $sql = "select count(1) as total from server_orders where {$where_str}";
            $order_total = app('api_db')->selectOne($sql, $where_data);
            $results['total'] = empty($order_total) ? 0 : $order_total->total;
        }
        return $results;
    }

    /**
     * 获取拦截订单和暂停成功订单列表
     */
    public static function GetInterceptAndHungupSuccessListV2(
        $filed = 'O.*',
        $filter = [],
        $limit = 20,
        $need_total = false,
        $order = 'id desc'
    ) {
        $results = [
            'order_list' => [],
        ];
        $where_data = [];
        $where_str = '1';
        if (!empty($filter)) {
            if (isset($filter['order_id'])) {
                $where_str .= " and I.`order_id`=:order_id";
                $where_data['order_id'] = $filter['order_id'];
            }
        }

        $limit = empty($limit) ? '' : ' limit ' . $limit;
        $sql = "select {$filed} from server_intercept_orders I join server_orders O on I.order_id=O.order_id where {$where_str} order by {$order} {$limit}";
        $results['order_list'] = app('api_db')->select($sql, $where_data);
        if ($need_total) {
            $sql = "select count(1) as total from server_intercept_orders I where {$where_str}";
            $order_total = app('api_db')->selectOne($sql, $where_data);
            $results['total'] = empty($order_total) ? 0 : $order_total->total;
        }
        return $results;
    }

    public static function addInterceptOrder($order_id)
    {
        return app('api_db')->insert("replace into server_intercept_orders(`order_id`,`create_time`) "
            . "values(:order_id, :current_time);", ['order_id' => $order_id, 'current_time' => time()]);
    }

    public static function delInterceptOrder($order_id)
    {
        return app('api_db')->delete("delete from server_intercept_orders where `order_id`=:order_id",
            ['order_id' => $order_id]);
    }

    /**
     * 拆单保存For Mis
     * @param type $new_split_order
     * @return boolean
     */
    public static function doSaveSplitOrder($new_split_order)
    {
        foreach ($new_split_order as $split_order) {
            $res = self::AddOrder($split_order);
            if (!$res) {
                return false;
            }
        }
        return true;
    }

    /**
     * 根据订单号锁定一组订单
     * @param type $order_ids
     * @return boolean
     */
    public static function LockOrderByIds($order_ids)
    {
        if (empty($order_ids)) {
            return false;
        }
        //开启事务
        app('db')->beginTransaction();
        $sql = "update server_orders set `is_locked`=1 where `order_id` in(" . implode(',',
                array_fill(0, count($order_ids), '?')) . ") and `is_locked`=0";
        $affect_rows = app('api_db')->update($sql, array_values($order_ids));
        if ($affect_rows > 0 && $affect_rows == count($order_ids)) {
            app('db')->commit();
            return true;
        }

        app('db')->rollBack();
        return false;
    }

    /**
     * 根据订单号解除锁定一组订单
     * @param type $order_ids
     * @return boolean
     */
    public static function UnLockOrderByIds($order_ids)
    {
        if (empty($order_ids)) {
            return false;
        }
        //开启事务
        app('db')->beginTransaction();
        $sql = "update server_orders set `is_locked`=0 where `order_id` in(" . implode(',',
                array_fill(0, count($order_ids), '?')) . ") and `is_locked`=1";
        $affect_rows = app('api_db')->update($sql, array_values($order_ids));
        if ($affect_rows > 0 && $affect_rows == count($order_ids)) {
            app('db')->commit();
            return true;
        }

        app('db')->rollBack();
        return false;
    }

    /**
     * @param $root_pid
     *
     * @return array
     */
    public static function GetOrderListByRootPId($root_pid)
    {
        if (empty($root_pid)) {
            return [];
        }

        return app('api_db')->table('server_orders')->where(array('root_pid' => $root_pid))->get()->all();
    }

    /**
     * @param $pid
     *
     * @return array
     */
    public static function GetOrderListByPid($pid)
    {
        if (empty($pid)) {
            return [];
        }

        return app('api_db')->table('server_orders')->where(array('pid' => $pid))->get()->all();
    }
}
