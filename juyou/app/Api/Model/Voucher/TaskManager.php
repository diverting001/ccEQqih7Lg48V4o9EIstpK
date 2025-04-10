<?php
/**
 * Created by PhpStorm.
 * User: chuanbin
 * Date: 2017/10/20
 * Time: 10:29
 */

namespace App\Api\Model\Voucher;


class TaskManager
{
    private $task = 'sdb_b2c_voucher_rules2goods_task';
    private $_db;

    public function __construct() {
        $this->_db = app('api_db')->connection('neigou_store');
    }

    public function addTask($rule_id,$condition,$act,$type){
        $data['rule_id'] = $rule_id;
        $data['rule_condition'] = $condition;
        $data['act'] = $act;
        $data['service_type'] = $type;
        $data['created'] = time();
        $Redis = new \Neigou\RedisNeigou();
        $Redis->_redis_connection->set('rules_update_time', time());
        return $this->_db->table($this->task)->insertGetId($data);
    }
}