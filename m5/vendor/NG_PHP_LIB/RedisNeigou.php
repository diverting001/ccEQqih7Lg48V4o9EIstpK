<?php
/**
 * Created by PhpStorm.
 * User: chuanbin
 * Date: 2017/10/26
 * Time: 18:18
 */

namespace Neigou;
@include_once dirname(__FILE__).'/Config.php';

class RedisNeigou
{
    private  $_host ;
    private  $_port ;
    private  $_password;
    public  $_redis_connection;
    public  $persistent_id;
    public $_wait=0;

    function __construct($persistent_id = null){
        $this->_host = PSR_REDIS_CACHE_HOST;
        $this->_port = PSR_REDIS_CACHE_PORT;
        $this->_password = PSR_REDIS_CACHE_PWD;
        $this->_redis_connection = null;
        $this->_debug = false;
        $this->_prefix = 'rq:';
        $this->_err_time = 3;
        $this->persistent_id = $persistent_id;
        $this->connect();
    }

    function __destruct(){
        $this->close();
    }

    //connect redis
    private function connect(){
        if(is_null($this->_redis_connection)){
            try {
                $this->_redis_connection = new \Redis();
                $this->_redis_connection->pconnect($this->_host,$this->_port,3,$this->persistent_id);//设置3s超时时间
                $this->_redis_connection->auth($this->_password);
            } catch (Exception $e) {
                $loger_data = array(
                    'remark'    => 'redis connect fail',
                    'data'    => $e,
                    'success' => false,
                );
                \Neigou\Logger::General('redis_queue_connect_fail',$loger_data);
                return 'connect to server fail';
            }
        }
        return true;
    }

    //断开redis的链接
    private function close(){
        if(is_object($this->_redis_connection)){
            $this->_redis_connection->close();
        }
        $this->_redis_connection = null;
    }


}