<?php

namespace App\Api\Logic\AssetOrder;

use App\Api\Logic\Service;
use App\Api\Model\Company\ClubCompany;
use App\Api\Model\Goods\TimedbuyObjitems;

class AssetCancel
{


    protected $serviceObj ;
    public function __construct()
    {
        $this->serviceObj = new Service();
    }

    public function timeBuyCancel($order_id)
    {
        $this->cancel_time_buy_promotion($order_id) ;
        $where['order_id'] = $order_id;
        $update['status'] = '2';
        $timedbuyObjitemsModel  = new TimedbuyObjitems() ;
        return $timedbuyObjitemsModel->baseUpdate($where ,$update) ;
    }

    public function limitMoneyCancel($order_id)
    {
       return $this->cancel_time_buy_promotion($order_id) ;
    }

    public function cancel_time_buy_promotion($order_id)
    {
        if(empty($order_id)) {
            return false ;
        }
        $ret = $this->serviceObj->ServiceCall('promotion_TimeBuy_UnLock' ,array('order_id'=>$order_id)) ;
        return  'SUCCESS' == $ret['error_code'] ? true :false ;
    }


    // 取消订单使用免邮券
    public function cancel_order_for_freeshipping_coupon($order_id) {

        $cancel_params = array(
            "order_id"=>$order_id,
        );
        $res = $this->serviceObj->ServiceCall('freeShipping_cancel' ,$cancel_params) ;
        \Neigou\Logger::Debug("voucher.new", array("action"=>"cancel_order_for_freeshipping_coupon", 'order_id' =>$order_id ,  "request"=>$cancel_params, "response" => $res));
        if($res['error_code'] == 'SUCCESS' && !empty($res['service_data']['data'])){
            return true ;
        }
        return false;
    }


    /*
    * @todo 取消积分锁定
    */
    public function cancelLockPoint($cancel_lock_data)
    {
        if (empty($cancel_lock_data)) {
            return false;
        }

        $send_data = array(
            'system_code' => 'NEIGOU',
            'company_id'  => $cancel_lock_data['company_id'],
            'member_id'   => $cancel_lock_data['member_id'],
            'use_type'    => $cancel_lock_data['use_type'],
            'use_obj'     => $cancel_lock_data['use_obj'],
            'channel'     => $cancel_lock_data['channel'],
            'memo'        => $cancel_lock_data['memo'],
        );
        $res = $this->serviceObj->ServiceCall('point_cancel' ,$send_data) ;
        if ('SUCCESS' == $res['error_code']) {
            $clubCompanyModel = new ClubCompany() ;
            $channel = $clubCompanyModel->getCompanyRealChannel($cancel_lock_data['company_id']);
            $errno = 0 ;
            $order_info = OrderService::getOrderInfo($cancel_lock_data['use_obj'],$errno);

            $cre_req['rmb_amount'] = $order_info['point_amount'];//需要获取订单积分部分信息 注意为负数
            $cre_req['channel']    = $channel;
            $cre_req['company_id'] = $cancel_lock_data['company_id'];
            $cre_req['order_id']   = $cancel_lock_data['use_obj'];
            $cre_req['part']       = 'point';
            $reply_id = '0' ;
            $credit_res            = $this->point_reply($cre_req,$reply_id);
            \Neigou\Logger::General(
                'store.service.credit.unlock',
                array(
                    'action'   => 'unlock',
                    'req'      => $cre_req,
                    'order_id' => $cre_req['order_id'] ,
                    'reply_id' => $reply_id ,
                    'response' => $credit_res
                )
            );
            return true;
        }
        return false;
    }


    public function point_reply($data,&$reply_id){
        //获取积分部分信息
        $req['rmb_amount'] = (0-$data['rmb_amount']);//需要获取订单积分部分信息 注意为负数
        $req['channel'] = $data['channel'];
        $req['company_id'] = $data['company_id'];
        $req['trans_date'] = time();
        $req['trans_type'] = 'reply';
        $req['trans_id'] = $data['order_id'];
        $req['settle_status'] = 0;
        $req['part'] = $data['part'];

        $res = $this->serviceObj->ServiceCall('credit_bill_reply' ,$req) ;
        if('SUCCESS' == $res['error_code'] && !empty($res['data'])) {
            $reply_id = $res['data'];
            return true;
        }
        \Neigou\Logger::General('store.credit.reply.fail',array(
            'remark'=>'额度归还失败',
            'req'=>$req,
            'order_id' => $data['order_id'] ,
            'res'=>$res
        ));
        return $res['error_detail_code'];
    }


    // 取消优惠券锁定
    public function cancelVoucher($voucher_number_list)
    {
        if(empty($voucher_number_list)) {
            return false ;
        }
        $req_param = array(
            'voucher_number_list'=>$voucher_number_list,
            'status'=>'normal',
            'memo'=>'订单取消-优惠券',
        );
        $ret = $this->serviceObj->ServiceCall('voucher_cancel' ,$req_param) ;

        \Neigou\Logger::General("newcart_voucher.cancelVoucher", array("platform"=>"web", "function"=>"cancelVoucher",  "req_param"=>$req_param,"ret"=>$ret));
        if('SUCCESS' == $ret['error_code']){
            return $ret['data'];
        }else{
            $error_code = $ret['error_detail_code'];
            $msg = isset($ret['error_msg']['0']) ? $ret['error_msg']['0'] : '';
            return false;
        }
    }


    /*
     * @todo 取消已锁定免税券
     */
    public function CancelLockDutyFree($order_id){
        if(empty($order_id) ){
            return false;
        }
        $lock_data  = array(
            'order_id' =>$order_id,
        );
        $res = $this->serviceObj->ServiceCall('dutyFree_cancel' ,$lock_data) ;
        if( 'SUCCESS' == $res['error_code']) {
            return true;
        }
        return false;
    }



    /**
     * 按trans_id 归还额度
     * @param $order_id
     * @return bool
     */
    public function cancel_record($order_id,&$error_code='0'){

        $req['trans_id'] = $order_id;

        $res = $this->serviceObj->ServiceCall('credit_record_cancel' ,$req) ;
        if( 'SUCCESS' == $res['error_code'] && !empty($res['data'])) {
            return true;
        }
        \Neigou\Logger::General('store.credit.cancel_record.fail',array(
            'remark'=>'额度取消使用失败',
            'order_id'  => $order_id ,
            'req'=>$req,
            'res'=>$res
        ));
        $error_code =  $res['service_data']['error_detail_code'];
        return  false ;
    }



    /*
    * 结算通道退款
    *
    * @param   $orderId        int 订单ID
    * @param   $refundList array   退款列表
    *              product_bn  string  货品编码
    *              num         int     数量
    * @param   $errMsg     string  错误信息
    * @return  boolean
    */
    public function settlementChannelRefund($orderId, $refundList = array())
    {
        $return = array();

        $data = array(
            'order_id' => $orderId,
            'refund_list' => $refundList,
        );
        $result = $this->serviceObj->ServiceCall('settlement_orderRefund' ,$data) ;
        if ($result['error_code'] == 'SUCCESS' && ! empty($result['data']))
        {
            $return = $result['data'];
        }
        else
        {
            \Neigou\Logger::General("order_settlement_channel_order_refund", array(
                'function'  => 'settlementChannelRefund',
                'order_id'  => $orderId ,
                'errMsg'    => $result['service_data']['error_msg'],
                "request"   => $data,
                "response"  => $result,
            ));
        }
        return $return;
    }

}
