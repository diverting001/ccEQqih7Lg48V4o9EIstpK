<?php


namespace App\Api\Logic\AssetOrder;
use App\Api\Logic\Invoice\Repeater;
use App\Api\Logic\Openapi;
use  App\Api\Logic\Service ;
use App\Api\Model\Company\ClubCompany;

class Settlement
{
   /*
    * 结算付款
    *
    */
    public function settlementChannelPay($request_data ,$goods_list,$checkChannel = false)
    {
        // 现金
        $cashMoney = $request_data['cur_money'];
        // 积分金额
        $pointAmount = $request_data['point_amount'];

        $goods_list_arr = array();
        foreach ($goods_list as $goodsInfo)
        {
            $goods_list_arr[] = array(
                'product_bn'    => isset($goodsInfo['product_bn']) ? $goodsInfo['product_bn'] : $goodsInfo['bn'],
                'goods_bn'      => $goodsInfo['goods_bn'],
                'name'          => $goodsInfo['name'],
                'price'         => $goodsInfo['price'],
                'cost'          => $goodsInfo['cost'],
                'num'           => $goodsInfo['quantity'],
                'cost_tax'      => $goodsInfo['cost_tax'], // 商品税费
            );
        }
        $data = array(
            'company_id'    => $request_data['company_id'],
            'order_id'      => $request_data['temp_order_id'],
            'payment_list' => array(
                'PAYMENT' => array('cash' => $cashMoney),
                'POINT' => array($request_data['point_channel'] => $pointAmount)
            ),
            'order_info' => array(
                'final_amount'  => $request_data['final_amount'], // 订单最终金额
                'pmt_amount'    => $request_data['pmt_amount'], // 优惠金额
                'cost_freight'  => $request_data['feright_amount'],
                'goods_list'    => $goods_list_arr,
            ),
            'order_category'    => 'local',
        );

        $serviceObj = new Service() ;
???????????
        $result = $serviceObj->ServiceCall('settlement_orderPay' ,$data) ;

        if ( !empty($result['data']['result']) OR ($checkChannel == false && $result['error_detail_code'] == 1000))
        {
            return array("code" => '200' ,'msg' =>'结算支付成功') ;
        }

        $msg = $result['error_msg'] ? $result['error_msg'][0] : '';
        $msg = $msg ? $msg : '支付配置异常，暂时无法下单，请联系管理员解决后重试' ;
        \Neigou\Logger::General("order_settlement_channel_order_pay", array(
            'msg' => $msg,
            'function'  => 'settlementChannelPay',
            'errMsg'    => $result['service_data']['error_msg'],
            "request"   => $data,
            'order_id'  => $data['order_id'] ,
            "response"  => $result,
        ));
        return  array("code" => $result['error_detail_code'] ,'msg' => $msg );
    }



    /**
     * 保持splist信息
     */
    public function saveSplit($splitInfo,$goods_list,&$e_msg)
    {
        $post_data = array(
            'split_info' => $splitInfo,
            'order_info' => $goods_list
        ) ;
        $serviceObj = new Service() ;
        $ret = $serviceObj->ServiceCall('order_split_create' ,$post_data) ;
        if ('SUCCESS' == $ret['error_code'] && !empty($ret['data'])) {
            $split_id = $ret['data']['split_id'];
            //写入成功，记录$split_id信息
            return $split_id;
        }
        $e_msg = $ret['error_msg'][0] ?? '保存拆单信息失败';
        \Neigou\Logger::General("order.saveSplit.error", array(
            'msg' => $e_msg,
            'temp_order_id' => $splitInfo['temp_order_id'],
            "platform"   => "web",
            'function'   => 'saveSplit',
            'response'   => $ret ,
        ));
        return false;
    }




    /**
     * 创建订单
     * @return array
     */
    public function createOrder($order_info,$delivery)
    {
        $serviceObj = new Service() ;

        $order_info = array_merge($order_info ,$delivery) ;
        $extend_data = $order_info['extend_data'] ;
        if(isset($extend_data['idcardname'])) {
            $order_info['idcardname'] = $extend_data['idcardname'] ;
        }
        if(isset($extend_data['idcardno'])) {
            $order_info['idcardno'] = $extend_data['idcardno'] ;
        }
        if($order_info['total_amount']) {
            $order_info['cost_item'] = $order_info['total_amount'] ;
        }
        // 运费
        $order_info['feright_price'] = $order_info['feright_amount'] ;

        $ret = $serviceObj->ServiceCall('order_create' ,$order_info) ;

        \Neigou\Logger::General("service_order_create", array(
            "action"   => "create-order",
            "sparam1"  => $order_info ,
            'sparam2'   => $ret ,
        ));

        if ( 'SUCCESS' == $ret['error_code'] && !empty($ret['data'])) {
            $order_id = $ret['data']['order_id'];
            return array('code' =>'200' ,'msg' => '成功' ,'order_id' => $order_id ) ;
        }
        $return_data = array() ;
        $bn_list = is_array($ret['data']) && !empty($ret['data']) ? array_values($ret['data']) : [] ;
        if ($ret['error_detail_code'] == '401') { //库存不足
            $return_data = array('code' => 1050  ,'msg' => '库存不足'  ,'data' => $bn_list) ;
        } elseif ($ret['error_detail_code'] == '402') {
            //下单风控拦截
            $msg = '订单生成失败，当日未支付或取消订单数量过多。如需帮助，请拨打客服电话：4006666365';
            $return_data = array('code' => 1060  ,'msg' => $msg   ,'data' => $bn_list) ;
        } elseif ($ret['error_detail_code'] == '403') {
            $msg = '订单生成失败' . (!empty($ret['error_msg'][0]) ? ':(' . $ret['error_msg'][0] . ')' : '');
            $return_data = array('code' => 1070  ,'msg' => $msg ,'data' =>$bn_list ) ;
        }  else {
            $msg = '订单生成失败' . (!empty($ret['error_msg'][0]) ? ':(' . $ret['error_msg'][0] . ')' : '');
            //  $ret['error_detail_code']
            $return_data =  array('code' => 1070   ,'msg' => $msg  ,'data' => $bn_list ) ;
        }
        return $return_data;
    }


    // 申请发票
    public function  createOrderInvoice($order_data ,$goods_list,$splitInfo,&$e_msg)
    {
        $extend_data = $order_data['extend_data'] ;
        if (empty($extend_data['invoice_service_info'])) {
            return true;
        }
        // 获取发票开启状态
        $productIds = array();
        foreach ($goods_list as $goods) {
            $productIds[] = $goods['id'];
        }
        // 公司ID
        $companyId = $order_data['company_id'];

        // 公司是否可以开发票
        $clubCompamyModel = new ClubCompany() ;

        $channel     = $clubCompamyModel->getCompanyRealChannel($companyId);
        $repeaterLogic = new Repeater() ;
        $invoiceData = $repeaterLogic->AggregationCompanyAndProductInvoiceData(
            $productIds,
            $companyId,
            $channel
        );

        if (empty($invoiceData) OR $invoiceData['is_make_invoice'] == false) {
            return true;
        }

        $orderList = array();

        foreach ($splitInfo['split_orders'] as $orderInfo) {
            $orderInfo['order_id'] = $orderInfo['temp_order_id'];
            $orderList[]           = $orderInfo;
        }

        // 申请发票
        $data = array(
            'invoice_service_info' => array(
                'invoice_info' => $extend_data['invoice_service_info'],
                'invoice_data' => $invoiceData,
            ),
            'order_list'           => $orderList,
        );
        $serviceObj = new Service() ;
        $ret = $serviceObj->ServiceCall('invoice_applyBatch' , $data) ;

        \Neigou\Logger::Debug( 'order_invoice_pre_apply',
            array(
                'action' => 'PreApply',
                'data'   => $data ,
                'result' => $ret
            )
        );
        if ( 'SUCCESS' != $ret['error_code']) {
            $e_msg = $ret['error_msg'][0] ?? '申请发票失败';
            return false;
        }
        return true;
    }



    /**
     * 保存发票信息
     */
    public function saveInvoiceInfo($request_data)
    {
        $extend_data = isset($request_data['extend_data'] ) ?? [];
        if (isset($extend_data['invoice_info']) && !empty($extend_data['invoice_info'])) {
            $invoice_info_req = array(
                "class_obj"    => "Invoice",
                "method"       => "saveInvoice",
                'member_id'    => $request_data['member_id'],
                'company_id'   => $request_data['company_id'],
                'type'         => 0,
                'number'       => $extend_data['invoice_info']['number'],
                'company_name' => $extend_data['invoice_info']['company_name'],
            );

            /* @var b2c_openapi $logic_openapi */
            $logic_openapi  = new Openapi();
            $host = config('neigou.CLUB_DOMAIN') ;
            $openapi_result = $logic_openapi->CurlOpenApi($host . '/OpenApi/apirun', $invoice_info_req);
            if ($openapi_result['Result'] == 'true') {
                return true;
            }
            return false ;
        }
        return true ;
    }

}
