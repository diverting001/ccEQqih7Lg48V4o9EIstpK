<?php

namespace App\Console\Commands;

use App\Api\Logic\Service as Service;
use Illuminate\Console\Command;

class PricingSetMessage extends Command
{
    /** @var string  */
    protected $signature   = 'PricingSetMessage {--unlock=}';

    /** @var string  */
    protected $description = 'rabbitmq 货品定价设置';

    public function handle()
    {
        $callback = function ($batch_message = array()) {
            if (empty($batch_message) || !is_array( $batch_message)) {
                echo 'batch message empty', PHP_EOL;
                return false;
            }

            $goods_ids = $this->_getGoodsIds($batch_message);

            $product_info = $this->_batchGetProductInfo($goods_ids);

            if (empty($product_info['list'])) {
                return false;
            }

            $product_bn_list = $this->_getProductBnList($product_info['list']);

            if (empty($product_bn_list)) {
                return false;
            }

            $arr  = array(
                'filter' => array(
                    'product_bn_list' => $product_bn_list,
                ) ,
            );

            $service_logic = new Service();
            $result  = $service_logic->ServiceCall('create_pricing', $arr);

            if ('SUCCESS' != $result['error_code']) {
                \Neigou\Logger::Debug(
                    'rabbitmq_create_pricing_fail',
                    array('action' => 'message handle',
                        "sparam1" => json_encode($product_bn_list),
                        "sparam2" => $result)
                );

                echo 'set price failed', PHP_EOL;
                return false ;
            }

            echo 'set price success', PHP_EOL;
            return true;
        };

        try {
            $exchange    = 'goods';
            $queue_name  = 'set.service.price.cache';
            $routing_key = 'goods.update.price';
            $counter     = 100;
            $unlock      = $this->option('unlock') ? true : false;
            $amqp        = new \Neigou\AMQP('goods');

            // 商城商品变动绑定routing_key
            $bindKeys = array('goods.update.mall');
            $channel = $amqp->getChannel();
            if ($channel) {
                $channel->queue_declare($queue_name, false, true, false, false);
                foreach ($bindKeys as $bindKey) {
                    $channel->queue_bind($queue_name, $exchange, $bindKey);
                }
            }

            $amqp->BatchConsumeMessage( $queue_name, $exchange, $routing_key, $callback, $counter, array(), $unlock);

        } catch (\Exception $ex) {

            \Neigou\Logger::General(
                'set.price.service.cache.error',
                array(
                    'remark' => $ex->getMessage(),
                    'action' => $queue_name . '|' . $exchange . '|' . $routing_key )
            );

        }
    }

   private function _getGoodsIds($batch_message)
   {
       //去重
       $unique_goods_ids = array();

       foreach ($batch_message as $item ) {
           if ( empty($item['goods_id']) || !is_numeric( $item['goods_id'] ) ) {
               continue;
           }

           $unique_goods_ids[] = $item['goods_id'];
       }

       return array_unique($unique_goods_ids);
   }

   private function _batchGetProductInfo($goods_ids)
   {
       if (empty($goods_ids)) {
          return false;
       }

       $arr  = array(
           'filter' => array(
               'goods_id' => $goods_ids,
           ) ,
       );

       $service_logic = new Service();
       $result  = $service_logic->ServiceCall('get_product_list', $arr);

       if ('SUCCESS' != $result['error_code']) {
           echo 'product info list is empty', PHP_EOL;
           \Neigou\Logger::Debug('rabbitmq_create_pricing_goods',
               array('action' => 'batch_get_product_info',
                   "sparam1" => json_encode($result),
                   "sparam2" => json_encode($goods_ids))
           );
           return false;
       }

       return  $result['data'];
   }

   private function _getProductBnList($product_info)
   {
       //获取product_bns
       $product_bn_list = array();

       foreach ($product_info as $product) {
           if (empty($product['product_bn']) || strstr($product['product_bn'], 'MRYX-')) continue;
           $product_bn_list[] = $product['product_bn'];
       }

       return array_unique($product_bn_list);
   }
}
