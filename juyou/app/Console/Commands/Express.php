<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class Express extends Command
{
    protected $force = '';
    protected $signature = 'Express {n}';
    protected $description = '订单服务主动拉取发货信息';

    public function handle()
    {
        $n = $this->argument('n');
        $n = $n > 0 ? $n : 1;
        $start = strtotime("-{$n}day");
        $end = strtotime("-1hour");

        $sql = "select * from server_orders where "
            . "`create_time`>:start and `create_time`<=:end "
            . "and  create_source in ('main','wms_order') "
            . "and `status`=1 and `pay_status`=2 and `ship_status` in(1,2) and `wms_order_bn`!='' "
            . "order by order_id asc limit 10000";

        $orders = app('api_db')->select($sql, ['start' => $start, 'end' => $end]);
        if (!empty($orders)) {
            foreach ($orders as $order) {
                $this->sendNotifyToOrderService($order->wms_order_bn, $order->wms_code);
            }
        }
    }

    //发送订单变化通知到订单服务
    public function sendNotifyToOrderService($wms_order_bn, $wms_code = 'SALYUT')
    {
        $req_data = array(
            'wms_order_bn' => $wms_order_bn,
            'wms_code' => $wms_code,
        );
        $extend_config = array(
            'timeout' => 3,
        );

        $ret = \Neigou\ApiClient::doServiceCall('order', 'Order/Message/OrderUpdate', 'v1', null, $req_data,
            $extend_config);
        if ('OK' == $ret['service_status'] && 'SUCCESS' == $ret['service_data']['error_code']) {
            return true;
        } else {
            $error_code = $ret['service_data']['error_code'];
            $msg = '发送订单变化通知到订单服务失败';
            \Neigou\Logger::Debug("salyut_update_order_status",
                array('func' => 'sendNotifyToOrderService', 'error_code' => $error_code, 'msg' => $msg));
        }
        return false;
    }


}
