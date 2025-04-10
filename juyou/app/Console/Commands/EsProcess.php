<?php
/**
 * Created by PhpStorm.
 * User: guke
 * Date: 2018/6/20
 * Time: 13:22
 */

namespace App\Console\Commands;

use Illuminate\Console\Command;

class EsProcess extends Command
{
    protected $signature = 'EsProcess';
    protected $description = 'ES中间处理(处理es更新队列中重复的商品ID)';

    public function handle()
    {
        $Redis = new \Neigou\RedisClient();
        $Redis->_debug = true;
        $queue_name = 'service_elasticsearch';
        $new_queue_name = 'service_elasticsearch_up';

        while (1) {
            //先取出 300条消息
            for ($i = 0; $i < 100; $i++) {
                $msg = $Redis->get_msg($queue_name);//获取消息
                $val = $msg;
                //校验
                $msg = json_decode($msg, true);
                $rule = $Redis->check_msg($queue_name, $msg, $val);
                if (!$rule) {
                    continue;
                    //消息为空 检索下是否存在process中的消息 存在回转到消息源中进行处理
                    sleep(5);
                    for ($i = 0; $i < 200; $i++) {
                        $Redis->roll_back($queue_name);
                    }
                    exit;
                }
                //处理消息
                //处理二次json的msg
                if (is_string($msg)) {
                    $msg = json_decode($msg['msg'], true);
                }
                if (is_string($msg['msg'])) {
                    $msg['msg'] = json_decode($msg['msg'], true);
                }
//                var_dump($msg);
                $gid = intval($msg['msg']['goods_id']);
                if ($gid > 0) {
                    $goods_id_arr[$gid] = json_encode($msg);
                    $Redis->del_process($queue_name, $val);
                } else {
                    $Redis->del_process($queue_name, $val);
                    $Redis->add_msg($queue_name . '_fail', $val);
                }
            }
            if(empty($goods_id_arr)) exit;
            //转移到新队列
            $goods_id_arr[0] = 'rq:' . $new_queue_name . '_s';//设置第一个参数为队列名称
            ksort($goods_id_arr);
            call_user_func_array(array($Redis->_redis_connection, 'lpush'), $goods_id_arr);
            //增加计数统计 按照小时计数
            $amount_click = 'rq:count:prefix_service_es_up:' . date('YmdH');
            $Redis->_redis_connection->incrBy($amount_click, count($goods_id_arr));
            $goods_id_arr = [];
            echo "100\n";
        }
    }
}
