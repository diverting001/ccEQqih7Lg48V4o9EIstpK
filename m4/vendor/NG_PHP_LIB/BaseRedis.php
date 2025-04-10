<?php

namespace Neigou;
@include_once dirname(__FILE__).'/Config.php';

class BaseRedis{
    static private $_redis = null;
    private $sign="mall_logic";
    private $server;
    private $port;
    private $auth;
    private $timeout;


    public  function __construct() {
        $this->server = PSR_REDIS_WEB_HOST;
        $this->port = PSR_REDIS_WEB_PORT;
        $this->timeout = 1;
        $this->auth = PSR_REDIS_WEB_PWD;
        
        self::$_redis = new \Redis();
        $result = self::$_redis->connect($this->server, $this->port, $this->timeout);
        if ($result && !empty($this->auth)){
            self::$_redis->auth($this->auth);
        }
    }

    public function store($key, $value, $expire= 0) {
        if ($expire) {
            $result = self::$_redis->setex($this->sign.$key, $expire, $value);
        } else {
            $result = self::$_redis->set($this->sign.$key, $value, $expire);
        }

        return $result;
    }

    public function fetch($key) {
        $store_code=self::$_redis->get($this->sign.$key);
        return $store_code;
    }

    public function delete($key) {
        self::$_redis->delete($this->sign.$key);
    }

    public function incr($key){
        return self::$_redis->incr($this->sign.$key);
    }

}

