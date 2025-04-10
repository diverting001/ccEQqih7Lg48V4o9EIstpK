<?php
namespace App\Api\V3\Service\Pricing;
/**
 * 商品价格设置
 * @version 0.1
 * @package ectools.lib.api
 */


class Setprice{


    /*
     * @todo 全量商品定价缓存
     */
    public function InsertPricing(){
        //获取需要生成价格的products
        if(empty($index_name))  $index_name = config('neigou.ESSEARCH_UP_INDEX');
        echo "\n\n\n".$index_name."\n\n\n";
        $size   = 1000;
        $time   = time();
        $product_mode   = app::get('b2c')->model('products');
        //$total = $goods_mode->db->select('select count(1) as num from sdb_b2c_goods');
        //$total_page = ceil($total[0]['num']/$size);
        $i = 1;
        while(true){
            $product_bns  = array();
            $s  = ($i-1)*$size;
            $sql = "select bn from sdb_b2c_products where last_modify <= {$time} order by goods_id asc limit {$s},$size";
            $product_list = $product_mode->db->select($sql);
            if(!empty($product_list)){
                foreach ($product_list as $k=>$v){
                    echo $v['bn'].'======'."\n";
                    $product_bns[]  = $v['bn'];
                }
                //生成价格缓存
                $create_res = $this->CreateCache($product_bns);
//                if($create_res == 'RULE_MAPPING_CHANGE'){
//                    $i  = $i--;
//                }
            }else{
                exit;
            }
            $i++;
            //sleep(1);
        }
        echo "\n\n\n".$index_name."\n\n\n";
    }

    /*
     * @todo 商品价格变化更新定价缓存(增量)
     */
    public function UpPricing(){
        while (true){
            echo '1111========='."\n";
            $product_bns    = array();  //商品bn列表
            $mq_ids = array();  //消息id
            //获取商品价格变更消息
            $mq_obj   = kernel::single('b2c_mq_mq');
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
        //创建推送消息

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
     * @todo rpc商品价格缓存更新
     */
    public function RpcCreateCache($bns){
        if(empty($bns)) return '请选择货品bn';
        $bns    = explode(',',$bns);
        $res    = $this->CreateCache($bns);
        if($res === 'RULE_MAPPING_CHANGE'){
            return '定价规则变化,请重试';
        }else if(!$res){
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
        $product_price_obj  = kernel::single('b2c_products_price_productprice');
        $product_list  = $product_price_obj->GetProductsPrice($product_bns); // 获取影响场景的属性
        $rule_lib   = kernel::single('b2c_products_price_rule');
        $price_cache_lib  = kernel::single('b2c_products_price_cache');
//      //数据拆分
//      $moduled_list   = $this->SplitModuled($products_list);
//      //获取模块数据
//      $products_list  = Moduled::GetModuledData($moduled_list);
        //获取当前价格规则版本
        $rule_version    = $rule_lib->GetRuleVersion();
        //计算商品价格
        $products_price_base = $rule_lib->PriceCompute($product_list,1);
        $products_prices = array();
        $getpriceObj = kernel::single('b2c_products_price_getprice');
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
        $price_cache_lib->setCachePriceType(1);
        $res1 = $price_cache_lib->SetCachePrice($products_price_base);
        $price_cache_lib->setCachePriceType(2);
        $res2 = $price_cache_lib->SetCachePrice($products_price_new);
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

    /*
     * @todo 影响商品范围
     */
    public function GetRangeProducts($range_data){
        if(empty($range_data)) return array();
        $pricing_range_mdl  = app::get('b2c')->model('pricing_range');
        $product_bns    = $pricing_range_mdl->GetRangeroduct($range_data);
        return $product_bns;
    }



    /*
     * @todo 数据库价格和DB价格对比
     */
    public function PriceDataDisparity(){
        $disparity_product_price    = array();
        $size   = 3000;
        $cache_obj  = kernel::single('b2c_products_price_cache');
        $i  = 0;
        while (true){
            $products_bn    = array();
            $limit  = ($i*$size).','.$size;
            $product_mode   = app::get('b2c')->model('products');
            $sql = "select price,bn from sdb_b2c_products limit {$limit}";
            $product_list   = $product_mode->db->select($sql);
            if(!empty($product_list)){
                foreach ($product_list as $k=>$v){
                    $products_bn[]  = $v['bn'];
                }
                //获取缓存里面的价格
                $products_cahe_price =  $cache_obj->GetCachePrice($products_bn);
                foreach ($product_list as $k=>$v){
                    if(isset($products_cahe_price[$v['bn']])){
                        if($products_cahe_price[$v['bn']]['price'] !=$v['price']){
                            $disparity_product_price[]  = array(
                                'product_bn'    => $v['bn'],
                                'cache_price'    => $products_cahe_price[$v['bn']]['price'],
                                'db_price'    => $v['price'],
                                'create_time'    => time(),
                            );
                        }
                    }
                }
            }else{
                if(!empty($disparity_product_price)){
                    $disparity_product_price    = array_slice($disparity_product_price,0,100);
                    $mld    = app::get('b2c')->model('pricing_disparity');
                    $mld->AddAll($disparity_product_price);
                    print_r($disparity_product_price);
                }
                exit;
            }
            $i++;
        }

    }


    /*
     * @todo 定价rpc更新
     */
    public function PricingRPC($range_id){
        if(empty($range_id)) return '范围id不能为空';
        $range_mdl   = app::get('b2c')->model('pricing_range'); //范围
        $range_info = $range_mdl->GetRangeData($range_id);
        $this->PricingRangeRPC($range_info);
    }


    public function PricingRangeRPC($range_info){
        if(empty($range_info))  return '范围不存在';
        $range_mdl   = app::get('b2c')->model('pricing_range'); //范围
        if($range_info['type'] == 6 || $range_info['type']  == 5){
            switch ($range_info['type'] == 6){
                case 6:
                    $product_mode   = app::get('b2c')->model('products');
                    $i = 1;
                    $size   = 500;
                    while(true) {
                        $product_bns = array();
                        $s = ($i - 1) * $size;
                        $sql = "select p.bn from mall_module_mall_goods as mg left join sdb_b2c_products as p on p.goods_id = mg.goods_id where mg.mall_id in(".implode(',',$range_info['value']).") order by mg.id asc limit {$s},$size";
                        $product_list = $product_mode->db->select($sql);
                        if (!empty($product_list)) {
                            foreach ($product_list as $k => $v) {
                                $product_bns[] = $v['bn'];
                            }
                            //生成价格缓存
                            $create_res = $this->CreateCache($product_bns);
                        }else{
                            break;
                        }
                        $i++;
                    }
                    break;
            }

        }else{
            $product_bn_list    = array();
            switch ($range_info['type']){
                case 2:
                    $sql = "select p.bn from sdb_b2c_products as p 
                        inner join sdb_b2c_goods as g on g.goods_id = p.goods_id where g.brand_id in(".implode(',',$range_info['value']).")";
                    $products_list  = $range_mdl->db->select($sql);
                    if(!empty($products_list)){
                        foreach ($products_list as $v){
                            $product_bn_list[]   = $v['bn'];
                        }
                    }
                    break;
                case 4:
                    $product_bn_list    = $range_info['value'];
                    break;
                case 7:
                    $sql = "select product_bn from mall_container_products where container_id in(".implode(',',$range_info['value']).")";
                    $products_list  = $range_mdl->db->select($sql);
                    if(!empty($products_list)){
                        foreach ($products_list as $v){
                            $product_bn_list[]   = $v['product_bn'];
                        }
                    }
                    break;
            }
            $create_res = $this->CreateCache($product_bn_list);
        }
    }


//    /*
//     * @todo 拆份商品所属数据
//     */
//    public function SplitModuled($products_list){
//        return $products_list;
//    }

}
