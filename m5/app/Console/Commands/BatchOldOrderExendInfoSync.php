<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Neigou\RedisNeigou;
use App\Api\Model\Order\Order as OrderModel;
use App\Api\Model\Order\OrderItemExtend;

class BatchOldOrderExendInfoSync extends Command
{
    /** @var string  */
    protected $signature = 'BatchOldOrderExendInfoSync {--start=} {--block=}';

    /** @var string  */
    protected $description = '历史订单数据扩展信息（基础价等）同步到扩展信息表';

    /** @var string  */
    const START_TIME_KEY = 'OldOrderExtendInfoSyncStartTime';

    public function handle()
    {
        set_time_limit(0);
        $redis = new RedisNeigou();
        /** @var  $block 截止时间 */
        $block = $this->option('block');
        // 每一轮的开始时间
        $start = $redis->_redis_connection->get(self::START_TIME_KEY) ?: $this->option('start');
        if ($start >= $block) {
            echo 'handle end!'.PHP_EOL;
            $redis->_redis_connection->del(self::START_TIME_KEY);
            return;
        }
        // 每一轮的结束时间：截止时间和开始时间在同一天则取截止时间为本轮结束时间
        $end = date('Ymd',$start) == date('Ymd',$block) ? $block : $start + 86400;
        $limit = 300;
        $curPage = 1;
        $totalCount = OrderModel::GetOrderExtendCountByCreateTime(array($start, $end));
        $totalPages = ceil($totalCount / $limit);
        while($curPage <= $totalPages) {
            $offset = ($curPage - 1) * $limit;
            $orderList = OrderModel::GetOrderExtendListByCreateTime(array($start, $end), $offset ,$limit);
            if (empty($orderList)) {
                break;
            }
            $itemExtendData = array();
            foreach ($orderList as $orderInfo) {
                $extendData = json_decode($orderInfo->extend_data, true);
                if (empty($extendData['price']) || !is_array($extendData['price'])) {
                    continue;
                }
                foreach ($extendData['price'] as $bn => $priceInfo) {
                    if (empty($bn) || empty($priceInfo['primitive_price'])) {
                        continue;
                    }
                    $itemExtendData[] = array(
                        'order_id' => $orderInfo->order_id,
                        'bn' => $bn,
                        'base_price' => $priceInfo['primitive_price']
                    );
                }
            }
            if (!empty($itemExtendData)) {
                /** 批量写入扩展信息表  */
                $message = OrderItemExtend::add($itemExtendData) ? 'success' : 'fail';
                printf("start:%s, end:%s, offset:%s, limit:%s ,count:%s, result:%s;\n",
                    $start,
                    $end,
                    $offset,
                    $limit,
                    count($itemExtendData),
                    $message
                );
            }
            $curPage++;
        }
        // 重置开始时间为当天的结束时间（下一天的开始时间）
        $redis->_redis_connection->set(
            self::START_TIME_KEY,
            $end
        );
    }
}
