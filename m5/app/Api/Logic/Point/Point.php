<?php

namespace App\Api\Logic\Point;

class Point{
    private $point_class_list   = array();


    //获取用户积分
    public function GetMemberPoint($data,$channel){
        if(empty($data['member_bn']) || empty($channel)) return false;
        $point_class    = $this->CreatePoinClass($channel);
        if(!is_object($point_class)) return false;
        $point_info = $point_class->GetMemberPoint($data['member_bn'],$data['company_bn']);
        return $point_info;
    }

    /** 获取用户积分记录
     *
     * @param $data
     * @param $channel
     * @return bool
     * @author liuming
     */
    public function GetMemberRecord($data,$channel){
        if(empty($data['member_bn']) || empty($channel)) return false;
        $point_class    = $this->CreatePoinClass($channel);
        if(!is_object($point_class)) return false;
        $point_info = $point_class->GetMemberRecord($data['member_bn'],$data['company_bn'],$data['page'],$data['rowNum'],$data);
        return $point_info;
    }

    //用户积分锁定
    public function LockMemberPoint($data,$channel){
        if(empty($data['member_bn']) || empty($data['point']) || empty($channel)) return false;
        $point_class    = $this->CreatePoinClass($channel);
        if(!is_object($point_class)) return false;
        $lock_res = $point_class->LockMemberPoint($data);
        return $lock_res;
    }

    //取消用户积分锁定
    public function CancelLockMemberPoint($data,$channel){
        if(empty($data['member_bn']) || empty($data['out_trade_no']) || empty($channel)) return false;
        $point_class    = $this->CreatePoinClass($channel);
        if(!is_object($point_class)) return false;
        $cancel_res = $point_class->CancelLockMemberPoint($data);
        return $cancel_res;
    }

    //确认用户积分锁定
    public function ConfirmLockMemberPoint($data,$channel){
        if(empty($data['member_bn']) || empty($data['out_trade_no']) || empty($channel)) return false;
        $point_class    = $this->CreatePoinClass($channel);
        if(!is_object($point_class)) return false;
        $confirm_res = $point_class->ConfirmLockMemberPoint($data);
        return $confirm_res;
    }

    //获取用户积分锁定
    public function GetLockMemberPoint($data,$channel){

    }


    //用户积分返还
    public function RefundMemberPoint($data,$channel){
        if(empty($data['member_bn']) || empty($data['out_trade_no']) || empty($channel)) return false;
        $point_class    = $this->CreatePoinClass($channel);
        if(!is_object($point_class)) return false;
        $confirm_res = $point_class->RefundPoint($data);
        return $confirm_res;
    }

    //用户积分比例
    public function GetPointRate($data,$channel){
        if(!$channel) return false;
        $point_class    = $this->CreatePoinClass($channel);
        if(!is_object($point_class)) return false;
        $confirm_res = $point_class->GetPointRate($data);
        return $confirm_res;
    }



    private function CreatePoinClass($channel){
        if(isset($this->point_class_list[$channel]) && is_object($this->point_class_list[$channel]))
            return $this->point_class_list[$channel];
        $point_config  = Config::GetPointSourceConfig($channel);
        if(!$point_config) return false;
        if(!class_exists($point_config['class'])) return false;
        $point_class   = new $point_config['class']($point_config);
        if($point_class instanceof \App\Api\Logic\Point\Point\Point){
            $this->point_class_list[$channel]   = $point_class;
            return $point_class;
        }else{
            return false;
        }
    }

    /** 获取积分名称
     *
     * @param $data ['channnel' => '渠道名称','member_bn' => '','company_bn' => '']
     * @return bool | string 返回false或者积分名称
     * @author liuming
     */
    public function GetPointName($data){
        if(!isset($data['channel'])) return false;
        $point_class    = $this->CreatePoinClass($data['channel']);
        if(!is_object($point_class)) return false;
        return $point_class->GetPointName($data);
    }

}
