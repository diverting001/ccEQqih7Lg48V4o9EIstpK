<?php

namespace App\Api\Logic\AssetOrder;

use App\Api\Common\Common;
use App\Api\Logic\AssetOrder\SplitPrice;
use App\Api\Logic\Service;

/**
 * Class OrderAsset
 * @package App\Api\Logic\Product
 * 计算服务时 计算订单资产详细 信息
 */
# 计算服务 资产计算主类
class OrderAssetCal
{
    protected  $order_info ;
    protected  $serviceLogic ;
    public $key_product_list = 'product_list' ;
    /**
     * @var Point
     */
    public $pointClassObj = null;

    public function __construct($order_info)
    {
        $this->order_info = $order_info ;
        $this->serviceLogic = new Service();
    }
    // 运营活动
    public function promotion()
    {
        $postProductList = [];
        $productListArr = $this->getOrderData($this->key_product_list) ;
        $giftAmount = '0' ;
        foreach ($productListArr as  $calcProductInfo) {
            if($calcProductInfo['is_gift'] != 1) {
                $postProductList[] = array(
                    'name' => $calcProductInfo['name'],
                    'brand_id' => $calcProductInfo['brand_id'],
                    'cat_path' => $calcProductInfo['cat_path'],
                    'mall_list_id' => $calcProductInfo['mall_list_id'],
                    'id'       => $calcProductInfo['product_id'],
                    'goods_id' => $calcProductInfo['goods_id'],
                    'nums'     => $calcProductInfo['quantity'],
                    'price'    => $calcProductInfo['price'],
                    'shop_id'  => $calcProductInfo['shop_id'],
                    'bn'       =>  $calcProductInfo['product_bn'],
                    'product_bn' => $calcProductInfo['product_bn'],
                    'goods_bn' => $calcProductInfo['goods_bn'],
                    'use_rule' => $calcProductInfo['use_rule'],
                );
            } else {
                $giftAmount = bcadd($giftAmount , $calcProductInfo['amount'] ,2 ) ;
            }
        }
        $this->order_info['discount']['gift_promotion'] = $giftAmount ;
        $postData = [
            'company_id' => $this->order_info['company_id'],
            'member_id'  => $this->order_info['member_id'],
            'product'    => $postProductList,
        ];

        if (!empty($this->order_info['order_use_rules'])) {
            $postData['order_use_rule'] = $this->order_info['order_use_rules'];
        }
        // 运营活动
        $promotionRes = $this->serviceLogic->ServiceCall('promotion_check', $postData);
        \Neigou\Logger::Debug("calculate.v6.promotion", array(
            'action'          => 'check_goods_promotion',
            "request_params"  => $postData,
            "response_result" => $promotionRes,
            'order_id' => $this->order_info['order_id'] ,
        ));


        if ('SUCCESS' != $promotionRes['error_code'] || empty($promotionRes['data'])) {
            return false;
        }
        $promotionData = $promotionRes['data']['product'] ;

        $order_use_rules = isset($this->order_info['order_use_rules']) ? $this->order_info['order_use_rules']: [] ;
         //是否免邮
        foreach ($productListArr as $calcProductInfo) {
            $curProductBn    = $calcProductInfo['product_bn'];
            $curProdPromData = $promotionData[$curProductBn];
            if($curProdPromData['use_rule']) {
                foreach ($curProdPromData['use_rule'] as $ruleId) {
                    $calcData = $curProdPromData['calc'][$ruleId];
                    if ($calcData['status'] == true && $calcData['data']['class'] == 'free_shipping') {
                        $productListArr[$curProductBn]['is_free_shipping'] = 1;
                    }
                }
            }
        }
        $this->setOrderData($this->key_product_list ,$productListArr) ;
        $promotionId = $order_use_rules[0] ;
        if (!empty($order_use_rules) && empty($promotionRes['data']['order'][$promotionId])) {
             return  true ;
        }
        $order_promotion = $promotionRes['data']['order'][$promotionId];
        $order_calc = $order_promotion['calc'];

        if ($order_calc['status'] !== true) {
            return true ;
        }
        //订单满减金额拆分到products
        $order_discount = $order_calc['data']['order_discount'];
        $asset_list_one = [
            'type' => 'promotion' ,
            'match_use_money' => $order_discount ,
            'voucher_id' => 'p_' . $promotionId ,
            'products' => [] ,
        ] ;
        foreach ($order_promotion['products'] as $product_info) {
            $bn = $product_info['bn'] ;
            $asset_list_one['products'][$bn] = $productListArr[$bn]['amount'];
        }
        $assetInfo =  $this->setAssetStation([$asset_list_one] ,'order_promotion') ;
        $productListArr= $this->getOrderData($this->key_product_list);
        foreach ($productListArr as $bn=>$goods_item) {
            $productListArr[$bn]['promotion_discount'] = $assetInfo[$bn]['voucher_discount'] ;
        }
        $this->setOrderData($this->key_product_list ,$productListArr) ;
        return $assetInfo ;
    }

    public function setOrderData($key,$val) {
          $this->order_info[$key] = $val;
    }

    public function getOrderData($key=null) {
        if(isset($this->order_info[$key]) && $key) {
            return  $this->order_info[$key] ;
        }
        return $this->order_info ;
    }

    /**
     * 现金指定金额折扣
     * @return array|bool|mixed
     */
    public function cash()
    {
        $cashInfo = $this->getOrderData('cash') ;
        if ( empty($cashInfo)) {
            return true ;
        }
        $productListArr = $this->getOrderData($this->key_product_list) ;

        $cashInfo = current($cashInfo);

        //规则数据
        $postData = array(
            'rule_list'   => $cashInfo['rule_list'],
            'filter_data' => array(
                'product' => []
            )
        );
        //需要验证的商品
        foreach ($cashInfo['match_product_bn'] as $productBn) {

            $productInfo = $productListArr[$productBn];

            $postData['filter_data']['product'][] = array('goods_bn' => $productInfo['goods_bn'], 'product_bn' => $productInfo['product_bn']);
        }
        $ret = $this->serviceLogic->ServiceCall('product_with_rule', $postData);
        if ('SUCCESS' != $ret['error_code']) {
            \Neigou\Logger::Debug( "calculate.v6.false",
                array(
                    'action'          => 'product_with_rule',
                    "request_params"  => $postData,
                    "response_result" => $ret,
                )
            );
            return false ;
        }
        //判断规则是否存在
        foreach ($cashInfo['rule_list'] as $ruleId) {

            //规则
            if (!isset($ret['data']['product'][$ruleId])) {
                return false;
            }

            //商品
            foreach ($cashInfo['match_product_bn'] as $productBn) {
                if (!isset($ret['data']['product'][$ruleId]['product_list'][$productBn])) {
                    return false;
                }
            }
        }

        $assetListArr = [] ;

        //现金折扣到商品
        $assetListOne = [
            'type' => 'cash' ,
            'match_use_money' => $cashInfo['amount'] ,
            'voucher_id' => 0,
            'products' => [],
        ] ;

        foreach ($cashInfo['match_product_bn'] as $productBn) {
            $assetListOne['products'][$productBn]   = $productListArr[$productBn]['cash_amount'] ;
        }
        $assetListArr[] = $assetListOne;

        $assetInfo =  $this->setAssetStation($assetListArr ,'cash') ;
        $productListArr= $this->getOrderData($this->key_product_list);
        foreach ($productListArr as $bn=>$goods_item) {
            $productListArr[$bn]['cash_discount'] = $assetInfo[$bn]['voucher_discount'] ;
        }
        $this->setOrderData($this->key_product_list ,$productListArr);
        return $assetInfo ;
    }


    //积分计算
    public function point()
    {
        $pointList = $this->getOrderData('point') ;
        if ( ! $pointList) {
            return true ;
        }
        $productListArr = $this->getOrderData($this->key_product_list) ;

        $point_info = current($pointList) ; //只支持一种积分
        $pointChannel = $point_info['extend_data']['point_channel'];
        if ( ! isset($pointList) || count($pointList) != 1) {
            return true ;
        }
        $pointObj = $this->getNewPointClass($pointChannel);
        $member_point = $pointObj->getMemberPoint();

        $withRuleRes = $this->getWithRuleRes($productListArr);
        $order_point_extend = $this->getOrderPointExtend($withRuleRes, $productListArr);
        $total_point = '0';
        $splitPriceArr = $this->getSplitPriceArr($pointList, $member_point, $order_point_extend, $withRuleRes, $productListArr,$total_point);
        if($splitPriceArr === true) {
            return true ;
        }

        // 积分需要单独处理
        $this->order_info['discount']['point'] = $total_point ;
        $this->order_info['discount']['point_money'] = $splitPriceArr['total'] ;
        // 计算现金
        $data = $this->getPointCalculateRes($splitPriceArr['asset_list']);
        $productListArr  = $this->getOrderData($this->key_product_list) ;
        // 该块要仔细测试
        foreach ($productListArr as $bn=>$item) {
            $productFreight  = bcsub($item['cost_freight'] ,$item['freight_discount'] ,3) ;
            $cash_amount     = bcadd($productFreight , $item['cash_amount'] ,3) ;
            $cash_amount     = bcadd($cash_amount ,   $item['cost_tax'] ,3) ; // 税费
            $productListArr[$bn]['cash_amount'] = Common::number2price($cash_amount) ;
        }
        $this->setOrderData($this->key_product_list ,$productListArr);
        return $data ;
    }
    // 优惠券计算
    public function voucher()
    {
        $voucherList = $this->getOrderData('voucher') ;
        $productListArr = $this->getOrderData($this->key_product_list) ;
        if ( empty($voucherList)) {
           return true ;
        }
        $postData = [
            'json_data' => [
                'version'      => 6,
                'newcart'      => 1,
                'voucher_data' => [],
            ],
        ];
        foreach ($voucherList  as $voucherNumArr => $voucherInfo) {
            $voucherData = [
                'voucher_number' => $voucherNumArr,
                'filter_data'    => [
                    'products' => [],
                ],
            ];
            foreach ($voucherInfo['match_product_bn'] as $productBn) {
                $productInfo      = $productListArr[$productBn];
                $voucherData['filter_data']['products'][] = [
                    "bn"       => $productInfo['product_bn'],
                    "goods_id" => $productInfo['goods_id'],
                    "price"    => $productInfo['price'],
                    "quantity" => $productInfo['quantity'],
                    "tax"      => $productInfo['cost_tax'],
                    'amount' => $productInfo['cash_amount'],
                ];
            }
            $postData['json_data']['voucher_data'][] = $voucherData;
        }
        $ret = $this->serviceLogic->ServiceCall('voucher_with_rule', $postData);
        if ('SUCCESS' != $ret['error_code']) {
            \Neigou\Logger::Debug( "calculate.v6.false",
                array(
                    'action'          => 'voucher_with_rule',
                    "request_params"  => $postData,
                    "response_result" => $ret,
                )
            );
            return false ;
        }
        $voucherData = isset($ret['data']['voucher_data']) ? $ret['data']['voucher_data'] : [] ;
        $assetListArr = [] ;
        //券抵扣金额拆分到products
        foreach ($voucherData as $userVoucherArr) {
            $useStatus = $userVoucherArr['result']['status'];
            if(!$useStatus) {
                 continue ;
            }
            $matchUseMoney   = $userVoucherArr['result']['match_use_money'] ? Common::number2price($userVoucherArr['result']['match_use_money']) : 0;
            $assetListOne = [
                'type' => 'voucher' ,
                'match_use_money' => $matchUseMoney ,
                'voucher_id' => $userVoucherArr['voucher_number'] ,
                'products' => [] ,
            ] ;
            foreach ($userVoucherArr['result']['product_bn_list'] as $productBn) {
                $assetListOne['products'][$productBn]   = $productListArr[$productBn]['cash_amount'] ;
            }
            $assetListArr[] = $assetListOne ;
        }
        $assetInfo =  $this->setAssetStation($assetListArr ,'voucher') ;
        $productListArr= $this->getOrderData($this->key_product_list);
        foreach ($productListArr as $bn=>$goods_item) {
            $productListArr[$bn]['voucher_discount'] = $assetInfo[$bn]['voucher_discount'] ;
        }
        $this->setOrderData($this->key_product_list ,$productListArr);
        return $assetInfo ;
    }

    // 免邮券计算
    public function freeshipping()
    {
        $freeshippingList = $this->getOrderData('freeshipping') ;
        $productListArr = $this->getOrderData($this->key_product_list) ;
        if ( ! $freeshippingList) {
            return [] ;
        }
        $postData = [
            'version'      => 6,
            'newcart'      => 1,
            'voucher_data' => [],
        ];
        foreach ($freeshippingList as $couponId => $couponInfo) {
            $voucherData = [
                'coupon_id'   => $couponId,
                'member_id'   => $this->order_info['member_id'],
                'company_id'  => $this->order_info['company_id'],
                'filter_data' => [
                    'products' => [],
                ],
            ];
            foreach ($couponInfo['match_product_bn'] as $productBn) {
                $productInfo   = $productListArr[$productBn];
                $voucherData['filter_data']['products'][] = [
                    "bn"       => $productInfo['product_bn'],
                    "goods_id" => $productInfo['goods_id'],
                    "price"    => $productInfo['price'],
                    "quantity" => $productInfo['quantity'],
                    'freight'  => $productInfo['cost_freight'], //运费
                    'amount' => $productInfo['cash_amount'],
                ];
            }
            $postData['voucher_data'][] = $voucherData;
        }
        $ret = $this->serviceLogic->ServiceCall('freeshipping_with_rule', $postData);
        if ('SUCCESS' != $ret['error_code']) {
            \Neigou\Logger::Debug("calculate.v6.false",
                array(
                    'action' => 'freeshipping_with_rule',
                    "request_params" => $postData,
                    "response_result" => $ret));

            return false ;
        }
        $voucherData = $ret['data']['voucher_data']  ;
        $assetListArr = [] ;
        //券抵扣金额拆分到products
        foreach ($voucherData as $userVoucherArr) {
            $useStatus = $userVoucherArr['result']['status'];
            if(!$useStatus) {
                continue ;
            }
            $matchUseMoney   = $userVoucherArr['result']['match_use_money'] ? Common::number2price($userVoucherArr['result']['match_use_money']) : 0;
            $assetListOne = [
                'type' => 'freeshipping' ,
                'match_use_money' => $matchUseMoney ,
                'voucher_id' => $userVoucherArr['coupon_id'] ,
                'products' => [] ,
            ] ;
            foreach ($userVoucherArr['result']['product_bn_list'] as $productBn) {
                $assetListOne['products'][$productBn] = $productListArr[$productBn]['cost_freight'] - $productListArr[$productBn]['freight_discount'];
            }
            $assetListArr[] = $assetListOne ;
        }
        $assetInfo =  $this->setAssetStation($assetListArr,'freeshipping') ;
        $productListArr= $this->getOrderData($this->key_product_list);
        foreach ($productListArr as $bn=>$goods_item) {
            $productListArr[$bn]['freight_discount'] = $assetInfo[$bn]['voucher_discount'] ;
        }
        $this->setOrderData($this->key_product_list ,$productListArr) ;
        return $assetInfo ;

    }
    //免税券
    public function taxation()
    {
        $dutyfreeList = $this->getOrderData('dutyfree') ;
        if ( ! $dutyfreeList ) {
            return true ;
        }
        $productListArr = $this->getOrderData($this->key_product_list) ;
        $postData = [
            'version'      => 6,
            'newcart'      => 1,
            'voucher_data' => [],
        ];
        foreach ($dutyfreeList as $couponId => $couponInfo) {
            $voucherData = [
                'coupon_id'   => $couponId,
                'member_id'   => $this->order_info['member_id'],
                'company_id'  => $this->order_info['company_id'],
                'filter_data' => [
                    'products' => [],
                ],
            ];
            foreach ($couponInfo['match_product_bn'] as $productBn) {
                $productInfo          = $productListArr[$productBn];
                $voucherData['filter_data']['products'][] = [
                    "bn"       => $productInfo['product_bn'],
                    "goods_id" => $productInfo['goods_id'],
                    "price"    => $productInfo['price'],
                    "quantity" => $productInfo['quantity'],
                    'tax'      => $productInfo['taxfees_price'],
                    'amount'   => $productInfo['cash_amount'],
                ];
            }
            $postData['voucher_data'][] = $voucherData;
        }
        $ret = $this->serviceLogic->ServiceCall('dutyfree_with_rule', $postData);
        if ('SUCCESS' != $ret['error_code']) {
            \Neigou\Logger::Debug( "calculate.v6.false",
                array(
                    'action'          => 'dutyfree_with_rule',
                    "request_params"  => $postData,
                    "response_result" => $ret,
                )
            );
            return  false ;
        }
        $voucherData = $ret['data']['voucher_data'];
        $assetListArr = [] ;
        foreach ($voucherData as $userVoucherArr) {
            $useStatus = $userVoucherArr['result']['status'];
            if(!$useStatus)  {
                continue;
            }
            $matchUseMoney   = $userVoucherArr['result']['match_use_money'] ? Common::number2price($userVoucherArr['result']['match_use_money']) : 0;
            $assetListOne = [
                'type' => 'dutyfree' ,
                'match_use_money' => $matchUseMoney ,
                'voucher_id' => $userVoucherArr['coupon_id'] ,
                'products' => [] ,
            ] ;
            foreach ($userVoucherArr['result']['product_bn_list'] as $productBn) {
                $assetListOne['products'][$productBn] = $productListArr[$productBn]['cost_tax'] - $productListArr[$productBn]['dutyfree_discount'];
            }
            $assetListArr[] = $assetListOne ;
        }
        //  免税券
        $assetInfo  =  $this->setAssetStation($assetListArr ,'dutyfree') ;
        $productListArr= $this->getOrderData($this->key_product_list);
        foreach ($productListArr as $bn=>$goods_item) {
            $productListArr[$bn]['dutyfree_discount'] = $assetInfo[$bn]['voucher_discount'] ;
        }
        $this->setOrderData($this->key_product_list ,$productListArr) ;
        return $assetInfo ;
    }

    /**
     * 计算货品对应的积分服务费和现金服务费
     * @param $pointCalcRes
     * @return bool|array
     */
    public function goodsPaymentTaxFee($pointCalcRes)
    {
        $productListArr = $this->getOrderData($this->key_product_list);

        $cash = 0;//总现金服务费
        foreach ($productListArr as &$product) {
            $tmp_cost_tax = 0;
            if (empty($product['payment_tax_fee_rate'])) {
                $product['payment_tax_fee_rate'] = array();
                continue;
            }
            $payment_tax_fee_rate = $product['payment_tax_fee_rate'];
            $tmp_cash = $tmp_point = 0;
            if (!empty($payment_tax_fee_rate['cash_rate'])) {
                //当前商品真实的现金金额
                $cash_real = $product['cash_amount'];
                //计算现金追加金额
                $tmp_cash = bcmul($cash_real, $payment_tax_fee_rate['cash_rate'], 2);//计算得到应收现金对应的服务费
                $product['payment_tax_fee_rate']['cash']['base_amount'] = $cash_real;//保存这个服务费金额
                $product['payment_tax_fee_rate']['cash']['amount'] = $tmp_cash;//保存这个服务费金额
                $product['payment_tax_fee_rate']['cash']['rate'] = bcmul($payment_tax_fee_rate['cash_rate'], 100, 2) . '%';
                $tmp_cost_tax = bcadd($tmp_cost_tax, $tmp_cash, 2);//将服务费金额加到税费中
                $cash = bcadd($cash, $tmp_cash, 2);//统计现金服务费
                unset($product['payment_tax_fee_rate']['cash_rate']);
            }
            if (!empty($payment_tax_fee_rate['point_rate'])) {
                $all_point_money = 0;
                if (!empty($pointCalcRes)) {
                    //得到当前bn对应的积分信息和积分账户信息
                    foreach ($pointCalcRes as $bn => $tmp) {
                        if ($bn != $product['product_bn']) {
                            continue;
                        }
                        $all_point_money = bcadd($all_point_money, $tmp['point_money'], 2);
                    }
                }
                $tmp_point = bcmul($all_point_money, $payment_tax_fee_rate['point_rate'], 2);
                $product['payment_tax_fee_rate']['point']['base_amount'] = $all_point_money;
                $product['payment_tax_fee_rate']['point']['amount'] = $tmp_point;
                $product['payment_tax_fee_rate']['point']['rate'] = bcmul($payment_tax_fee_rate['point_rate'], 100, 2) . '%';
                $tmp_cost_tax = bcadd($tmp_cost_tax, $tmp_point, 2);//将服务费金额加到税费中
                $cash = bcadd($cash, $tmp_point, 2);//统计积分对应的现金服务费
                unset($product['payment_tax_fee_rate']['point_rate']);
            }

            if (!empty($tmp_cost_tax)) {
                $product['cost_tax'] = bcadd($product['cost_tax'], $tmp_cost_tax, 2);//税费追加
                $product['amount'] = bcadd($product['amount'], $tmp_cost_tax, 2);//总金额追加
                $product['payment_tax_fee_rate']['total_amount'] = $tmp_cost_tax;
            }
            if (!empty($tmp_cash)) $product['cash_amount'] = bcadd($product['cash_amount'], $tmp_cash, 2);//现金支付金额追加
            if (!empty($tmp_point)) $product['cash_amount'] = bcadd($product['cash_amount'], $tmp_point, 2);//积分对应的现金支付金额追加

        }
        $this->setOrderData($this->key_product_list, $productListArr);

        if (!empty($cash)) $this->order_info['payment_tax_fee']['cash'] = $cash;//总现金服务费
        return true;
    }

    // 设置资产  备注：其中积分单独处理
    protected function  setAssetStation($assetListArr,$type) {

        $splitPriceArr =  SplitPrice::Calculate($assetListArr) ;
        if($splitPriceArr === true) {
            return true ;
        }
        $cash_amount_key = ['voucher','order_promotion' ,'point_money', 'cash'] ;
        if(in_array($type ,$cash_amount_key))
        {
            $productListArr = $this->getOrderData($this->key_product_list) ;
            // 计算现金
            foreach ($splitPriceArr['asset_list'] as $bn=>$item) {
                $productListArr[$bn]['cash_amount'] = Common::number2price($productListArr[$bn]['cash_amount'] - $item['voucher_discount']) ;
            }
            $this->setOrderData($this->key_product_list ,$productListArr) ;
        }
        $this->order_info['discount'][$type] = $splitPriceArr['total'] ;
        // 积分需要转换
        if($type == 'point_money') {
            return $splitPriceArr ;
        }
        return $splitPriceArr['asset_list'] ;
    }

    /**
     * 获取一个 point 类的实例对象
     * @param $pointChannel
     * @return Point
     */
    private function getNewPointClass($pointChannel = null): Point
    {
        if ($this->pointClassObj) {
            return $this->pointClassObj;
        }
        $this->pointClassObj = new Point($this->order_info['member_id'], $this->order_info['company_id'], $pointChannel);
        return $this->pointClassObj;
    }

    /**
     * 基于用户积分，订单积分，用户输入积分，对资产金额进行重算
     * @param $pointList
     * @param $member_point
     * @param array $order_point_extend
     * @param array $withRuleRes
     * @param $productListArr
     * @param $total_point
     * @return array|mixed|true
     */
    private function getSplitPriceArr($pointList, $member_point, array $order_point_extend, array $withRuleRes, $productListArr,&$total_point)
    {
        $asset_list_arr = [];
        foreach ($pointList as $sceneId => $curPointData) {
            //当前场景账户余额
            $curMemberPoint = is_numeric($member_point[$sceneId]['point']) ? $member_point[$sceneId]['point'] : 0;
            $sceneDiscountPoint = is_numeric($order_point_extend[$sceneId]['deductible_point']) ? $order_point_extend[$sceneId]['deductible_point'] : 0;
            //计算最大可使用积分数 最大可用积分策略 min(用户输入，用户剩余积分，订单可用积分数)
            $sceneMaxDiscountPoint = min($curPointData['amount'], $curMemberPoint, $sceneDiscountPoint);

            \Neigou\Logger::Debug("calculate.v6.point",
                array(
                    'order_id' => $this->order_info['order_id'],
                    "sceneDiscountPoint" => $sceneDiscountPoint,
                    'amount' => $curPointData['amount'],
                    'curMemberPoint' => $curMemberPoint,
                )
            );
            if (!$sceneMaxDiscountPoint) {
                continue;
            }
            $scene_point_money = $this->pointClassObj->point2money($sceneMaxDiscountPoint, $sceneId);
            $total_point = bcadd($total_point, $sceneMaxDiscountPoint, 3);

            $curSceneRule = $withRuleRes[$sceneId];
            $assetListOne = [
                'type' => 'point',
                'match_use_money' => $scene_point_money,
                'voucher_id' => $sceneId,
                'products' => [],
            ];
            foreach ($productListArr as $bn => $productInfo) {
                if (!in_array($productInfo['product_bn'], $curSceneRule['product_list'])) {
                    continue;
                }
                $productFreight = $productInfo['cost_freight'] - $productInfo['freight_discount'];
                if ($productInfo['jifen_pay'] == 1) {
                    $goodsMaxUsePoint = $productInfo['cash_amount'] + $productFreight;
                } else {
                    $goodsMaxUsePoint = $productFreight;
                }
                $goodsMaxUsePoint = $goodsMaxUsePoint + $productInfo['cost_tax'];
                $assetListOne['products'][$bn] = $goodsMaxUsePoint;
            }

            $asset_list_arr[] = $assetListOne;
        }
        $splitPriceArr = $this->setAssetStation($asset_list_arr, 'point_money');
        return $splitPriceArr;
    }

    /**
     * 基于货品的积分账户分组，计算每一个账户应该支付的积分/积分金额
     * @param array $withRuleRes
     * @param $productListArr
     * @return array
     */
    private function getOrderPointExtend(array $withRuleRes, $productListArr): array
    {
        $order_point_extend = [];
        foreach ($withRuleRes as $withProductRule) {
            $sceneId = $withProductRule['scene_id'];
            $orderPointMoney = 0;
            //订单可用积分抵扣数 = 商品可用积分抵扣数(商品金额-运营活动抵扣-优惠券抵扣) + 运费积分抵扣数(运费总计-运费抵扣)
            foreach ($productListArr as $bn => $productInfo) {
                if (!in_array($productInfo['product_bn'], $withProductRule['product_list'])) {
                    continue;
                }
                $cost_freight = bcsub($productInfo['cost_freight'], $productInfo['freight_discount'], 2);
                $orderPointMoney = $orderPointMoney + $cost_freight + $productInfo['cost_tax'];
                if (isset($productInfo['jifen_pay']) && $productInfo['jifen_pay'] == 1) {
                    $orderPointMoney = $orderPointMoney + $productInfo['cash_amount'];
                }
            }
            $orderPoint = $this->pointClassObj->money2point($orderPointMoney, $sceneId);
            $order_point_extend[$sceneId] = array(
                'scene_id' => $sceneId,
                'deductible_point' => max($orderPoint, 0),
                'deductible_point_price' => max($orderPointMoney, 0),
            );
        }
        return $order_point_extend;
    }

    /**
     * 根据货品的积分账户进行分组，得到一个新的积分规则=>商品bn数组
     * @param $productListArr
     * @return array
     */
    private function getWithRuleRes($productListArr): array
    {
        $withRuleRes = [];
        foreach ($productListArr as $bn => $product) {
            foreach ($product['point_account'] as $account) {
                if (!$withRuleRes[$account]) {
                    $withRuleRes[$account]['scene_id'] = $account;
                }
                $withRuleRes[$account]['product_list'][] = $product['product_bn'];
            }
        }
        return $withRuleRes;
    }

    /**
     * 将资产分组金额重组
     * @param $asset_list
     * @return array
     */
    private function getPointCalculateRes($asset_list)
    {
        $data = [];
        foreach ($asset_list as $bn => $item) {
            $data[$bn] = array(
                'point' => 0,
                'point_money' => $item['voucher_discount'],
                'asset_list' => [],
            );
            $sp = '0';
            foreach ($item['asset_list'] as $pid => $money) {
                $point = $this->pointClassObj->money2point($money, $pid);
                $sp = bcadd($sp, $point, 3);
                $data[$bn]['asset_list'][$pid] = array(
                    'point' => $point,
                    'point_money' => $money,
                );
            }
            $data[$bn]['point'] = $sp;
        }
        return $data;
    }


}
