<?php

namespace App\Api\Logic\Point\Point;
use App\Api\Logic\Point\Point\Point;

class DefaultPoint extends Point{
    private $config = array();

    public function __construct($config){
        parent::__construct();
        $this->config   = $config;
    }

    //获取用户积分
    public function GetMemberPoint($member_bn,$company_bn){
        if(empty($member_bn)) return false;
        $post_data  = [
            'member_bn' => $member_bn,
            'company_bn'    => $company_bn
        ];
        $res_data   = $this->SendData('get_member_point_uri',$post_data);
        return $res_data;
    }

    //获取用户积分
    public function GetMemberRecord($member_bn,$company_bn,$page,$rowNum,$data=array()){
        if(empty($member_bn)) return false;
        $post_data  = [
            'member_bn' => $member_bn,
            'company_bn'    => $company_bn,
            'page' => $page,
            'rowNum' => $rowNum,
            'record_type'=>$data['record_type'],
            'begin_time'=>$data['begin_time'],
            'end_time'=>$data['end_time'],
        ];

        return $this->SendData('member_record_uri',$post_data);
    }

    //用户积分锁定
    public function LockMemberPoint($data){
        if(empty($data['member_bn']) || empty($data['out_trade_no']) || empty($data['point'])) return false;
        $post_data  = [
            'member_bn' => $data['member_bn'],
            'company_bn' => $data['company_bn'],
            'out_trade_no' => $data['out_trade_no'],
            'money' => $data['money'],
            'point' => $data['point'],
        ];
        $res_data   = $this->SendData('lock_member_point_uri',$post_data);
        return $res_data;
    }

    //取消用户积分锁定
    public function CancelLockMemberPoint($data){
        if(empty($data['member_bn']) || empty($data['out_trade_no'])) return false;
        $post_data  = [
            'member_bn' => $data['member_bn'],
            'company_bn' => $data['company_id'],
            'out_trade_no' => $data['out_trade_no'],
            'memo' => $data['memo'],
        ];
        $res_data   = $this->SendData('cancel_member_point_uri',$post_data);
        return $res_data;
    }

    //确认用户积分锁定
    public function ConfirmLockMemberPoint($data){
        if(empty($data['member_bn']) || empty($data['out_trade_no'])) return false;
        $post_data  = [
            'member_bn' => $data['member_bn'],
            'company_bn' => $data['company_id'],
            'out_trade_no' => $data['out_trade_no'],
        ];
        $res_data   = $this->SendData('confirm_member_point_uri',$post_data);
        return $res_data;
    }

    //获取用户积分锁定
    public function GetLockMemberPoint($data){

    }

    //用户返还锁定积分
    public function RefundPoint($data){
        if(empty($data['member_bn']) || empty($data['out_trade_no']) || empty($data['point'])) return false;
        $post_data  = [
            'member_bn' => $data['member_bn'],
            'company_bn' => $data['company_bn'],
            'out_trade_no' => $data['out_trade_no'],
            'money' => $data['money'],
            'point' => $data['point'],
        ];
        $res_data   = $this->SendData('refund_member_point_uri',$post_data);
        return $res_data;
    }

    /** 获取积分名称
     *
     * @param $data ['member_bn' => '','company_bn' => '']
     * @return bool | string 返回false或者积分名称
     * @author liuming
     */
    public function GetPointName($data){
        if(empty($data['company_bn'])) return false;

        $post_data = [
            'company_bn' => $data['company_bn'],
        ];

        //member_bn为非必填, 如果有member_bn则传入member_bn
        if (isset($data['member_bn'])){
            $post_data['member_bn'] = $data['member_bn'];
        }

        return $this->SendData('get_point_name',$post_data);
    }


    private function SendData($uri,$post_data){
        if(empty($post_data)) return false;
        if(!isset($this->config[$uri])){
            return false;
        }
        $post_data['time']  = time();
        $send_data = array('data'=>base64_encode(json_encode($post_data)));
        $token= \App\Api\Common\Common::GetEcStoreSign($send_data);
        $send_data['token'] = $token;
        $curl = new \Neigou\Curl();
        $result_str  = $curl->Post(config('neigou.STORE_DOMIN').$this->config[$uri],$send_data);
        $result = trim($result_str, "\xEF\xBB\xBF");
        $result = json_decode($result,true);
        if($result['Result'] == 'false'){
            \Neigou\Logger::Debug('third_point_post_fail',array(
                'sender'    => json_encode($send_data),
                'reason'    => json_encode($result),
                'action'    => $this->config['host'].$this->config[$uri]
            ));
        }
        return $result;
    }

}
