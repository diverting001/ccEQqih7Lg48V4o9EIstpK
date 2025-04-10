<?php

namespace App\Api\V1\Service\Order;

use App\Api\Model\Order\Order as OrderModel;
use App\Api\Logic\Mq as Mq;
use App\Api\Model\Order\Concurrent as MdlConcurrent;
use App\Api\Model\Order\OrderPayLog;


/*
 * @todo 订单创建
 */

class Push
{
    public $error = '';

    public function Push($order_data)
    {
        if (empty($order_data['order_id'])) {
            $this->error = '订单号不能为空';
            return false;
        }
        if (empty($order_data['items'])) {
            $this->error = '订单明细不能为空';
            return false;
        }
        if (empty($order_data['member_id'])) {
            $this->error = '用户id不能为空';
            return false;
        }
        if (empty($order_data['company_id'])) {
            $this->error = '公司id不能为空';
            return false;
        }

        //商品详细
        $main_items = [];
        foreach ($order_data['items'] as $item) {
            $main_items[$item['product_bn']] = [
                'bn' => $item['product_bn'],
                'p_bn' => '',
                'name' => $item['name'],
                'cost' => $item['cost'],
                'price' => $item['price'],
                'mktprice' => $item['mktprice'],
                'amount' => $item['amount'],
                'weight' => $item['weight'],
                'nums' => $item['nums'],
                'pmt_amount' => $item['pmt_amount'],
                'cost_freight' => $item['cost_freight'],
                'point_amount' => $item['point_amount'],
                'item_type' => $item['item_type'],
            ];
        }
        $save_data = [
            //基础信息
            'order_id' => $order_data['order_id'],
            'pid' => '',
            'root_pid' => $order_data['order_id'],
            'create_source' => 'main',
            'member_id' => $order_data['member_id'],
            'company_id' => $order_data['company_id'],
            'create_time' => $order_data['create_time'],
            'last_modified' => $order_data['last_modified'],
            'ship_province' => $order_data['ship_province'],
            'ship_city' => $order_data['ship_city'],
            'ship_county' => $order_data['ship_county'],
            'ship_town' => $order_data['ship_town'],
            'ship_name' => $order_data['ship_name'],
            'idcardname' => $order_data['idcardname'],
            'idcardno' => $order_data['idcardno'],
            'ship_addr' => $order_data['ship_addr'],
            'ship_zip' => $order_data['ship_zip'],
            'ship_tel' => $order_data['ship_tel'],
            'ship_mobile' => $order_data['ship_mobile'],
            'terminal' => $order_data['terminal'],
            'anonymous' => $order_data['anonymous'],
            'business_code' => $order_data['business_code'],
            'extend_info_code' => $order_data['extend_info_code'],
            'order_category' => $order_data['order_category'],
            'business_project_code' => $order_data['business_project_code'],
            'system_code' => $order_data['system_code'],
            'memo' => empty($order_data['memo']) ? '' : $order_data['memo'],
            'extend_data' => $order_data['extend_data'],
            'payment_restriction' => $order_data['payment_restriction'],
            'pay_status' => $order_data['pay_status'],
            'status' => $order_data['status'],
            'ship_status' => $order_data['ship_status'],
            //订单金额信息
            'final_amount' => $order_data['final_amount'],
            'cur_money' => $order_data['cur_money'],
            'point_amount' => $order_data['point_amount'],
            'point_channel' => empty($order_data['point_channel']) ? '' : $order_data['point_channel'],
            'cost_freight' => $order_data['cost_freight'],
            'cost_item' => $order_data['cost_item'],
            'pmt_amount' => $order_data['pmt_amount'],
            //其它
            'weight' => $order_data['weight'],
            'pop_owner_id' => $order_data['pop_owner_id'],
            'channel' => 'EC',
            'wms_code' => empty($order_data['wms_code']) ? 'EC' : $order_data['wms_code'],
            'wms_order_bn' => $order_data['order_id'],
            //商品明细
            'items' => $main_items,
        ];
        //未支付订单无履约平台订单号
        if ($save_data['pay_status'] == 1) {
            unset($save_data['wms_order_bn']);
        }
        $pay_log_data = [];
        //订单支付
        if ($save_data['pay_status'] == 2 || $save_data['pay_status'] == 3) {
            $save_data['payment'] = $order_data['pay_info']['pay_name'];
            $save_data['payed'] = $order_data['pay_info']['pay_money'];
            $save_data['pay_time'] = $order_data['pay_info']['pay_time'];
            //订单支付日志
            $extend_data = [
                'pay_time' => $order_data['pay_info']['pay_time'],
                'pay_money' => $order_data['pay_info']['pay_money'],
            ];
            $pay_log_data = [
                'order_id' => $order_data['order_id'],
                'pay_name' => $order_data['pay_info']['pay_name'],
                'trade_no' => $order_data['pay_info']['trade_no'],
                'payment_id' => $order_data['pay_info']['payment_id'],
                'payment_system' => $order_data['pay_info']['payment_system'],
                'create_time' => time(),
                'extend_data' => json_encode($extend_data),
            ];
        }
        //订单取消
        if ($save_data['status'] == 2) {
            $save_data['cancel_time'] = $order_data['log_list']['cancel']['alttime'];
        }
        //订单完成
        if ($save_data['status'] == 3) {
            $save_data['delivery_time'] = $order_data['log_list']['delivery']['alttime'];
            $save_data['finish_time'] = $order_data['log_list']['finish']['alttime'];
        }
        //订单发货
        if ($save_data['ship_status'] == 2) {
            $save_data['logi_name'] = empty($order_data['logi_name']) ? '' : $order_data['logi_name'];
            $save_data['logi_no'] = empty($order_data['logi_no']) ? '' : $order_data['logi_no'];
            $save_data['logi_code'] = empty($order_data['logi_code']) ? '' : $order_data['logi_code'];
            $save_data['wms_delivery_bn'] = empty($order_data['delivery_id']) ? '' : $order_data['delivery_id'];
        }
        //是否拆分
        if (!empty($order_data['delivery_list'])) {
            foreach ($order_data['delivery_list'] as $delivery) {
                $new_order[] = $this->SplitOrderGenerate($save_data, $delivery);
            }

        }
        if (!empty($new_order)) {
            $save_data['split'] = 2;
        }
        $new_order[] = $save_data;
        $res = OrderModel::SaveOrderAll($new_order);
        if (!empty($pay_log_data)) {
            OrderPayLog::SaveLog($pay_log_data);
        }
        return $res;
    }


    protected function SplitOrderGenerate($order_info, $son_order)
    {
        $server_concurrent = new Concurrent();
        if (empty($order_info) || empty($son_order)) {
            return [];
        }
        $cost_item = 0;    //商品总金额
        $cost_freight = 0;    //快递总金额
        $point_amount = 0;    //积分支付金额
        $pmt_amount = 0;    //订单优惠金额
        $weight = 0;    //重量
        $new_split_order_items = [];
        foreach ($son_order['items'] as $item) {
            $order_info_itme = $order_info['items'][$item['product_bn']];
            if (empty($order_info_itme)) {
                $this->error = '拆分订单商品明细为空';
                return false;
            }
            $item_cost_item = $order_info_itme['price'] * $item['nums'];
            $item_pmt_amount = ($item_cost_item / $order_info_itme['amount']) * $order_info_itme['pmt_amount'];
            $item_point_amount = ($item_cost_item / $order_info_itme['amount']) * $order_info_itme['point_amount'];
            $item_cost_freight = ($item_cost_item / $order_info_itme['amount']) * $order_info_itme['cost_freight'];
            $new_split_order_items[] = [
                'bn' => $item['product_bn'],
                'p_bn' => '',
                'name' => $order_info_itme['name'],
                'cost' => $order_info_itme['cost'],
                'price' => $order_info_itme['price'],
                'mktprice' => $order_info_itme['mktprice'],
                'amount' => $item_cost_item,
                'weight' => $order_info_itme['weight'] * $item['nums'],
                'nums' => $item['nums'],
                'pmt_amount' => $item_pmt_amount,
                'cost_freight' => $item_cost_freight,
                'point_amount' => $item_point_amount,
                'item_type' => $order_info_itme['item_type'],
            ];
            $cost_item += $item_cost_item;
            $cost_freight += $item_cost_freight;
            $point_amount += $item_point_amount;
            $pmt_amount += $item_pmt_amount;
            $weight += $order_info_itme['weight'] * $item['nums'];
        }
        $new_split_order = [
            'order_id' => $this->GetOrderId($order_info['create_time']),
            'pid' => $order_info['order_id'],
            'root_pid' => $order_info['order_id'],
            'create_source' => 'split_order',
            'final_amount' => $cost_item + $cost_freight,   //订单总金额(商品总金额+快递总金额)
            'cur_money' => ($cost_item + $cost_freight) - $point_amount,  //现金需支付总额
            'member_id' => $order_info['member_id'],
            'company_id' => $order_info['company_id'],
            'create_time' => $order_info['create_time'],
            'pay_time' => intval($order_info['pay_time']),
            'last_modified' => time(),
            'stplit_create_time' => time(),
            'ship_province' => $order_info['ship_province'],
            'ship_city' => $order_info['ship_city'],
            'ship_county' => $order_info['ship_county'],
            'ship_town' => $order_info['ship_town'],
            'ship_name' => $order_info['ship_name'],
            'idcardname' => $order_info['idcardname'],
            'idcardno' => $order_info['idcardno'],
            'weight' => $weight,
            'ship_addr' => $order_info['ship_addr'],
            'ship_zip' => $order_info['ship_zip'],
            'ship_tel' => $order_info['ship_tel'],
            'ship_mobile' => $order_info['ship_mobile'],
            'cost_item' => $cost_item,
            'cost_freight' => $cost_freight,
            'point_amount' => $point_amount,
            'point_channel' => empty($order_info['point_channel']) ? '' : $order_info['point_channel'],
            'pmt_amount' => $pmt_amount,
            'payed' => ($cost_item + $cost_freight) - $point_amount,
            'terminal' => $order_info['terminal'],
            'anonymous' => $order_info['anonymous'],
            'business_code' => $order_info['business_code'],
            'business_project_code' => $order_info['business_project_code'],
            'system_code' => $order_info['system_code'],
            'extend_info_code' => $order_info['extend_info_code'],
            'order_category' => $order_info['order_category'],
            'payment_restriction' => $order_info['payment_restriction'],
            'pay_status' => 2,
            'payment' => $order_info['payment'],
            'wms_order_bn' => $order_info['wms_order_bn'],
            'wms_code' => $order_info['wms_code'],
            'memo' => $order_info['memo'],
            'channel' => $order_info['channel'],
            'pop_owner_id' => $order_info['pop_owner_id'],
            //发货信息
            'wms_delivery_bn' => $son_order['delivery_id'],
            'ship_status' => $son_order['delivery_status'],
            'status' => $son_order['status'],
            'items' => $new_split_order_items
        ];
        //订单已完成
        if ($new_split_order['status'] == 3) {
            $new_split_order['finish_time'] = $order_info['finish_time'];
        }
        //已发货
        if ($new_split_order['ship_status'] == 2) {
            $new_split_order['delivery_time'] = $son_order['t_begin'];
            $new_split_order['logi_name'] = $son_order['logi_name'];
            $new_split_order['logi_no'] = $son_order['logi_no'];
            $new_split_order['logi_code'] = $son_order['logi_code'];
        }
        return $new_split_order;
    }


    public function UpdateOrder($order_id)
    {
        //获取订单
        $order_info = OrderModel::GetOrderInfoById($order_id);
        $class_name = 'App\\Api\\Logic\\OrderWMS\\' . ucfirst(strtolower($order_info->wms_code));
        if (!class_exists($class_name)) {
            $this->error = '未到可处理订单类';
            return false;
        }
        $class_obj = new $class_name();
        $wms_order_info = $class_obj->GetInfo($order_info->wms_order_bn);
        if (!$wms_order_info) {
            $this->error = '在履约平台未到订单';
        }
        $res = $this->OrderSplit($order_info, $wms_order_info);
        return $res;
    }

    /*
     * @todo 订单拆单
     */
    protected function OrderSplit($order_info, $wms_order_info)
    {
//        echo $order_info->order_id.'======';
        $new_split_order = [];   //新建拆分订单
        $cancel_split_order = [];   //取消拆分订单
        $update_order = [];   //更新订单
        $send_mq_list = [];   //需要发送消息列表
        if (empty($order_info) || empty($wms_order_info)) {
            $this->error = '履约平台数据为空';
            return false;
        }
        //查询订单items
        $order_items = [];
        $order_item_list = OrderModel::GetOrderItemsByOrderId($order_info->order_id);
        if (empty($order_item_list)) {
            $this->error = '未找到订单商品明细';
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
                    $this->error = '履约商品和原订单商品不一致';
                    return false;
                }
                if ($order_items[$item['product_bn']]->nums != $item['nums']) {
                    $wms_order_info['son_orders'] = $wms_order_info;
                    break;
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
        if (!in_array($wms_order_info['delivery_status'], array(1, 2, 3))) {
            $this->error = '主订单发货状态错误';
            return false;
        }
        if (!in_array($wms_order_info['status'], array(1, 2, 3))) {
            $this->error = '主订单状态错误';
            return false;
        }
        if (!empty($wms_order_info['son_orders'])) {
            //已拆分子订单
            foreach ($wms_order_info['son_orders'] as $son_order) {
                if (!in_array($son_order['delivery_status'], array(1, 2, 3))) {
                    $this->error = '子订单发货状态错误';
                    return false;
                }
                if (!in_array($son_order['status'], array(1, 2, 3))) {
                    $this->error = '子订单状态错误';
                    return false;
                }
                //验证商品是否正确
                if (empty($son_order['items'])) {
                    return false;
                }
                foreach ($son_order['items'] as $item) {
                    if (!isset($order_items[$item['product_bn']])) {
                        $this->error = '子订商品不存在';
                        return false;
                    }
                    if ($order_items[$item['product_bn']]->nums < $item['nums']) {
                        $this->error = '子订单商品数据错误';
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
                } else {
                    //不存在的商品进行新建拆分订单
                    $new_split_order[] = $this->SplitOrderGenerateUp($order_info, $son_order, $send_mq_list);
                }
            }
            //有拆分子订单,原订单设置为已拆分取消
            if ($order_info->status == 1 && $order_info->split == 1) {
                $cancel_split_order[] = $order_info->order_id;
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
                    }
                }
            }
            // 拆单成功
            if ( ! empty($new_split_order))
            {
                Mq::OrderSplit($order_info->order_id);
            }
        }
        return $res;
    }


    /*
     * @todo 创建一个新的拆分订单
     */
    protected function SplitOrderGenerateUp($order_info, $son_order, &$send_mq_list)
    {
        $server_concurrent = new Concurrent();
        if (empty($order_info) || empty($son_order)) {
            return [];
        }
        $cost_item = 0;    //商品总金额
        $cost_freight = 0;    //快递总金额
        $point_amount = 0;    //积分支付金额
        $pmt_amount = 0;    //订单优惠金额
        $weight = 0;    //重量
        $new_split_order_items = [];
        foreach ($son_order['items'] as $item) {
            $order_info_itme = $order_info->items[$item['product_bn']];
            if (empty($order_info_itme)) {
                return false;
            }
            $item_cost_item = $order_info_itme->price * $item['nums'];
            $item_pmt_amount = ($item_cost_item / $order_info_itme->amount) * $order_info_itme->pmt_amount;
            $item_point_amount = ($item_cost_item / $order_info_itme->amount) * $order_info_itme->point_amount;
            $item_cost_freight = ($item_cost_item / $order_info_itme->amount) * $order_info_itme->cost_freight;
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
                'point_amount' => $item_point_amount,
                'item_type' => $order_info_itme->item_type,
            ];
            $cost_item += $item_cost_item;
            $cost_freight += $item_cost_freight;
            $point_amount += $item_point_amount;
            $pmt_amount += $item_pmt_amount;
            $weight += $order_info_itme->weight * $item['nums'];
        }
        $new_split_order = [
            'order_id' => $this->GetOrderId($order_info->create_time),
            'pid' => $order_info->order_id,
            'root_pid' => $order_info->root_pid,
            'create_source' => 'split_order',
            'final_amount' => $cost_item + $cost_freight,   //订单总金额(商品总金额+快递总金额)
            'cur_money' => ($cost_item + $cost_freight) - $point_amount,  //现金需支付总额
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
            'idcardname' => $order_info->idcardname,
            'idcardno' => $order_info->idcardno,
            'weight' => $weight,
            'ship_addr' => $order_info->ship_addr,
            'ship_zip' => $order_info->ship_zip,
            'ship_tel' => $order_info->ship_tel,
            'ship_mobile' => $order_info->ship_mobile,
            'cost_item' => $cost_item,
            'cost_freight' => $cost_freight,
            'point_amount' => $point_amount,
            'pmt_amount' => $pmt_amount,
            'payed' => ($cost_item + $cost_freight) - $point_amount,
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
            $new_split_order['logi_code'] = $son_order['logi_code'];
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
                    $save_data['status'] = 2;
                    $save_data['cancel_time'] = time();
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
                if ($new_order['delivery_status'] == 2) {
                    $send_mq_list[] = [];
                    $update_order['ship_status'] = 2;
                    $update_order['delivery_time'] = time();
                    $update_order['logi_name'] = $new_order['logi_name'];
                    $update_order['logi_code'] = $new_order['logi_code'];
                    $update_order['logi_no'] = $new_order['logi_no'];
                    $update_order['wms_delivery_bn'] = $new_order['delivery_id'];
                    //订单发货消息
                    $send_mq_list[] = [
                        'type' => 'delivery',
                        'order_id' => $old_order->order_id,
                    ];
                }
                break;
            case 2: //已发货
                break;
            case 3: //已完成
                break;
        }
        return $update_order;
    }


    /*
     * @todo 获取可用的订单号
     */
    public function GetOrderId($time)
    {
        do {
            $order_id = date('YmdHis', $time) . rand(1000, 9999);
            $order_id_isset = MdlConcurrent::GetOrderId($order_id);
        } while ($order_id_isset);
        //保存记录
        MdlConcurrent::SaveOrderId($order_id);
        return $order_id;
    }

}
