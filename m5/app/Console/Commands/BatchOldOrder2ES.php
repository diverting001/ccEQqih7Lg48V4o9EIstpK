<?php
namespace App\Console\Commands;

use App\Api\V2\Service\SearchOrder\EsSyncData;
use App\Api\V2\Service\SearchOrder\OrderDataSource\Es;
use Illuminate\Console\Command;

/**
 * Class BatchOldOrder2ES
 * @package App\Console\Commands
 * 将历史订单数据同步到ES
 *
 * php artisan BatchOldOrder2ES --start=2022-01-01 --end=2022-03-31
 * php artisan BatchOldOrder2ES --start=2022-04-01 --end=2022-06-30
 * php artisan BatchOldOrder2ES --start=2022-07-01 --end=2022-09-30
 * php artisan BatchOldOrder2ES --start=2022-10-01 --end=2022-12-31
 *
 * php artisan BatchOldOrder2ES --start=2023-01-01 --end=2023-03-31
 * php artisan BatchOldOrder2ES --start=2023-04-01 --end=2023-06-30
 * php artisan BatchOldOrder2ES --start=2023-07-01 --end=2023-09-30
 * php artisan BatchOldOrder2ES --start=2023-10-01 --end=2023-12-31
 */

class BatchOldOrder2ES extends Command{
    protected $force = '';
    protected $signature = 'BatchOldOrder2ES {--start=} {--end=}';
    protected $description = '历史订单数据同步到ES';


    public function handle(){
        set_time_limit(0);

        $start = $this->option('start');
        $end = $this->option('end');

        $this->ExeSync($start, $end);
    }

    public function ExeSync($start, $end){
        $this->info('=====START=====');

        if (empty($end)){
            $end = '2023-12-31';
        }

        // 分时间段 - 分页处理
        // 2022-01-01 00:00:00 2022-03-31 23:59:59
        $start_time = strtotime($start);
        $end_time = strtotime($end) + 86400;

        $this->info('同步时间：' . date('Y-m-d H:i:s', $start_time) . '---' . date('Y-m-d H:i:s', $end_time));

        $page = 0;
        $size = 1000;

        while ($page < 99999){
            $page++;

            $this->info('页码：' . $page);

            $offset = ($page - 1) * $size;

            // 分时间段、分页获取订单 & 格式化订单数据
            $order_list = $this->GetOrderList($offset, $size, $start_time, $end_time);

            if (empty($order_list)){
                $this->error('已经没有数据了，退出！');
                break;
            }

            // 同步到ES
            $err_msg = '';

            $sync_es_res = $this->SyncToES($order_list, $err_msg);

            $total = count($order_list);
            $err_msg .= "(处理数量：{$total})";

            $this->info('同步结果：' . ($sync_es_res ? '成功！' : '失败！-') . $err_msg);
        }

        $this->info('=====FINISH=====');
    }

    private function GetOrderList($offset, $limit, $start, $end){
        // 分时间段、分页获取订单
        $sql = "select * from server_orders where `create_time` >= {$start} and `create_time` < {$end} limit {$offset},{$limit}";

        $main_list = app('api_db')->select($sql);

        if (empty($main_list)){
            return [];
        }

        $main_list = $this->ObjToArr($main_list);

        // 格式化订单数据
        $EsSyncLogic = new EsSyncData();
        $format_order_list = $EsSyncLogic->formatOrderList($main_list);

        return $format_order_list;
    }

    private function SyncToES($order_list, &$err_msg){
        if (empty($order_list)){
            return true;
        }

        $Es = new Es();

        $sync_res = $Es->CoverEsDoc($order_list, $err_msg);

        return $sync_res;
    }

    // 对象转数组
    private function ObjToArr($obj, $is_column = false, $column = ''){
        $arr = json_decode(json_encode($obj), true);

        if ($is_column){
            $arr = array_unique(array_column($arr, $column));
        }

        return $arr;
    }
}
