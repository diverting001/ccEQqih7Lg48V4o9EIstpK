<?php
namespace App\Api\V3\Service\Pricing;
use App\Console\Model\PricingRange ;
use App\Console\Model\Mq ;


/**
 * 商品价格设置  设置redis 缓存
 * @version 0.2 sundongliang
 * @package neigou_service
 */
class Setprice
{
    /*
     *  全量商品定价缓存
     */
    public function InsertPricing(){
        //获取需要生成价格的products
        if(empty($index_name)) {
            $index_name = ESSEARCH_UP_INDEX;
        }
        $start_time = microtime(true) ;
        echo "\n\n".$index_name."\n\n";
        $size   = 1000;
        $product_mode   = app('api_db')->connection('neigou_store');
        $i = 1;
        $last_id = 0 ;
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
            $create_res = $this->CreateCache($product_bns);
        }
        $end_time  = microtime(true) ;
        echo "cost time = " .($end_time - $start_time )  . "s \n" ;
        echo "\n\n\n".$index_name."\n\n\n";

    }

    /*
     * 商品价格变化更新定价缓存(增量)
     */
    public function UpPricing(){
        while (true){
            $product_bns    = array();  //商品bn列表
            $mq_ids = array();  //消息id
            //获取商品价格变更消息 mq
            $mq_obj   = new Mq();
            $message_list   = $mq_obj->GetMessage('price',500);

            if(empty($message_list)) return ;
            if(!empty($message_list)){
                foreach ($message_list as $v){
                    $message_value  = json_decode($v['message'],true);
                    if(isset($message_value['product_bn']) && !empty($message_value['product_bn'])){
                        $product_bns[]  = $message_value['product_bn'];
                    }
                    $mq_ids[]  = $v['mq_id'];
                }
            }
            //生成价格缓存
            $create_res = $this->CreateCache($product_bns);
            if($create_res !== 'RULE_MAPPING_CHANGE'){
                if($create_res){
                    //删除商品消息
                    $mq_obj->DelMessage($mq_ids);
                }else{
                    //消息处理错误,blocked
                    $mq_obj->UpMessageStatus($mq_ids,'blocked');
                }
            }
        }
    }
    /*
     * @todo 商品价格变化更新定价缓存(增量)
     */
    public function UpPricingRedis($data){
        $product_bns[] = $data['product_bn'];
        //生成价格缓存
        $create_res = $this->CreateCache($product_bns);
        if($create_res !== 'RULE_MAPPING_CHANGE'){
            if($create_res){
                //删除商品消息
                return true;
            }else{
                //消息处理错误,blocked
                return false;
            }
        }
    }


    /*
     * @todo 定价信息变动处理(增量)
     */
    public function PricingMessageManage($measssge_list){
        $product_bn = array();
        if(!empty($measssge_list)){
            foreach ($measssge_list as $k=>$v){
                $get_product_bn = $this->GetRangeProducts($v);
                if(empty($get_product_bn)) $get_product_bn  = array();
                $product_bn = array_merge($product_bn,$get_product_bn);
            }
        }
        $product_bn = array_unique($product_bn);
        $res    = $this->CreateCache($product_bn);
        while($res  === 'RULE_MAPPING_CHANGE'){
            $res    = $this->CreateCache($product_bn);
            sleep(1);
        }
        if(!$res){
            return '更新失败';
        }else{
            return '更新成功';
        }
    }

    /*
     * @todo 创建商品价格缓存
     */
    public function CreateCache($product_bns){
        for($i = 0; $i < count($product_bns); $i++) {
            if (strstr($product_bns[$i], 'MRYX-')) {
                unset($product_bns[$i]);
            }
        }
        if(empty($product_bns)) return false;
        $product_price_obj  = new ProductPrice();
        $product_list  = $product_price_obj->GetProductsPrice($product_bns); // 获取影响场景的属性
        $rule_lib   = new Rule();
        $price_cache_lib  = new Cache();
        //获取当前价格规则版本
        $rule_version    = $rule_lib->GetRuleVersion();
        //计算商品价格
        $products_price_base = $rule_lib->PriceCompute($product_list,1);

        $products_prices = array();
        $getpriceObj = new GetPrice();

        foreach ($product_list as $product){
            $product_price = $getpriceObj->ProductPriceCompute($products_price_base[$product['product_bn']], array('all'=>'all'), 1);
            if(!is_null($product_price['price'])){
                $product['price'] = $product_price['price'];
            }
            //替换branch_list
            if(isset($product['branch_list']) && !empty($product['branch_list'])){
                foreach ($product['branch_list'] as $key=>$value){
                    $branch_price = $getpriceObj->ProductPriceCompute($products_price_base[$product['product_bn']]['branch']['price_data'][$key], array('all'=>'all'), 1);
                    if(!is_null($branch_price['price'])){
                        $product['branch_list'][$key]['price']['price'] = $branch_price['price'];
                    }
                }
            }
            $products_prices[] = $product;
        }
        $products_price_new = $rule_lib->PriceCompute($products_prices,2);
        //判断版本是否变化
        if($rule_version != $rule_lib->GetRuleVersion()){
            //清空原价格规则映射
            $rule_lib->UnsetRuleMapping();
            return 'RULE_MAPPING_CHANGE';
        }
        //设置商品缓存
        $price_cache_lib->setCachePriceType(2);
        $res2 = $price_cache_lib->SetCachePrice($products_price_new);


        // 定价缓存写入成功后写MQ
        if ($res2) {
            $this->_stagePricingChangePushMq($products_price_new);
        }

        $price_cache_lib->setCachePriceType(1);
        $res1 = $price_cache_lib->SetCachePrice($products_price_base);
        if(!$res1 || !$res2){
            //商品价格缓存失败
            $this->PrincingError($product_list);
        }
        return $res1 && $res2;
    }

    /*
     * @todo 商品价格缓存生成失败处理
     */
    public function PrincingError($product_list){

    }

    private function _stagePricingChangePushMq($products_price_new)
    {
        if (empty($products_price_new) || !is_array($products_price_new)) {
            return true;
        }

        $products_price_message = array();

        foreach ($products_price_new as $products_price) {

            $company = [];
            if (!empty($products_price['stage_price']['stage_rule_list']['company'])) {
                $company =  array_keys($products_price['stage_price']['stage_rule_list']['company']);
            }

            $products_price_message[] = array(
                'product_bn'    => $products_price['product_bn'],
                'goods_id'      => $products_price['goods_id'],
                'company'       => $company
            );
        }

        if (empty($products_price_message)) {
            return true;
        }

        $mq = new \Neigou\AMQP('goods');
        $routing_key    = 'product.update.pricing';
        $channel_name   = 'goods';

        $res = $mq->BatchPublishMessage($channel_name, $routing_key, $products_price_message);
        \Neigou\Logger::General('stage_pricing_change_push_mq_log', array(
            'data'      => $products_price_message,
            'reason'    => $res
        ));

        return $res;
    }

    /*
     * @todo 影响商品范围
     */
    public function GetRangeProducts($range_data){
        if(empty($range_data)) return array();
        $pricing_range_mdl  =  new PricingRange ;
        $product_bns    = $pricing_range_mdl->GetRangeroduct($range_data);
        return $product_bns;
    }

    public function UpdatePriceRule(int $type)
    {
        $Rule = new Rule();
        $Rule->CreateRuleTree($type,1);
    }
}
