<?php


namespace App\Api\Logic\AssetOrder;
use App\Api\V1\Service\Order\Concurrent as Concurrent;
use  App\Api\Logic\Service ;
# 规则服务
class OrderService
{
    /**
     *  获取订单号
     * @return bool
     */
    public static  function getOrderId()
    {
        //订单号分批配
        $serviceObj = new Service ;
        $ret  =  $serviceObj->ServiceCall("orderId_create" ,[]) ;
        if ( 'SUCCESS' == $ret['error_code'] && !empty($ret['data'])) {
            return  $ret['data']['order_id'];
        }
        \Neigou\Logger::General("service_orderId_create", array(
            'msg'         => '生成订单ID失败',
            'response' => $ret ,
        ));
        $server_concurrent = new Concurrent();
        return  $server_concurrent->GetOrderId();
    }

    // 积分规则请求
    public static function getChannelRuleBn($ruleBnList)
    {
        if(empty($ruleBnList)) {
            return false ;
        }
        $post_data =   array(
            'channel'  => 'NEIGOU_SHOPING',
            'rule_bns' => $ruleBnList ,
        );
        $serviceObj = new Service ;
        $ruleRes  =  $serviceObj->ServiceCall("rule_bn_to_neigou_rule_id" ,$post_data) ;
        if($ruleRes['error_code'] != 'SUCCESS') {
          \Neigou\Logger::General("rule_bn_to_neigou_rule", array(
                'action'         => 'rule_bn_to_neigou_rule',
                'request_data' => $post_data ,
                'response' => $ruleRes ,
          ));
          return false ;
        }
        return $ruleRes['data'] ;
    }

    // 请求规则服务
    // $rule_list 规则列表 [1,2,3]
    // product_list 商品bn 列表
    public static function getWithRule($rule_list,$product_list) {
        $post_data =   array(
            'rule_list'   => $rule_list,
            'filter_data' => array(
                'product' => $product_list ,
            )
        );
        $serviceObj = new Service ;
        $withRes  =  $serviceObj->ServiceCall('product_with_rule' ,$post_data) ;
        if($withRes['error_code'] != 'SUCCESS') {
            \Neigou\Logger::General("rule_bn_to_neigou_rule", array(
                'action'         => 'rule_bn_to_neigou_rule',
                'request_data' => $post_data ,
                'response' => $withRes ,
            ));
            return false ;
        }
        return $withRes['data'] ;
    }



    /**
     * 获取订单信息
     *
     * @param $order_id
     * @param $code （code明确了服务调用是否成功、订单是否存在：1403:参数错误，1200:订单存在,1204:订单不存在，1499:服务不可用）
     * @return bool
     */
    public static function getOrderInfo($order_id, &$code = '0')
    {
        if (empty($order_id)) {
            $code = '1403';
            return false;
        }
        $serviceObj = new Service ;
        $ret  =  $serviceObj->ServiceCall("order_info" ,array('order_id' => $order_id)) ;
        if ( 'SUCCESS' == $ret['error_code'] && !empty($ret['data'])) {
            $order_info = $ret['data'];
            $code       = '1200';
            return $order_info;
        } elseif ($ret['error_detail_code'] == '401') {
            $code = '1204';
        } else {
            $code = '1499';
        }
        return false;
    }
    /**
     * 获取订单列表
     * @param $member_id
     * @param int $page_size
     * @return bool
     */
    public static  function getOrderList($member_id, $page_size = 1)
    {
        if (empty($member_id)) {
            return false;
        }
        $serviceObj = new Service ;
        $ret  =  $serviceObj->ServiceCall("order_list" ,array('member_id' => $member_id, 'page_size' => $page_size)) ;
        if ('SUCCESS' == $ret['error_code'] && !empty($ret['data'])) {
            $order_list = $ret['data'];
            return $order_list;
        }
        return false;
    }

    /**
     * 获取下单数
     * @param $member_id
     * @return bool
     */
    public static  function getOrderCount($member_id)
    {
        $page_size  = 1;
        $order_list = self::getOrderList($member_id, $page_size);
        if ($order_list == false || !isset($order_list['total'])) {
            return false;
        }
        return $order_list['total'];
    }
}
