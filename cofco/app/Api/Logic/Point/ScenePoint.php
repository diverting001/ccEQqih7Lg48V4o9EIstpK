<?php

namespace App\Api\Logic\Point;

class ScenePoint
{

    private $point_class_list = array();

    //获取用户积分
    public function GetMemberPoint($data, $channel)
    {
        if (empty($data['member_bn']) || empty($channel)) return false;
        $point_class = $this->CreatePoinClass($channel);
        if (!is_object($point_class)) return false;
        $point_info = $point_class->GetMemberPoint($data);
        return $point_info;
    }

    public function GetMemberPointByOverdueTime($data, $channel)
    {
        if (empty($data['member_bn']) || empty($channel)) return false;
        $point_class = $this->CreatePoinClass($channel);
        if (!is_object($point_class)) return false;
        $point_info = $point_class->GetMemberPointByOverdueTime($data);
        return $point_info;
    }

    //用户积分锁定
    public function LockMemberPoint($data, $channel)
    {
        if (empty($data['member_bn']) || empty($data['point']) || empty($channel)) {
            return false;
        }
        $point_class = $this->CreatePoinClass($channel);
        if (!is_object($point_class)) return false;
        $lock_res = $point_class->LockMemberPoint($data);
        return $lock_res;
    }

    //取消用户积分锁定
    public function CancelLockMemberPoint($data, $channel)
    {
        if (empty($data['member_bn']) || empty($data['out_trade_no']) || empty($channel)) return false;
        $point_class = $this->CreatePoinClass($channel);
        if (!is_object($point_class)) return false;
        $cancel_res = $point_class->CancelLockMemberPoint($data);
        return $cancel_res;
    }

    //确认用户积分锁定
    public function ConfirmLockMemberPoint($data, $channel)
    {
        if (empty($data['member_bn']) || empty($data['out_trade_no']) || empty($channel)) return false;
        $point_class = $this->CreatePoinClass($channel);
        if (!is_object($point_class)) return false;
        $confirm_res = $point_class->ConfirmLockMemberPoint($data);
        return $confirm_res;
    }

    //获取用户积分流水
    public function GetMemberRecord($data, $channel)
    {
        if (empty($data['member_bn']) || empty($channel)) return false;
        $point_class = $this->CreatePoinClass($channel);
        if (!is_object($point_class)) return false;
        $point_info = $point_class->GetMemberRecord($data);
        return $point_info;
    }

    //用户积分返还
    public function RefundMemberPoint($data, $channel)
    {
        if (empty($data['member_id']) || empty($data['order_id']) || empty($channel)) return false;
        $point_class = $this->CreatePoinClass($channel);
        if (!is_object($point_class)) return false;
        $confirm_res = $point_class->RefundPoint($data);
        return $confirm_res;
    }

    private function CreatePoinClass($channel)
    {
        if (isset($this->point_class_list[$channel]) && is_object($this->point_class_list[$channel]))
            return $this->point_class_list[$channel];
        $point_config = SceneConfig::GetPointSourceConfig($channel);
        if (!$point_config) return false;
        if (!class_exists($point_config['class'])) return false;
        $point_class = new $point_config['class']($point_config);
        if ($point_class instanceof \App\Api\Logic\Point\ScenePoint\ScenePoint) {
            $this->point_class_list[$channel] = $point_class;
            return $point_class;
        } else {
            return false;
        }
    }


    public function GetCompanyPoint($data, $channel)
    {
        if (empty($data['company_bn']) || empty($channel)) return false;
        $point_class = $this->CreatePoinClass($channel);
        if (!is_object($point_class)) return false;
        $point_info = $point_class->GetCompanyPoint(array(
            'company_bn' => $data['company_bn']
        ));
        return $point_info;
    }

    public function GetCompanyRecordList($data, $channel)
    {
        if (empty($data['company_ids']) || empty($channel)) return false;
        $point_class = $this->CreatePoinClass($channel);
        if (!is_object($point_class)) return false;
        $point_info = $point_class->GetCompanyRecordList($data);
        return $point_info;
    }

    public function LockCompanyPoint($data, $channel)
    {
        $point_class = $this->CreatePoinClass($channel);
        if (!is_object($point_class)) return false;
        $point_info = $point_class->LockCompanyPoint($data);
        return $point_info;
    }

    public function UnLockCompanyPointByAssign($data, $channel)
    {
        $point_class = $this->CreatePoinClass($channel);
        if (!is_object($point_class)) return false;
        $point_info = $point_class->UnLockCompanyPointByAssign($data);
        return $point_info;
    }


    public function CompanyAssignToMembers($data, $channel)
    {
        $point_class = $this->CreatePoinClass($channel);
        if (!is_object($point_class)) return false;
        $point_info = $point_class->CompanyAssignToMembers($data);
        return $point_info;
    }

}
