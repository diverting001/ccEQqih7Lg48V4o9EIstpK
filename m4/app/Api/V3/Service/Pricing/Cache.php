<?php
namespace App\Api\V3\Service\Pricing;
/**
 * 保存商品价格缓存
 * @version 0.1
 * @package ectools.lib.api
 */
use App\Api\Logic\Redis;

class Cache{
    private $_redis_obj  = null;
    private $_cache_prefix  = 'product_price_type_2';
    private $_base_cache_prefix = 'product_price';
    private $_cache_version  = 'V6';
    private $_rule_mapping  = 'V6_rule_mapping';   //定价规则缓存key

    public function __construct(){
        $config = array(
            'host'  => config('neigou.REDIS_THIRD_WEB_HOST'),
            'port'  => config('neigou.REDIS_THIRD_WEB_PORT'),
            'auth'  => config('neigou.REDIS_THIRD_WEB_PWD'),
        );
        $this->_redis_obj    = new Redis($config);
    }

    /**
     * 设置缓存类型
     * @param $type
     */
    public function setCachePriceType($type){
        $this->_cache_prefix = $this->_base_cache_prefix.'_type_'.$type;
    }

    /*
     * @todo 设置价格缓存
     */
    public function SetCachePrice($data){
        if(empty($data)) return array();
        $cache_data = $products_bn  = array();
        $cache_data_log = array();
        foreach ($data as $k=>$v){
            $products_bn[]  = $v['product_bn'];
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
            $cache_data_log[$cache_key] = json_encode($v);
            if (isset($v['stage_price']['rule_price_list'])) {
                unset($v['stage_price']['rule_price_list']);
            }
            $cache_data[$cache_key] = json_encode($v);
        }
        $old_cache_data = $this->GetCachePrice($products_bn);
        //保存缓存
        $res =  $this->_redis_obj->mset($cache_data);
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
        $products_price = array();
        if(empty($products_bn)) return $products_price;
        $cache_keys     = array();
        foreach ($products_bn as $product_bn){
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
        return $products_price;
    }

    /*
     * @todo 删除商品缓存
     */
    public function DelCachePrice($products_bn){
        if(empty($products_bn)) return $products_bn;
        $cache_key  = $this->CreateCacheKey($products_bn);
        return $this->_redis_obj->delete($cache_key);
    }

    /*
     * @todo 删除每日优鲜商品缓存
     */
    public function DelMRYXCachePrice($product_bn,$version='V5'){
//        $branch_data = $this->_redis_obj->mget(array('product_price_type_2-V5_branch_0_f143fad71fa7f247fe18730f07ee7617'));
//        print_r($branch_data);die;
//        $cache_keys[] = $this->_cache_prefix.'-'.$this->_cache_version.'_'.md5($product_bn);
        $products_price = array();
        $product_cache_keys[] = $this->_cache_prefix.'-'.$version.'_'.md5($product_bn);
        $product_cache_data = $this->_redis_obj->mget($product_cache_keys);
//        print_r($cache_data);die;
        foreach ($product_cache_data as $v){
            $product_price_data = json_decode($v,true);
            if($product_price_data){
                $products_price[$product_price_data['product_bn']]   = $product_price_data;
            }
        }
        if(!$products_price){
            return;
        }
        foreach ($products_price as $bn=>$item) {
            $del_keys_tmp = array();
            if($item['branch_cache']){
                foreach ($item['branch_cache'] as $branch_cache_key){
    //                $branch_data = $this->_redis_obj->mget(array($branch_cache_key));
    //                print_r($branch_data);die;
    //                echo $this->_cache_prefix;
                    $del_keys_tmp[str_replace($this->_cache_prefix.'-','',$branch_cache_key)] = 1;
                }
                $del_keys = array_keys($del_keys_tmp);
                foreach ($del_keys as $del_key) {
                    $this->_redis_obj->delete($this->_cache_prefix,$del_key);
                    echo $branch_cache_key."    删除\n";
                }
            }
            $this->_redis_obj->delete($this->_cache_prefix,str_replace($this->_cache_prefix.'-','',$this->_cache_prefix.'-'.$version.'_'.md5($product_bn)));
            echo $bn."    删除\n";
        }



//        return $this->_redis_obj->delete($cache_key);
    }


    /*
     * @todo 生成一个缓存key
     */
    public function CreateCacheKey($product_bn){
        $cache_key  = $this->_cache_prefix.'-'.$this->_cache_version.'_'.md5($product_bn);

        return $cache_key;
    }


    /*
     * @todo 设置定价规则mapping缓存
     */
    public function SetRuleMapping($mapping){
//        $this->_redis_obj->store($this->_cache_prefix,$this->_rule_mapping,$mapping);
        $mapping['rule_info_mapping']['version'] = $mapping['version'];
        $mapping['rule_mapping']['version'] = $mapping['version'];
        $this->_redis_obj->store($this->_cache_prefix, $this->_rule_mapping . '_rule_info_mapping', $mapping['rule_info_mapping']);
        $this->_redis_obj->store($this->_cache_prefix, $this->_rule_mapping . '_rule_mapping', $mapping['rule_mapping']);
    }

    /*
     * @todo 获取定价规则mapping缓存
     */
    public function GetRuleMapping(){
        $rule_mapping    = array();
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

    /*
     * @todo 删除定价规则mapping缓存
     */
    public function DelRuleMapping(){
//        return $this->_redis_obj->delete($this->_cache_prefix,$this->_rule_mapping);
        return $this->_redis_obj->delete($this->_cache_prefix,$this->_rule_mapping . '_rule_info_mapping');
        return $this->_redis_obj->delete($this->_cache_prefix,$this->_rule_mapping . '_rule_mapping');
    }

    /*
     * @todo 生成一个微仓缓存key
     */
    public function CreateBranchCacheKey($product_bn,$key){
        $cache_key  = $this->_cache_prefix.'-'.$this->_cache_version.'_branch_'.$key.'_'.md5($product_bn);
        return $cache_key;
    }

    public function MGet($chache_key_list){
        if(empty($chache_key_list)) return array();
        $cache_list = $this->_redis_obj->mget($chache_key_list);
        if(!empty($cache_list)){
            foreach ($cache_list as $key=>$v){
                $cache_list[$key] = json_decode($v,true);
            }
        }
        return $cache_list;
    }


}
