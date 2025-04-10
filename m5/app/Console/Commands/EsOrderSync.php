<?php
namespace App\Console\Commands;

use App\Api\V2\Service\SearchOrder\EsSyncData;
use Illuminate\Console\Command;

/**
 * Class EsOrderSync
 * @package App\Console\Commands
 * ES订单同步
 * php artisan EsOrderSync
 */
class EsOrderSync extends Command{
    protected $force = '';
    protected $signature = 'EsOrderSync';
    protected $description = '订单数据同步到ES';

    // MQ
    private $mq_exchange = 'service';
    private $mq_queue = 'es_order';
    private $mq_routing_key_mapping = [
        // 订单创建
        'create' => [
            'routing_key' => 'order.create.success',
        ],
        // 订单取消
        'cancel' => [
            'routing_key' => 'order.cancel.success',
        ],
        // 订单支付
        'pay' => [
            'routing_key' => 'order.pay.success',
        ],
        // 订单确认
        'confirm' => [
            'routing_key' => 'order.confirm.success',
        ],
        // 订单拆单
        'split' => [
            'routing_key' => 'order.split.success',
        ],
        // 订单发货
        'delivery' => [
            'routing_key' => 'order.delivery.success',
        ],
        // 订单完成
        'finish' => [
            'routing_key' => 'order.finish.success',
        ],
        // 订单支付后取消
        'payedcancel' => [
            'routing_key' => 'order.payedcancel.success',
        ],
    ];

    public function handle(){
        set_time_limit(0);

        $this->SyncMessageToEs();
    }

    /**
     * Notes  : 批量拉取MQ消息并处理
     */
    public function SyncMessageToEs(){
        $callback = function ($batch = array()){
            if (!$batch || !is_array($batch)){
                return false;
            }

            $order_ids = [];

            foreach ($batch as $item){
                $order_ids[] = $item['data']['order_id'];
            }

            $order_ids = array_unique($order_ids);

            // 根据order_id获取订单数据，创建 or 更新到ES
            $es_sync_obj = new EsSyncData();
            $sync_es_res = $es_sync_obj->SyncOrderData($order_ids);

            if (!$sync_es_res){
                return false;
            }

            return true;
        };

        // 遍历queue绑定的routing_key
        $routing_key_arr = $this->mq_routing_key_mapping;

        $retry = array(
            'is_retry' => true,
            'delay_level' => MQ_RETRY_LEVEL_GENERAL
        );

        $count = 100;

        foreach ($routing_key_arr as $type => $routing_key_item){
            $routing_key = $routing_key_item['routing_key'];

            try {
                $amqp = new \Neigou\AMQP();
                $amqp->BatchConsumeMessage($this->mq_queue, $this->mq_exchange, $routing_key, $callback, $count, $retry);
            }catch (\Exception $exception){
                \Neigou\Logger::General('es.order.sync.error', array('remark' => $exception->getMessage(), 'action' => $this->mq_queue.'|'.$this->mq_exchange.'|'.$routing_key));
            }
        }
    }
}
