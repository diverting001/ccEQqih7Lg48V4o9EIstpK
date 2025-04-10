<?php
namespace App\Api\Logic;


class Redis {

    private $_redis = null;

    function __construct($config=array())
    {
        if(!isset($this->_redis)){
            $config = is_array($config)?$config:array();
            $server = isset($config['host'])?$config['host']:(defined('REDIS_SERVER_HOST')?REDIS_SERVER_HOST:"localhost");
            $port = isset($config['port'])?$config['port']:(defined('REDIS_SERVER_PORT')?REDES_SERVER_PORT:6379);
            $timeout = isset($config['timeout'])?$config['timeout']:(defined('REDIS_SERVER_TIMEOUT')?REDIS_SERVER_TIMEOUT:1);
            $auth   = isset($config['auth'])?$config['auth']:(defined('REDIS_AUTH')?REDIS_AUTH:false);

            try {
                $redis = new \Redis();

                $redis->connect($server, $port, $timeout);

                if ($auth) {
                    $redis->auth($auth);
                }

                $this->_redis = $redis;
            } catch (\Exception $e) {
                $log = array(
                    'remark'    => 'redis 链接失败',
                    'server'    => $server,
                    'port'    => $port,
                    'timeout'    => $timeout,
                    'auth'    => $auth,
                    'error'=>$e->getMessage()
                );
                \Neigou\Logger::General('redis_connect_fail',$log);
            }
        }
    }//End Function

    public function convertTokenToSharedKey($token){

        if (!is_string($token)){
            return false;
        }
        $decode_base64 = base64_decode($token);
        if (empty($decode_base64)){
            return false;
        }

        return  $decode_base64.md5('#$%^YGVFR%^&*()PLKJHGFD@WSDT^TGUJKO'.$token.'#$%^YGVFR%^&*()PLKJHGFD@WSDT^TGUJKO');
    }

    public function tokenHelper_GenerateToken($shared_prefix, $token_data, $ttl){

        $token = base64_encode('openapi-'.md5(json_encode($token_data).microtime().microtime()));
        $redis_key = base64_decode($token).md5('#$%^YGVFR%^&*()PLKJHGFD@WSDT^TGUJKO'.$token.'#$%^YGVFR%^&*()PLKJHGFD@WSDT^TGUJKO');

        if ($this->store($shared_prefix, $redis_key, $token_data, $ttl)){
            return $token;
        }

        return false;

    }

    public function tokenHelper_AtomGetAndDestroy($shared_prefix, $token){
        $key = $this->convertTokenToSharedKey($token);
        if (empty($key)){
            return false;
        }
        $key = $shared_prefix.'-'.$key;

        $tmp_token_key = 'ecstore-tmp-.'.$key.microtime().microtime();
        $this->_redis->renameKey($key, $tmp_token_key);
        $token_data = $this->_redis->get($tmp_token_key);
        $this->_redis->delete($tmp_token_key);
        $token_data = json_decode($token_data, true);

        if (empty($token_data)){
            return false;
        }

        return $token_data;
    }

    public function tokenHelper_getTokenData($shared_prefix, $token){
        $key = $this->convertTokenToSharedKey($token);
        if (empty($key)){
            return false;
        }

        if (!$this->fetch($shared_prefix, $key, $value)){
            return false;
        }

        return $value;
    }

    public function fetch($shared_prefix, $shared_key, &$value, $timeout_version=null)
    {
        $data = $this->_redis->get($shared_prefix.'-'.$shared_key);

        if (false !== $data){
            $value = $data;

            $decoded_value = json_decode($value, true);
            if (!empty($decoded_value)){
                $value = $decoded_value;

                return true;
            }
        }

        return false;

    }//End Function

    public function store($shared_prefix, $shared_key, $array_value, $ttl=0)
    {
        $actual_key = $shared_prefix.'-'.$shared_key;
        if ($ttl) {
            $result = $this->_redis->setex($actual_key, $ttl, json_encode($array_value));
        } else {
            $result = $this->_redis->set($actual_key, json_encode($array_value));
        }

        return $result;

    }//End Function
    //mget
    public function mget($keys){
        $data = $this->_redis->mget($keys);
        return $data;
    }
    //mset
    public function mset($data){
        return $this->_redis->mset($data);
    }

    public function expire($key, $outtime=0){
        return $this->_redis->expire($key, $outtime);
    }

    public function delete($shared_prefix, $shared_key)
    {
        $this->_redis->del($shared_prefix.'-'.$shared_key);

    }//End Function
    // 返回redis 实力对象
    public function getRedisObj()
    {
        return $this->_redis ;
    }
    // hashtable 批量插入
    public function hmset($redisKey,$data)
    {
        if(empty($data)) {
            return false ;
        }
        return $this->_redis->hMSet($redisKey, $data) ;
    }
    // hashtable 单个插入
    public function hset($hashKey ,$key,$data) {
        $data = is_array($data) ? json_encode($data) : $data ;
        return  $this->_redis->hset($hashKey, $key , $data) ;
    }
    // 批量获取  从hashtable中批量获取数据
    public function hmget($hashKey,$keyArr) {
        if(empty($keyArr)) {
            return false ;
        }
        return  $this->_redis->hMGet($hashKey ,$keyArr) ;
    }
    //单个 获取hashtable 中的数据
    public function hget($hashKey ,$key) {
        if(empty($key)) {
            return false ;
        }
        return  $this->_redis->hGet($hashKey ,$key) ;
    }
    // 删除hashtable中的元素
    public function hdel($key1,$key2)
    {
        return $this->_redis->hDel($key1 ,$key2);
    }

    // 有序集合操作
    public function zIncrBy($key, $value, $member)
    {
        return $this->_redis->zIncrBy($key, $value, $member) ;
    }

    // 删除key
    public function zRemRangeByScore($key,$min,$max)
    {
        return $this->_redis->zRemRangeByScore($key,$min,$max) ;
    }

    // 检查有序集合中元素是否存在
    public function zExists($zsetkey, $member)
    {
        $ret = $this->_redis->zScore($zsetkey, $member);
        return false === $ret ? false : true;
    }

    // 获取有序集合的分数
    public function zScore($zsetkey, $member) {
        return  $this->_redis->zScore($zsetkey, $member);
    }
    //删除有序集合中的元素
    public function zRem($key1,$key2) {
        return $this->_redis->zRem($key1 ,$key2) ;
    }

}
