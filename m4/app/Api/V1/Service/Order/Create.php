<?php

namespace App\Api\V1\Service\Order;

use App\Api\Model\Order\Order as Order;
use App\Api\Model\Order\OrderLog as OrderLog;
use App\Api\Logic\Service as Service;
use App\Api\Logic\Salyut\Orders as SalyutOrder;
use App\Api\Logic\Shop\Orders as ShopOrder;
use App\Api\Logic\Mq as Mq;

/*
 * @todo 订单创建
 */

class Create
{

    /*
     * @todo 保存订单
     */
    public function Create($order_data)
    {
        $msg = '';
        //日志记录
        $funcgion_log = function ($method_name, $success, $msg) use ($order_data) {
            $log_array = array(
                'action' => $method_name,
                'success' => $success ? 1 : 0,
                'data' => json_encode($order_data),
                'bn' => $order_data['order_id'],
                'remark' => $msg,
            );
            \Neigou\Logger::General('service_order_create', $log_array);
        };
        //检查订单数据
        $cherck_res = $this->CheckOrder($order_data, $msg);
        if (!$cherck_res) {
            $funcgion_log('order_check', false, $msg);
            //发送消息
            Mq::OrderCreate($order_data['order_id']);
            return $this->Response(400, $msg);
        }
        //获取订单拆分结果
        $service_logic = new Service();
        $split_data = $service_logic->ServiceCall('order_split', ['split_id' => $order_data['split_id']]);
        if ($split_data['error_code'] != 'SUCCESS') {
            //发送消息
            Mq::OrderCreate($order_data['order_id']);
            $funcgion_log('order_get_split_data', false, $split_data['error_msg']);
            return $this->Response(400, '拆单服务错误:' . $split_data['error_msg']);
        }

        // 格式化拆单数据，并生成订单号
        $split_info = $this->FormatSplitOrder($split_data['data']['split_info'], $order_data);

        if (empty($split_info) || empty($split_info['items'])) {
            //发送消息
            Mq::OrderCreate($order_data['order_id']);
            $funcgion_log('empty_split_items', false, '商品数据为空');
            return $this->Response(400, '商品数据为空');
        }
        //订单风控
        if (!$this->CheckProduct($split_info['items'], $order_data)) {
            //发送消息
            Mq::OrderCreate($order_data['order_id']);
            $funcgion_log('order_riskManagement_stop', false, '订单风控阻止');
            return $this->Response(402, '订单风控阻止');
        }
        //进行库存锁定
        $response_data = [];
        $stock_lock_response = $this->StockLock($order_data, $split_info, $response_data);
        if (!$stock_lock_response) {
            //取消库存锁定
            $this->StockCancelLock($order_data['order_id'], $order_data['channel']);
            $funcgion_log('product_lock_fail', false, json_encode($response_data));
            if (!empty($response_data)) {
                //发送消息
                Mq::OrderCreate($order_data['order_id']);
                return $this->Response(401, '商品库存不足', $response_data);
            } else {
                //发送消息
                Mq::OrderCreate($order_data['order_id']);
                return $this->Response(400, '库存锁定失败');
            }
        }
        //数据保存
        $save_res = $this->Save($order_data, $split_info);
        if (!$save_res) {
            //发送消息
            Mq::OrderCreate($order_data['order_id']);
            $this->StockCancelLock($order_data['order_id'], $order_data['channel']);
            return $this->Response(400, '订单创建失败');
        }
        //记录日志
        $log_data = [
            'order_id' => $order_data['order_id'],
            'title' => '订单创建',
            'content' => json_encode($order_data),
            'create_time' => time()
        ];
        OrderLog::SaveLog($log_data);
        //发送消息
        Mq::OrderCreate($order_data['order_id']);
        return $this->Response(200, '创建成功', ['order_id' => $order_data['order_id']]);
    }

    /*
     * @todo 检查订单
     */
    private function CheckOrder($order_data, &$msg)
    {
        if (empty($order_data['order_id'])) {
            $msg = '订单创建错误';
            return false;
        }
        //检查订单号是否已存在
        if (!Order::CheckOrderId($order_data['order_id'])) {
            $msg = '订单已存在';
            return false;
        }
        if (empty($order_data['ship_province']) || empty($order_data['ship_city']) || empty($order_data['ship_county']) || !isset($order_data['ship_town'])) {
            $msg = '收货地区不能为空';
            return false;
        }
        if (empty($order_data['ship_addr'])) {
            $msg = '收货地址不能为空';
            return false;
        }
        if (empty($order_data['ship_name'])) {
            $msg = '收货人姓名不能为空';
            return false;
        }
        if (empty($order_data['ship_mobile']) && empty($order_data['ship_tel'])) {
            $msg = '手机或电话必填其一';
            return false;
        }
        if (empty($order_data['split_id'])) {
            $msg = '订单拆分错误';
            return false;
        }
        if (empty($order_data['channel'])) {
            $msg = '下单渠道错误';
            return false;
        }
        return true;
    }

    /*
     * @todo 货品库存锁定
     */
    private function StockLock($order_data, $split_info, &$response_data)
    {
        if (empty($order_data) || empty($split_info)) {
            return false;
        }
        $response_data = [];
        //进行库存锁定
        $service_response = $this->StockServicceLock($order_data, $split_info, $response_data);

        //进行salyut预下单
        $salyut_response = $this->SalyutCreateOrder($order_data, $split_info, $response_data);

        //进行Shop预下单
        $shop_response = $this->ShopCreateOrder($order_data, $split_info, $response_data);

        return $service_response && $salyut_response && $shop_response;
    }


    /*
     * @todo 订单保存
     */
    private function Save($order_data, $split_info)
    {
        if (empty($order_data) || empty($split_info)) {
            return false;
        }
        //商品详细
        $main_items = [];
        foreach ($split_info['items'] as $item) {
            $main_items[] = [
                'bn' => $item['bn'],
                'p_bn' => $item['p_bn'],
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
                'cost_tax' => $item['cost_tax'],
                'item_type' => $item['item_type'],
            ];
        }
        $save_data['items'] = $main_items;
        //如果有拆分出子订单进行
        $split_orders = [];
        if (!empty($split_info['split_orders'])) {
            $server_concurrent = new Concurrent();
            foreach ($split_info['split_orders'] as $order) {
                //商品明细
                $order_items = [];
                foreach ($order['items'] as $item) {
                    $order_items[] = [
                        'bn' => $item['bn'],
                        'p_bn' => $item['p_bn'],
                        'name' => $item['name'],
                        'cost' => $item['cost'],
                        'price' => $item['price'],
                        'mktprice' => $item['mktprice'],
                        'amount' => $item['amount'],
                        'weight' => $item['weight'],
                        'nums' => $item['nums'],
                        'pmt_amount' => $item['pmt_amount'],
                        'cost_freight' => $item['cost_freight'],
                        'cost_tax' => $item['cost_tax'],
                        'point_amount' => $item['point_amount'],
                        'item_type' => $item['item_type'],
                    ];
                }
                $split_orders[] = [
                    'order_id' => $order['order_id'],
                    'create_source' => 'wms_order',
                    'final_amount' => $order['final_amount'],
                    'cur_money' => $order['cur_money'],
                    'point_amount' => $order['point_amount'],
                    'cost_freight' => $order['cost_freight'],
                    'cost_tax' => $order['cost_tax'],
                    'cost_item' => $order['cost_item'],
                    'pmt_amount' => $order['pmt_amount'],
                    'weight' => $order['weight'],
                    'wms_code' => $order['wms_code'],
                    'pop_owner_id' => $order['pop_owner_id'],
                    'items' => $order_items,
                    'memo' => isset($order['memo']) ? $order['memo'] : '',
                    'project_code' => $order_data['project_code'],
                ];
            }
        }

        $save_data = [
            //基础信息
            'order_id' => $order_data['order_id'],
            'pid' => '',
            'root_pid' => $order_data['order_id'],
            'create_source' => 'main',
            'member_id' => $order_data['member_id'],
            'company_id' => $order_data['company_id'],
            'create_time' => time(),
            'last_modified' => time(),
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
            'receive_mode' => $order_data['receive_mode'],
            'terminal' => $order_data['terminal'],
            'anonymous' => $order_data['anonymous'],
            'business_code' => $order_data['business_code'],
            'extend_info_code' => $order_data['extend_info_code'],
            'order_category' => $order_data['order_category'],
            'business_project_code' => $order_data['business_project_code'],
            'system_code' => $order_data['system_code'],
            'memo' => $order_data['memo'],
            'extend_data' => $order_data['extend_data'],
            'payment_restriction' => $order_data['payment_restriction'],
            'pay_status' => 1,
            'status' => 1,
            //订单金额信息
            'final_amount' => $split_info['final_amount'],
            'cur_money' => $split_info['cur_money'],
            'point_amount' => $split_info['point_amount'],
            'point_channel' => empty($order_data['point_channel']) ? '' : $order_data['point_channel'],
            'cost_freight' => $split_info['cost_freight'],
            'cost_tax' => $split_info['cost_tax'],
            'cost_item' => $split_info['cost_item'],
            'pmt_amount' => $split_info['pmt_amount'],
            //其它
            'weight' => $split_info['weight'],
            'wms_code' => count($split_orders) < 2 ? $split_info['wms_code'] : '', //拆分订单无wms_code
            'pop_owner_id' => count($split_orders) < 2 ? $split_info['pop_owner_id'] : '', //拆分订单无pop_owner_id
            'split' => count($split_orders) > 1 ? 2 : 1, //拆分订单大于1为主订为已拆分
            'channel' => $order_data['channel'],
            //商品明细
            'items' => $main_items,
            //拆分子订单
            'split_orders' => $split_orders,
            'project_code' => $order_data['project_code'],
        ];
        //保存数据
        $res = Order::SaveOrder($save_data);
        return $res;
    }


    /*
     * @todo 库存服务锁定
     */
    public function StockServicceLock($order_data, $split_info, &$response_data)
    {
        $service_logic = new Service();
        $product_list = [];
        foreach ($split_info['items'] as $item) {
            $product_list[] = [
                'product_bn' => $item['bn'],
                'count' => $item['nums']
            ];
        }
        if (!empty($order_data['lock_source'])) {
            $stock_temp_lock_change_data = [
                'channel' => $order_data['channel'],
                'change_data' => [
                    'lock_obj' => $order_data['lock_source']['lock_obj'],
                    'lock_type' => $order_data['lock_source']['lock_type'],
                    'to_lock_obj' => $order_data['order_id'],
                    'to_lock_type' => 'service_order',
                    'product_list' => $product_list
                ]
            ];
            $stock_response = $service_logic->ServiceCall('stock_temp_lock_change', $stock_temp_lock_change_data);
        } else {
            //提取货品数据进行锁库存
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
            $stock_response = $service_logic->ServiceCall('stock_temp_lock', $stock_lock_data);
        }
        //锁库存失败
        if ($stock_response['error_code'] != 'SUCCESS') {
            if ($stock_response['error_detail_code'] == 401) {
                $response_data = array_merge((array)$stock_response['data'], (array)$response_data);
            }
            return false;
        }
        return true;
    }

    /*
     * @todo salyut库存服务锁定
     */
    public function SalyutCreateOrder($order_data, $split_info, &$response_data)
    {
        $salyut_response_data = [];
        $salyut_order_logic = new SalyutOrder();
        $order_items = [];
        $preorder_itmes = [];   //预下订单商品
        //检查复用预下单
        if (!empty($order_data['preorder_order'])) {
            //查询预下订单
            $service_logic = new Service();
            $preorder_info = $service_logic->ServiceCall('preorder_info', ['temp_order_id' => $order_data['order_id']]);
            if ($preorder_info['error_code'] == 'SUCCESS' && !empty($preorder_info['data'])) {
                foreach ($preorder_info['data']['data']['result_data'] as $bn => $item) {
                    $preorder_itmes[$bn] = $bn;
                }
            }
        }
        foreach ($split_info['items'] as $item) {
            if (!isset($preorder_itmes[$item['bn']])) {
                $order_items[] = [
                    'product_bn' => $item['bn'],
                    'nums' => $item['nums'],
                    'name' => $item['name'],
                    'price' => $item['price'],
                ];
            }
        }
        if (empty($order_items)) {
            return true;
        }
        $salyut_response = $salyut_order_logic->Create($order_data, $order_items, $salyut_response_data, $split_info);
        if (!$salyut_response) {
            if (!empty($salyut_response_data)) {
                foreach ($salyut_response_data as $bn) {
                    $response_data[$bn] = $bn;
                }
            }
            return false;
        }
        return true;
    }

    /*
    * @todo shop库存服务锁定
    */
    public function ShopCreateOrder($order_data, $split_info, &$response_data)
    {
        $shop_order_logic = new ShopOrder();

        $shop_response = $shop_order_logic->Create($order_data, $split_info, $response_data);
        if (!$shop_response) {
            return false;
        }
        return true;
    }

    /*
     * @todo 库存回滚
     */
    public function StockCancelLock($order_id, $channel)
    {
        $service_logic = new Service();
        $stock_lock_cancel_data = [
            'lock_obj' => $order_id,
            'lock_type' => 'service_order',
            'channel' => $channel
        ];
        $stock_response = $service_logic->ServiceCall('stock_temp_lock_cancel', $stock_lock_cancel_data);

        // salyut 取消订单
        $salyut_order_logic = new SalyutOrder();
        $salyut_order_logic->OrderCancel(['order_id' => $order_id]);
    }


    private function Response($error_code, $error_msg, $data = [])
    {
        return [
            'error_code' => $error_code,
            'error_msg' => $error_msg,
            'data' => $data,
        ];
    }

    /*
     * @todo 京东商品风控检查
     */
    private function CheckProduct($itme_list, $order_data)
    {
        if ($order_data['system_code'] == 'cps' && $order_data['order_category'] == 'cps_pay' || $order_data['system_code'] == 'gallywix' || $order_data['system_code'] == 'unicom') {
            return true;
        }
        foreach ($itme_list as $k => $item) {
//            if($item['price'] < 20){
//                if($item['price'] * $item['nums'] > 200){
//                    return false;
//                }
//            }else{
//                if ($item['nums'] > 500) {
//                    return false;
//                }
//            }
        }
        $today_time = strtotime(date('Y-m-d'));
        $sql = "select count(1) as total from server_orders where pay_status = 1 and status = 1 and member_id = :member_id and create_time > :create_time and create_source = 'main'";
        $order_total = app('api_db')->selectOne($sql,
            ['member_id' => $order_data['member_id'], 'create_time' => $today_time]);
        if ($order_total->total >= 500000) {
            return false;
        }
        return true;
    }

    /**
     * 格式化拆单数据
     *
     * @param   $splitData      array   拆单数据
     * @param   $orderData      array   订单数据
     * @return  array
     */
    private function FormatSplitOrder($splitData, $orderData)
    {
        $return = $splitData;

        $return['items'] = array();
        // 商品详细
        foreach ($splitData['items'] as $item) {
            $return['items'][] = [
                'bn' => $item['product_bn'],
                'p_bn' => $item['p_bn'],
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
                'cost_tax' => $item['cost_tax'],
                'item_type' => $item['item_type'],
            ];
        }

        $return['split_orders'] = array();
        // 子订单拆分
        if (!empty($splitData['split_orders'])) {
            $splitOrderCount = count($splitData['split_orders']);
            $server_concurrent = new Concurrent();
            foreach ($splitData['split_orders'] as $order) {
                // 商品明细
                $order_items = [];
                foreach ($order['items'] as $item) {
                    $order_items[] = [
                        'bn' => $item['product_bn'],
                        'p_bn' => $item['p_bn'],
                        'name' => $item['name'],
                        'cost' => $item['cost'],
                        'price' => $item['price'],
                        'mktprice' => $item['mktprice'],
                        'amount' => $item['amount'],
                        'weight' => $item['weight'],
                        'nums' => $item['nums'],
                        'pmt_amount' => $item['pmt_amount'],
                        'cost_freight' => $item['cost_freight'],
                        'cost_tax' => $item['cost_tax'],
                        'point_amount' => $item['point_amount'],
                        'item_type' => $item['item_type'],
                    ];
                }

                $return['split_orders'][] = [
                    'order_id' => $splitOrderCount > 1 ? ($order['temp_order_id'] ? $order['temp_order_id'] : $server_concurrent->GetOrderId()) : $orderData['order_id'],
                    'create_source' => 'wms_order',
                    'final_amount' => $order['final_amount'],
                    'cur_money' => $order['cur_money'],
                    'point_amount' => $order['point_amount'],
                    'cost_freight' => $order['cost_freight'],
                    'cost_tax' => $order['cost_tax'],
                    'cost_item' => $order['cost_item'],
                    'pmt_amount' => $order['pmt_amount'],
                    'weight' => $order['weight'],
                    'wms_code' => $order['wms_code'],
                    'pop_owner_id' => $order['pop_owner_id'],
                    'items' => $order_items,
                    'memo' => isset($order['memo']) ? $order['memo'] : '',
                ];
            }
        }

        return $return;
    }

}
