<?php

namespace App\Api\V6\Service\Calculate;

use App\Api\Common\Common;
use App\Api\Logic\AssetOrder\FreightCal;
use App\Api\Logic\Service;
use App\Api\Logic\Freight;
use App\Api\Logic\AssetOrder\GoodsItem ;
use App\Api\Logic\AssetOrder\OrderAssetCal ;
use App\Api\Model\ServerOrders\ServerCreateOrders ;
use App\Api\Model\Promotion\CalculateLog;

use Swoole\Exception;

/**
 * Description of Calculate
 *
 * @author sundongliang
 */
class CalculateV2
{

    private $freightLogic;
    private $serviceLogic;
    // 参数对象
    private $paramData ;

    public function __construct($paramData)
    {
        // 运费计算
        $this->freightLogic = new Freight();
        $this->paramData = $paramData ;
        //服务调用
        $this->serviceLogic = new Service();
    }

    public function run() {
        if(empty($this->paramData)) {
            return false ;
        }
        // $order_info 订单入库
        $model = new ServerCreateOrders() ;
        $order_res = $model->getRow(['order_id' =>$this->paramData['order_id'] ]) ;
        if(!empty($order_res)) {
            return   ['code' => 200, 'msg' => '已计算成功' ,'data' => ['id' =>$order_res['id']]] ;
        }
        $productList = GoodsItem::getProductInfo($this->paramData['product_list']) ;

        $goodsIdList   = [];
        $productBnList = [];
        foreach ($this->paramData['product_list'] as $product) {
            $goodsIdList[]   = $product['goods_id'];
            $productBnList[] = $product['product_bn'];
        }
        $order_info = [
            'cost_tax' => 0 ,    // 商品税金
            'cost_item' => 0 ,   // 商品总价格
            'gift_promotion' => 0 ,// 赠品总金额
            'total_weight' => 0 , // 货品总重量
            'order_id' => $this->paramData['order_id'] ,
            'member_id' => $this->paramData['member_id'] ,
            'company_id' => $this->paramData['company_id'] ,
        ] ;
        //o2o免邮
        $o2oProductBnList = GoodsItem::getO2OProductList($productBnList) ;

        \Neigou\Logger::Debug( "calculate.v6.point.start2",
            array(
                'order_id' => $this->paramData['order_id'] ,
                'productList' => $productList ,
                'init_productlist' =>$this->paramData['product_list'] ,
            )
        );
        foreach ($productList as $key=>$product) {
            if (in_array($product['product_bn'], $o2oProductBnList) || $product['ziti']) {
                $product['is_free_shipping'] = true;
            } else {
                $product['is_free_shipping'] = false;
            }
            $product  = $this->initProduct($product);
            if($product['is_gift'] != 1 ) {
                $order_info['cost_tax']  +=   $product['cost_tax'];
            }
            $order_info['cost_item'] +=   $product['amount'] ;
            $order_info['total_weight'] += $product['total_weight'] ;
            $productList[$key] =  $product ;
        }
        // 总的税金
        $order_info['cost_tax'] = Common::number2price($order_info['cost_tax']);
        $order_info['cost_item'] = Common::number2price($order_info['cost_item']) ;
        $order_info['total_weight'] = Common::number2price($order_info['total_weight']);

        //防止商品顺序不同对分摊结果造成影响，这里做排序
        ksort($productList);
        $product_list_key = 'product_list' ;
        $this->setParamData($product_list_key , $productList) ;
        \Neigou\Logger::Debug( "calculate.v6.point.start0",
            array(
                'order_id' => $this->paramData['order_id'] ,
                'getOrderData' => $this->getParamData()
            )
        );
        $orderAssetCalObj = new OrderAssetCal($this->getParamData()) ;
        //运营活动
        $promotionProductList = $orderAssetCalObj->promotion();

        if ( $promotionProductList === false) {
            return ['code' => '1010' ,'msg' =>'运营活动失败'];
        }
        $freightObj = new FreightCal($orderAssetCalObj->getOrderData()) ;
        //运费计算
        $freightRes = $freightObj->batchFreight();

        if ( $freightRes === false ) {
            return ['code' => '1011' ,'msg' =>'运费计算失败'];
        }
        //订单总运费
        $order_info['cost_freight'] = $freightObj->getOrderData("freight_amount") ;
        if(is_null($order_info['cost_freight'])) {
            $order_info['cost_freight'] = '0' ;
        }
        $orderAssetCalObj->setOrderData('cost_freight' , $order_info['cost_freight']);
        // 设置 每个商品分摊的运费
        $orderAssetCalObj->setOrderData($product_list_key , $freightObj->getOrderData($product_list_key));

        //免邮券计算
        $freeshippingRes = $orderAssetCalObj->freeshipping();
        if ( $freeshippingRes === false ) {
            return ['code' => '1012' ,'msg' =>'免邮券计算失败'];
        }
        //免税券计算
        $taxationRes = $orderAssetCalObj->taxation();
        if ( $taxationRes === false ) {
            return ['code' => '1013' ,'msg' =>'免税券计算失败'];
        }
        //内购券计算
        $voucherRes = $orderAssetCalObj->voucher();

        if ( $voucherRes === false) {
            return   ['code' => '1014' ,'msg' =>'内购券计算失败'];
        }
        //积分计算
        $pointRes = $orderAssetCalObj->point();

        if ($pointRes === false) {
            return   ['code' => '1015' ,'msg' =>'积分计算失败'];
        }
        $order_info_result  = $orderAssetCalObj->getOrderData() ;
        // 运费
        $feright  = bcsub($order_info['cost_freight'] ,$order_info_result['discount']['freeshipping'] ,2) ;

        //商品总价+快递费+税费
        $total_amount = Common::bcfunc(array(
            $order_info['cost_item'],
            $feright,
            $order_info['cost_tax']
        ),'+' ,2);
        $total_amount = max($total_amount, 0);
        //订单总金额（商品金额+快递金额+商品税金-优惠金额）
        $final_amount = Common::bcfunc(array(
            $total_amount,
            $order_info_result['discount']['dutyfree'],
            $order_info_result['discount']['voucher'],
            $order_info_result['discount']['promotion'],
            $order_info_result['discount']['gift_promotion'],
            $order_info_result['discount']['order_promotion'],
        ),"-" ,2);
        //积分支付总额度(元)
        $totalPointMoney = $order_info_result['discount']['point_money'];
        $totalPoint = $order_info_result['discount']['point'] ;
        //现金支付金额（订单总金额-积分支付金额）
        $cur_money = Common::bcfunc(array($final_amount, $totalPointMoney) ,'-' ,2);
        if ($cur_money < 0) {
            $cur_money = 0;
        }

        //现金指定优惠计算
        $cashRes = $orderAssetCalObj->cash();
        if ($cashRes === false) {
            return   ['code' => '1015' ,'msg' =>'现金指定折扣计算失败'];
        }
        $order_info_result  = $orderAssetCalObj->getOrderData() ;
        if ($order_info_result['discount']['cash'] > 0) {
            //订单总金额（商品金额+快递金额+商品税金-优惠金额）
            $final_amount = Common::bcfunc(array(
                $final_amount,
                $order_info_result['discount']['cash'],
            ),"-" ,2);
            //现金支付金额（订单总金额-积分支付金额）
            $cur_money = Common::bcfunc(array($final_amount, $totalPointMoney) ,'-' ,2);
            if ($cur_money < 0) {
                $cur_money = 0;
            }
        }

        //计算货品对应的积分服务费和现金服务费
        $paymentTaxFeeRes = $orderAssetCalObj->goodsPaymentTaxFee($pointRes);
        if ($paymentTaxFeeRes === false) {
            return   ['code' => '1016' ,'msg' =>'积分服务费和现金服务费计算失败'];
        }
        $order_info_result  = $orderAssetCalObj->getOrderData() ;
        if (!empty($order_info_result['payment_tax_fee'])) {
            //订单总金额（订单总金额+总现金服务费+总积分服务费）
            $final_amount = Common::bcfunc(array(
                $final_amount,
                $order_info_result['payment_tax_fee']['cash'] ?? 0,
            ), "+", 2);

            //现金支付金额（订单总金额-积分支付金额）
            $cur_money = Common::bcfunc(array($final_amount, $totalPointMoney), '-', 2);
            if ($cur_money < 0) {
                $cur_money = 0;
            }
            //追加税费
            $order_info['cost_tax'] = Common::bcfunc(array(
                $order_info['cost_tax'],
                $order_info_result['payment_tax_fee']['cash'] ?? 0,
            ), '+', 2);
        }

        $order_info['final_amount']  = $final_amount ;
        $order_info['cur_money'] = $cur_money ;
        $order_info['point_amount'] = $totalPoint ;
        $order_info['point_money'] = $totalPointMoney ;
        $order_info['voucher']    = $order_info_result['discount']['voucher'] ;
        $order_info['promotion']  = $order_info_result['discount']['promotion'] ;
        $order_info['dutyfree']   = $order_info_result['discount']['dutyfree'] ;
        $order_info['freeshipping']  = $order_info_result['discount']['freeshipping'] ;
        $order_info['order_promotion'] = $order_info_result['discount']['order_promotion'] ;
        $order_info['gift_promotion'] =  $order_info_result['discount']['gift_promotion'] ;
        $order_info['pmt_amount']  = Common::bcfunc([
            $order_info['dutyfree'] ,
            $order_info['order_promotion'] ,
            $order_info['voucher'],
            $order_info['promotion'] ,
            $order_info_result['discount']['gift_promotion'] ,
            $order_info_result['discount']['cash'] ,
        ]) ;

        $model->beginTransaction() ;
        try {
            $order_id_res = $model->baseInsert($order_info) ;
            //商品订单入库
            $productList = $order_info_result['product_list'] ;
            foreach ($productList as $item) {
                $root_product_bn =  $item['root_product_bn'] ;
                if(isset($item['root_product_bn']) && is_array($item['root_product_bn'])) {
                    $root_product_bn  = strval(current($item['root_product_bn']));
                }
                $productData = [
                     "order_id" => $order_info['order_id'] ,
                     'goods_id' => $item['goods_id'],
                     'product_id' => $item['product_id'] ,
                     'goods_bn' => $item['goods_bn'] ,
                     'product_bn' => $item['product_bn'] ,
                     'cost' => $item['cost'] ,
                     'price' => $item['price'] ,
                     'amount' => $item['amount'] ,
                     'cost_tax' => $item['cost_tax'] ,
                     'cost_freight' => $item['cost_freight'] ,
                     'is_free_shipping' => $item['is_free_shipping'] ,
                     'is_gift' => $item['is_gift'] ,
                     'root_product_bn' => $root_product_bn ,
                     'weight' => $item['weight'] ,
                     'quantity' => $item['quantity'] ,
                     'cash_amount' => $item['cash_amount'] ,
                ] ;
                 $bn = $item['product_bn'] ;
                 $assetListData = [] ;
                 // 运营活动
                 if(isset($promotionProductList[$bn])) {
                     $productData['promotion_discount'] = $promotionProductList[$bn]['voucher_discount'] ;
                     foreach ($promotionProductList[$bn]['asset_list'] as $vid=>$amount) {
                         if(bccomp($amount ,'0' , 2) == 0) {
                             continue ;
                         }
                         $assetListData[] = [
                             'type' => 'promotion' ,
                             'order_id' => $order_info_result['order_id'] ,
                             'amount' => $amount ,
                             'money' => $amount ,
                             'asset_bn' => $vid ,
                         ] ;
                     }
                 }
                 // 优惠券
                 if(isset($voucherRes[$bn])){
                     $productData['voucher_discount'] = $voucherRes[$bn]['voucher_discount'] ;
                     foreach ($voucherRes[$bn]['asset_list'] as $vid=>$amount) {
                         if(bccomp($amount ,'0' , 2) == 0) {
                             continue ;
                         }
                         $assetListData[] = [
                             'type' => 'voucher' ,
                             'order_id' => $order_info_result['order_id'] ,
                             'amount' => $amount ,
                             'money' => $amount ,
                             'asset_bn' => $vid ,
                         ] ;
                     }
                 }
                 // 积分计算
                 if(isset($pointRes[$bn])){
                     $productData['use_point'] = $pointRes[$bn]['point'] ;
                     $productData['use_point_money'] = $pointRes[$bn]['point_money'] ;
                     foreach ($pointRes[$bn]['asset_list'] as $vid=>$spoint) {
                         if(bccomp($spoint['point'] ,'0' , 2) == 0) {
                             continue ;
                         }
                         $assetListData[] = [
                             'type' => 'point' ,
                             'order_id' => $order_info_result['order_id'] ,
                             'amount' => $spoint['point'] ,
                             'money' => $spoint['point_money'] ,
                             'asset_bn' => $vid ,
                         ] ;
                     }
                 }
                // 免邮计算
                 if(isset($freeshippingRes[$bn])) {
                     $productData['freight_discount'] = $freeshippingRes[$bn]['voucher_discount'] ;
                     foreach ($freeshippingRes[$bn]['asset_list'] as $vid=>$amount) {
                         if(bccomp($amount ,'0' , 2) == 0) {
                             continue ;
                         }
                         $assetListData[] = [
                             'type' => 'freeshipping' ,
                             'order_id' => $order_info_result['order_id'] ,
                             'amount' => $amount ,
                             'money' => $amount ,
                             'asset_bn' => $vid ,
                         ] ;
                     }
                 }
                 // 免税计算
                 if(isset($taxationRes[$bn])) {
                     $productData['dutyfree_discount'] = $taxationRes[$bn]['voucher_discount'] ;
                     foreach ($taxationRes[$bn]['asset_list'] as $vid=>$amount) {
                         if(bccomp($amount ,'0' , 2) == 0) {
                             continue ;
                         }
                         $assetListData[] = [
                             'type' => 'dutyfree' ,
                             'order_id' => $order_info_result['order_id'] ,
                             'amount' => $amount ,
                             'money' => $amount ,
                             'asset_bn' => $vid ,
                         ] ;
                     }
                 }
                 //指定现金折扣
                if(isset($cashRes[$bn])) {
                    $productData['cash_discount'] = $cashRes[$bn]['voucher_discount'] ;
                    foreach ($cashRes[$bn]['asset_list'] as $vid=>$amount) {
                        if(bccomp($amount ,'0' , 2) == 0) {
                            continue ;
                        }
                        $assetListData[] = [
                            'type' => 'cash' ,
                            'order_id' => $order_info_result['order_id'] ,
                            'amount' => $amount ,
                            'money' => $amount ,
                            'asset_bn' => $vid ,
                        ] ;
                    }
                }
                //给产品赋予服务费信息
                if (!empty($item['payment_tax_fee_rate'])) {
                    $productData['payment_tax_fee_rate'] = json_encode($item['payment_tax_fee_rate']);
                }
                 // 过滤空值
                $productData = array_filter($productData) ;
                 // $productData 入库
                $goods_order_id = $model->setTable('server_calculate_goods')->baseInsert($productData) ;
                if($assetListData) {
                  foreach ($assetListData as $k=>$val) {
                     $assetListData[$k]['asset_goods_id'] = $goods_order_id ;
                  }
                  // $assetListData 入库
                  $model->setTable('server_calculate_asset')->mulitInsert($assetListData) ;
               }
            }
            $model->commit() ;
            return ['code' => 200, 'msg' => '成功', 'data' => ['id' => $order_id_res]];
        }catch (Exception $e) {
            \Neigou\Logger::Debug('calculate-service-log', array(
                    'action'  => 'return-error',
                    'msg' =>$e->getTraceAsString() ,
                    'paramData'=> $this->paramData
                )
            );
            $model->rollBack() ;
            return ['code' => 1017, 'msg' => $e->getMessage() ] ;
       }
    }

    public function setParamData($key,$val) {
        if(isset($this->paramData[$key])) {
            $this->paramData[$key] = $val;
        }
    }
    public function getParamData($key=null) {
        if(isset($this->paramData[$key]) && $key) {
           return   $this->paramData[$key] ;
        }
        return $this->paramData ;
    }
    /**
     * 商品明细信息
     * @param $product
     *
     * @return array
     */
    private function initProduct($product)
    {
        $info = $product ;
        //货品基础属性
        $info['shop_id']               = $product['shop_id'];
        $info['goods_id']              = $product['goods_id'];
        $info['goods_bn']              = $product['goods_bn'];
        $info['product_id']            = $product['product_id'];
        $info['product_bn']            = $product['product_bn'];
        $info['product_name']          = $product['product_name'];
        $info['weight']                = isset($product['weight']) ? $product['weight'] : '0';
        $info['point_account']         = isset($product['point_account']) ? $product['point_account']: [] ;
        //货品数量
        $info['quantity'] = $product['quantity'];
        //货品总重量
        $info['total_weight'] = $info['weight'] * $info['quantity'];
        //货品单价
        $info['price'] = Common::number2price($product['price']);
        $info['cost'] = $product['cost'] ;
        //货品总价格（货品单价*货品数量）
        $info['amount'] = Common::number2price($info['price'] * $info['quantity']);
        $info['is_gift'] = $product['is_gift'] ? $product['is_gift'] : '0' ;
        if(strval($info['is_gift']) == '1') {
            $info['amount']  = '0' ;  // 赠品金额为0
        }
        // 字段兼容
        if(isset($product['taxfees']) && $product['taxfees'] > 0) {
            $product['cost_tax'] =   $product['taxfees'] ;
        }
        //货品税金
        $taxfees = is_numeric($product['cost_tax']) ? Common::number2price($product['cost_tax']) : 0;
        $info['taxfees_price'] = $taxfees;
        //货品运营属性
        $info['promotion_rules'] = [];
        //内部获取字段----开始
        $info['brand_id']     = $product['brand_id'];
        $info['cat_path']     = $product['cat_path'];
        $info['mall_list_id'] = $product['mall_list_id'];
        //是否免运费
        $info['is_free_shipping'] = $product['is_free_shipping'];
        //是否支持积分抵扣
        $info['jifen_pay'] = isset($product['jifen_pay']) ? $product['jifen_pay'] : 0;
        $info['use_rule'] = $product['use_rule'];
        $info['cash_amount'] = $info['amount'] ;

        $info['cost_freight'] = 0 ; // 运费

        $info['root_product_bn'] = isset($product['root_product_bn']) ?$product['root_product_bn']:""  ;

        $info['promotion_discount'] = 0 ; //
        $info['voucher_discount']   = 0 ; //
        $info['freight_discount'] = 0 ; //
        $info['dutyfree_discount'] = 0 ; //免税券折扣
        $info['use_point'] = 0 ;  //积分
        $info['use_point_money'] = 0 ; // 积分对应金额

        $info['order_promotion_discount'] = 0;

        $info['freight_bn'] = $product['freight_bn'] ?? '' ; // 运费模版
        $info['freight_source'] = $product['freight_source'] ?? '' ; //模版来源
        // 总的税金
        $info['cost_tax'] = Common::number2price($taxfees * $info['quantity']) ;

        $info['payment_tax_fee_rate'] = $product['payment_tax_fee_rate'] ?? [] ;//商品的服务费比率 {"cash_rate": "0.8888","point_rate": "0.8888"}

        return $info;
    }

    /**
     * 保存计算日志
     *
     * @param $temp_order_id
     * @param $data type array
     * @param $version type string
     *
     * @return bool
     */
    public function saveCalculateLog($temp_order_id, $data, $version = 'V2')
    {
        $log_data = [
            'order_id' => $temp_order_id,
            'data'     => json_encode($data),
            'version'  => $version,
        ];

        return CalculateLog::save($log_data);
    }

}
