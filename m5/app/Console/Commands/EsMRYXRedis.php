<?php
/**
 * Created by PhpStorm.
 * User: guke
 * Date: 2018/6/20
 * Time: 13:22
 */

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Api\V1\Service\Search\Elasticsearchupindexdata;

class EsMRYXRedis extends Command
{
    protected $signature = 'EsMRYXRedis';
    protected $description = '商品更新任务';

    public function handle()
    {
        $Redis = new \Neigou\RedisClient();
        $Redis->_debug = true;
        $queue_name = 'service_mryx_es';
        $create_index_obj = new Elasticsearchupindexdata();

        while (1) {
            $msg = $Redis->get_msg($queue_name);//获取消息
            $val = $msg;
            //校验
            $msg = json_decode($msg, true);
            $rule = $Redis->check_msg($queue_name, $msg, $val);
            if (!$rule) {
                //消息为空 检索下是否存在process中的消息 存在回转到消息源中进行处理
                sleep(5);
                for ($i = 0; $i < 200; $i++) {
                    $Redis->roll_back($queue_name);
                }
                exit;

            }
            //处理消息
            //处理二次json的msg
            if (is_string($msg['msg'])) {
                $msg['msg'] = json_decode($msg['msg'], true);
            }
            if (intval($msg['msg']['goods_id']) <= 0) {
                $loger_data = array(
                    'remark' => false,
                    'data' => $msg['msg']['goods_id'],
                    'argv' => ''
                );
                \Neigou\Logger::Debug('redis_es_up_fail', $loger_data);
                //处理消息失败 插入回消息队列 记录失败的消息key
                $Redis->del_process($queue_name, $val);
                $Redis->add_msg($queue_name . '_fail', $msg);
            }
            $rzt = $create_index_obj->MRYXUpIndexRedis($msg['msg']);
            //获取缓存
            $cache_key = 'rq:count:es_up:' . date('YmdH');
            $count = count($msg);
            //统计这个时段的请求数
            $Redis->_redis_connection->incrBy($cache_key, 1);
            //统计所有请求数
            $Redis->_redis_connection->incrBy('rq:count:all', 1);
            //处理消息成功 删除消息
            if ($rzt) {
                $loger_data = array(
                    'remark' => $rzt,
                    'data' => $msg['msg']['goods_id'],
                    'argv' => ''
                );
                \Neigou\Logger::Debug('service_redis_mryx_es_up_succ', $loger_data);
                $Redis->del_process($queue_name, $val);
            } else {
                $loger_data = array(
                    'remark' => $rzt,
                    'data' => $msg['msg']['goods_id'],
                    'argv' => ''
                );
                \Neigou\Logger::Debug('service_redis_mryx_es_up_fail', $loger_data);
                //处理消息失败 插入回消息队列 记录失败的消息key
                $Redis->del_process($queue_name, $val);
                $Redis->add_msg($queue_name . '_fail', $msg);
            }
        }
    }
}