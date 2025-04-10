<?php
namespace App\Api\Logic\AssetOrder;
use App\Api\Common\Common;
use  App\Api\Logic\Service ;
use App\Api\Model\BaseModel;
use App\Api\Model\Goods\Shop;
use App\Api\Model\Goods\Transaction ;
#锁定优惠券等
class B2cOrderResource
{
    static $undo_list = array() ;
    public static  function  getUndoList() {
        return self::$undo_list ;
    }
    public static  function  setUndoList($k,$v) {
         self::$undo_list[$k] = $v ;
    }
    /**
     * 锁定资源
     *
     * @param   $orderId    string      订单ID
     * @param   $type       string      资源类型
     * @param   $item       string      资源项
     * @param   $value      string      资源值
     * @param   $memo       string      备注
     * @return  mixed
     */
    public static function lockOrderResource($orderId, $type, $item = '', $value = '', $memo = '')
    {
        $data = array(
            'object'            => 'ORDER',
            'object_item'       => $orderId,
            'resource'          => strtoupper($type),
            'resource_item'     => $item,
            'resource_value'    => $value,
            'memo'              => $memo ? $memo : 'create_order',
        );
        $serviceObj = new Service ;
        $ret = $serviceObj->ServiceCall('resource_lock' ,$data) ;

        if ($ret['error_code'] != 'SUCCESS' || empty($ret['data']))
        {
            return false;
        }
        return $ret['data']['res_id'] ? $ret['data']['res_id'] : false;
    }


    // 资产注册
    public static function registerFinance($request_data,$product_list,&$msg)
    {
        $order_id = $request_data['temp_order_id'];
        $use_obj_items = array();
        $origin_asset_list = $request_data['calc_asset'] ;
        foreach ($product_list as $bn => $item) {
            $use_obj_items[] = array(
                "item_bn" => $item['bn'],
                "item_id" => $item['id'],
                "name" => $item['name'],
                "price" => $item['price'],
                "quantity" => $item['nums'],
                "amount" => $item['amount'],
            );
        }
        $asset_list = array();
        // 现金 如果两个数相等返回0, 左边的数left_operand比较右边的数right_operand大返回1, 否则返回-1.
        if(bccomp($request_data['cur_money'] , 0,2) == 1) {
            $asset_list[] = array(
                "asset_type" => 'cash',
                "asset_bn" => "",
                "asset_amount" => $request_data['cur_money'],
            ) ;
        }
        $point_list = isset($origin_asset_list['point']) ? $origin_asset_list['point']: array() ;
        foreach ($point_list as $item) {
            if(empty($item) || bccomp($item['amount'] ,'0' ,2) < 1 ){
                continue ;
            }
            $asset_list[] = array(
                "asset_type" => "point",
                "asset_bn" => $request_data['point_channel'] ? $request_data['point_channel']: "NEIGOU",
                "asset_amount" => $item['amount'],
                "asset_data" => array(
                    "point" => $item['amount'],
                    "money" => $item['money'],
                    'point_channel'   => $request_data['point_channel'] ,
                    "third_point_pwd" => $request_data['third_point_pwd'],
                )
            );
        }
        $vlist = array(
            array("type" => 'voucher',      'data' => $origin_asset_list['voucher'] , 'name' => '优惠券'),
            array('type' => 'freeshipping', 'data' => $origin_asset_list['freeshipping'] , 'name' => '免邮券'),
            array('type' => 'dutyfree',     "data" => $origin_asset_list['dutyfree']  , 'name' => '免税券'),
        );
        // 优惠卷
        foreach ($vlist as $asset) {
            if (empty($asset['data'])) {
                continue;
            }
            $voucher_bns = array_keys($asset['data']);
            $voucherInfo = array(
                "asset_type" => $asset['type'],
                "asset_bn"   => implode(',', $voucher_bns),
                "asset_amount" => 0,
                'asset_items'  => array(),
            );
            $total_money = 0 ;
            foreach ($voucher_bns as $voucher) {
                $asset_amount = isset($asset['data'][$voucher]['amount']) ? $asset['data'][$voucher]['amount'] : 0 ;
                $asset_money = isset($asset['data'][$voucher]['money']) ? $asset['data'][$voucher]['money'] : 0 ;
                $voucherInfo['asset_items'][] = array(
                    "asset_bn" => $voucher,
                    'asset_name' => $asset['name'],
                    'asset_amount' => $asset_amount ,
                    'asset_money'  => $asset_money ,
                );
                $total_money += $asset_amount ;
            }
            $voucherInfo['asset_amount'] = $total_money ;
            $asset_list[] = $voucherInfo;
        }
        $post_data = array(
            "use_type"  => "ORDER" ,
            "use_obj"   =>  $order_id ,
            "member_id" =>  $request_data['member_id'],
            "company_id" => $request_data['company_id'],
            "memo" => "orderGather 资产注册",
            "use_obj_items" => $use_obj_items  , // 商品明细
            "asset_list" => $asset_list ,  // 所有资产明细
        ) ;
        $serviceObj = new Service ;
        $service_data =  $serviceObj->ServiceCall("asset_register" ,$post_data) ;
        \Neigou\Logger::General("service_finance", array(
            'order_id'         => $order_id,
            'action'         => 'service_finance',
            'request_data' => $request_data ,
            'post_data'       => $post_data ,
            'response' => $service_data ,
        ));
        if($service_data['error_code'] != 'SUCCESS') {
            $msg = '资产注册失败,' . $service_data['error_msg'][0] ?? '';
            return false ;
        }
        return $service_data['data'] ;
    }


    /**
     * 限购信息写入db、锁定积分、优惠券、免邮券
     * @param $order_data
     * @param $goods_list
     * @param string $msg
     * @return array
     */
    public static function lockAllAsset($order_data,$goods_list, &$msg = '')
    {
        //限时限购，记录购买goods_id,数量,时间
        $order_id         = $order_data['temp_order_id'];
//        $goods_list       = $goods_list;
        $member_id        = $order_data['member_id'];
        $company_id       = $order_data['company_id'];
        $asset_list       = isset($order_data['calc_asset']) ? $order_data['calc_asset'] : [] ;
        // 锁定限时限购资源
        self::lockOrderResource($order_id, 'timeBuyCancel');
        $flag      = self::create_time_buy_promotion($order_id, $goods_list, $company_id, $member_id,$msg);
        if (false === $flag) {
            return array('code' => '1030' ,'msg' => '限时限购，库存不足') ;
        }
        self::setUndoList('timeBuyCancel' ,true);
        // 锁定限额资源
        self::lockOrderResource($order_id, 'limitMoneyCancel');
        $flag      = self::create_limit_money_promotion($order_id, $goods_list, $company_id, $member_id, $msg);
        if (false === $flag) {
            return array('code' => '1034' ,'msg' => '限额，金额不足') ;
        }
        self::setUndoList('limitMoneyCancel' ,true);
        $pointTotalNum = 0;
        if(!empty($asset_list['point'])) {
            foreach ($asset_list['point'] as $pointUseInfo) {
                $pointTotalNum += $pointUseInfo['amount'];
            }
        }
        $shopModel = new Shop() ;
        //积分
        if ($pointTotalNum > 0) {
            $lock_point_data    = array(
                'company_id'    => $company_id,
                'member_id'    => $member_id,
                'use_type'    => 'order',
                'use_obj'    => $order_id,
                'channel'    => $order_data['point_channel'],
                'point'    => $order_data['pmt_point'],
            );
            // 锁定积分资源
            self::lockOrderResource($order_id, 'point', '', json_encode($lock_point_data));
            $order_pmt_point_detail = [] ;
            foreach ($asset_list['point'] as $code=>$pointInfo) {
                $order_pmt_point_detail[]  =  array(
                    'code'     => $code ,
                    'pmt_sum'  => $pointInfo['amount'],
                    'products' => json_encode($pointInfo['product_bn_list']),
                    'detail'   => json_encode(array(
                        'order_id'    => $order_id ,
                        'member_id'     => $order_data['member_id'],
                        'company_id'    => $order_data['company_id'],
                        'point_channel' => $order_data['point_channel']
                    )),
                    'type'     => 'point',
                );
            }
            $insertRes = $shopModel->SavepreFerential($order_id ,$order_pmt_point_detail) ;
            if(empty($insertRes)) {
                $msg = '锁定积分资源时，向 sdb_b2c_pop_preferential 表存储积分数据失败';
                self::putCreateErrorGeneralLog($msg, array('order_id' => $order_id, 'order_pmt_point_detail' => $order_pmt_point_detail));
                return array('code' => '1130' ,'msg' => '积分数据错误') ;
            }
            $error_msg = '' ;
            if (false === self::lockUsePoint($order_data,$goods_list,$error_msg)) {
                return array('code' => '1031' ,'msg' => $error_msg) ;
            }
            self::setUndoList('unlock_use_point' ,true);
        }
        $msg = '';
        //优惠券
        if (isset($asset_list['voucher']) && !empty($asset_list['voucher'])) {
            // 锁定优惠券资源
            self::lockOrderResource($order_id, 'voucher');
            $voucher_list = array();
            $order_pmt_voucher_detail = [] ;
            foreach ($asset_list['voucher'] as $coupon_id => $used_data) {
                $use_money = $used_data['money'];
                if (!is_numeric($use_money) || $use_money <= 0) {
                    continue;
                }

                $voucher_number_list = array(
                    $coupon_id
                );
                $voucher_list[] = array(
                    'voucher_number_list' => $voucher_number_list,
                    'use_money'           => $use_money,
                    'memo'                => '',
                );
                $order_pmt_voucher_detail[] = array(
                    'code'     => $coupon_id,
                    'pmt_sum'  => $use_money,
                    'products' => json_encode($used_data['product_bn_list']),
                    'type'     => 'voucher',
                );
            }
            $insertRes = $shopModel->SavepreFerential($order_id ,$order_pmt_voucher_detail) ;
            if(empty($insertRes)) {
                $error_msg = ' 锁定优惠券资源时，向 sdb_b2c_pop_preferential 表保存数据失败';
                $msg = '券数据错误';
                self::putCreateErrorGeneralLog($msg . ' ' . $error_msg, array('temp_order_id' => $order_data['temp_order_id'], 'order_pmt_voucher_detail' => $order_pmt_voucher_detail,));
                return array('code' => '1132' ,'msg' => $msg) ;
            }
            $ret = self::lockVoucher($order_id,$member_id,$voucher_list,$error_code,$msg);
            if (false === $ret) {
                $msg        = '使用优惠券失败:(' . $msg . ")";
                self::putCreateErrorGeneralLog($msg, array('temp_order_id' => $order_data['temp_order_id'], 'voucher_list' => $voucher_list,));
                return array('code' => '1037' ,'msg' => $msg) ;
            }
            self::setUndoList('unlock_voucher_list' ,true);
        }

        //免邮券
        if (isset($asset_list['freeshipping']) && !empty($asset_list['freeshipping'])) {
            // 锁定免邮券资源
            self::lockOrderResource($order_id ,'freeshipping') ;
            $freeshipping_list = array();
            $order_pmt_freeshipping_detail = [] ;
            foreach ($asset_list['freeshipping'] as $coupon_id => $used_data) {
                $filter_data_products = array();
                foreach ($used_data['product_bn_list'] as $product_bn) {
                    $temp                   = array(
                        'bn'       => $product_bn,
                        'goods_id' => $goods_list[$product_bn]['goods_id'],
                        'freight'  => $goods_list[$product_bn]['freight'],
                        'price'    => $goods_list[$product_bn]['price'],
                        'quantity' => $goods_list[$product_bn]['nums'],
                    );
                    $filter_data_products[] = $temp;
                }
                $order_pmt_freeshipping_detail[] = array(
                    'code'     => $coupon_id,
                    'pmt_sum'  => $used_data['amount'],
                    'products' => json_encode($used_data['product_bn_list']),
                    'detail'    => json_encode([
                        'money' => $used_data['money'] ,
                    ]) ,
                    'type'     => 'freeshipping',
                );
                $freeshipping_list[] = array(
                    'coupon_id'   => $coupon_id,
                    'pmt_sum'    => $used_data['amount'] ,
                    'filter_data' => array(
                        'products' => $filter_data_products,
                        'version'  => 6,
                        'newcart'  => 1,
                    ),
                );
            }
            $insertRes = $shopModel->SavepreFerential($order_id ,$order_pmt_freeshipping_detail) ;
            if(empty($insertRes)) {
                $error_msg = ' 锁定免邮券资源时，向 sdb_b2c_pop_preferential 表保存数据失败';
                $msg = '免邮券数据错误';
                self::putCreateErrorGeneralLog($msg .' ' .$error_msg, array('temp_order_id' => $order_data['temp_order_id'], 'order_pmt_freeshipping_detail' => $order_pmt_freeshipping_detail,));
                return array('code' => '1133' ,'msg' => $msg) ;
            }
            $ret = self::lockFreeShipping($order_id, $member_id, $company_id, $freeshipping_list,$error_code, $msg);
            if (false === $ret) {
                $msg        = '使用免邮券失败:' . $msg;
                self::putCreateErrorGeneralLog($msg, array('temp_order_id' => $order_data['temp_order_id'], 'freeshipping_list' => $freeshipping_list,));
                return array('code' => '1033' ,'msg' => $msg) ;
            }
            self::setUndoList('unlock_freeshipping' ,true);
        }

        //免税券
        if (isset($asset_list['dutyfree']) && !empty($asset_list['dutyfree'])) {
            // 锁定免税券资源
            self::lockOrderResource($order_id ,'dutyfree' ,'' ,$order_data['pmt_tax']) ;
            $dutyfree_list = array();
            $order_pmt_dutyfree_detail = [] ;
            foreach ($asset_list['dutyfree'] as $coupon_id => $used_data) {
                $filter_data_products = array();
                foreach ($used_data['product_bn_list'] as $product_bn) {
                    $temp                   = array(
                        'bn'       => $product_bn,
                        'goods_id' => $goods_list[$product_bn]['goods_id'],
                        'tax'      => $goods_list[$product_bn]['tax'],
                        'price'    => $goods_list[$product_bn]['price'],
                        'quantity' => $goods_list[$product_bn]['nums'],
                    );
                    $filter_data_products[] = $temp;
                }
                $order_pmt_dutyfree_detail[] = array(
                    'code'     => $coupon_id ,
                    'pmt_sum'  => isset($used_data['amount']) ?$used_data['amount'] : '0' ,
                    'products' => json_encode($used_data['product_bn_list']),
                    'type'     => 'dutyfree',
                );
                $dutyfree_list[] = array(
                    'coupon_id'   => $coupon_id,
                    'filter_data' => array(
                        'products' => $filter_data_products,
                        'version'  => 6,
                        'newcart'  => 1,
                    ),
                );
            }
            $insertRes = $shopModel->SavepreFerential($order_id ,$order_pmt_dutyfree_detail) ;
            if(empty($insertRes)) {
                $error_msg = ' 锁定免税券资源时，向 sdb_b2c_pop_preferential 表保存数据失败';
                $msg = '免税券数据错误';
                self::putCreateErrorGeneralLog($msg .' ' .$error_msg, array('temp_order_id' => $order_data['temp_order_id'], 'order_pmt_dutyfree_detail' => $order_pmt_dutyfree_detail,));
                return array('code' => '1134' ,'msg' => $msg) ;
            }
            $ret = self::lockDutyFree($order_id, $member_id, $company_id, $dutyfree_list, $error_code, $msg);
            if (false === $ret) {
                $msg        = '使用免税券失败:' . $msg;
                self::putCreateErrorGeneralLog($msg, array('temp_order_id' => $order_data['temp_order_id'], 'dutyfree_list' => $dutyfree_list,));
                return array('code' => '1038' ,'msg' => $msg) ;
            }
            self::setUndoList('unlock_dutyfree' ,true);
        }
        return array('code' => '200' ,'msg' => '成功') ;
    }
    /*
     *  同步订单优惠回滚，根据反向操作标记进行回滚
     */
    public static  function thisRollback($order_data)
    {
        $order_id = $order_data['temp_order_id'];
        if (empty($order_id)) {
            return false;
        }
        $undo_list = self::getUndoList();
        if(empty($undo_list)) {
            return true ;
        }
        $serviceOrder = new OrderService ;
        //确认订单未创建成功才允许回滚
        $serviceOrder->getOrderInfo($order_id, $code);
        if($code != '1204') {
            return false ;
        }
        $assetCancelObj = new AssetCancel() ;
        if (isset($undo_list['timeBuyCancel'])) {
            $ret              = $assetCancelObj->timeBuyCancel($order_id);
            \Neigou\Logger::Debug("service.order",
                array('action' => 'newcartPromotion->timeBuyCancel', 'order_id' => $order_id, 'ret' => $ret));
            // 释放资源
            if ($ret){
                self::releaseOrderResource($order_id, 'timeBuyCancel');
            }
        }

        if (isset($undo_list['limitMoneyCancel'])) {
            $ret              = $assetCancelObj->limitMoneyCancel($order_id);
            \Neigou\Logger::Debug("service.order",
                array('action' => 'newcartPromotion->limitMoneyCancel', 'order_id' => $order_id, 'ret' => $ret));
            // 释放资源
            if ($ret){
                self::releaseOrderResource($order_id, 'limitMoneyCancel');
            }
        }

        if (isset($undo_list['unlock_freeshipping'])) {
            $ret     = $assetCancelObj->cancel_order_for_freeshipping_coupon($order_id);
            \Neigou\Logger::Debug("service.order", array(
                'action'   => 'thisRollback->cancel_order_for_freeshipping_coupon',
                'order_id' => $order_id,
                'ret'      => $ret
            ));
            // 释放资源
            if ($ret){
                self::releaseOrderResource($order_id, 'freeshipping');
            }
        }

        if (isset($undo_list['unlock_use_point'])) {
            //取消之前锁定积分
            $cancel_lock_data = array(
                'company_id' => $order_data['company_id'],
                'member_id'  => $order_data['member_id'],
                'use_type'   => 'order',
                'use_obj'    => $order_id,
                'channel'    => $order_data['point_channel'],
                'memo'       => '订单取消,订单号：' . $order_id,
            );
            $ret = $assetCancelObj->cancelLockPoint($cancel_lock_data);
            \Neigou\Logger::Debug("service.order", array(
                'action'   => 'thisRollback->memberOrderStatusChanged',
                'order_id' => $order_id,
                'ret'      => $ret
            ));
            if ($ret) {
                // 释放资源
                self::releaseOrderResource($order_id, 'point');
            }
        }
        //新版优惠券
        if (isset($undo_list['unlock_voucher_list'])) {

            $cancel_voucher_number_list = isset($order_data['calc_asset']['voucher']) ? array_keys($order_data['calc_asset']['voucher']) : [] ;
            $ret       = $assetCancelObj->cancelVoucher($cancel_voucher_number_list);

            if ($ret) {
                // 释放资源
                self::releaseOrderResource($order_id, 'voucher');
            }
        }

        //免税券
        if (isset($undo_list['unlock_dutyfree'])) {

            $ret = $assetCancelObj->CancelLockDutyFree($order_id);

            \Neigou\Logger::Debug(
                "service.order.dutyfree",
                array(
                    'action'   => 'thisRollback->exchange_status',
                    'order_id' => $order_id,
                    'ret'      => $ret
                )
            );
            if ($ret) {
                // 释放资源
                self::releaseOrderResource($order_id, 'dutyfree');
            }
        }

        //释放订单锁定的资源额度
        $assetCancelObj->cancel_record($order_id);

        // 订单金额限制
        if (isset($undo_list['order_amount_limit']))
        {
            $transactionObj = new Transaction() ;
            $updataRes= $transactionObj->updateAmountLimitStatus($order_id) ;
            if ($updataRes)
            {
                self::releaseOrderResource($order_id, 'order_amount_limit');
            }
        }

        // 订单金额
        if (isset($undo_list['settlement_channel_pay']))
        {
            if ($assetCancelObj->settlementChannelRefund($order_id))
            {
                self::releaseOrderResource($order_id, 'settlement_channel_pay');
            }
        }
        return true;
    }

    // 锁定限时限购资源
    public static  function create_time_buy_promotion($order_id,$goods_list,$company_id,$member_id,&$msg)
    {
        $serviceObj = new Service ;
        $post_data =  array('order_id'=>$order_id,'product'=>$goods_list,'company_id'=>$company_id,'member_id'=>$member_id);
        $ret =  $serviceObj->ServiceCall("promotion_timeBuy_lock" ,$post_data) ;
        //如果有限购商品，调用创建限购接口
        $data_goods = $ret['data'] ? $ret['data'] : array();
        if('SUCCESS' != $ret['error_code'] || empty($data_goods)) {
            $msg = '限时限购,库存锁定失败，' . ($ret['error_msg'] ? $ret['error_msg'][0] : '接口异常');
            return false ;
        }
        $error_data = array() ;
        foreach($data_goods['product'] as $item){
            if($item['limit_rule'] !== false && $item['limit_res']['status'] === false){
                $error_data[] = $item['product_bn'];
            }
        }
        //都锁定成功则返回成功
        if(count($error_data) > 0){
            $msg = '部分限时限购，库存锁定失败，bn: ' . implode(',', $error_data);
            return false;
        }
        return true;
    }

    // 锁定限额资源
    public static  function create_limit_money_promotion($order_id,$goods_list,$company_id,$member_id, &$msg)
    {
        $serviceObj = new Service;
        $post_data =  array('order_id'=>$order_id,'product'=>$goods_list,'company_id'=>$company_id,'member_id'=>$member_id);
        $ret =  $serviceObj->ServiceCall("promotion_money_lock" ,$post_data) ;
        // 如果有限额商品，调用创建限额接口
        $data_goods = $ret['data'] ? $ret['data'] : array();
        if('SUCCESS' != $ret['error_code'] || empty($data_goods)) {
            $msg = '库存锁定，限额锁定失败，' . ($ret['error_msg'] ? $ret['error_msg'][0] : '接口异常');
            return false ;
        }
        $error_data = array() ;
        foreach($data_goods['product'] as $item){
            if($item['limit_rule'] !== false && $item['limit_res']['status'] === false){
                $error_data[] = $item['product_bn'];
            }
        }
        //都锁定成功则返回成功
        if(count($error_data) > 0){
            $msg = '库存锁定，部分限额限购锁定失败，bn: ' . implode(',', $error_data);
            return false;
        }
        return true;
    }

    // 锁定使用积分
    public static function lockUsePoint($order_data,$goods_list,&$error_msg) {
        // 参与优惠券的商品
        $accountList  = array();
        $totalPoint   = 0;
        $saveDataList = array();
        foreach ($goods_list as $product) {
            if(!isset($product['asset_list']['point'])) {
                continue ;
            }
            $quantity = isset($product['quantity']) ? $product['quantity']: $product['nums'] ;
            // 需修改
            foreach ($product['asset_list']['point'] as $pointItem) {
                if ($pointItem['amount'] <= 0) {
                    continue;
                }
                $totalPoint = bcadd($totalPoint , $pointItem['amount'] ,2) ;
                if ($accountList[$pointItem['asset_bn']]) {
                    $accountList[$pointItem['asset_bn']]['point']   += $pointItem['amount'];
                    $accountList[$pointItem['asset_bn']]['money']   += $pointItem['money'];
                    $accountList[$pointItem['asset_bn']]['items'][] = array(
                        'bn'           => $product['bn'],
                        'name'         => $product['name'],
                        'price'        => $product['price'],
                        'nums'         => $quantity,
                        'amount'       => $product['amount'],
                        'point_amount' => $pointItem['amount'],
                    );
                } else {
                    $accountList[$pointItem['asset_bn']] = array(
                        'account' => $pointItem['asset_bn'],
                        'point'   => $pointItem['amount'],
                        "money" => $pointItem['money'] ,
                        'items'   => array(
                            array(
                                'bn'           => $product['bn'],
                                'name'         => $product['name'],
                                'price'        => $product['price'],
                                'nums'         => $quantity,
                                'amount'       => $product['amount'],
                                'point_amount' => $pointItem['amount'],
                            )
                        )
                    );
                }
                $saveDataList[] = array(
                    'order_id'    => $order_data['temp_order_id'],
                    'goods_bn'    => $product['goods_bn'],
                    'product_bn'  => $product['bn'],
                    'scene_id'    => $pointItem['asset_bn'],
                    'point'       => $pointItem['amount'],
                    'money'       => $pointItem['money'] , // 由计算服务计算
                    'amount'      => $product['amount'],
                    'nums'        => $quantity ,
                    'create_time' => time(),
                    'update_time' => time()
                );
            }
        }
        if(empty($saveDataList)) {
            $error_msg = '商品无使用积分' ;
            return true ;
        }
        $model = new BaseModel() ;
        $model = $model->setTable('sdb_b2c_order_scene_point_info');
        $saveStatus =  $model->mulitInsert($saveDataList) ;
        if(empty($saveStatus)) {
            $error_data = ' 锁定用户积分时，向 sdb_b2c_order_scene_point_info 表存入数据失败';
            self::putCreateErrorGeneralLog($error_data, array('temp_order_id' => $order_data['temp_order_id'], 'saveDataList' => $saveDataList,));
            $error_msg = '锁定积分,积分信息保存失败 ' ;
            return false ;
        }
        // 加上税费
        // $totalPoint = bcadd($totalPoint ,$order_data['cost_tax'],2) ;
        if (abs($order_data['pmt_point'] - $totalPoint) > 0.001) {
            $error_data = ' 锁定用户积分时，订单总金额pmt_point：' . $order_data['pmt_point'] . ' - 总积分金额totalPoint：' . $totalPoint . ' 大于 0.001了';
            self::putCreateErrorGeneralLog($error_data, array('temp_order_id' => $order_data['temp_order_id'], 'order_data' => $order_data, 'totalPoint' => $totalPoint,));
            $error_msg = '锁定积分,积分金额错误';
            return false;
        }
        $lock_point_data = array(
            'system_code'     => 'NEIGOU',
            'company_id'      => $order_data['company_id'],
            'member_id'       => $order_data['member_id'],
            'use_type'        => 'order',
            'use_obj'         => $order_data['temp_order_id'],
            'channel'         => $order_data['point_channel'],
            'point'           => $order_data['pmt_point'],
            'money'           => $order_data['point_amount'],
            'third_point_pwd' => isset($order_data['third_point_pwd']) ? $order_data['third_point_pwd'] : '',
            'account_list'    => array_values($accountList),
            'extend_data'     => array(
                 'order_total_amount' => $order_data['final_amount'],
                 'use_son_asset' => isset($order_data['use_son_asset']) ? $order_data['use_son_asset'] : array()
            ),
            'memo'            => '创建订单,订单号：' . $order_data['temp_order_id'],
        );
        $service_obj =new Service() ;
        $res = $service_obj->ServiceCall('point_lock',$lock_point_data) ;

        \Neigou\Logger::General("service_point_LockThirdPoint", array(
            "platform" => "service_use_point",
            "res"     => $res ,
            'request'    => $lock_point_data ,
            'order_id'  => $order_data['temp_order_id'] ,
        ));
        if ('SUCCESS' == $res['error_code']) {
            return true ;
        }
        $error_msg = $res['error_msg'][0]  ? $res['error_msg'][0] : '锁定积分失败' ;
        if(is_array($error_msg)) {
            $error_msg =  $error_msg[0] ;
        }
        return false;
    }


    //锁定内购、打折券
    public static  function lockVoucher($order_id,$member_id,$voucher_list,&$error_code,&$msg)
    {
        $req_param = array();
        foreach($voucher_list as $item){
            $req_param[] = array(
                'voucher_number_list' => $item['voucher_number_list'],
                'order_id'=> $order_id,
                'member_id'=>$member_id,
                'use_money'=>$item['use_money'],
                'memo'=>$item['memo'],
            );
        }
        $serviceObj = new Service ;
        $ret = $serviceObj->ServiceCall('voucher_lock' , $req_param) ;
        \Neigou\Logger::General("service_voucher.lockFreeShipping", array("platform"=>"web", "function"=>"lockFreeShipping","req_param"=>$req_param,"ret"=>$ret));

        if ('SUCCESS' == $ret['error_code']) {
            return true ;
        }
        $error_code = $ret['error_detail_code'];
        $msg = isset($ret['error_msg']['0']) ? $ret['error_msg']['0'] : '';
        return false;

    }

    public static function cancelVoucher($voucher_number_list,&$error_code,&$msg)
    {
        $req_param = array(
            'voucher_number_list'=>$voucher_number_list,
            'status'=>'normal',
            'memo'=>'订单取消',
        );
        $serviceObj = new Service ;
        $ret = $serviceObj->ServiceCall('voucher_cancel' , $req_param) ;
        \Neigou\Logger::General("service_voucher.cancelVoucher", array("platform"=>"web", "function"=>"cancelVoucher","req_param"=>$req_param,"ret"=>$ret));
        if ('SUCCESS' == $ret['error_code']) {
            return $ret['data'] ;
        }
        $error_code = $ret['error_detail_code'];
        $msg = isset($ret['error_msg']['0']) ? $ret['error_msg']['0'] : '';
        return false;
    }

    //锁定免邮券
    public static  function lockFreeShipping($order_id,$member_id,$company_id,$freeshipping_list,&$error_code,&$msg)
    {
        $req_param = array(
            'couponInfo'=>array(),
        );
        foreach($freeshipping_list as $item){
            $temp = array(
                'coupon_id'=>$item['coupon_id'],
                'order_id'=>$order_id,
                'member_id'=>$member_id,
                'company_id'=>$company_id,
                'filter_data'=>$item['filter_data'],
            );
            $req_param['couponInfo'][] = $temp;
        }
        $serviceObj = new Service() ;
        $ret = $serviceObj->ServiceCall("freeShipping_lock" ,$req_param) ;
        \Neigou\Logger::General("service_voucher.lockFreeShipping", array("platform"=>"web", "function"=>"lockFreeShipping","req_param"=>$req_param,"ret"=>$ret));
        if('SUCCESS' == $ret['error_code']){
            return $ret['data'];
        }
        $error_code = $ret['error_detail_code'];
        $msg = isset($ret['error_msg']['0']) ? $ret['error_msg']['0'] : '';
        return false;
    }
    //锁定免税券
    public  static  function lockDutyFree($order_id,$member_id,$company_id,$dutyfree_list,&$error_code,&$msg)
    {
        $req_param = array(
            'couponInfo'=>array(),
        );
        foreach($dutyfree_list as $item){
            $temp = array(
                'coupon_id'=>$item['coupon_id'],
                'order_id'=>$order_id,
                'member_id'=>$member_id,
                'company_id'=>$company_id,
                'filter_data'=>$item['filter_data'],
            );
            $req_param['couponInfo'][] = $temp;
        }
        $serviceObj = new Service() ;
        $ret = $serviceObj->ServiceCall("dutyFree_lock" ,$req_param) ;

        \Neigou\Logger::General("service_voucher.lockDutyFree", array("platform"=>"web", "function"=>"lockDutyFree","req_param"=>$req_param,"ret"=>$ret));
        if('SUCCESS' == $ret['error_code']){
            return $ret['data'];
        }
        $error_code = $ret['error_detail_code'];
        $msg = isset($ret['error_msg']['0']) ? $ret['error_msg']['0'] : '';
        return false;
    }
    /**
     * 释放资源
     *
     * @param   $orderId    string      订单ID
     * @param   $type       string      资源类型
     * @param   $item       string      资源项
     * @param   $memo       string      备注
     * @return  mixed
     */
    public static  function releaseOrderResource($orderId, $type, $item = '', $memo = '')
    {
        $data = array(
            'object'            => 'ORDER',
            'object_item'       => $orderId,
            'resource'          => strtoupper($type),
            'resource_item'     => $item,
            'memo'              => $memo ? $memo : 'cancel order',
        );
        $serviceObj = new Service() ;
        $ret = $serviceObj->ServiceCall('resource_release' ,$data) ;
        if ( $ret['error_code'] != 'SUCCESS' OR empty($ret['data']))
        {
            return false;
        }
        return $ret['data']['res_id'] ? $ret['data']['res_id'] : false;
    }

    private static function putCreateErrorGeneralLog($message, $error_extend)
    {
        \Neigou\Logger::General('service_order_create', array(
            'message' => $message, 'error_extend' => $error_extend,
        ));
    }


}
