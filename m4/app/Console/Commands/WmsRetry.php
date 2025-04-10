<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Api\Logic\Mq as Mq;

class WmsRetry extends Command
{
    protected $force = '';
    protected $signature = 'WmsRetry {n}';
    protected $description = '履约异常重试';

    public function handle()
    {
        $n = $this->argument('n');
        $n = $n > 0 ? $n : 1;
        $start = strtotime("-{$n}hour");
        $end = strtotime("-1hour");

        $sql = "select * from server_orders where "
            . "`pay_time`>:start and `pay_time`<=:end "
            . "and  create_source='main' "
            . "and `status`=1 and `pay_status`=2 and `ship_status`=1 and `confirm_status`=2 and `wms_order_bn`='' "
            . "order by order_id asc limit 10000";

        $orders = app('api_db')->select($sql, ['start' => $start, 'end' => $end]);
        if (!empty($orders)) {
            foreach ($orders as $order) {
                Mq::OrderConfirm($order->order_id);
            }
        }
    }

}
