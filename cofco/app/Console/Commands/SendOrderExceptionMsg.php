<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Api\Model\Order\Order as OrderModel;

class SendOrderExceptionMsg extends Command
{
    protected $signature = 'SendOrderExceptionMsg';
    protected $description = '发送订单异常消息';

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

}
