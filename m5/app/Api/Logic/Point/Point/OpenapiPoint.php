<?php

namespace App\Api\Logic\Point\Point;
use App\Api\Logic\Openapi;

class OpenapiPoint extends Point{
    private $config = array();

    public function __construct($config){
        parent::__construct();
        $this->config   = $config;
    }

    //获取用户积分
    public function GetMemberPoint($member_bn,$company_bn){
        if(empty($member_bn)) return false;
        $post_data  = [
            'member_id' => $member_bn,
            'company_id'    => $company_bn
        ];
        $res_data   = $this->SendData('get_member_point_uri',$post_data);
        $data   = array(
            'member_bn' => $member_bn,
            'company_bn' => $company_bn,
            'money' => $res_data['Data']['usable_money'],
            'freeze_money' => $res_data['Data']['freeze_money'],
            'used_money' => $res_data['Data']['used_money'],
            'point' => $res_data['Data']['usable_point'],
            'freeze_point' => $res_data['Data']['freeze_point'],
            'used_point' => $res_data['Data']['used_point'],
        );
        $res_data['Data']   = $data;
        return $res_data;
    }

    //用户积分锁定
    public function LockMemberPoint($data){
        if(empty($data['member_bn']) || empty($data['out_trade_no']) || empty($data['point'])) return false;
        $post_data  = [
            'member_id' => $data['member_bn'],
            'company_id' => $data['company_bn'],
            'order_id' => $data['out_trade_no'],
            'money' => $data['money'],
            'point' => $data['point'],
            'items' => $data['items'],
            'extend_data' => $data['extend_data'],
            'third_point_pwd' => $data['third_point_pwd'],
        ];
        $res_data   = $this->SendData('lock_member_point_uri',$post_data);
        $res_data['Data']['member_bn']  = $data['member_bn'];
        $res_data['Data']['company_bn']  = $data['company_bn'];
        $res_data['Data']['trade_no']  = empty($res_data['Data']['out_trade_no'])?$data['out_trade_no']:$res_data['Data']['out_trade_no'];
        return $res_data;
    }

    //取消用户积分锁定
    public function CancelLockMemberPoint($data){
        if(empty($data['member_bn']) || empty($data['out_trade_no'])) return false;
        $post_data  = [
            'member_id' => $data['member_bn'],
            'company_id' => $data['company_id'],
            'order_id' => $data['out_trade_no'],
            'memo' => $data['memo'],
        ];
        $res_data   = $this->SendData('cancel_member_point_uri',$post_data);
        return $res_data;
    }

    //确认用户积分锁定
    public function ConfirmLockMemberPoint($data){
        if(empty($data['member_bn']) || empty($data['out_trade_no'])) return false;
        $post_data  = [
            'member_id' => $data['member_bn'],
            'company_id' => $data['company_id'],
            'order_id' => $data['out_trade_no'],
        ];
        $res_data   = $this->SendData('confirm_member_point_uri',$post_data);
        return $res_data;
    }

    //获取用户积分锁定
    public function GetLockMemberPoint($data){

    }

    public function RefundPoint($data){
        if(empty($data['member_bn']) || empty($data['out_trade_no']) || empty($data['point'])) return false;
        $post_data  = [
            'member_id' => $data['member_bn'],
            'company_id' => $data['company_bn'],
            'order_id' => $data['out_trade_no'],
            'point' => $data['point'],
            'money' => $data['money'],
            'refund_id' => $data['refund_id'],//添加售后流水号
        ];
        $res_data   = $this->SendData('refund_member_point_uri',$post_data);
        $format_data   = array(
            'member_bn' => $data['member_bn'],
            'company_bn' => $data['company_bn'],
            'trade_no'  => empty($res_data['Data']['trade_no'])?$data['out_trade_no']:$res_data['Data']['trade_no'],
            'money'  => $res_data['Data']['money'],
            'point'  => $res_data['Data']['point'],
        );
        $res_data['Data']   = $format_data;
        return $res_data;
    }

    //获取用户积分--三方积分暂时没有流水
    public function GetMemberRecord($member_bn, $company_bn, $page, $rowNum, $data=array())
    {
        return [
            'Result' => 'true',
            'Data'   => [],
        ];
    }

    public function GetPointRate($data){
        $post_data  = [
            'member_id' => $data['member_bn'],
        ];
        $res_data   = $this->SendData('get_point_ratio',$post_data);
        return $res_data;
    }

    private function SendData($uri,$post_data){
        if(empty($post_data)) return false;
        if(!isset($this->config[$uri])){
            return false;
        }
        $post_data['channel']   = $this->config['channel'];
        $openapi_logic  = new Openapi();
        $result = $openapi_logic->Query($this->config[$uri],$post_data);
        if($result['Result'] != 'true'){
            \Neigou\Logger::Debug('third_point_post_fail',array(
                'sender'    => json_encode($post_data),
                'reason'    => json_encode($result),
                'action'    => $this->config[$uri]
            ));
        }
        return $result;
    }

}
