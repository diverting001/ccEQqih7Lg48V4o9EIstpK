<?php

namespace App\Api\Logic\Point\ScenePoint;

abstract class ScenePoint
{

    public function __construct()
    {
    }

    /**
     * 获取用户积分
     */
    abstract public function GetMemberPoint($data);

    /**
     * 用户积分锁定
     */
    abstract public function LockMemberPoint($data);

    /**
     * 用户积分取消锁定
     */
    abstract public function CancelLockMemberPoint($data);

    /**
     * 用户积分确认锁定并消费
     */
    abstract public function ConfirmLockMemberPoint($data);

    /**
     * 用户积分退还
     */
    abstract public function RefundPoint($data);

    /**
     * 用户积分流水
     */
    abstract public function GetMemberRecord($data);

}
