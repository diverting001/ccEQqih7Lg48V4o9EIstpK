<?php

namespace App\Api\V3\Service\Pricing;
/**
 * 保存商品价格缓存
 * @version 0.1
 * @package ectools.lib.api
 */

use App\Api\Logic\Redis;

class Cache
{
    private $_redis_obj = null;
    private $_cache_prefix = 'product_price_type_2';
    private $_base_cache_prefix = 'product_price';
    private $_cache_version = 'V6';
    private $_rule_mapping = 'V6_rule_mapping';   //定价规则缓存key
    private $_cache_rule_prefix = 'product_price_rule';//定价规则string类型前缀
    protected $_company_info_hash = 'company_rule_list_hash_01'; // 避免与hash 槽分配重复

    // 对之前的hash类型改为string类型
    protected $_company_info_prefix = 'company_rule';

    protected $_company_info_hash_bak = 'company_rule_list_hash_01_bak';

    protected $_company_keys_sortset = 'company_info_hash_sortset_v2'; // 记录 hash_key 中key的引用次数

    public function __construct()
    {
        $config = array(
            'host'  => config('neigou.REDIS_THIRD_WEB_HOST'),
            'port'  => config('neigou.REDIS_THIRD_WEB_PORT'),
            'auth'  => config('neigou.REDIS_THIRD_WEB_PWD'),
        );
        $this->_redis_obj = new Redis($config);
    }

    /**
     * 设置缓存类型
     * @param $type
     */
    public function setCachePriceType($type)
    {
        $this->_cache_prefix = $this->_base_cache_prefix . '_type_' . $type;
    }

    /*
     * @todo 设置价格缓存
     */
    public function SetCachePrice($data)
    {
        if(empty($data)) return array();
        $cache_data = $products_bn  = array();
        $cache_data_log = array();
        $cache_company = array() ;
        foreach ($data as $k=>$v) {
            $products_bn[]  = $v['product_bn'];
        }
        $old_cache_data = $this->GetCachePriceOrigin($products_bn);
        foreach ($data as $k=>$v){
            $cache_key  = $this->CreateCacheKey($v['product_bn']);
            $v['update_time']   = time();
            $branch_price   = $v['branch'];
            unset($v['branch']);
            //微仓价格缓存
            if(!empty($branch_price)){
                //价格数据
                foreach ($branch_price['price_data'] as $k=>$price_data){
                    $branch_cache_key   = $this->CreateBranchCacheKey($v['product_bn'],$k);
                    $cache_data[$branch_cache_key] = json_encode($price_data);
                }
                //价格和仓对应ud
                foreach ($branch_price['cache_key'] as $branch_id=>$k){
                    $branch_cache_key   = $this->CreateBranchCacheKey($v['product_bn'],$k);
                    $v['branch_cache'][$branch_id] = $branch_cache_key;
                }
            }
            $stage_rule_list = isset($v['stage_price']['stage_rule_list']) ? $v['stage_price']['stage_rule_list']:array() ;
            unset( $v['stage_price']) ;
            // 公司 对于公司 下的规则信息 单独存储
            $company_info = isset($stage_rule_list['company']) ? $stage_rule_list['company'] : '' ;
            $old_company_key_origin = isset($old_cache_data[$v['product_bn']]['stage_rule_list']['company']) ? $old_cache_data[$v['product_bn']]['stage_rule_list']['company']:"" ;
            $old_company_key = is_array($old_company_key_origin) ? md5(json_encode($old_company_key_origin)) :$old_company_key_origin ;
            if($company_info) {
                $json_company_info = json_encode($company_info) ;
                $new_company_key = md5($json_company_info) ;
                $old_key_is_exists = $this->_redis_obj->zExists($this->_company_keys_sortset,$old_company_key);

                if( $old_company_key && ! $old_key_is_exists ) {
                    $res1 = $this->zIncrBy($old_company_key ,1) ;
                }
                if($old_company_key && $old_company_key != $new_company_key) {
                    if( $old_key_is_exists && is_string($old_company_key_origin) ) {
                        $res1 = $this->zIncrBy($old_company_key ,-1) ;
                    }
                    $res2 = $this->zIncrBy($new_company_key ,1) ;
                    // 上线 观察没问题 再行删除
                    if($res1 <= 0 && $old_company_key) {
                        //$old_company_info =   $this->_redis_obj->hget($this->_company_info_hash ,$old_company_key) ;
                        //$this->_redis_obj->hset($this->_company_info_hash_bak ,$old_company_key ,$old_company_info) ;
                        //hash $this->_redis_obj->hdel($this->_company_info_hash,$old_company_key) ;

                        // 设置key的过期时间实现删除
                        $delCacheKey = $this->CreateCompanyInfoCacheKey($old_company_key);
                        $this->_redis_obj->expire($delCacheKey, -10);

                        \Neigou\Logger::Debug('service_price_company_hash_err_del', array('old_company_key' => $old_company_key, 'res1'=>$res1 ,'res2' =>$res2 ,'new_company_key' => $new_company_key,'product_bn' =>$v['product_bn']));
                    }
                   // $res2 不能为0
                   if($res2 <= 0 ) {
                       $incr = abs($res2) + 1 ;
                       $this->zIncrBy($new_company_key ,$incr) ;
                   }
//                    \Neigou\Logger::Debug( $this->_company_info_hash  ,array(
//                        'old_company_key' => "old-" . $old_company_key ,
//                        'new_company_key' => 'new-' . $new_company_key ,
//                        'old_res1' => $res1 ,
//                        'new_res2' => $res2 ,
//                        'is_exists' => $is_exists ,
//                        'product_bn' =>$v['product_bn'] ,
//                    ));
                }
                $stage_rule_list['company'] = $new_company_key ;
                //hash $cache_company[$new_company_key] = $json_company_info ;

                // Redis - company_info 改用 Hash->String
                $company_info_cache_key  = $this->CreateCompanyInfoCacheKey($new_company_key);
                $cache_company[$company_info_cache_key] = $json_company_info;
            }
            if(!empty($stage_rule_list)) {
                $v['stage_rule_list'] = $stage_rule_list ;
            }
            $json =  json_encode($v);
            $cache_data_log[$cache_key] = $json;
            $cache_data[$cache_key] = $json ;
        }

        //保存缓存
        $res =  $this->_redis_obj->mset($cache_data);
        // 保存公司 信息
        //hash $this->hmset($cache_company) ;

        // 保存公司信息改用 hash->string
        $this->_redis_obj->mset($cache_company);

        if($res){
            foreach ($cache_data as $k=>$v){
                $product_info  = json_decode($v,true);
                unset($product_info['update_time'],$old_cache_data[$product_info['product_bn']]['update_time']);
                if(md5(json_encode($product_info)) != md5(json_encode($old_cache_data[$product_info['product_bn']]))){
                    //保存商品变更信息
                    $log_data   = array(
                        'bn'    => $product_info['product_bn'],
                        'iparam1' => time(),
                        'reason'    => json_encode($old_cache_data[$product_info['product_bn']]),
                        'target'    => $cache_data_log[$k],
//                        'data'      => $data ,
                        'action'    => $k,
                    );
                    if($this->_cache_prefix == 'product_price_type_2'){
                        \Neigou\Logger::Debug('pricing_create',$log_data);
                    }else{
                        \Neigou\Logger::Debug('pricing_base_create',$log_data);
                    }
                }
            }
        }
        return $res;
    }

    /*
     * @todo 获取商品价格缓存
     */
    public function GetCachePrice($products_bn){
        $products_price = $this->GetCachePriceOrigin($products_bn) ;
        return $this->getRedisCompany($products_price);
    }
    // 得到 company info 数据
    public function getRedisCompany($products_price) {
        $company_arr = array() ;
        foreach ($products_price as $bn=>$item) {
            if(isset($item['stage_rule_list']['company']) && is_string($item['stage_rule_list']['company'])) {
                $company_arr[$bn] = $item['stage_rule_list']['company'] ;
            }
        }
        $company_info= array() ;
        if($company_arr) {
            // $company_info  = $this->_redis_obj->hmget($this->_company_info_hash ,$company_arr) ;
            //hash $company_info = $this->hmget($company_arr) ;
            $company_info = $this->GetCompanyInfoByCacheKey($company_arr);
        }
        foreach ($products_price as $bn=>$item) {
            $srl = isset($item['stage_rule_list']) ? $item['stage_rule_list'] : $item['stage_price']['stage_rule_list'] ;
            if(isset($srl['company']) && is_string($srl['company']) && !empty($srl['company'])) {
                $info  =  isset($company_info[$srl['company']]) ? $company_info[$srl['company']] : '' ;
                if($info) {
                    $srl['company'] = json_decode($info,true) ;
                }
            }
            unset($products_price[$bn]['stage_rule_list']) ;
            //保持与以前格式相同
            $products_price[$bn]['stage_price']['stage_rule_list'] = $srl  ;
        }
        return $products_price ;
    }

    // 得到redis 的原始信息
    public function GetCachePriceOrigin($products_bn) {
        $products_price = array();
        if(empty($products_bn)) return $products_price;
        $cache_keys     = array();
        foreach ($products_bn as $product_bn) {
            $cache_keys[]   = $this->CreateCacheKey($product_bn);
        }
        $cache_data = $this->_redis_obj->mget($cache_keys);

        unset($cache_keys);
        if(!empty($cache_data) && is_array($cache_data)){
            foreach ($cache_data as $v){
                $product_price_data = json_decode($v,true);
                if($product_price_data){
                    $products_price[$product_price_data['product_bn']]   = $product_price_data;
                }
            }
        }
        unset($cache_data) ;
        return $products_price;
    }

    /*
     * @todo 删除商品缓存
     */
    public function DelCachePrice($products_bn)
    {
        if (empty($products_bn)) return $products_bn;
        $cache_key = $this->CreateCacheKey($products_bn);
        return $this->_redis_obj->delete($cache_key);
    }

    /*
     * @todo 删除每日优鲜商品缓存
     */
    public function DelMRYXCachePrice($product_bn, $version = 'V5')
    {
        $products_price = array();
        $product_cache_keys[] = $this->_cache_prefix . '-' . $version . '_' . md5($product_bn);
        $product_cache_data = $this->_redis_obj->mget($product_cache_keys);
        foreach ($product_cache_data as $v) {
            $product_price_data = json_decode($v, true);
            if ($product_price_data) {
                $products_price[$product_price_data['product_bn']] = $product_price_data;
            }
        }
        if (!$products_price) {
            return;
        }
        foreach ($products_price as $bn => $item) {
            $del_keys_tmp = array();
            if ($item['branch_cache']) {
                foreach ($item['branch_cache'] as $branch_cache_key) {
                    //                $branch_data = $this->_redis_obj->mget(array($branch_cache_key));
                    //                print_r($branch_data);die;
                    //                echo $this->_cache_prefix;
                    $del_keys_tmp[str_replace($this->_cache_prefix . '-', '', $branch_cache_key)] = 1;
                }
                $del_keys = array_keys($del_keys_tmp);
                foreach ($del_keys as $del_key) {
                    $this->_redis_obj->delete($this->_cache_prefix, $del_key);
                    echo $branch_cache_key . "    删除\n";
                }
            }
            $this->_redis_obj->delete($this->_cache_prefix, str_replace($this->_cache_prefix . '-', '', $this->_cache_prefix . '-' . $version . '_' . md5($product_bn)));
            echo $bn . "    删除\n";
        }


    }


    /*
     * @todo 生成一个缓存key
     */
    public function CreateCacheKey($product_bn)
    {
        $cache_key = $this->_cache_prefix . '-' . $this->_cache_version . '_' . md5($product_bn);

        return $cache_key;
    }

    /**
     *  生成一个规则信息的缓存key
     * @param $rule_id
     * @return string
     */
    public function CreateRuleInfoCacheKey($rule_id)
    {
        return $this->_cache_rule_prefix . '-' . $this->_cache_version .'_' . md5($rule_id);
    }


    /*
     * @todo 设置定价规则 mapping 缓存和单条存放的 rule 信息
     */
    public function SetRuleMapping($mapping)
    {
//        $this->_redis_obj->store($this->_cache_prefix,$this->_rule_mapping,$mapping);
        $mapping['rule_info_mapping']['version'] = $mapping['version'];
        $mapping['rule_mapping']['version'] = $mapping['version'];
        $this->_redis_obj->store($this->_cache_prefix, $this->_rule_mapping . '_rule_info_mapping', $mapping['rule_info_mapping']);
        $this->_redis_obj->store($this->_cache_prefix, $this->_rule_mapping . '_rule_mapping', $mapping['rule_mapping']);
        //设置单条存放的rule信息
        if (isset($mapping['rule_list']) && $mapping['rule_list']) {
            $time = time();
            $rules = [];
            foreach ($mapping['rule_list'] as $rule) {
                $cache_key = $this->CreateRuleInfoCacheKey($rule['rule_id']);
                $rule['add_time'] = $time;
                $rules[$cache_key] = json_encode($rule);
            }
            if ($rules) $this->_redis_obj->mset($rules);
        }
    }

    /*
     * @todo 获取定价规则mapping缓存[]
     */
    public function GetRuleMapping()
    {
        $rule_mapping = array();
//        $this->_redis_obj->fetch($this->_cache_prefix,$this->_rule_mapping,$rule_mapping);
        $this->_redis_obj->fetch($this->_cache_prefix, $this->_rule_mapping . '_rule_info_mapping', $rule_mapping['rule_info_mapping']);
        $this->_redis_obj->fetch($this->_cache_prefix, $this->_rule_mapping . '_rule_mapping', $rule_mapping['rule_mapping']);

        if ($rule_mapping['rule_info_mapping']['version'] != $rule_mapping['rule_mapping']['version']) {
            return array();
        }
        $rule_mapping['version'] = $rule_mapping['rule_mapping']['version'];
        unset($rule_mapping['rule_info_mapping']['version']);
        unset($rule_mapping['rule_mapping']['version']);
        return $rule_mapping;
    }

    /**
     *  @todo 获取定价规则mapping缓存
     * 不走hashtable，走string单条获取
     * @param $rule_ids
     * @return array
     */
    public function GetRuleListByRuleIds($rule_ids = [])
    {
        $rule_mapping = array();
        if(!$rule_ids){
            return $rule_mapping;
        }
        $cache_key = [];
        foreach ($rule_ids as $rule_ids_v){
            //生成需要获取的缓存key名
            $cache_key[] = $this->CreateRuleInfoCacheKey($rule_ids_v);
        }
        if($cache_key){
            //批量获取
            $rule_mapping = $this->_redis_obj->mget($cache_key);

            foreach ($rule_mapping as &$rule_mapping_v){
                $rule_mapping_v =  json_decode($rule_mapping_v, true);
            }
        }
        return $rule_mapping;
    }

    /*
     * @todo 删除定价规则mapping缓存
     */
    public function DelRuleMapping()
    {
//        return $this->_redis_obj->delete($this->_cache_prefix,$this->_rule_mapping);
        return $this->_redis_obj->delete($this->_cache_prefix, $this->_rule_mapping . '_rule_info_mapping');
        return $this->_redis_obj->delete($this->_cache_prefix, $this->_rule_mapping . '_rule_mapping');
    }

    /*
     * @todo 生成一个微仓缓存key
     */
    public function CreateBranchCacheKey($product_bn, $key)
    {
        $cache_key = $this->_cache_prefix . '-' . $this->_cache_version . '_branch_' . $key . '_' . md5($product_bn);
        return $cache_key;
    }

    public function MGet($chache_key_list)
    {
        if (empty($chache_key_list)) return array();
        $cache_list = $this->_redis_obj->mget($chache_key_list);
        if (!empty($cache_list)) {
            foreach ($cache_list as $key => $v) {
                $cache_list[$key] = json_decode($v, true);
            }
        }
        return $cache_list;
    }

    //设置单个
    public function hset($redisKey, $data)
    {
        if (empty($data)) {
            return false;
        }
        return $this->_redis_obj->hset($this->_company_info_hash, $redisKey, json_encode($data));
    }

    // 批量获取
    public function hmget($company_arr)
    {
        $data = array_values($company_arr) ;
        if (empty($data)) {
            return array();
        }
        $result =   $this->_redis_obj->hmget($this->_company_info_hash ,$data) ;
        $result =  array_filter($result) ;
        $diff  =  array_diff($data , array_keys($result)) ;
        if(empty($diff)) {
            return $result ;
        }
        $product_list = array() ;
        $company_arr_diff = array() ;
        foreach ($company_arr as $bn=>$key) {
            if(in_array($key ,$diff)) {
                $product_list[] = $bn ;
                $company_arr_diff[$bn] = $key ;
            }
        }
        // 调用创建cache接口
        $setPriceModle = new Setprice() ;
        $res =  $setPriceModle->CreateCache($product_list) ;

//        \Neigou\Logger::General('service_price_company_hash_err', array('diff' => $diff,'company_arr' => $company_arr_diff));
//        $result2 = $this->_redis_obj->hmget($this->_company_info_hash_bak ,array_values($diff)) ;
//        $result2 = array_filter($result2) ;
//        if($result2) {
//            $result =  array_merge($result ,$result2) ;
//        }
        return $result ;
    }

    // hmset 批量保存公司信息
    public function hmset($data)
    {
        if (empty($data)) {
            return false;
        }
        $res = $this->_redis_obj->hmset($this->_company_info_hash, $data);

        // 以下兼容redis 设置数据丢失的情况 添加日志观察情况
//        $keys = array_keys($data) ;
//        $result2 = $this->_redis_obj->hmget($this->_company_info_hash ,$keys) ;
//        $result2_keys = array_keys(array_filter($result2)) ;
//
//        $diff = array() ;
//        foreach ($data as $k=>$info) {
//            if(!in_array($k ,$result2_keys)) {
//                $diff[$k] = $info ;
//            }
//        }
//        if($diff) {
//            \Neigou\Logger::General('service_price_company_hash_set_err', array('diff' => $diff, 'data' => $data));
//            $res =    $this->_redis_obj->hmset($this->_company_info_hash, $diff);
//        }
        return $res ;
    }

    // 往有序集合中增加值
    public function zIncrBy($key , $inrc = 1 ) {
       $res =  $this->_redis_obj->zIncrBy($this->_company_keys_sortset ,$inrc ,$key) ;
       return $res ;
    }
    public function getScoreKey() {
        return $this->_company_keys_sortset ;
    }

    /**
     * 生成公司&定价规则的key
     * company_key => md5后的
     */
    public function CreateCompanyInfoCacheKey($company_key)
    {
        $cache_key = $this->_company_info_prefix . '_' . $company_key;

        return $cache_key;
    }

    /**
     * 根据company_key获取公司ID & 定价规则缓存
     * ['bn' => 'company_key']
     */
    public function GetCompanyInfoByCacheKey($company_arr){
        $company_arr = array_filter($company_arr);

        if (empty($company_arr)){
            return array();
        }

        $uncache_bn = array();

        $company_info_cache = array();

        $company_rule_cache_keys = array();

        foreach ($company_arr as $company_rule_key){
            $company_rule_cache_keys[] = $this->CreateCompanyInfoCacheKey($company_rule_key);
        }

        $company_rule_cache_info = $this->_redis_obj->mget($company_rule_cache_keys);

        foreach ($company_rule_cache_keys as $k => $v){
            $key_arr = explode('_', $v);
            $company_key = $key_arr[2];

            if (!empty($company_rule_cache_info[$k])){
                $company_info_cache[$company_key] = $company_rule_cache_info[$k];
                continue;
            }

            // 未在新的缓存中命中
            $unhit_new_cache_keys[] = $company_key;
        }

        if (!empty($unhit_new_cache_keys)){
            $company_rule_cache_info_old = $this->_redis_obj->hmget($this->_company_info_hash, $unhit_new_cache_keys);

            foreach ($company_rule_cache_info_old as $old_company_key => $old_company_cache_val){
                if (!empty($old_company_cache_val)){
                    // 旧的缓存合并到新的缓存结果中
                    $company_info_cache[$old_company_key] = $old_company_cache_val;
                    // 旧的hash中能够查询到，则直接写入新的缓存中
                    $new_cache_key = $this->CreateCompanyInfoCacheKey($old_company_key);
                    $set_new_cache[$new_cache_key] = $old_company_cache_val;
                }
            }

            if ($set_new_cache){
                // 旧的缓存存入到新的缓存中
                $this->_redis_obj->mset($set_new_cache);
            }
        }

        // 将新旧缓存中均为查询到的bn收集，重新设置缓存
        foreach ($company_arr as $product_bn => $origin_company_key){
            if (!$company_info_cache[$origin_company_key]){
                $uncache_bn[] = $product_bn;
            }
        }

        // 把缺失的bn补充缓存
        if (!empty($uncache_bn)){
            $setPriceObj = new Setprice();

            $setPriceObj->CreateCache($uncache_bn);

            \Neigou\Logger::General('service_price_company_hash_err', array('uncache_bn' => $uncache_bn, 'company_arr' => $company_arr));
        }

        return $company_info_cache;
    }
}
