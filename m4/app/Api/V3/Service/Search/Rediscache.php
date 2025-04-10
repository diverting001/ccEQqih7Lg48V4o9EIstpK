<?php
/**
 * redis缓存数据
 * @version 0.1
 * @package ectools.lib.api
 */

namespace App\Api\V3\Service\Search;

use Neigou\RedisNeigou;

class Rediscache
{
    protected $cache_key = '';   //缓存key
    protected $cache_time = '';  //是否使用缓存
    protected $cache_shared_prefix = 'ecstore_v3';   //缓存前缀
    protected $cache_obj = null; //使用缓存对象
    protected $cache_value_num = 20;   //缓存数据间隔

    public function __construct()
    {
//        $this->cache_obj = kernel::single('base_sharedkvstore');
        $this->cache_obj = new RedisNeigou();
    }

    public function GetCacheData($cache_key, $start, $limit)
    {
        $return_data = $return_data = array(    //需要返回数据
            'cache_data' => array(),
            'not_chache' => array()
        );
        $this->cache_key = $cache_key;
        $cache_keys = $this->GetCacheKeys($start, $limit);
        if (empty($cache_keys)) return array();
        //获取所需所有cache_key的数据
        $cache_data = $this->MgetCache($cache_keys);
        foreach ($cache_data as $k => $v) {
            $v = json_decode($v, true);
            if (!is_null($v)) {
                $return_data['cache_data'] = array_merge($return_data['cache_data'], $v);
            } else {
                $cache_key = explode('_', $cache_keys[0]);
                $end_cache_key = explode('_', $cache_keys[count($cache_keys) - 1]);
                $return_data['not_chache'] = array(
                    'start' => $cache_key[count($cache_key) - 2],
                    'limit' => $end_cache_key[count($end_cache_key) - 1] - $cache_key[count($cache_key) - 2] + 1,
                );
                break;
            }
        }
        return $return_data;
    }

    /*
     * @todo 设置缓存数据
     * @parameter $cache_key 缓存key $data 缓存数据 $start缓存存放开始位置
     */
    public function SetCacheData($cache_key, $data, $start = 0, $cache_time = 180)
    {
        if (empty($cache_key) || empty($data)) return false;
        $cache_data = array_chunk($data, $this->cache_value_num);
        foreach ($cache_data as $k => $v) {
            $save_cache_key = $cache_key . '_' . ($start + ($k * $this->cache_value_num)) . '_' . ($start + (($k + 1) * $this->cache_value_num - 1));
            $this->SetCache($save_cache_key, $v, $cache_time);
        }
        return true;
    }

    /*
     * @todo 获取请全部数据所需缓存keys
     */
    protected function GetCacheKeys($start, $limit)
    {
        $cache_keys = array();
        $this->limit = $limit;
        $this->cache_end = ceil(($limit + $start) / $this->cache_value_num) * $this->cache_value_num;
        $this->cache_start = floor($start / $this->cache_value_num) * $this->cache_value_num;
        for ($i = $this->cache_start; $i < $this->cache_end; $i += $this->cache_value_num) {
            $cache_keys[] = $this->cache_shared_prefix . '-' . $this->cache_key . '_' . $i . '_' . ($i + $this->cache_value_num - 1);
        }
        return $cache_keys;
    }

    /*
     * @todo 批量获取缓存
     */
    protected function MgetCache($cache_keys)
    {
        $data = $this->cache_obj->_redis_connection->mget($cache_keys);
        return $data;
    }

    /*
     * @todo 获取缓存
     */
    public function fetch($cache_key)
    {
        $data = '';
        $this->cache_obj->fetch($this->cache_shared_prefix, $cache_key, $data);
        return $data;
    }

    /*
     * @todo 设置缓存
     */
    public function SetCache($key, $data, $outtime = 0)
    {
        return $this->cache_obj->store($this->cache_shared_prefix, $key, $data, $outtime);
    }

    /*
     * @todo 清空缓存
     */
    protected function RemoveCache($key)
    {
        $this->cache_obj->delete($this->cache_shared_prefix, $key);
    }
}
