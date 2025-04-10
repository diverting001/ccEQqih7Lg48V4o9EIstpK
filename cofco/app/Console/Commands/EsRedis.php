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

class EsRedis extends Command
{
    protected $signature = 'EsRedis';
    protected $description = '商品更新任务';

    public function handle()
    {
        $limit = 200;
        $Redis = new \Neigou\RedisClient();
        $Redis->_debug = true;
        $queue_name = 'service_elasticsearch_up';
        $create_index_obj = new Elasticsearchupindexdata();
        $i = 1;
        $msg_list = array();
        while (1) {
            if ($i % $limit == 0) {
                $goods_ids = array_keys($msg_list);
                $rzt = $create_index_obj->SaveElasticSearchsData($goods_ids);
                if ($rzt) {
                    foreach ($msg_list as $v) {
                        //获取缓存
                        $cache_key = 'rq:count:service_es_up:' . date('YmdH');
                        //统计这个时段的请求数
                        $Redis->_redis_connection->incrBy($cache_key, 1);
                        //统计所有请求数
                        $Redis->_redis_connection->incrBy('rq:count:all', 1);
                        //处理消息成功 删除消息
                        if ($rzt) {
                            $Redis->del_process($queue_name, $v['val']);
                        } else {
                            //处理消息失败 插入回消息队列 记录失败的消息key
                            $Redis->del_process($queue_name, $v['val']);
                            $Redis->add_msg($queue_name . '_fail', $v['msg']);
                        }
                    }
                }
                $i = 1;
                $msg_list = array();
            } else {
                $msg = $Redis->get_msg($queue_name);//获取消息
                $val = $msg;
                //校验
                $msg = json_decode($msg, true);
                $rule = $Redis->check_msg($queue_name, $msg, $val);
                if (!$rule) {
                    break;
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
                if (intval($msg['msg']['goods_id']) > 0 && !isset($msg_list[$msg['msg']['goods_id']])) {
                    $msg_list[$msg['msg']['goods_id']] = array(
                        'goods_id' => $msg['msg']['goods_id'],
                        'val' => $val,
                        'msg' => $msg
                    );
                    $i++;
                }
            }
        }
        $goods_ids = array_keys($msg_list);
        $rzt = $create_index_obj->SaveElasticSearchsData($goods_ids);
        if ($rzt) {
            foreach ($msg_list as $v) {
                //获取缓存
                $cache_key = 'rq:count:service_es_up:' . date('YmdH');
                //统计这个时段的请求数
                $Redis->_redis_connection->incrBy($cache_key, 1);
                //统计所有请求数
                $Redis->_redis_connection->incrBy('rq:count:all', 1);
                //处理消息成功 删除消息
                if ($rzt) {
                    $Redis->del_process($queue_name, $v['val']);
                } else {
                    //处理消息失败 插入回消息队列 记录失败的消息key
                    $Redis->del_process($queue_name, $v['val']);
                    $Redis->add_msg($queue_name . '_fail', $v['msg']);
                }
            }
        }

//        while (1) {
//            $msg = $Redis->get_msg($queue_name);//获取消息
//            $val = $msg;
//            //校验
//            $msg = json_decode($msg, true);
//            $rule = $Redis->check_msg($queue_name, $msg, $val);
//            if (!$rule) {
//                //消息为空 检索下是否存在process中的消息 存在回转到消息源中进行处理
//                sleep(5);
//                for ($i = 0; $i < 200; $i++) {
//                    $Redis->roll_back($queue_name);
//                }
//                exit;
//            }
//            //处理消息
//            //处理二次json的msg
//            if (is_string($msg['msg'])) {
//                $msg['msg'] = json_decode($msg['msg'], true);
//            }
//            if (intval($msg['msg']['goods_id']) > 0) {
//                $rzt = $create_index_obj->UpIndexRedis($msg['msg']);
//                //获取缓存
//                $cache_key = 'rq:count:service_es_up:' . date('YmdH');
//                $count = count($msg);
//                //统计这个时段的请求数
//                $Redis->_redis_connection->incrBy($cache_key, 1);
//                //统计所有请求数
//                $Redis->_redis_connection->incrBy('rq:count:all', 1);
//                //处理消息成功 删除消息
//                if ($rzt) {
//                    $Redis->del_process($queue_name, $val);
//                } else {
//                    //处理消息失败 插入回消息队列 记录失败的消息key
//                    $Redis->del_process($queue_name, $val);
//                    $Redis->add_msg($queue_name . '_fail', $msg);
//                }
//            } else {
//                //处理消息失败 插入回消息队列 记录失败的消息key
//                $Redis->del_process($queue_name, $val);
//                $Redis->add_msg($queue_name . '_fail', json_encode($msg));
//            }
//        }
    }
}
