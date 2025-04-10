<?php

namespace App\Api\Logic\Point\Point;

abstract class Point{

    public function __construct(){
    }

    //获取用户积分
    abstract public function GetMemberPoint($member_id,$company_id);

    //用户积分锁定
    abstract public function LockMemberPoint($data);

    //取消用户积分锁定
    abstract public function CancelLockMemberPoint($data);

    //确认用户积分锁定
    abstract public function ConfirmLockMemberPoint($data);

    //获取用户积分锁定
    abstract public function GetLockMemberPoint($data);

    //获取订单流水
    abstract public function GetMemberRecord($member_bn, $company_bn, $page, $rowNum, $data=array());
}
