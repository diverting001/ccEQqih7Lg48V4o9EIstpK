<?php


namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\Api\V3\Service\Pricing\Cache ;
use App\Api\Logic\Redis ;

/**
 * Class CatCache
 * @package App\Console\Commands
 * php artisan  PriceDiff
 */

class PriceDiff extends  Command
{

    protected $signature = 'PriceDiff {--action=}';
    protected $description = '价格对比';


    public function handle()
    {
        $config = array(
            'host' => config('neigou.REDIS_THIRD_WEB_HOST'),
            'port' => config('neigou.REDIS_THIRD_WEB_PORT'),
            'auth' => config('neigou.REDIS_THIRD_WEB_PWD'),
        );

        $redis_obj = new Redis($config);
        $redis_obj_client = $redis_obj->getRedisObj() ;

        $action = $this->option('action');
        if($action == 'del_redis_hash_bak') {
            echo $action . "\n";
            $this->del_bak($redis_obj_client);
            exit(1) ;
        }

        $set_redis_key = 'company_info_hash_sortset_v2' ;
        $redis_company_list =  $redis_obj_client->zRangeByScore($set_redis_key,-10000000,1000) ;
        echo  "item nums :" . count($redis_company_list) . " \n" ;

        if(empty($redis_company_list)) {
            return false ;
        }

        $product_mode   = app('api_db')->connection('neigou_store');
        $i = 1;
        $last_id = 0 ;
        $size = 1000 ;
        $where = " and marketable='true' " ;

        while(true) {
            $sql = "select bn,product_id from sdb_b2c_products where product_id > $last_id {$where} order by product_id asc limit $size";
            $product_list = $product_mode->select($sql);
            if(empty($product_list)) {
                break ;
            }
            $product_bns  = array();
            foreach ($product_list as $k=>$v){
                $last_id = $v->product_id ;
                $product_bns[]  = $v->bn;
                $i++;
            }
            echo "last_id = $last_id \n" ;
            $company_hash_list  =   $this->getComanyHashByBn($product_bns ,$redis_obj);
            if(empty($company_hash_list)) {
                continue ;
            }
            foreach ($redis_company_list as $key=>$hash) {
                // $score =    $redis_obj->zScore($set_redis_key,$hash) ;
                // $score > 0
                if( in_array($hash ,$company_hash_list) ) {
                    echo "ERROR : hash_key = " . $hash . "bns = "  . json_encode($product_bns) ." score = " .$score . " \n" ;
                    unset($redis_company_list[$key]) ;
                }
            }
        }

        echo "最终的删除数量 item nums " . count($redis_company_list) . " \n" ;
        $hashKey = 'company_rule_list_hash_01' ;
        $hashkeyBak = 'company_rule_list_hash_01_bak' ;
        foreach ($redis_company_list as $key=>$hash) {
            $old_company_info = $redis_obj->hget($hashKey ,$hash) ;
            if($old_company_info){
                $redis_obj->hset($hashkeyBak ,$hash ,$old_company_info) ;
            }
            $redis_obj_client->zRem($set_redis_key,$hash) ;
            echo "hash key = {$hash} del \n" ;
            $redis_obj->hdel($hashKey ,$hash)  ;
        }
        echo "++++++++++ END++++++++++++++++\n" ;
    }

  protected  function  getComanyHashByBn($key_arr ,$redisObj) {
        $key_list  = array() ;
        $price_cache_lib  = new  Cache ;
        foreach ($key_arr as $key) {
            $key_redis =  $price_cache_lib->CreateCacheKey($key) ;
            if(empty($key_redis)) {
                echo ("param error\n");
                continue ;
            }
            $key_list[] = $key_redis ;
        }
        $hashKey = 'company_rule_list_hash_01' ;
        $productInfo_redis  =  $redisObj->mget($key_list) ;

        if(empty($productInfo_redis)) {
            return false ;
        }
        $company_hash = array() ;
        foreach ($productInfo_redis as $item) {
            $productInfo = json_decode($item ,true) ;
            $bn = $productInfo['product_bn'] ;
            $stage_rule_list = isset($productInfo['stage_rule_list']) ? $productInfo['stage_rule_list'] : $productInfo['stage_price']['stage_rule_list'] ;
            $company = isset($stage_rule_list['company']) ? $stage_rule_list['company'] : '' ;

            if($company && is_string($company)) {
                $company_hash[$bn] =  $company ;
//                $company_info =  $redisObj->hget($hashKey ,$company) ;
//                if($company_info) {
//                    $company_hash[] =  $company ;
//                }
            }
        }
        return $company_hash ;
    }


    public function del_bak($redis_obj_client) {

        $hashkeyBak = 'company_rule_list_hash_01_bak' ;
        $hashtablekeys = $redis_obj_client->hKeys($hashkeyBak) ;

        echo "before hash_table_bak=$hashkeyBak   count= ".count($hashtablekeys)." \n" ;

        foreach ($hashtablekeys as $hash_key) {
            $redis_obj_client->hDel($hashkeyBak ,$hash_key)  ;
        }

        $hashtablekeys = $redis_obj_client->hKeys($hashkeyBak) ;
        echo "after hash_table_bak=$hashkeyBak   count= ".count($hashtablekeys)." \n" ;
    }

}
