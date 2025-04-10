<?php

namespace App\Api\V3\Controllers;
use App\Api\Common\Controllers\BaseController;
use App\Api\Logic\Redis;
use App\Api\V3\Service\Pricing\GetPrice;
use App\Api\V3\Service\Pricing\Setprice ;
use Illuminate\Http\Request;
use App\Api\V3\Service\Pricing\Cache;

class PriceController extends BaseController
{
    /*
     * @todo 获取商品场景价格
     */
    public function GetList(Request $request){
        $content_data = $this->getContentArray($request);
        $filter = $content_data['filter'];
        $environment = $content_data['environment'];
        $product_list    = array(); //货品列表
        foreach ($filter['product_bn_list'] as $v){
            if(empty($v)){
                $this->setErrorMsg("bn 不能为空");
                return   $this->outputFormat([],201);
            }
            $product_list[] = [
                'product_bn'    => $v,
                'branch_id' => isset($filter['branch_id']) && !empty($filter['branch_id'])?$filter['branch_id']:0,
            ];
        }
        //场景列表
        $stages = array(
            'project_code'  => empty($environment['project_code'])?'':$environment['project_code'],
            'company'  => empty($environment['company_id'])?0:$environment['company_id'],
            'channel'  => empty($environment['channel'])?'':$environment['channel'],
            'tag'  => empty($environment['tag'])?'':$environment['tag'],
        );
        $price_service = new GetPrice();
        // 公司商品定价缓存
        $cacheCompanyList = explode(',', config('neigou.PRICE_CACHE_COMPANY'));
        if (in_array($environment['company_id'], $cacheCompanyList)) {
            $config = array(
                'host' => config('neigou.REDIS_GOODS_PRICE_HOST'),
                'port' => config('neigou.REDIS_GOODS_PRICE_PORT'),
                'auth' => config('neigou.REDIS_GOODS_PRICE_PWD'),
            );
            $redis_obj = new Redis($config);
            $cache_prefix = 'product_price_company';
            $cache_key = $environment['company_id']. '_'. md5(json_encode($product_list));
            $cache_data = array();
            $redis_obj->fetch($cache_prefix, $cache_key, $cache_data);
            if ($cache_data) {
                return $this->outputFormat($cache_data);
            }
            $price_list = $price_service->GetPrice($product_list,$stages);
            if ( ! empty($price_list)) {
                $redis_obj->store($cache_prefix, $cache_key, $price_list, 600);
            }
            return $this->outputFormat($price_list);
        }
        //获取货品价格
        $price_list = $price_service->GetPrice($product_list,$stages);
        return $this->outputFormat($price_list);
    }

    /*
     * @todo 获取商品基础价格
     */
    public function GetBaseList(Request $request)
    {
        $content_data = $this->getContentArray($request);
        $filter = $content_data['filter'];
        $environment = $content_data['environment'];
        $product_list = array(); //货品列表
        foreach ($filter['product_bn_list'] as $v) {
            $product_list[] = [
                'product_bn' => $v,
                'branch_id' => isset($filter['branch_id']) && !empty($filter['branch_id']) ? $filter['branch_id'] : 0,
            ];
        }
        //场景列表
        $stages = array(
            'project_code' => empty($environment['project_code']) ? '' : $environment['project_code'],
            'company' => empty($environment['company_id']) ? 0 : $environment['company_id'],
            'channel' => empty($environment['channel']) ? '' : $environment['channel'],
            'tag' => empty($environment['tag']) ? '' : $environment['tag'],
        );
        $price_service = new GetPrice();
        //获取货品价格
        $price_list = $price_service->GetPrice($product_list, $stages, 1);
        return $this->outputFormat($price_list);
    }

    // 获取历史价格
    public function getProductPriceHistory(Request $request)
    {
        $content_data = $this->getContentArray($request);
        $product_list = $content_data['pricing_data'] ;
        $environment = isset($content_data['environment']) ?$content_data['environment']:[] ;
        if(empty($product_list)) {
            $this->setErrorMsg("bns 不能为空");
            return   $this->outputFormat([],201);
        }
        $cache_lib = new Cache() ;
        $product_list_new = $cache_lib->getRedisCompany($product_list) ;
        //场景列表
        $stages = [] ;
        if(!empty($environment)) {
            $stages = array(
                'project_code' => empty($environment['project_code']) ? '' : $environment['project_code'],
                'company' => empty($environment['company_id']) ? 0 : $environment['company_id'],
                'channel' => empty($environment['channel']) ? '' : $environment['channel'],
                'tag' => empty($environment['tag']) ? '' : $environment['tag'],
            );
        }
        $getPrice = new GetPrice();
        $priceData = [];
        foreach ($product_list_new as $item) {
           $priceItem  = $getPrice->ProductPriceCompute($item, $stages);
           $priceItem['product_bn'] = $item['product_bn'];
           $priceData[] = $priceItem;
        }
        return $this->outputFormat($priceData);
    }


    // 生成缓存
    public function createPricing(Request $request) {
        $content_data = $this->getContentArray($request);
        // $products_bns = $content_data['products_bns'];
        $products_bns =  isset($content_data['filter']['product_bn_list']) ? $content_data['filter']['product_bn_list']:"" ;
        if(empty($products_bns)) {
            $this->setErrorMsg("bns 不能为空");
            return   $this->outputFormat([],201);
        }

        $setPriceModle = new Setprice ;
        $res =  $setPriceModle->CreateCache($products_bns) ;
        $msg =  $res ? "更新成功" : '更新失败' ;
        $this->setErrorMsg($msg);
        $code = $res ? 0 : 201 ;
        return   $this->outputFormat(["result" => $res ],$code);
    }
    // 获取价格详情
    public function priceDetail(Request $request)
    {
        $content_data = $this->getContentArray($request);
        $filter = $content_data['filter'];
        $environment = $content_data['environment'];
        $product_bn_list = $filter['product_bn_list'] ;
        if(empty($product_bn_list)) {
            $this->setErrorMsg("filter-product_bn_list 不能为空");
            return   $this->outputFormat([],201);
        }
        $stages= array() ;
        //场景列表
        if(!empty($environment)) {
            $stages = array(
                'project_code'  => empty($environment['project_code'])?'':$environment['project_code'],
                'company'  => empty($environment['company_id'])? "" :$environment['company_id'],
                'channel'  => empty($environment['channel'])?'': $environment['channel'],
                'tag'      => empty($environment['tag'])?'': $environment['tag'],
            );
        }
        $product_list = array(); //货品列表
        foreach ($filter['product_bn_list'] as $v) {
            $product_list[] = [
                'product_bn' => $v,
                'branch_id' => isset($filter['branch_id']) && !empty($filter['branch_id']) ? $filter['branch_id'] : 0,
            ];
        }
        $price_service = new GetPrice();
        $cache_price_type = 2 ;
        //获取商品定价规则缓存
        $products_cahe_price = $price_service->GetBranchProductPriceCache($product_list, $cache_price_type);
        foreach ($product_bn_list as $product_bn) {
            if (isset($products_cahe_price[$product_bn])) {
                //计算合并商品多场景定价规则
                $rule = $products_cahe_price[$product_bn] ;
                $priceCompute = $price_service->GetProductAllRulePrice($rule, $stages, $cache_price_type);
                $priceCompute = $price_service->mergeStageRule($priceCompute) ;
                $products_price[$product_bn] = $priceCompute ;
            } else {
                $null_product_bns[]          = $product_bn;
                $products_price[$product_bn] = array();
            }
        }
        return $this->outputFormat($products_price);
    }
    // 得到缓存
    public function priceCache(Request $request) {
        $content_data = $this->getContentArray($request);
        $filter = $content_data['filter'];
        $product_bn_list = $filter['product_bn_list'] ;
        if(empty($product_bn_list)) {
             $this->setErrorMsg('product_bn_list不能为空');
            return $this->outputFormat([], 400);
        }
        $key_list  = array() ;
        $price_cache_lib  = new  Cache ;
        foreach ($product_bn_list as $key) {
            $key_redis =  $price_cache_lib->CreateCacheKey($key) ;
            $key_list[$key] = $key_redis ;
        }
        $redis_obj = new Redis();
        $productInfo_redis  =  $redis_obj->mget(array_values($key_list)) ;
        $productInfo_data = [] ;
        $comany_hash_key  = [] ;
        foreach ($productInfo_redis as $k=>$val) {
            if(empty($val)) {
                continue ;
            }
            $temp = json_decode($val ,true );
            if(isset($temp['stage_rule_list']['company']) && !empty($temp['stage_rule_list']['company'])) {
                $comany_hash_key[] = $temp['stage_rule_list']['company'] ;
            }
            $productInfo_data[$temp['product_bn']] = $temp ;
        }
        $comany_hash_data = $price_cache_lib->hmget($comany_hash_key) ;
        $comany_hash_data_bak = $redis_obj->hmget("company_rule_list_hash_01_bak" , $comany_hash_key) ;
        $score_list = array() ;
        foreach ($comany_hash_key as  $val) {
            $score_list[$val] = $redis_obj->zScore($price_cache_lib->getScoreKey() ,$val) ;
        }
        unset($productInfo_redis) ;
        $data = [
            'bn_key' => $key_list ,
            'price_cache' => $productInfo_data ,
            'score_list' => $score_list ,
            'comany_hash_01' => $comany_hash_data ,
            'company_hash_bak' => $comany_hash_data_bak  ,
        ] ;
        return $this->outputFormat($data);
    }

}
