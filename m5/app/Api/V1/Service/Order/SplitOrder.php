<?php

namespace App\Api\V1\Service\Order;

use App\Api\Logic\Service as ServiceLogic;
use App\Api\Model\Order\Order as Order;

class SplitOrder
{
    /**
     * 检查商品有效性
     */
    public function checkProductList(&$product_list, $order_items)
    {
        $sku_list = [];
        foreach ($product_list['valid'] as &$split_order_data) {
            foreach ($split_order_data as $bn => $sku_info) {
                if (!isset($sku_list["{$bn}"])) {
                    $sku_list["{$bn}"] = $sku_info['num'];
                } else {
                    $sku_list["{$bn}"] += $sku_info['num'];
                }
                $split_order_data[$bn]['bn'] = $bn;
            }
        }

        foreach ($product_list['invalid'] as $bn => $sku_info) {
            if (!isset($sku_list["{$bn}"])) {
                $sku_list["{$bn}"] = $sku_info['num'];
            } else {
                $sku_list["{$bn}"] += $sku_info['num'];
            }
            $product_list['invalid'][$bn]['bn'] = $bn;
        }

        if (count($sku_list) != count($order_items)) {
            return $this->Response(201, '分隔订单商品明细与原始订单明细不匹配');
        }

        $excption_skus = [];
        foreach ($order_items as $item) {
            if ($item->nums != $sku_list["{$item->bn}"]) {
                $excption_skus[] = $item->bn;
            }
        }

        if (count($excption_skus) > 0) {
            return $this->Response(202, '分隔订单商品明细与原始订单明细商品数量不匹配', $excption_skus);
        }

        return $this->Response(200, '成功');
    }

    /**
     * 保存分隔订单
     * @param type $order_info
     * @param type $order_items
     * @param type $product_list
     * @return type
     */
    public function saveSplitOrders($order_info, $order_items, $product_list)
    {
        app('db')->beginTransaction();

        $order_id = $order_info->order_id;

        //直接用原始订单明细商品所占金额百分比，计算优惠
        //只能创建子订单并挂在该订单所在的主单下面
        //并标记无效的订单为取消订单
        //返回新建子订单信息列表
        //1.该待拆分订单标记为已拆分
        {
            $where = [
                'status' => 1,
                'order_id' => $order_id,
                'split' => 1,
            ];
            $update_order_data = [
                'split' => 2,
                'status' => 2,
            ];
            $res = Order::OrderUpdate($where, $update_order_data);
            if (!$res) {
                app('db')->rollBack();
                return $this->Response(601, '更新待拆分订单为拆分订单失败');
            }
        }

        //2.组织子订单数据
        $ret_data = [];
        $split_order_list = $this->generateSplitOrderData($order_info, $order_items, $product_list, $ret_data);

        //3.库存锁定
        foreach ($split_order_list as $split_order) {
            if ($son_order['create_source'] == 'wms_order') {
                $lock_stock_resp = $this->LockStock($split_order);
                if ($lock_stock_resp['error_code'] != 200) {
                    app('db')->rollBack();
                    return $this->Response($lock_stock_resp['error_code'], $lock_stock_resp['error_msg'],
                        $lock_stock_resp['data']);
                }
            }
        }

        //4.保存子订单数据
        $bool_val = Order::doSaveSplitOrder($split_order_list);
        if ($bool_val == false) {
            app('db')->rollBack();
            return $this->Response(602, '保存拆单结果失败');
        }
        app('db')->commit();
        return $this->Response(200, '成功', $ret_data);
    }

    private function generateSplitOrderData($order_info, $order_items, $product_list, &$ret_data)
    {
        $split_order_list = [];

        $order_items_index = [];
        foreach ($order_items as $item) {
            $order_items_index["{$item->bn}"] = $item;
        }
        //生成分隔订单
        foreach ($product_list['valid'] as $valid_son_order_sku_list) {
            $split_order = $this->SplitOrderGenerate($order_info, $order_items_index, $valid_son_order_sku_list, true,
                'wms_order');
            $ret_data['valid'][] = $split_order['order_id'];
            $split_order_list[] = $split_order;
        }

        if (isset($product_list['invalid']) && !empty($product_list['invalid'])) {
            $invalid_son_order_sku_list = $product_list['invalid'];
            $split_order = $this->SplitOrderGenerate($order_info, $order_items_index, $invalid_son_order_sku_list,
                false, 'wms_order');
            $ret_data['invalid'][] = $split_order['order_id'];
            $split_order_list[] = $split_order;
        }
        return $split_order_list;
    }

    /**
     * 锁定库存
     * @param type $order_data
     * @return type
     */
    public function LockStock($order_data)
    {
        //提取货品数据进行锁库存
        $product_list = [];
        foreach ($order_data['items'] as $item) {
            $product_list[] = [
                'product_bn' => $item['bn'],
                'count' => $item['nums']
            ];
        }
        $stock_lock_data = [
            'lock_type' => 'service_order',
            'lock_obj' => $order_data['order_id'],
            'channel' => $order_data['channel'],
            'area' => array(
                'province' => $order_data['ship_province'],
                'city' => $order_data['ship_city'],
                'county' => $order_data['ship_county'],
                'town' => $order_data['ship_town'],
            ),
            'product_list' => $product_list
        ];
        $service_logic = new ServiceLogic;
        $stock_response = $service_logic->ServiceCall('stock_temp_lock', $stock_lock_data);
        //锁库存失败
        if ($stock_response['error_code'] != 'SUCCESS') {
            $msg = !empty($stock_response['error_msg']) ? $stock_response['error_detail_code'] . ':' . $stock_response['error_msg'][0] : '锁库存失败';
            return $this->Response(304, $msg, $product_list);
        }
        return $this->Response(200, '成功');
    }

    protected function SplitOrderGenerate(
        $order_info,
        $order_items_index,
        $son_order_sku_list,
        $valid = true,
        $create_source = 'wms_order'
    ) {
        $server_concurrent = new Concurrent();
        $cost_item = 0;    //商品总金额
        $cost_freight = 0;    //快递总金额
        $cost_tax = 0;    //税费金额
        $point_amount = 0;    //积分支付金额
        $pmt_amount = 0;    //订单优惠金额
        $weight = 0;    //重量
        $new_split_order_items = [];
        foreach ($son_order_sku_list as $bn => $item) {
            $order_info_item = get_object_vars($order_items_index[$bn]);
            $item_cost_item = round($order_info_item['price'] * $item['num'], 2);
            if ($order_info_item['amount'] > 0) {
                $item_pmt_amount = round(($item_cost_item / $order_info_item['amount']) * $order_info_item['pmt_amount'],
                    2);
                $item_point_amount = round(($item_cost_item / $order_info_item['amount']) * $order_info_item['point_amount'],
                    2);
                $item_cost_freight = round(($item_cost_item / $order_info_item['amount']) * $order_info_item['cost_freight'],
                    2);
                $item_cost_tax = round(($item_cost_item / $order_info_item['amount']) * $order_info_item['cost_tax'],
                    2);
            } else {
                $item_pmt_amount = 0;
                $item_point_amount = 0;
                $item_cost_freight = 0;
                $item_cost_tax = 0;
            }
            $new_split_order_items[] = [
                'bn' => $item['bn'],
                'p_bn' => '',
                'name' => $order_info_item['name'],
                'cost' => $order_info_item['cost'],
                'price' => $order_info_item['price'],
                'mktprice' => $order_info_item['mktprice'],
                'amount' => $item_cost_item,
                'weight' => $order_info_item['weight'] * $item['num'],
                'nums' => $item['num'],
                'pmt_amount' => $item_pmt_amount,
                'cost_freight' => $item_cost_freight,
                'point_amount' => $item_point_amount,
                'cost_tax' => $item_cost_tax,
                'item_type' => $order_info_item['item_type'],
            ];
            $cost_item += $item_cost_item;
            $cost_tax += $item_cost_tax;
            $cost_freight += $item_cost_freight;
            $point_amount += $item_point_amount;
            $pmt_amount += $item_pmt_amount;
            $weight += $order_info_item['weight'] * $item['num'];
        }
        $order_id = $server_concurrent->GetOrderId();
        $new_split_order = [
            'order_id' => $order_id,
            'pid' => $order_info->order_id,
            'root_pid' => $order_info->root_pid,
            'create_source' => $create_source,
            'final_amount' => $cost_item + $cost_tax + $cost_freight - $pmt_amount,   //订单总金额(商品总金额+商品税费+快递总金额 - 优惠总金额)
            'cur_money' => ($cost_item + $cost_freight + $cost_tax) - $pmt_amount - $point_amount,  //现金需支付总额
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
            'cost_tax' => $cost_tax,
            'point_channel' => empty($order_info->point_channel) ? '' : $order_info->point_channel,
            'pmt_amount' => $pmt_amount,
            'payed' => ($cost_item + $cost_freight + $cost_tax) - $pmt_amount - $point_amount,
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
            'wms_delivery_bn' => '',
            'ship_status' => 1,
            'status' => $valid ? $order_info->status : 2,
            'confirm_status' => $order_info->confirm_status,
            'hung_up_status' => $order_info->hung_up_status,
            'items' => $new_split_order_items,
        ];

        return $new_split_order;
    }

    private function Response($error_code, $error_msg, $data = [])
    {
        return [
            'error_code' => $error_code,
            'error_msg' => $error_msg,
            'data' => $data,
        ];
    }

}
