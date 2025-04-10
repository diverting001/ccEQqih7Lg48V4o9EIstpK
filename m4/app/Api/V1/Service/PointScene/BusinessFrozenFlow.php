<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-01-23
 * Time: 10:32
 */

namespace App\Api\V1\Service\PointScene;

use App\Api\Model\PointScene\BusinessFrozenFlow as BusinessFrozenFlowModel;

class BusinessFrozenFlow
{
    /**
     * 根据业务生成一个业务流水号
     */
    public function Create($businessInfo)
    {
        $businessType = $businessInfo['business_type'];
        $businessBn = $businessInfo['business_bn'];
        $systemCode = $businessInfo['system_code'];
        do {
            $businessFrozenCode = date('YmdHis') . rand(1000, 9999);
            $businessFlowCodeIsset = BusinessFrozenFlowModel::FindByBusinessFrozenCode($businessFrozenCode);
        } while ($businessFlowCodeIsset);

        $createStatus = BusinessFrozenFlowModel::Create(array(
            "business_frozen_code" => $businessFrozenCode,
            "business_type" => $businessType,
            "business_bn" => $businessBn,
            "system_code" => $systemCode,
        ));
        if (!$createStatus) {
            return $this->Response(false, "生成业务锁定单号失败");
        }
        return $this->Response(true, "成功", array("business_frozen_code" => $businessFrozenCode));
    }

    public function BindFrozenPoolCode($businessFrozenCode, $frozenPoolCode)
    {
        $status = BusinessFrozenFlowModel::BindFrozenPoolCode($businessFrozenCode, $frozenPoolCode);
        if (!$status) {
            return $this->Response(false, "业务流水绑定账单号失败");
        }
        return $this->Response(true, "关联成功");
    }

    public function GetBusinessFrozenPoolCode($businessFrozenCode)
    {
        $info = BusinessFrozenFlowModel::GetBusinessFrozenPoolCode($businessFrozenCode);
        if (!$info) {
            return $this->Response(false, "锁定池获取失败");
        }
        return $this->Response(true, "获取成功", $info);
    }

    public function GetBusinessFrozenPoolCodeByBusinessBn($businessType, $businessBn, $systemCode)
    {
        $info = BusinessFrozenFlowModel::GetBusinessFrozenPoolCodeByBusinessBn($businessType, $businessBn, $systemCode);
        if ($info->count() <= 0) {
            return $this->Response(false, "锁定池获取失败");
        }
        return $this->Response(true, "获取成功", $info);
    }

    private function Response($status = true, $msg = '成功', $data = [])
    {
        return [
            'status' => $status,
            'msg' => $msg,
            'data' => $data,
        ];
    }
}
