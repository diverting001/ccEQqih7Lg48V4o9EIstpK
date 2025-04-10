<?php

namespace App\Console\Commands;

use App\Api\V1\Service\Outlet\OutletService;
use App\Api\V1\Service\Search\Elasticsearchupindexdata;
use Illuminate\Console\Command;

/**
 * 将店铺与门店变更后的关联关系数据同步到Es中
 */
class GoodsOutletChangeUpdateToEs extends Command
{
    protected $signature = 'GoodsOutletChangeUpdateToEs {unlock?}';
    protected $description = '将店铺与门店变更后的关联关系数据同步到Es中';
    private $mq_update_goods_outlet_exchange = 'goods';
    private $mq_update_goods_outlet_routing_key = 'goods.update.outlet.goods';
    private $mq_update_goods_outlet_queue = 'ec_goods_outlet_goods_es';

    public function handle()
    {
        $callback = function ($batch_message = array()) {
            if (!$batch_message || !is_array($batch_message)) {
                return false;
            }
            \Neigou\Logger::General('es.goods.outlet.change.data', $batch_message);

            $batch_data = [];
            foreach ($batch_message as $batch_message_v){
                $batch_data = array_merge($batch_data, $batch_message_v);
            }
            $batch_data = array_column($batch_data,NULL,'goods_id');

            $outlet_ids = [];
            foreach ($batch_data as $batch_data_v) {
                $outlet_ids = array_merge($outlet_ids, $batch_data_v['outlet_ids']);
            }
            $outlet_ids = array_unique($outlet_ids);

            //查询门店信息
            $OutletService = new OutletService();
            $ret = $OutletService->getDataByOutletId($outlet_ids);
            if ($ret['code'] != 0) {
                return false;
            }
            $outlet_list = array_column($ret['data'], NULL, 'outlet_id');

            //组合成批量更新需要的数据
            $data = [];
            foreach ($batch_data as $batch_data_v_1) {
                foreach ($batch_data_v_1['outlet_ids'] as $outlet_ids_v) {
                    $data[$batch_data_v_1['goods_id']]['goods_id'] = $batch_data_v_1['goods_id'];
                    if (isset($outlet_list[$outlet_ids_v])) {
                        $data[$batch_data_v_1['goods_id']]['outlet_list'][] = [
                            "outlet_id" => $outlet_list[$outlet_ids_v]['outlet_id'],
                            "province_id" => $outlet_list[$outlet_ids_v]['province_id'],
                            "outlet_address" => $outlet_list[$outlet_ids_v]['outlet_address'],
                            "outlet_name" => $outlet_list[$outlet_ids_v]['outlet_name'],
                            "area_id" => $outlet_list[$outlet_ids_v]['area_id'],
                            "outlet_logo" => $outlet_list[$outlet_ids_v]['outlet_logo'],
                            "city_id" => $outlet_list[$outlet_ids_v]['city_id'],
                            "coordinate" => [
                                "lon" => $outlet_list[$outlet_ids_v]['longitude'],
                                "lat" => $outlet_list[$outlet_ids_v]['latitude'],
                            ],
                        ];
                    }
                }
            }

            $Elasticsearchupindexdata = new Elasticsearchupindexdata();
            $es_ret = $Elasticsearchupindexdata->EsFieldUpdate($data);

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
                \Neigou\Logger::General('es.goods.outlet.change.error', $fail_data);
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
            if($amqp->queue_lock_key){
                $amqp->unlock($amqp->queue_lock_key);
            }
            \Neigou\Logger::General('es.goods.outlet.change.error', array('remark' => $ex->getMessage(), 'action' => $queue_name . '|' . $exchange . '|' . $routing_key));
        }
    }
}
