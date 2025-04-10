<?php

namespace App\Api\V1\Controllers;

use App\Api\Common\Common;
use App\Api\Common\Controllers\BaseController;
use App\Api\Logic\AssetOrder\Credit;
use App\Api\Logic\AssetOrder\OrderAmountLimit;
use App\Api\Logic\AssetOrder\OrderService;
use App\Api\Logic\AssetOrder\Point;
use App\Api\Logic\AssetOrder\Settlement;
use App\Api\Logic\CashBlacklist;
use App\Api\Logic\GoodsPool\GoodsPaymentTaxFeeLogic;
use App\Api\Model\BaseModel;
use App\Api\Model\Company\ClubCompany;
use App\Api\Model\Goods\Restriction ;
use App\Api\Logic\Service ;
use App\Api\Logic\AssetOrder\Popwms ;
use App\Api\Model\Region\Region ;
use App\Api\Model\Goods\Transaction;
use App\Api\Logic\AssetOrder\B2cOrderResource ;
use App\Api\Model\DaemonTask\DaemonTask;
use App\Api\Model\Order\CompanyCfgs;


use App\Api\V1\Service\BasicBusinessLimit\BasicBusinessLimit;
use Illuminate\Http\Request;
/**
 * Class OrderGatherController
 * @package App\Api\V1\Controllers
 *
 * 下单 流程服务
 */

class OrderGatherController extends BaseController
{
    /** @var Popwms $popwmsObj*/
    protected $popwmsObj = null ;
    // 商品信息
    protected $goods_list = [] ;
    // 订单信息
    protected $order_data = [] ;
    // 地址信息
    protected $delivery  = [] ;

    // 资产信息
    protected $serviceObj = null ;
    public function __construct() {
        $this->serviceObj = new Service() ;
    }

    protected function initOrderData($order_data) {
        //平台来源PC|手机
        $order_data['terminal'] = isset($order_data['terminal']) ? $order_data['terminal'] : 'pc';
        //匿名下单
        $order_data['anonymous'] = isset($order_data['payment']['anonymous']) ? $order_data['payment']['anonymous'] : 'no';
        //交易币种
        $order_data['currency'] = isset($order_data['payment']['currency']) ? $order_data['payment']['currency'] : '';
        //支付方式限制
        $order_data['payment_restriction'] = $order_data['global'] == 'true' ? 'global' : '';
        //订单分类 platform(内购订单，积分订单)
        $order_data['platform'] = isset($order_data['platform']) ? $order_data['platform'] : 'neigou';
        $order_data['system_code'] = isset($order_data['system_code']) ? $order_data['system_code'] : 'neigou';
        $order_data['channel']     = 'EC';
        $order_data['extend_info_code'] = isset($order_data['extend_info_code']) ? $order_data['extend_info_code'] : null ;
        $order_data['terminal'] = isset($order_data['terminal']) ? $order_data['terminal'] : 'pc' ;
        // 收货方式,默认为：1 表示快递
        $order_data['receive_mode'] = isset($order_data['receive_mode']) ? $order_data['receive_mode'] : '1';
        $order_data['use_son_asset'] = isset($order_data['extend_data']['use_son_asset']) ? $order_data['extend_data']['use_son_asset'] : array();
        return $order_data ;
    }

    // 创建订单 Request $request
    public function Create(Request $request)
    {
         $this->popwmsObj =  new Popwms() ;
         $order_data = $this->getContentArray($request);
         //$order_data  = $this->test() ;

        if(empty($order_data['temp_order_id'])) {
            $order_data['temp_order_id']  = OrderService::getOrderId() ;
        }

        $temp_order_id = $order_data['temp_order_id'];
        $asset_list     = Common::array_group($order_data['asset_list'] ,'type');   // 资产列表
        // 积分渠道
        if(isset($asset_list['point'][0]['extend_data']['point_channel'])) {
            $order_data['point_channel'] = $asset_list['point'][0]['extend_data']['point_channel'] ;
        }
        $product_list   = $order_data['product_list'] ; // 产品列表
        $this->delivery = $this->formatDelivery($order_data['delivery']); // 格式化地址信息
        unset($order_data['product_list'] ,$order_data['delivery']) ;
        $this->order_data = $this->initOrderData($order_data) ;

        /*//用来获取配置，判断是否需要请求业财的逻辑，但是目前默认走业财，本段代码以无效 24-12-19 zlx
        $thirdCompanyModel = new ClubCompany() ;
        $companyId = $this->order_data['company_id'];
        $where = " `scope` = 'company' and `scope_value` = '{$companyId}' and `key` = 'yc_new_settlement' ";
        $newSettlement = $thirdCompanyModel->getScopeByWhereSqlStr($where);
        \Neigou\Logger::Debug('OrderGatherController.newSettlement', array('companyId' => $companyId , 'newSettlement' => $newSettlement));
         */
        $e_msg = '';

        try {
            //检查是否重复下单
            $error_code = '0' ;
            $order_info = OrderService::getOrderInfo($temp_order_id ,$error_code);
            if(!empty($order_info)) {
                $msg = '重复下单' ;
                $error_msg = array('1403' => '参数错误', '1200' => '订单存在', '1204' => '订单不存在', '1499' => '服务不可用');
                $this->putCreateErrorGeneralLog($msg . ' 订单号已存在', array('temp_order_id' => $temp_order_id, 'error_code' => $error_code, 'error_msg' => $error_msg[$error_code] ?? '',));
                return $this->errorReturn($error_code, $msg);
            }
            // 地区检查
            $regionModel = new Region ;
            $region_res =  $regionModel->is_correct_leaf_region($this->delivery['ship_area'], $e_msg) ;
            if(empty($region_res)) {
                $msg        = '所选收货地址信息不完整，请完善信息';
                $error_code = '1005';
                $this->putCreateErrorGeneralLog($msg .'，'. $e_msg, array('temp_order_id' => $temp_order_id,'error_code' => $error_code, 'ship_area' => $this->delivery['ship_area'],));
                return $this->errorReturn($error_code,$msg) ;
            }
            //按照履约主题重构订单信息
           $goods_list_rebulid   = $this->initGoodsList($product_list, $e_msg) ;

           if($goods_list_rebulid === false) {
               $msg        = '获取购物车信息失败';
               $error_code = '1010';
               $this->putCreateErrorGeneralLog($msg .' '. $e_msg, array('temp_order_id' => $temp_order_id,'error_code' => $error_code, 'product_list' => $product_list,));
               return $this->errorReturn($error_code,$msg) ;
           }
           //积分检查
           $msg = '' ;
           $pointRes =  $this->_checkUsePoint($asset_list ,$e_msg) ;
           if(empty($pointRes)) {
               $msg = '[积分余额不足]' ;
               $error_code = '2016' ;
               $this->putCreateErrorGeneralLog($msg . ' ' . $e_msg, array('temp_order_id' => $temp_order_id, 'error_code' => $error_code, 'asset_list' => $asset_list,));
               return $this->errorReturn($error_code, $msg);
           }
           $this->goods_list = $goods_list_rebulid ;

           $calTotalRes =  $this->total($order_data['asset_list'],$e_msg) ;
            //计算订单金额、分摊费用到商品
            if (false === $calTotalRes) {
                $msg = '计算服务失败' ;
                $error_code = '1022' ;
                $this->putCreateErrorGeneralLog($msg . ' ' . $e_msg, array('temp_order_id' => $temp_order_id, 'error_code' => $error_code, 'asset_list' => $order_data['asset_list'],));
                return $this->errorReturn($error_code,$msg) ;
            }
            // 检查下单金额
            $checkRes = $this->checkData() ;
            if($checkRes['code'] != '200') {
                /** $this->order_data['cur_money'] ; // 现金支付金额; $this->order_data['total_amount'] ;// 商品总额 */
                $this->putCreateErrorGeneralLog('未通过下单金额验证 ' . $checkRes['msg'], array('temp_order_id' => $temp_order_id, 'error_code' => $checkRes['code'],
                    'cur_money' => $this->order_data['cur_money'], 'total_amount' => $this->order_data['cur_money'], 'company_id' => $this->order_data['company_id'],));
                return $this->errorReturn($checkRes['code'], $checkRes['msg']);
            }

            //写入待定订单
            if (false === $this->writeTempOrder()) {
                $msg = '预下单失败';
                $error_code = 1025;
                return $this->errorReturn($error_code, $msg);
            }

            $transactionModel =new OrderAmountLimit() ;
            $limitMessage = '';
            $tres = $transactionModel->checkAmountLimit($this->order_data['company_id'],$this->order_data['temp_order_id'],
                $calTotalRes['final_amount'],$this->goods_list, $limitMessage, $this->order_data['member_id'] , $e_msg) ;

            //检查全部下单额度
            if (!$tres) {
                $limitMessage OR $limitMessage = '订单金额超出限制，请联系客服！';
                $error_code = 1006 ;
                $this->putCreateErrorGeneralLog('未通过下单额度验证，文案：' . $limitMessage . ' ，提示：' . $e_msg, array('temp_order_id' => $temp_order_id, 'error_code' => $error_code,
                    'cur_money' => $this->order_data['cur_money'], 'total_amount' => $this->order_data['cur_money'], 'company_id' => $this->order_data['company_id'],));
                return $this->errorReturn($error_code,$limitMessage) ;
            }

            $basicBusinessLimitService = new BasicBusinessLimit();
            $basicBusinessLimitData = $this->formatBasicBusinessData($goods_list_rebulid);
            $basicBusinessRes  = $basicBusinessLimitService->validate($basicBusinessLimitData, $msg);
            if (!$basicBusinessRes['status']) {
                $error_code = 1008 ;
                $this->putCreateErrorGeneralLog('下单商品未通过店铺下单数量/金额验证, ' . $msg, array('temp_order_id' => $temp_order_id, 'error_code' => $error_code,
                    'basicBusinessLimitData' => $basicBusinessLimitData, 'res_data' => $basicBusinessRes['data'],));
                return $this->errorReturn($error_code, $msg);
            }

            B2cOrderResource::setUndoList('order_amount_limit' ,true );
            // 资产注册
            $regFinance = B2cOrderResource::registerFinance($this->order_data ,$product_list,$e_msg) ;
            if(empty($regFinance)) {
                $msg        = '资产注册失败！';
                $error_code = 1007 ;
                $this->putCreateErrorGeneralLog($msg . ' ' . $e_msg, array('temp_order_id' => $temp_order_id, 'error_code' => $error_code,));
                return $this->errorReturn($error_code,$msg) ;
            }
            // 锁定订单资源限制
            B2cOrderResource::lockOrderResource($this->order_data['temp_order_id'], 'order_amount_limit');

            // 锁定优惠券
            $lockRes =  B2cOrderResource::lockAllAsset($this->order_data ,$this->goods_list ,$e_msg) ;
            if($lockRes['code'] != '200') {
                $this->putCreateErrorGeneralLog($lockRes['msg'] .' '. $e_msg, array('temp_order_id' => $temp_order_id, 'error_code' => $lockRes['code'],));
                return  $this->errorReturn($lockRes['code'] ,$lockRes['msg']) ;
            }

            // 结算支付  扣完积分余额了
            $settlementObj = new Settlement() ;
            $settRes = $settlementObj->settlementChannelPay($this->order_data ,$this->goods_list) ;
            if($settRes['code'] != '200') {
                $error_code = '1080' ; // $settRes['code']
                $this->putCreateErrorGeneralLog($settRes['msg'], array('temp_order_id' => $temp_order_id, 'raw_error_code' => $settRes['code'], 'now_error_code' => $error_code,));
                return $this->errorReturn($error_code ,$settRes['msg']) ;
            }

            // 根据C端支付订单，进行B端结算
            // if (!empty($newSettlement)) {
            $checkSettlementOrderPay = $this->checkSettlementOrderPay();
            // if (!$checkSettlementOrderPay) {
            //     $msg        = '根据C端支付订单，进行B端结算失败';
            //     $error_code = 1028 ;
            //     return $this->errorReturn($error_code,$msg) ;
            // }
            // }

            // 锁定订单金额限制
            B2cOrderResource::lockOrderResource($this->order_data['temp_order_id'], 'settlement_channel_pay');

            // 保存拆单信息
            $splitInfo =$this->initSplitOrder() ;


            $splitId = $settlementObj->saveSplit($splitInfo ,$this->goods_list ,$e_msg) ;

            //保存拆单信息
            if (false === $splitId) {
                $msg        = '订单创建失败';
                $error_code = '1040';
                $this->putCreateErrorGeneralLog($msg .' '. $e_msg, array('temp_order_id' => $temp_order_id, 'error_code' => $error_code,));
                return $this->errorReturn($error_code ,$msg) ;
            }
            // 保存拆单ID
            $this->order_data['split_id'] = $splitId ;

            // 申请发票
            $invoiceRes = $settlementObj->createOrderInvoice($this->order_data,$this->goods_list ,$splitInfo,$e_msg) ;
            if(empty($invoiceRes)) {
                $error_code = '1099' ;
                $msg = '开票失败' ;
                $this->putCreateErrorGeneralLog($msg .' '. $e_msg, array('temp_order_id' => $temp_order_id, 'error_code' => $error_code,));
                return $this->errorReturn($error_code ,$msg) ;
            }

            // 下单
            $createOrderRes =  $settlementObj->createOrder($this->order_data,$this->delivery) ;

            if($createOrderRes['code'] != '200') {
                $model = new BaseModel() ;
                // 下单失败更新状态
                $model->setTable('sdb_b2c_pop_transaction')->baseUpdate(['order_id' => $temp_order_id],['status' => '25']) ;
                $err_data = isset($createOrderRes['data']) ? $createOrderRes['data'] : [] ;
                $this->putCreateErrorGeneralLog($createOrderRes['msg'], array('temp_order_id' => $temp_order_id, 'error_code' => $createOrderRes['code'],'err_data'=>$err_data));
                return  $this->errorReturn($createOrderRes['code'] ,$createOrderRes['msg'],$err_data) ;
            }
            $this->order_data['order_id'] = $createOrderRes['order_id'] ;

            // 保存发票
            $settlementObj->saveInvoiceInfo($this->order_data) ;

            $this->setErrorMsg('成功');
            return $this->outputFormat($this->order_data) ;

        }   catch (\Exception $exception) {
            $this->putCreateErrorGeneralLog('exception捕获异常', array('temp_order_id' => $temp_order_id, 'error_code' => $error_code, array(
                $exception->getFile(), $exception->getLine(), $exception->getMessage(), $exception->getCode()
            )));
            return  $this->errorReturn('1500' ,$exception->getMessage()) ;
        }
    }

    public function checkData ()
    {
        $curMoney = $this->order_data['cur_money'] ; // 现金支付金额
        $company_id = $this->order_data['company_id'] ;
        $orderId = $this->order_data['temp_order_id'] ;
        // 商品总额
        $cost_item = $this->order_data['total_amount'] ;
        $msg = '' ;
        //检查单次下单额度是否允许  cost_item 商品总金额
        $ret_val = (new Restriction())->checkCompanyOrderAmount($this->order_data['company_id'],$cost_item ,$msg);
        if (!$ret_val) {
            $error_code = '1008';
            $msg        = strlen($msg) > 0 ? $msg : '订单金额不满足叁万元起运标准';
            return  array('code' => $error_code ,'msg' => $msg) ;
        }

        $third_company = new ClubCompany() ;
        $channel_info = $third_company -> getChannelByCompanyId($company_id);
        $channel = $channel_info['channel'] ;
        // 检测数据是否合法 现金支付部分 是否超过信用额度 // 废弃 at 2021-05-21
//        $returnCurMoney = $this->checkCurMoney($curMoney ,$company_id,$orderId ,$channel) ;
//
//        if($returnCurMoney['code'] != '200') {
//            return $returnCurMoney ;
//        }
        // 是否支持现金支付
        $returnIsPayCurMoney = $this->checkIsPayForMoney($company_id ,$curMoney ,$channel) ;
        if($returnIsPayCurMoney['code'] != '200') {
            return $returnIsPayCurMoney ;
        }

      // 检查当前货品是否存在禁用现金补差
        $returnJoinCashBlacklistResult = $this->checkIsJoinCashBlacklist($company_id, $curMoney, array_keys($this->goods_list));
        if($returnJoinCashBlacklistResult['code'] != '200') {
            return $returnJoinCashBlacklistResult;
        }

        // 最大单笔金额
        $maxPriceRes = Credit::getScopeByWeight('single_transaction_max_price' ,$channel ,$company_id) ;
        if (isset($maxPriceRes['key_value']) && $maxPriceRes['key_value']) {
            if ($cost_item - $maxPriceRes['key_value'] > 0.0001) {
                $error_code = '1097';
                $msg        = '当前订单金额过大，本公司单笔交易允许最大金额：' . $maxPriceRes['key_value'] . '元';
                return array('code' => $error_code ,'msg'=> $msg  ) ;
            }
        }
        return  array('code' => '200' ,'msg' => '验证通过') ;
    }

    //检测数据是否合法 现金支付部分 是否超过信用额度  废弃 at 2021-05-21
    public function checkCurMoney ($curMoney ,$company_id ,$orderId ,$channel='') {

        if(bccomp($curMoney , '0' ,2) <= 0) {
            return  array('code' => '200' ,'msg' => '验证通过') ;
        }
        $cre_req = array() ;
        $cre_req['channel'] = $channel ;
        $cre_req['company_id'] = $company_id;
        $cre_req['order_id'] = $orderId ;
        $cre_req['part'] = 'money';
        $cre_req['rmb_amount'] = $curMoney ;
        $credit_res = Credit::record($cre_req);
        if($credit_res['code'] === 404) {
            // code= 2100 , msg = 超过支付限额，请联系客服
             return array('code' => '1099' ,'msg' => '超过支付限额，请联系客服') ;
        }
        return  array('code' => '200' ,'msg' => '验证通过') ;
    }

    // 是否现金支付
    public function checkIsPayForMoney($company_id ,$cur_money,$channel='')
    {
        $cashDisableRes = Credit::GetCompanyChannelSetByKey( $company_id, $channel , 'new_point_mall_cash_disable' );
        $cashDisableKeyValue = $cashDisableRes['key_value'] ? json_decode( $cashDisableRes['key_value'], true ) : array();
        if ( $cashDisableKeyValue && $cashDisableKeyValue['status'] )
        {
            if ( $cur_money > 0 )
            {
                $error_code = '1097';
                $msg = $cashDisableKeyValue['message'];
                return array('code' => $error_code ,'msg'=> $msg ,'error_data' => array() ) ;

            }
        }
        return  array('code' => '200' ,'msg' => '验证通过') ;
    }

    public function checkIsJoinCashBlacklist($company_id ,$cur_money, $product_bns)
    {
        if ($cur_money > 0) {
           $companyBlacklistRule =  CashBlacklist::getCompanyBlacklistRule($company_id, $product_bns);
           if (!empty($companyBlacklistRule['rule_id'])) {
               $error_code = '1097';
               return array('code' => $error_code ,'msg'=> '该商品无法使用现金补全差价，请重新选择商品' ,'error_data' => array() ) ;
           }
        }
        return  array('code' => '200' ,'msg' => '验证通过');
    }

    // 如果失败 则回滚信息
    protected function errorReturn($error_code,$error_msg,$error_data=[]) {

        // echo  "this_rollback_err : " .$this->order_data['temp_order_id'] ;  echo "\n" ;
        B2cOrderResource::thisRollback($this->order_data) ;
        $errList = [] ;
        if(!empty($error_data)) {
            $product_list = Common::array_rebuild($this->goods_list ,'product_bn') ;
            foreach ($error_data as $bn) {
                if(isset($product_list[$bn])) {
                    $errList[] = array(
                        'name' => $product_list[$bn]['name'] ,
                        'bn' => $bn ,
                        'quantity' => $product_list[$bn]['quantity']
                    ) ;
                }
            }
        }
        $this->setErrorMsg($error_msg);
        return  $this->outputFormat($errList, $error_code);
    }

    private function initProduct($product)
    {
        $info = $product;
        //货品单价
        $info['price'] = $product['price']['price'];
        //货品单价
        $info['primitive_price'] = $product['price']['primitive_price'];
        //市场价
        $info['mktprice'] = $product['price']['mktprice'] ?? 0 ;
        //成本价
        $info['cost'] = $product['price']['cost'] ?? 0;
        //
        $info['product_bn'] = $product['bn'] ;
        $info['weight']  = $product['weight'] ?$product['weight']: '0';
        $info['quantity'] = isset($info['quantity']) ? $info['quantity'] : 0 ;
        $info['total_weight'] = bcmul($info['weight'] , $info['quantity'] ,2);
        //货品总价格（货品单价*货品数量）
        $info['amount'] = bcmul($info['price'], $info['quantity'],3);
        //商品类型（'product','pkg','gift','adjunct'）
        $info['item_type'] = isset($product['item_type']) ? $product['item_type'] : 'product';
        // 赠品金额为0
        if($info['is_gift'] == 1) {
            $info['amount']  = '0' ;
            $info['item_type'] = 'gift' ;
        }
        //积分价格
        $info['point_amount'] = $product['price']['point_price'];
        //默认优惠金额
        $info['pmt_amount'] = 0;
        // 字段兼容
        if(isset($product['taxfees']) && $product['taxfees'] > 0) {
            $product['cost_tax'] =   $product['taxfees'] ;
        }
        //货品税金
        $info['cost_tax'] = isset($product['cost_tax']) ? $product['cost_tax'] : 0;
        //商品参与免邮运营活动
        if (isset($product['is_free_shipping']) && $product['is_free_shipping'] == true) {
            $info['is_free_shipping'] = true;
        }
         //是否积分支付
        $info['jifen_pay'] = isset($product['jifen_pay']) ? $product['jifen_pay'] : 0;
        //运费
        $info['cost_freight'] = 0;
        //服务费比例信息['cash_rate'=>0.8911,'point_rate'=>0.3214]
        $info['payment_tax_fee_rate'] = $product['payment_tax_fee_rate'] ?? [];
        // 如果为赠品 则为父ID
        $info['p_bn'] = isset($product['root_product_bn']) && !empty($product['root_product_bn']) ?  implode(',',$product['root_product_bn']) : "" ;
        // 参与的促销规则
        // $info['use_rules'] = $product['use_rules'];
        return $info;
    }

    // 格式化地址信息
    protected function formatDelivery($delivery)
    {
        $new_delivery = array() ;
        $new_delivery['ship_name']   = $delivery['ship_name'];
        $new_delivery['ship_mobile'] = $delivery['ship_mobile'];
        $new_delivery['ship_zip']    = $delivery['ship_zip'];
        $new_delivery['ship_area']   = $delivery['ship_area'] ;
        if (isset($delivery['deliver_area'])) {
            $new_delivery['extend_deliver_area'] = $delivery['deliver_area'];
        }
        $new_delivery['gps_info'] = $delivery['gps_info'] ;
        $new_delivery['shipping_id'] = $delivery['shipping_id'];
        $temp_area_addr                    = explode(':', $delivery['ship_area']);
        $new_delivery['ship_pack']   = $temp_area_addr[0];
        $temp_area                         = explode('/', $temp_area_addr[1]);
        //收货人所在省
        $new_delivery['ship_province'] = $temp_area[0];
        //收货人所在市
        $new_delivery['ship_city'] = $temp_area[1];
        //收货人所在县
        $new_delivery['ship_county'] = !empty($temp_area[2]) ? $temp_area[2] : "";
        //收货人所在镇
        $new_delivery['ship_town'] = !empty($temp_area[3]) ? $temp_area[3] : '';

        $new_delivery['area_id'] = !empty($temp_area_addr[2]) ? $temp_area_addr[2] : "";
        $separator                       = ' ';//需要用空格分割拼接，四级地址+详细地址
        $new_delivery['ship_addr'] = $new_delivery['ship_province'] . $separator .
            $new_delivery['ship_city'] . $separator .
            $new_delivery['ship_county'] . $separator .
            $new_delivery['ship_town'] . $separator .
            $delivery['ship_addr'];
        return $new_delivery ;
    }

    // 分单数据初始化
    private function initSplitItems()
    {
        return array(
            'cost_freight' => 0 ,// 快递费用
            'cost_item'    => 0 ,   // 商品总金额
            'final_amount' => 0 ,//订单总金额(快递费用 +商品总金额-优惠总金额)
            'pmt_amount'   => 0 ,  //优惠总金额
            'cur_money'    => 0 ,//订单在线支付总金额(订单总金额-积分支付金额)
            'point_amount' => 0 , //积分支付金额
            'weight'       => 0 ,//商品重量
            'wms_code'     => '' ,// 履约平台编码
            'pop_owner_id' => '' , //运营主体id
            'cost_tax'     => 0 ,   //商品税金
            'memo'         => '' ,// 备注
            'items'        => [] ,// //商品明细
        );
    }
    /**
     * 拆单数据组装
     */
    private function initSplitOrder()
    {
        bcscale(2) ;
        $split_orders = [] ;
        $split = array();
        foreach ($this->goods_list as $item) {
            $pop_owner_id = $item['pop_owner_id'];
            if ($item['is_presale']) {
                $pop_owner_id = $pop_owner_id.'_'.$item['id'];
            }
            if (!isset($split_orders[$pop_owner_id])) {
                $split_orders[$pop_owner_id] = $this->initSplitItems();
            }
            $ownerArr = $split_orders[$pop_owner_id] ;
            // 快递费用
            $ownerArr['cost_freight'] = bcadd($ownerArr['cost_freight'], $item['cost_freight']);
             //优惠总金额
            $ownerArr['pmt_amount']   = bcadd($ownerArr['pmt_amount'], $item['pmt_amount']);
            // 积分支付金额
            $ownerArr['point_amount'] = bcadd($ownerArr['point_amount'], $item['point_amount'] );
            // 商品总金额
            $ownerArr['cost_item']    = bcadd($ownerArr['cost_item'], $item['amount']);
            //订单总金额(快递费用 +商品总金额-优惠总金额)  $item['cost_tax']
            $tmp    = Common::bcfunc(array(
                Common::bcfunc([ $item['cost_freight'], $item['amount'] ,$item['cost_tax']],"+"),
                $item['pmt_amount']) ,"-");
            $ownerArr['final_amount'] = bcadd($ownerArr['final_amount'], $tmp) ;
            //订单在线支付总金额(订单总金额-积分支付金额)
            //  $cur_money = Common::bcfunc([$tmp ,$item['point_amount'] ,$item['freight_discount']] ,'-') ;
            $cur_money = Common::bcfunc([$tmp ,$item['point_amount'] ] ,'-') ;
            $ownerArr['cur_money'] = bcadd($ownerArr['cur_money'],$cur_money);
            //商品总重量汇总
            $tmp_weight         = bcmul( $item['weight'],  $item['quantity']);
            $ownerArr['weight'] = bcadd($ownerArr['weight'], $tmp_weight);

            $ownerArr['wms_code']     = $item['pop_wms_code'];
            $ownerArr['pop_owner_id'] = $item['pop_owner_id'];

            $split['wms_code']     = $item['pop_wms_code'] ;
            $split['pop_owner_id'] = $item['pop_owner_id'] ;
            // 商品税金
            $ownerArr['cost_tax']     =  bcadd($ownerArr['cost_tax'], $item['cost_tax']);
            $ownerArr['items'][] = $item  ;
            $split_orders[$pop_owner_id]  = $ownerArr ;
        }
        foreach ($split_orders as $k => &$v) {
            $v['cur_money'] = max($v['cur_money'],0);
            $v['temp_order_id']         = count($split_orders) > 1 ?  OrderService::getOrderId() : $this->order_data['temp_order_id'];
        }
        $split['final_amount'] = $this->order_data['final_amount'] ;
        $split['pmt_amount']   = $this->order_data['pmt_amount'];
        $split['point_amount'] = $this->order_data['point_amount'];
        $split['cur_money']    = $this->order_data['cur_money'];
        $split['cost_tax']     = $this->order_data['cost_tax'];
        $split['cost_freight'] = $this->order_data['feright_amount'] ; // 总运费
        $split['weight']       = $this->order_data['total_weight'] ;// 总重量
        $split['cost_item']    = $this->order_data['total_amount'] ; // 商品总金额
        $split['temp_order_id'] = $this->order_data['temp_order_id'] ;
        $split['split_orders'] = array_values($split_orders) ;
        $split['items'] = $this->goods_list ;
        return $split ;
    }

    /**
     *
     * 待定订单写入db，用于下单失败回滚锁定资源（锁定资源有：优惠券，免邮券，积分，限时限购库存占用）
     * @return mixed
     */
    private function writeTempOrder()
    {
        $data['order_id']         = $this->order_data['temp_order_id'];
        $data['member_id']        = $this->order_data['member_id'];
        $data['company_id']       = $this->order_data['company_id'];
        $data['extend_info_code'] = $this->order_data['extend_info_code'] ? $this->order_data['extend_info_code'] :"";
        $data['order_category']   = $this->order_data['order_category'] ?$this->order_data['order_category']: "";
        $data['status']           = '1';
        $data['create_time']      = time();
        $model =new Transaction() ;
        $ret = $model->baseInsert($data) ;
        if (!$ret) {
            $this->putCreateErrorGeneralLog('预下单失败 存入sdb_b2c_pop_transaction表失败[1025]', array('temp_order_id' => $data['order_id'], 'writeTempOrder_data' => $data,));
        }
        return $ret;

    }


    // 获取订单号
    public function pullProductAvailableAccount()
    {
        $point_server = new Point($this->order_data['member_id'] ,$this->order_data['company_id'],$this->order_data['point_channel']) ;
        $pointList = $point_server->getMemberPoint();
        $ruleBnToAccount = array();
        foreach ($pointList as $accountInfo) {
            foreach ($accountInfo['rule_bns'] as $rule_bn) {
                $ruleBnToAccount[$rule_bn][] = strval($accountInfo['account']);
            }
        }
        $ruleRes= OrderService::getChannelRuleBn( array_keys($ruleBnToAccount) ) ;
        if(empty($ruleRes)) {
            return true ;
        }
        $ruleBnToNeigouRule = array();
        foreach ($ruleRes as $ruleInfo) {
            $ruleBnToNeigouRule[$ruleInfo['channel_rule_bn']] = $ruleInfo['rule_bn'];
        }
        $goodsArr = array();
        foreach ($this->goods_list as $product) {
            $bn = isset($product['bn']) ? $product['bn']: $product['product_bn'] ;
            $goodsArr[] = array('goods_bn' => $product['goods_bn'],'product_bn' => $bn);
        }
        $withRes =   OrderService::getWithRule(array_keys($ruleBnToNeigouRule) , $goodsArr);
        if(empty($withRes)) {
            return true ;
        }
        $productRuleArr = array();
        foreach ($withRes['product'] as $neigouRuleId => $data) {
            foreach ($data['product_list'] as $goods) {
                $ruleBn = $ruleBnToNeigouRule[$neigouRuleId];
                $bn =  $goods['product_bn'] ;
                if (isset($productRuleArr[$bn])) {
                    $productRuleArr[$bn] = array_merge($productRuleArr[$bn] , $ruleBnToAccount[$ruleBn]);
                } else {
                    $productRuleArr[$bn] = $ruleBnToAccount[$ruleBn];
                }
            }
        }
        foreach ($this->goods_list as &$product) {
            $bn = isset($product['bn']) ? $product['bn']: $product['product_bn'] ;
            $product['point_account'] = array_unique($productRuleArr[$bn]);
        }
        return true;
    }

    // 分摊费用到商品
    public function total($asset_list,&$errorMsg = '')
    {
        $this->pullProductAvailableAccount() ;

        $goods_data = array();
        // 处理赠品的情况 调试时候处理
        foreach ($this->goods_list as $goods) {
            $goods_data[] = array(
                'goods_id'              => $goods['goods_id'],
                'goods_bn'              => $goods['goods_bn'],
                'product_id'            => $goods['product_id'],
                'product_bn'            => $goods['bn'],
                'product_name'          => $goods['name'],
                 "price" => array(
                      'price'           => $goods['price'],
                      'cost'            => $goods['cost'] ,
                 ) ,
                'weight'                => $goods['weight'] ? $goods['weight'] : '0',
                'total_weight'          => $goods['total_weight'] ? $goods['total_weight'] : '0' ,
                'quantity'              => $goods['quantity'],
                'shop_id'               => $goods['shop_id'],  //shop_id
                'cost_tax'              => $goods['cost_tax'], //税金
                'point_account'         => $goods['point_account'] ? $goods['point_account'] : array(),
                'use_rule'              => $goods['use_rules'],
                'single_freight'        => $goods['is_presale'] ? true : false,
                'freight_bn'            => $goods['freight_bn'] ,
                'freight_source'        => $goods['freight_source'] ,
                'jifen_pay'             => $goods['jifen_pay'] ,
                 // 'gift_list'         => $giftList,
                "is_gift"               => isset($goods['is_gift']) ? $goods['is_gift'] : 0  ,
                "payment_tax_fee_rate"  => $goods['payment_tax_fee_rate'] ?? [],
                // 如果是赠品 则 root_product_bn 为 赠品的父ID
                'root_product_bn'       => isset($goods['root_product_bn'])  ?  implode(',' ,$goods['root_product_bn'])  : [] ,
            );
        }
        $delivery      = $this->delivery; // 商品信息
        $post_data = array(
            'order_id' => $this->order_data['temp_order_id'],
            'company_id'    => $this->order_data['company_id'],
            'member_id'     => $this->order_data['member_id'],
            'product_list'    => $goods_data,
            'order_use_rules' => $this->order_data['order_use_rules'],
            'asset_list'   => $asset_list ,
            'delivery'      => array(
                'addr'           => $delivery['ship_addr'],
                'ship_area_id'   => $delivery['area_id'],
                'town'        => $delivery['ship_town'],
                'county'     => $delivery['ship_county'],
                'city'        => $delivery['ship_city'],
                'province'    => $delivery['ship_province'],
                'shipping_id' => $delivery['shipping_id'] ,
                "delivery_time" => $this->order_data['delivery_time'],
                "gps_info"    =>  $delivery['gps_info'],
            ),
        );
        //计算服务
        $result =  $this->serviceObj->ServiceCall("calculatev2_put" ,$post_data) ;
        \Neigou\Logger::Debug("order.service.calculatev2_put",
            array(
                'action' => 'service_calculate' ,
                "request_data" => $post_data,
                'order_id'  => $post_data['order_id'] ,
                'result' => $result ,
            ));
        if ($result['error_code'] == 'SUCCESS' && $result['data']) {
            //组织订单结算数据
            return $this->generateOrderTotal($post_data['order_id'], $errorMsg);
        } else {
            $errorMsg = $result['error_msg'][0] ?? '';
            return false;
        }
    }
     /*
      * 组织订单数据 计算分摊费用
      */
    private function generateOrderTotal($order_id, &$errorMsg)
    {
        if (empty($order_id)) {
            $errorMsg = '没有order_id';
            return  false;
        }
       $calculate_info =  $this->serviceObj->ServiceCall('calculatev2_get' ,array('order_id' => $order_id)) ;
        if(empty($calculate_info['data'])) {
            $errorMsg = '获取计算结果失败 ' . ($calculate_info['error_msg'][0] ?? '');
           return false ;
       }
       bcscale(2) ;
       $calculate_data = $calculate_info['data'] ;
       $dict = array(
           'point_amount' => 'point_money' ,// 积分对应的钱
           'pmt_tax'      => 'dutyfree' ,//税金优惠
           'pmt_voucher'  => 'voucher' ,//券优惠
           'pmt_amount'   => 'pmt_amount' , //优惠券总额度
            // 'feright_amount' => 'cost_freight' , // 运费总额度
           'pmt_point'   => 'point_amount' , // 全部的积分
            // 'point_amount' => "point_amount" ,
           'final_amount' => 'final_amount' ,//订单总金额
           'cur_money'   => 'cur_money' , //现金支付金额
           'cost_tax'    => 'cost_tax' ,  //商品总税金
           'total_amount' => 'cost_item' ,//商品总金额
           'total_weight' => 'total_weight'  ,// 总重量
       ) ;
       foreach ($dict as $k=>$v) {
           $this->order_data[$k] = Common::number2price($calculate_data[$v]) ;
       }
       $this->order_data['feright_amount'] = bcsub($calculate_data['cost_freight'] ,$calculate_data['freeshipping']) ;
       $calc_asset = $product_payment_tax_fee = array() ;
       // 统计 计算服务之后的资产
       foreach ($calculate_data['goods_list'] as $goods) {
           $bn = $goods['product_bn'] ;
           if($goods['is_gift'] == 1) {
               $bn = $bn . "_gift" ;
           }
           if( isset( $this->goods_list[$bn]) ) {
               $goods_dict = array(
                   'cost_freight' => 'cost_freight' ,
                   'is_free_shipping'=> 'is_free_shipping' ,
                   'cash_amount'  => 'cash_amount' ,
                   'promotion_discount' => 'promotion_discount' ,
                   'voucher_discount' =>   'voucher_discount' ,
                   'pmt_point'  => 'use_point' ,
                   'point_amount' => 'use_point_money' , // 积分对应的钱
                   'dutyfree_discount' => 'dutyfree_discount' ,
                   'freight_discount'  => 'freight_discount' ,
                   'cash_discount'  => 'cash_discount' ,
                   'quantity' => 'quantity' ,
                   'nums'   => "quantity" ,
                   'cost_tax' => 'cost_tax' ,
                   'tax'    => 'cost_tax' ,
               ) ;
               foreach ($goods_dict as $k=>$v) {
                   $this->goods_list[$bn][$k] = $goods[$v] ;
               }
               // 运费计算
               $this->goods_list[$bn]['cost_freight'] = bcsub($goods['cost_freight'],$goods['freight_discount']) ;
               // 商品分摊优惠金额 = 商品分摊优惠金额 + 商品分摊的订单满减金额
               $this->goods_list[$bn]['pmt_amount'] = Common::bcfunc([$goods['voucher_discount'] ,$goods['dutyfree_discount'] ,$goods['promotion_discount'], $goods['cash_discount']]);
           }
           //给产品赋予服务费信息
           if (!empty($goods['payment_tax_fee_rate'])) $product_payment_tax_fee[$bn] = json_decode($goods['payment_tax_fee_rate'],true);
            if(!isset($goods['asset_list'])) {
                continue ;
            }
            $this->goods_list[$bn]['asset_list'] = Common::array_group($goods['asset_list'],'type') ;
            bcscale(2) ;
            foreach ($goods['asset_list'] as $asset_info) {
                $key = $asset_info['type'] ;
                $bn  = $asset_info['asset_bn'] ;
                $calc_asset[$key][$bn]['product_bn_list'][]=  $goods['product_bn'] ;
                $calc_asset[$key][$bn]['bn'] = $bn ;
                if(isset($calc_asset[$key][$bn])) {
                    $calc_asset[$key][$bn]['amount'] = bcadd( $asset_info['amount'] , $calc_asset[$key][$bn]['amount'] ) ;
                    $calc_asset[$key][$bn]['money'] = bcadd($asset_info['money'] , $calc_asset[$key][$bn]['money']) ;
                } else {
                    $calc_asset[$key][$bn]['amount'] = $asset_info['amount'] ;
                    $calc_asset[$key][$bn]['money']  = $asset_info['money'] ;
                }
                $calc_asset[$key][$bn]['product_bn_list'] = array_unique($calc_asset[$key][$bn]['product_bn_list']) ;
            }
        }
        $this->order_data['calc_asset'] = $calc_asset ;
        if (!empty($product_payment_tax_fee)) {
            //给订单备注上产品对应的服务费信息
            $this->order_data['extend_data']['product_payment_tax_fee'] = $product_payment_tax_fee;
        }
        return  $calculate_data ;
    }

    private function initGoodsList($product_list , &$msg)
    {
        // 处理赠品合并的情况
        $bn_list = [];
        foreach ($product_list as $key=>$goods_item) {
            $bn = isset($goods_item['product_bn']) ? $goods_item['product_bn'] : $goods_item['bn'] ;
            $bn_list[] = $bn ;
        }
        $wwsList =   $this->popwmsObj->get_goods_pop_wms($bn_list) ;
        if(empty($wwsList)) {
            $msg = '获取product_bn对应的店铺信息pop_wms失败';
            return false ;
        }

        $GoodsPaymentTaxFeeLogic = new GoodsPaymentTaxFeeLogic();
        $tax_ret = $GoodsPaymentTaxFeeLogic->getRateMulti($this->order_data['company_id'],array_column($product_list,'product_bn'));
        $tax_data = $tax_ret['data'];

        $goods_list = [] ;
        foreach ($product_list as $bn =>$goods_item ) {
            $temp_item = $goods_item ;
            $product_bn = $goods_item['product_bn'] ;
            if(isset($wwsList[$product_bn])) {
                $goods_item = array_merge($goods_item ,$wwsList[$product_bn]) ;
            }
            // cps 订单 自己传 wms_code pop_owner_id
            if(isset($temp_item['wms_code']) && !empty($temp_item['wms_code'])) {
                $goods_item['pop_wms_code']  = $temp_item['wms_code'] ;
            }
            if(isset($temp_item['pop_owner_id']) && !empty($temp_item['pop_owner_id'])) {
                $goods_item['pop_owner_id']= $temp_item['pop_owner_id'] ;
            }
            $goods_item['payment_tax_fee_rate'] = $tax_data[$goods_item['product_bn']]['payment_tax_fee_rate'] ?? [];
            $goods_list[$bn] = $this->initProduct($goods_item) ;
        }
        return $goods_list ;
    }

    /**
     * 验证积分是否足够使用
     * @param $asset_list
     * @param $msg
     * @return bool
     */
    private function _checkUsePoint($asset_list ,&$msg)
    {
        // 如果没有积分资产不检查 返回通过
        if(!isset($asset_list['point'])) {
            return true ;
        }
        $pointChannel = $this->order_data['point_channel'] ;
        $pointObj = new Point($this->order_data['member_id'] ,$this->order_data['company_id'],$pointChannel) ;
        $member_point = $pointObj->getMemberPoint() ;

        if (empty($member_point)) {
            $msg = '没有足够的可使用积点，用户: ' . $this->order_data['member_id'] . ' 在当前公司: ' . $this->order_data['company_id'] . ' 没有指定积分渠道: ' . $pointChannel . ' 的积分';
            return false;
        }
        $pointAsset = $asset_list['point'] ;
        $data  = [] ;
        foreach ($pointAsset as $pointItem) {
            $sceneId = $pointItem['bn']; // 账户
            if (!$member_point[$sceneId]) {
                $msg = '没有足够的可使用积点#2, 用户没有指定账户：' . $sceneId;
                return false;
            } elseif ($pointItem['amount'] > $member_point[$sceneId]['point']) {
                $msg = '没有足够的可使用积点#3, 本次需要支付积分' . $pointItem['amount'] . ' 大于用户账户积分金额[账户：' . $sceneId . ' 积分：' . $member_point[$sceneId]['point'] . ']';
                return false;
            } else {
                $data[$sceneId] = $member_point[$sceneId];
            }
        }
        return true ;
    }

    protected function  test()
    {
     $orderParamStr = '{"member_id":"971254","company_id":"18643","temp_order_id":"","terminal":"pc","from":"store","extend_data":{"isprintprice":"0","isNewZiti":0,"order_source":"","needVerifyLevel":0,"is_jdbt_mx":0,"is_mabcchinahtmlmx":0,"invoice_info":[],"invoice_service_info":"","o2o_express":"","delivery_time":"","gps_info":{"lat":"31.229113899","lng":"121.51741645"},"present_rules":{"SHOP-5F16900509DA0-523":{"SHOP-5F16900509DA0-0":{"id":264,"type":"present","name":"\u8d60\u54c1\u89c4\u52190731 (Apple iPhoneX\u5168\u65b0\u624b\u673a \u82f9\u679cX\u624b\u673aiphone X-128G-\u6df1\u7a7a\u7070\u8272-\u5b98\u65b9\u6807 App  \u4e702\u8d601   )","times":"per","present_nums":"1","operator_type":"nums","operator_value":"2"}}},"idcardname":"","idcardno":""},"asset_list":[{"type":"point","bn":202007311928022587,"amount":"3.42","extend_data":{"point_channel":"SCENENEIGOU","third_point_pwd":""},"match_product_bn":[]},{"type":"voucher","bn":"98D9D89D30FF7D6C","amount":"0","match_product_bn":["SHOPNG-5E5E1FD3ACD1E-668","SHOP-605BF7BAEA415-0","SHOP-5F16900509DA0-523"]}],"product_list":{"SHOPNG-5E5E1FD3ACD1E-668":{"goods_bn":"SHOPNG-5E5E1FD3ACD1E","goods_id":"1607205","bn":"SHOPNG-5E5E1FD3ACD1E-668","name":"\u6d4b\u8bd5\u5546\u54c10303\u591a\u89c4\u683c\u5546\u54c1\u4e8c","price":{"price":"0.200","point_price":"2.000","mktprice":"2.000","cost":"0.192"},"weight":null,"quantity":"2","shop_id":"248","product_id":"11903226","use_rules":null,"is_presale":null,"taxfees":0,"presents":null,"product_bn":"SHOPNG-5E5E1FD3ACD1E-668","is_gift":"0","root_product_bn":[],"freight_bn":"gwtfSKCBJt","freight_source":"internal"},"SHOP-605BF7BAEA415-0":{"goods_bn":"SHOP-605BF7BAEA415","goods_id":"7749590","bn":"SHOP-605BF7BAEA415-0","name":"\u5c0f\u91d1\u989d\u5546\u54c1","price":{"price":"1.00","point_price":"0.020","mktprice":"0.020","cost":"0.010"},"weight":null,"quantity":"1","shop_id":"23738","product_id":"13828756","use_rules":null,"is_presale":null,"taxfees":0,"presents":null,"product_bn":"SHOP-605BF7BAEA415-0","is_gift":"0","root_product_bn":[],"freight_bn":"fhDDBgEyEx","freight_source":"internal"},"SHOP-5F16900509DA0-523":{"goods_bn":"SHOP-5F16900509DA0","goods_id":"4923790","bn":"SHOP-5F16900509DA0-523","name":" Apple iPhoneX\u5168\u65b0\u624b\u673a \u82f9\u679cX\u624b\u673aiphone X-128G-\u6df1\u7a7a\u7070\u8272-\u5b98\u65b9\u6807 App","price":{"price":"0.010","point_price":"1.000","mktprice":"1.000","cost":"0.010"},"weight":null,"quantity":"2","shop_id":"23738","product_id":"11899382","use_rules":[264],"is_presale":null,"taxfees":0,"presents":{"SHOP-5F16900509DA0-0":{"shop_id":"23738","shop_name":"zgtest","product_id":"6506873","product_bn":"SHOP-5F16900509DA0-0","product_name":" Apple iPhoneX\u5168\u65b0\u624b\u673a \u82f9\u679cX\u624b\u673aiphone X-128G-\u6df1\u7a7a\u7070\u8272-\u5b98\u65b9\u6807 App","weight":null,"cost":"0.010","taxfees":null,"spec_info":"\u82b1\u8272\uff1a\u7409\u7483","marketable":1,"goods_id":"4923790","goods_bn":"SHOP-5F16900509DA0","jifen_pay":1,"ziti":"0","global":0,"image":"\/\/test.neigou.com\/public\/v2\/images\/db\/20\/1e\/6187fd25342ec935d06089bdb4b09019.png?x-oss-process=image\/resize,m_lfit,h_300,w_300","goods_source":"normal","delivery_bn":"35HdkTuBsd","quantity":1,"rule":{"id":264,"type":"present","name":"\u8d60\u54c1\u89c4\u52190731 (Apple iPhoneX\u5168\u65b0\u624b\u673a \u82f9\u679cX\u624b\u673aiphone X-128G-\u6df1\u7a7a\u7070\u8272-\u5b98\u65b9\u6807 App  \u4e702\u8d601   )","times":"per","present_nums":"1","operator_type":"nums","operator_value":"2"},"price":{"price":"0.010","point_price":"1.000","mktprice":"1.000"},"total_amount":0.01,"total_weight":0,"stock":9453}},"product_bn":"SHOP-5F16900509DA0-523","is_gift":"0","root_product_bn":[],"freight_bn":"Lgn1mDhayg","freight_source":"internal"},"SHOP-5F16900509DA0-0_gift":{"shop_id":"23738","shop_name":"zgtest","product_id":"6506873","product_bn":"SHOP-5F16900509DA0-0","product_name":" Apple iPhoneX\u5168\u65b0\u624b\u673a \u82f9\u679cX\u624b\u673aiphone X-128G-\u6df1\u7a7a\u7070\u8272-\u5b98\u65b9\u6807 App","weight":null,"cost":"0.010","taxfees":null,"spec_info":"\u82b1\u8272\uff1a\u7409\u7483","marketable":1,"goods_id":"4923790","goods_bn":"SHOP-5F16900509DA0","jifen_pay":1,"ziti":"0","global":0,"image":"\/\/test.neigou.com\/public\/v2\/images\/db\/20\/1e\/6187fd25342ec935d06089bdb4b09019.png?x-oss-process=image\/resize,m_lfit,h_300,w_300","goods_source":"normal","delivery_bn":"35HdkTuBsd","quantity":1,"rule":{"id":264,"type":"present","name":"\u8d60\u54c1\u89c4\u52190731 (Apple iPhoneX\u5168\u65b0\u624b\u673a \u82f9\u679cX\u624b\u673aiphone X-128G-\u6df1\u7a7a\u7070\u8272-\u5b98\u65b9\u6807 App  \u4e702\u8d601   )","times":"per","present_nums":"1","operator_type":"nums","operator_value":"2"},"price":{"price":"0.010","point_price":"1.000","mktprice":"1.000"},"total_amount":0.01,"total_weight":0,"stock":9453,"is_gift":"1","root_product_bn":["SHOP-5F16900509DA0-523"],"freight_bn":"Lgn1mDhayg","freight_source":"internal"}},"point_channel":"SCENENEIGOU","order_use_rules":null,"delivery_time":1617792550,"delivery":{"ship_name":"lhw ","ship_mobile":"18510335783","ship_zip":"1231","ship_addr":"\u8fdc\u4e1c\u5927\u53a6 23123123","shipping_id":5,"ship_area":"mainland:\u4e0a\u6d77\/\u4e0a\u6d77\u5e02\/\u9ec4\u6d66\u533a\/\u57ce\u533a:3928","gps_info":{"lat":"31.229113899","lng":"121.51741645"}},"payment":{"anonymous":"no","currency":"CNY"},"memo":[],"platform":"neigou","global":"false","extend_info_code":"standard","receive_mode":1,"order_category":"local"}';
     return  json_decode($orderParamStr ,true);
    }

    private function checkSettlementOrderPay()
    {
        $data = array(
            "business_code" => $this->order_data['company_id'],
            "order_id" => $this->order_data['temp_order_id'],
            "platform" => $this->order_data['platform'],
            "business_type" => "MALL",
            "property_range" => array(
                "property_platform" => "mall",
                "property_list" => array(
                ),
            ),
            "order_info" => array(
                "final_amount" => $this->order_data['final_amount'],
                "pmt_amount" => $this->order_data['pmt_amount'],
                "cost_freight" =>  $this->order_data['feright_amount'],
                "goods_list" => array()
            ),
            'order_category' => $this->order_data['order_category'] ?$this->order_data['order_category']: "",
            "order_business_platform" => "mall",
            "order_business_no" => $this->order_data['temp_order_id'],
            'order_business_source' => "order",
            'order_channel' => 'goods_order',
            'business_create_time' => time(),
        );

        //现金-总
        $cash = 'unknow';
        if (!empty($this->order_data['cur_money']) && $this->order_data['cur_money'] > 0) {
            //根据key值查询 现金支付方式
            $cashModel = new CompanyCfgs();
            $cashCfgs = $cashModel->getCompanyCfgs($this->order_data['payment']['payment_list']);
            if ($cashCfgs->payee == 'own') {
                $cash = 'accept_payment';
            } else if ($cashCfgs->payee == 'third_party') {
                $cash = 'third_payment';
            }
            $curMoney = array(
                "method" => "cash",
                "detailed" => array(
                    array(
                        "type" => $cash,
                        "amount" => $this->order_data['cur_money']
                    )
                ),
            );
            array_push($data['property_range']['property_list'], $curMoney);
        }

        //积分-总
        if (array_key_exists('point_channel', $this->order_data) && array_key_exists('point_amount' , $this->order_data) && $this->order_data['point_amount'] > 0) {
            $point = array(
                "method" => "point",
                "detailed" => array(
                    array(
                        "type" => $this->order_data['point_channel'],
                        "amount" => $this->order_data['point_amount'],
                    )
                )
            );
            array_push($data['property_range']['property_list'], $point);
        }

        //总优惠
        $voucherTotal = 0;
        //总运费优惠
        $freightTotal = 0;
        //总税收优惠
        $taxTotal = 0;
        $g = 0;
        \Neigou\Logger::Debug('OrderGatherController.LIst2.goods_list', array('goods_list' => $this->goods_list));

        foreach ($this->goods_list as $key => $value) {
            $data['order_info']['goods_list'][$g] = array(
                "product_bn" => $value['product_bn'],
                "goods_bn" => $value['goods_bn'],
                "name" => $value['name'],
                "price" => $value['price'],
                "cost" => $value['cost'],
                "num" => $value['quantity'],
                'basic_price' => $value['primitive_price'],
                "cost_tax" => $value['cost_tax'],
                "shop_id" => $value['pop_owner_id'],
                "cost_freight" => $value['cost_freight'],
                "property_range" => array(
                    "property_platform"=>"mall",
                    "property_list" => array(
                    ),
                ),
            );

            //现金-商品
            if (!empty($value['cash_amount']) && $value['cash_amount'] > 0) {
                $amount = bcadd(bcadd($value['amount'], $value['cost_tax'], 2), $value['cost_freight'], 2);
                $pointAmount = bcadd($value['point_amount'], $value['pmt_amount'], 2);
                $cashAmount = bcsub($amount, $pointAmount, 2);
                $curProduct = array(
                    "method" => "cash",
                    "detailed" => array(
                        array(
                            "type" => $cash,
                            "amount" => $cashAmount
                        )
                    ),
                );
                array_push($data['order_info']['goods_list'][$g]['property_range']['property_list'], $curProduct);
            }

            //优惠劵-商品
            $voucherArrProduct = array(
                "method" => "voucher",
                "detailed" => array()
            );
            if (!empty($value['voucher_discount']) && $value['voucher_discount'] > 0) {
                $voucherTotal = bcadd($voucherTotal, $value['voucher_discount'] , 2);
                array_push($voucherArrProduct['detailed'], array("type"=>"voucher", "amount" => $value['voucher_discount']));
            }
//            if (!empty($value['taxfees']['dutyfree_discount'])) {
//                $taxTotal = bcadd($taxTotal, $value['taxfees']['dutyfree_discount'] , 2);
//                array_push($voucherArrProduct['detailed'], array("type"=>"tax_voucher", "amount" => $value['taxfees']['dutyfree_discount']));
//            }
            if (!empty($value['freight_discount']) && $value['freight_discount'] > 0) {
                $freightTotal = bcadd($freightTotal, $value['freight_discount'] , 2);
                array_push($voucherArrProduct['detailed'], array("type"=>"freight_voucher", "amount" => $value['freight_discount']));
            }
            array_push($data['order_info']['goods_list'][$g]['property_range']['property_list'], $voucherArrProduct);

            //积分product
            $pointProduct = array(
                "method" => "point",
                "detailed" => array()
            );
            if (array_key_exists('point_channel', $this->order_data) && array_key_exists('point_amount' , $value) && $value['point_amount'] > 0) {
                array_push($pointProduct['detailed'], array("type"=>$this->order_data['point_channel'],"amount"=>$value['point_amount']));
            }
            array_push($data['order_info']['goods_list'][$g]['property_range']['property_list'], $pointProduct);
            $g++;
        }

        //优惠劵
        $voucherArr = array(
            "method" => "voucher",
            "detailed" => array()
        );
        if (!empty($voucherTotal)) {
            array_push($voucherArr['detailed'], array("type"=>"voucher","amount"=>$voucherTotal));
        }
        if (!empty($taxTotal)) {
            array_push($voucherArr['detailed'], array("type"=>"tax_voucher","amount"=>$taxTotal));
        }
        if (!empty($freightTotal)) {
            array_push($voucherArr['detailed'], array("type"=>"freight_voucher","amount"=>$freightTotal));
        }
        array_push($data['property_range']['property_list'], $voucherArr);

        // 同步业财退款回滚
        $daemonTaskParameters = array(
            "order_id"      => $this->order_data['temp_order_id'],
            "refund_id"     => $this->order_data['temp_order_id'],
            "business_type" => "MALL",
            "refund_money"  => $this->order_data['final_amount'],
            "item_list"     => $data['order_info']['goods_list'],
            "order_business_platform" => "mall",
            "order_business_no" => $this->order_data['temp_order_id'],
            'order_business_source' => "order",
            'order_channel' => 'goods_order',
            'business_create_time' => time(),
        );
        $daemonTaskID = $this->_addYCSettlementRollbackDaemonTask($daemonTaskParameters);

        $result = \Neigou\ApiClient::doServiceCall('yc_settlement', 'settlement/orderPay', 'v1', null, $data);
        \Neigou\Logger::Debug('OrderGatherController.checkSettlementOrderPay', array('data' => $data, 'result' => $result, 'order_data' => $this->order_data, 'goods_list' => $this->goods_list));
        if ('OK' !== $result['service_status'] || 'SUCCESS' !== $result['service_data']['error_code']) {
            return false;
        }
        if ($result['service_data']['data']['result'] === true) {
            if (!empty($daemonTaskID)) {
                $this->_updateYCSettlementRollbackDaemonTask($daemonTaskID);
            }
            return true;
        }
        else {
            return false;
        }
    }

    /**
     * 新增业财结算回滚后台任务
     * @return int|null
     */
    private function _addYCSettlementRollbackDaemonTask(array $parameters = array())
    {
        $daemonTaskModel = new DaemonTask();
        $res = $daemonTaskModel->addDaemonTask(
            'order.yc_settlement.order_refund',
            '\App\Api\V1\Controllers\OrderGatherController->ycSettlementOrderRefund',
            $parameters, time(), 3, 15, 10
        );

        return $res;
    }

    /**
     * 更新业财结算回滚后台任务（等待加入消息队列）
     * @return bool
     */
    private function _updateYCSettlementRollbackDaemonTask(int $id = 0)
    {
        $daemonTaskModel = new DaemonTask();

        return $daemonTaskModel->findData(array(array('id', '=', $id)))->updateStatusWaitingJoinMQ();
    }

    /**
     * 业财结算回滚
     */
    public function ycSettlementOrderRefund(DaemonTask $daemonTaskModel, array $data = array())
    {
        if (empty($data['order_id'])) {
            $daemonTaskModel->recordOutput('`order_id`为空', array('data' => $data));
            return false;
        }
        $ret = \Neigou\ApiClient::doServiceCall('order', 'Order/Get', 'v1', null, array('order_id' => $data['order_id']));
        if('OK' == $ret['service_status'] && 'SUCCESS' == $ret['service_data']['error_code'] && !empty($ret['service_data']['data'])) {
            $orderData = $ret['service_data']['data'];
        }else{
            $orderData = array();
        }
        if (empty($orderData)) {
            $daemonTaskModel->recordOutput('未查询到订单数据', array('orderData' => $orderData));

            if ($daemonTaskModel->isTheLastTime() === DaemonTask::THE_LAST_TIME_YES) {
                $res = \Neigou\ApiClient::doServiceCall('yc_settlement', 'settlement/orderRefund', 'v1', null, $data, array());
                if ($res['service_status'] !== 'OK' || $res['service_data']['error_code'] !== 'SUCCESS') {
                    $daemonTaskModel->recordOutput('业财结算回滚失败', array('data' => $data, 'res' => $res));
                }
                else {
                    $daemonTaskModel->recordOutput('业财结算回滚成功', array('data' => $data, 'res' => $res));
                    return true;
                }
            }
            return false;
        }
        $daemonTaskModel->recordOutput('查询到订单数据', array('orderData' => $orderData));
        return true;
    }


    private  function formatBasicBusinessData($dataList)
    {
        $formatDataList = [];
        foreach($dataList as $item) {
            $formatData = [];
            $formatData['goods_bn'] = $item['goods_bn'];
            $formatData['product_bn'] = $item['product_bn'];
            $formatData['price'] = $item['price'];
            $formatData['quantity'] = $item['quantity'];
            $formatData['pop_shop_id'] = $item['pop_shop_id'];
            $formatDataList[] = $formatData;
        }

        return $formatDataList;
    }

    private function putCreateErrorGeneralLog($message, $error_extend)
    {
        \Neigou\Logger::General('service_order_create', array(
            'message' => $message, 'error_extend' => $error_extend,
        ));
    }

}
