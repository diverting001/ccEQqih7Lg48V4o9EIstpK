<?php
/**
 *Create by PhpStorm
 *User:liangtao
 *Date:2020-8-18
 */

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Api\V1\Service\Search\Elasticsearchupindexdata;

class GoodsModifyUpdateToES extends Command
{
    protected $signature = 'GoodsModifyUpdateToES {unlock?}';
    protected $description = '商品变更更新ES';
    private $mq_update_goods_exchange = 'goods';
    private $mq_update_goods_routing_key = 'goods.update.*';
    private $mq_update_goods_queue = 'elasticsearch';

    public function handle()
    {

        $callback = function ( $batch_message = array() )
        {
            if ( !$batch_message || !is_array( $batch_message ) )
            {
                return false;
            }

            $goods_ids = [];
            foreach ( $batch_message as $message )
            {
                $goods_ids[] = $message['goods_id'];
            }

            $goods_ids = array_unique( $goods_ids );

            $create_index_obj = new Elasticsearchupindexdata();
            $rzt = $create_index_obj->SaveElasticSearchsData( $goods_ids );

            if ( $rzt != true )
            {
                return false;
            }

            return true;
        };

        try
        {

            $exchange = $this->mq_update_goods_exchange;
            $queue_name = $this->mq_update_goods_queue;
            $routing_key = $this->mq_update_goods_routing_key;
            $counter = 300;

            $retry = array(
                'is_retry' => true,
                'delay_level' => MQ_RETRY_LEVEL_GENERAL
            );

            $unlock = $this->argument('unlock');
            $unlock = $unlock ? true : false;

            $amqp = new \Neigou\AMQP('goods');
            $amqp->BatchConsumeMessage( $queue_name, $exchange, $routing_key, $callback, $counter, $retry ,$unlock);

        } catch ( \Exception $ex )
        {
            \Neigou\Logger::General('es.goods.modify.error',array('remark'=>$ex->getMessage(),'action'=>$queue_name.'|'.$exchange.'|'.$routing_key));
        }
    }
}