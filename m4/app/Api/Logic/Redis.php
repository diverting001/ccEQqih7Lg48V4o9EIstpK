<?php
namespace App\Api\Logic;


class Redis {

    static private $_redis = null;

    function __construct($config=array())
    {
        if(!isset(self::$_redis)){
            $config = is_array($config)?$config:array();
            $server = isset($config['host'])?$config['host']:(defined('REDIS_SERVER_HOST')?REDIS_SERVER_HOST:"localhost");
            $port = isset($config['port'])?$config['port']:(defined('REDIS_SERVER_PORT')?REDES_SERVER_PORT:6379);
            $timeout = isset($config['timeout'])?$config['timeout']:(defined('REDIS_SERVER_TIMEOUT')?REDIS_SERVER_TIMEOUT:1);
            $auth   = isset($config['auth'])?$config['auth']:(defined('REDIS_AUTH')?REDIS_AUTH:false);

            self::$_redis = new \Redis();
            $result = self::$_redis->connect($server, $port, $timeout);

            if ($result && $auth){
                self::$_redis->auth($auth);
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
        self::$_redis->renameKey($key, $tmp_token_key);
        $token_data = self::$_redis->get($tmp_token_key);
        self::$_redis->delete($tmp_token_key);
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
        $data = self::$_redis->get($shared_prefix.'-'.$shared_key);

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
            $result = self::$_redis->setex($actual_key, $ttl, json_encode($array_value));
        } else {
            $result = self::$_redis->set($actual_key, json_encode($array_value));
        }

        return $result;

    }//End Function
    //mget
    public function mget($keys){
        $data = self::$_redis->mget($keys);
        return $data;
    }
    //mset
    public function mset($data){
        return self::$_redis->mset($data);
    }

    public function expire($key, $outtime=0){
        return self::$_redis->expire($key, $outtime);
    }

    public function delete($shared_prefix, $shared_key)
    {
        self::$_redis->delete($shared_prefix.'-'.$shared_key);

    }//End Function

}
