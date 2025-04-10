<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Api\Model\Order\Order as OrderModel;
use App\Api\Logic\Service as Service;

class SendOrderExceptionMsg extends Command
{
    protected $signature = 'SendOrderExceptionMsg';
    protected $description = '发送订单异常消息';

    private $type_config    = array(
        '2' => '超时支付',
        '3' => '超时未发货',
        '4' => '超时未完成',
        '10'    => '售后超时未寄回',
        '11'    => '寄回未入库',
        '12'    => '订单未完成申请售后'
    );


    public function handle()
    {
        //超时支付
        $this->sendTimeoutPayMsg();

        //超时未发货
        $this->sendTimeoutShipMsg();

        //自动清除已取消，已完成的异常消息
        $this->autoClearExceptionOrder();

        //超时未完成订单
        $this->sendTimeoutUnCompleteOrderMsg();

        //超时未寄回售后
        $this->afterException();

        //清除已发货售后
        $this->clearAfterException();
    }

    private function sendTimeoutPayMsg()
    {
        $n = 1;
        $start_time = strtotime("-{$n}hour");
        $end_time = time();
        $orders = OrderModel::GetTimeoutPayOrders($start_time, $end_time);
        if (!empty($orders)) {
            foreach ($orders as $order) {
                $update_data = [
                    'order_id' => $order->order_id,
                    'pid' => $order->pid,
                    'root_pid' => $order->root_pid,
                    'wms_code' => $order->wms_code,
                    'wms_msg' => '超时支付',
                    'type' => 2,
                ];
                OrderModel::UpdateWmsOrderMsg($update_data);
            }
        }
    }

    private function sendTimeoutUnCompleteOrderMsg()
    {
        $days = 7;
        $time_range_seconds = 86400;
        $end_time = time() - $days * 24 * 3600;
        $start_time = $end_time - $time_range_seconds;
        $orders = OrderModel::GetTimeoutUncompleteOrders($start_time, $end_time);
        if (!empty($orders)) {
            foreach ($orders as $order) {
                //检查订单是否需要监控
                if(!$this->checkOrder($order)){
                    continue;
                }
                $update_data = [
                    'order_id' => $order->order_id,
                    'pid' => $order->pid,
                    'root_pid' => $order->root_pid,
                    'wms_code' => $order->wms_code,
                    'wms_msg' => '超时未完成',
                    'type' => 4,
                ];
                OrderModel::UpdateWmsOrderMsg($update_data);
            }
        }
    }

    private function sendTimeoutShipMsg()
    {
        $days = 2;
        $create_time = time() - $days * 24 * 3600;
        $orders = OrderModel::GetTimeoutShipOrders($create_time);
        if (!empty($orders)) {
            foreach ($orders as $order) {
                //检查订单是否需要监控
                if(!$this->checkOrder($order)){
                    continue;
                }
                $update_data = [
                    'order_id' => $order->order_id,
                    'pid' => $order->pid,
                    'root_pid' => $order->root_pid,
                    'wms_code' => $order->wms_code,
                    'wms_msg' => '超时未发货',
                    'type' => 3,
                ];
                OrderModel::UpdateWmsOrderMsg($update_data);
            }
        }
    }

    private function getPopShopIdByOrderId($order_id)
    {
        $pop_shop_id = 0;
        $order_items = OrderModel::GetOrderItemsByOrderId($order_id);
        if (!empty($order_items)) {
            $product_bn = $order_items[0]->bn;
            $sql = "select pop_shop_id from sdb_b2c_products where bn=:bn";
            $rs = app("db")->connection("neigou_store")->selectOne($sql, ['bn' => $product_bn]);
            if (!empty($rs)) {
                $pop_shop_id = $rs->pop_shop_id;
            }
        }
        return $pop_shop_id;
    }

    private function getExcludeConf()
    {
        $conf = [
            'exclude_company_ids' => [],
            'exclude_member_ids' => [],
            'exclude_pop_shop_ids' => [],
        ];
        $sql = "select * from `server_exception_order_conf`";
        $res = app('api_db')->selectOne($sql);
        if (!empty($res)) {
            $conf['exclude_company_ids'] = explode(',', $res->exclude_company_ids);
            $conf['exclude_member_ids'] = explode(',', $res->exclude_member_ids);
            $conf['exclude_pop_shop_ids'] = explode(',', $res->exclude_pop_shop_ids);
        }

        return $conf;
    }

    private function autoClearExceptionOrder()
    {
        //自动清理超时发货订单
        $sql = "select E.*,O.status as order_status from server_order_exception_msg E join server_orders O on E.order_id=O.order_id where O.status in(1) and o.ship_status =2 and e.status =0 and type =3;";
        $results = app('api_db')->select($sql);
        if (!empty($results)) {
            foreach ($results as $row) {
                $sql = "delete from server_order_exception_msg where id=:id";
                app('api_db')->delete($sql, ['id' => $row->id]);

            }
        }
        //自动清理超时未完成订单
        $sql = "select E.*,O.status as order_status from server_order_exception_msg E join server_orders O on E.order_id=O.order_id where O.status in(3) and o.ship_status =2 and e.status =0 and type =4;";
        $results = app('api_db')->select($sql);
        if (!empty($results)) {
            foreach ($results as $row) {
                $sql = "delete from server_order_exception_msg where id=:id";
                app('api_db')->delete($sql, ['id' => $row->id]);

            }
        }
    }

    /*
     * @检查订单否符合报警条件
     */
    private function checkOrder($order_info){
        if(is_null($this->__exclude_conf)){
            $this->__exclude_conf   = $this->getExcludeConf();
        }
        if (in_array($order_info->company_id, $this->__exclude_conf['exclude_company_ids'])) {
            return false;
        }
        if (in_array($order_info->member_id, $this->__exclude_conf['exclude_member_ids'])) {
            return false;
        }
        $pop_shop_id = $this->getPopShopIdByOrderId($order_info->order_id);
        if (in_array($pop_shop_id, $this->__exclude_conf['exclude_pop_shop_ids'])) {
            return false;
        }
        return true;
    }


    /*
     * @todo 超时未寄回售后
     */
    private function afterException(){
        //获取审核通过售后单列表
        $service_logic = new Service();
        $aftersale_data = $service_logic->ServiceCall('aftersale_getlist', ['data' => ['company_id'=>[34335],'status'=>[2,4]],'condition'=>'whereIn']);
        if(!empty($aftersale_data['data'])){
            foreach ($aftersale_data['data'] as $info){
                $type   = 0;
                switch ($info['status']){
                    case 2: //售后超时未寄回
                        if($this->CheckAfterJHException($info)){
                            $type   = 10;
                        }
                        break;
                    case 4: //寄回未入库
                        if($this->CheckAfterRKException($info)){
                            $type   = 11;
                        }
                        break;
                }
                if($type){
                    $update_data = [
                        'order_id' => $info['order_id'],
                        'pid' => $info['order_id'] == $info['root_pid']?'':$info['root_pid'],
                        'root_pid' => $info['root_pid'],
                        'wms_code' => '',
                        'wms_msg' => $this->type_config[$type],
                        'type' => $type,
                    ];
                    $msg_list   = OrderModel::GetExceptionOrderMsgListForMis('*',['msg_type'=>$type,'msg_status'=>0,'order_id'=>$info['order_id']],500);
                    if(empty($msg_list['order_list'])){
                        OrderModel::UpdateWmsOrderMsg($update_data);
                    }
                }
            }
        }
    }
    //清除已发货售后
    private function clearAfterException(){
        //获取审核通过售后单列表
        $service_logic = new Service();
        $msg_list   = OrderModel::GetExceptionOrderMsgListForMis('*',['msg_status'=>0],1000);
        if(!empty($msg_list['order_list'])){
            foreach ($msg_list['order_list'] as $order){
                $delete = false;
                $aftersale_data = $service_logic->ServiceCall('aftersale_getlist', ['data' => ['order_id'=>$order->order_id],'condition'=>'where']);
                switch ($order->type){
                    case 10:
                        $delete  = true;
                        foreach ($aftersale_data['data'] as $info){
                            if($this->CheckAfterJHException($info)){
                                $delete = false;
                            }
                        }
                        break;
                    case 11:
                        $delete  = true;
                        foreach ($aftersale_data['data'] as $info){
                            if($this->CheckAfterRKException($info)){
                                $delete = false;
                            }
                        }
                        break;
                    case 12:
                        if(empty($aftersale_data['data'])){
                            $delete = false;
                        }
                        break;
                }

                if($delete){
                    $sql = "delete from server_order_exception_msg where id=:id";
                    app('api_db')->delete($sql, ['id' => $order->id]);
                }
            }
        }
    }

    //检查售后是否超时未寄回异常
    private function CheckAfterJHException($after_info){
        if(empty($after_info)) return false;
        $time = time();
        $max_time   = $time-2*24*3600;
        if($after_info['update_time'] < $max_time && $after_info['status'] == 2){
            return true;
        }
        return false;
    }

    //检查售后是否寄回未入库异常
    private function CheckAfterRKException($after_info){
        if(empty($after_info)) return false;
        //获取物流信息，express_get 升级 express_get_v4 2024-03-29
        $service_logic = new Service();
        $express_info = $service_logic->ServiceCall('express_get_v4', ['express_com'=>$after_info['express_code'],'express_no'=>$after_info['express_no']]);
        if($express_info['data']){
            if($express_info['data']['status']  == 3 && $after_info['status']  == 4){
                return true;
            }
        }
        return false;
    }

}
