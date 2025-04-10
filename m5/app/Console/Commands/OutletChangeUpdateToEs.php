<?php

namespace App\Console\Commands;

use App\Api\V1\Service\Search\Elasticsearchupindexdata;
use Illuminate\Console\Command;

/**
 * 将门店变更数据同步到Es中
 */
class OutletChangeUpdateToEs extends Command
{
    protected $signature = 'OutletChangeUpdateToEs {unlock?}';
    protected $description = '将门店变更后的数据同步到Es中';
    private $mq_update_goods_outlet_exchange = 'goods';
    private $mq_update_goods_outlet_routing_key = 'goods.update.outlet.info';
    private $mq_update_goods_outlet_queue = 'ec_goods_outlet_info_es';

    public function handle()
    {
        $callback = function ($batch_message = array()) {

            if (!$batch_message || !is_array($batch_message)) {
                return false;
            }
            \Neigou\Logger::General('es.outlet.change.data', $batch_message);

            $batch_datas = [];
            foreach ($batch_message as $batch_message_v) {
                $batch_datas = array_merge($batch_datas, $batch_message_v);
            }
//            $batch_datas = array_column($batch_datas, NULL, 'outlet_id');
            $goods_list = [];
            $i = 0;
            foreach ($batch_datas as $batch_datas_v) {
                if (!(isset($batch_datas_v['outlet_id']) && $batch_datas_v['outlet_id'])) {
                    continue;
                }
                $script = [];
                if (isset($batch_datas_v['outlet_name']) && $batch_datas_v['outlet_name']) {
                    $script['outlet_name'] = $batch_datas_v['outlet_name'];
                }
                if (isset($batch_datas_v['outlet_address']) && $batch_datas_v['outlet_address']) {
                    $script['outlet_address'] = $batch_datas_v['outlet_address'];
                }
                if (isset($batch_datas_v['outlet_logo']) && $batch_datas_v['outlet_logo']) {
                    $script['outlet_logo'] = $batch_datas_v['outlet_logo'];
                }
                if (isset($batch_datas_v['province_id']) && (int)$batch_datas_v['province_id'] > 0) {
                    $script['province_id'] = (int)$batch_datas_v['province_id'];
                }
                if (isset($batch_datas_v['city_id']) && (int)$batch_datas_v['city_id'] > 0) {
                    $script['city_id'] = (int)$batch_datas_v['city_id'];
                }
                if (isset($batch_datas_v['area_id']) && (int)$batch_datas_v['area_id'] > 0) {
                    $script['area_id'] = (int)$batch_datas_v['area_id'];
                }
                if (isset($batch_datas_v['latitude']) && is_float($batch_datas_v['latitude'])) {
                    $script['coordinate.lat'] = $batch_datas_v['latitude'];
                }
                if (isset($batch_datas_v['longitude']) && is_float($batch_datas_v['longitude'])) {
                    $script['coordinate.lon'] = $batch_datas_v['longitude'];
                }
                if (!$script) {
                    continue;
                }
                foreach ($batch_datas_v['goods_ids'] as $goods_v) {
                    $goods_list[$i]['goods_id'] = $goods_v;
                    $goods_list[$i]['outlet_id'] = $batch_datas_v['outlet_id'];
                    $goods_list[$i]['script'] = $script;
                    $i++;
                }
            }

            //执行更新操作
            $Elasticsearchupindexdata = new Elasticsearchupindexdata();
            $es_ret = $Elasticsearchupindexdata->EsNestedFieldUpdate(
                $goods_list,
                'outlet_list',
                "outlet_id"
            );

            $results = json_decode($es_ret, true);
            $fail_data = [];
            foreach ($results['items'] as $item) {
                if ($item['update']['status'] != 200) {
                    $fail_data[] = [
                        '_type' => $item['update']['_type'],
                        '_id' => $item['update']['_id'],
                        'reason' => $item['update']['error']['reason']
                    ];
                }
            }
            if ($fail_data) {
                \Neigou\Logger::General('es.outlet.change.error', $fail_data);
                return false;
            }
            return true;
        };

        $amqp = new \Neigou\AMQP('goods');
        try {

            $exchange = $this->mq_update_goods_outlet_exchange;
            $queue_name = $this->mq_update_goods_outlet_queue;
            $routing_key = $this->mq_update_goods_outlet_routing_key;
            $counter = 300;

            $retry = array(
                'is_retry' => true,
                'delay_level' => MQ_RETRY_LEVEL_GENERAL
            );

            $unlock = $this->argument('unlock');
            $unlock = $unlock ? true : false;

//            $amqp = new \Neigou\AMQP();
            $amqp->BatchConsumeMessage($queue_name, $exchange, $routing_key, $callback, $counter, $retry, $unlock);

        } catch (\Exception $ex) {
            if ($amqp->queue_lock_key) {
                $amqp->unlock($amqp->queue_lock_key);
            }
            \Neigou\Logger::General('es.outlet.change.error', array('remark' => $ex->getMessage(), 'action' => $queue_name . '|' . $exchange . '|' . $routing_key));
        }
    }
}
