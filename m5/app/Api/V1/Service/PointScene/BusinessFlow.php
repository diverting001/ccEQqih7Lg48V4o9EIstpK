<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-01-23
 * Time: 10:32
 */

namespace App\Api\V1\Service\PointScene;

use App\Api\Model\PointScene\BusinessFlow as BusinessFlowModel;

class BusinessFlow
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
            $businessFlowCode = date('YmdHis') . rand(1000, 9999);
            $businessFlowCodeIsset = BusinessFlowModel::FindByFlowCode($businessFlowCode);
        } while ($businessFlowCodeIsset);

        $createStatus = BusinessFlowModel::Create(array(
            "business_flow_code" => $businessFlowCode,
            "business_type" => $businessType,
            "system_code" => $systemCode,
            "business_bn" => $businessBn
        ));

        if ($createStatus) {
            return $this->Response(true, "成功", array("business_flow_code" => $businessFlowCode));
        }

        return $this->Response(false, "业务流水号生成失败");
    }

    public function Delete($businessFlowCode)
    {
        $status = BusinessFlowModel::Delete($businessFlowCode);
        if (!$status) {
            return $this->Response(false, "删除失败");
        }
        return $this->Response(true, "删除成功");
    }

    public function BindBillCode($businessFlowCode, $billCode)
    {
        $status = BusinessFlowModel::BindBillCode($businessFlowCode, $billCode);
        if (!$status) {
            return $this->Response(false, "业务流水绑定账单号失败");
        }
        return $this->Response(true, "关联成功");
    }

    public function GetBillCodeByBusinessFlow($businessFlowCode)
    {
        $relList = BusinessFlowModel::GetBillCodeByBusinessFlow($businessFlowCode);
        if (!$relList || !$relList->count()) {
            return $this->Response(false, "关联单号不存在");
        }
        return $this->Response(true, "获取成功", $relList);
    }

    public function GetBillCodeByBusiness($sourceBusinessType, $sourceBusinessBn, $systemCode)
    {
        $relList = BusinessFlowModel::GetBillCodeByBusiness($sourceBusinessType, $sourceBusinessBn, $systemCode);
        if (!$relList || !$relList->count()) {
            return $this->Response(false, "关联单号不存在");
        }
        return $this->Response(true, "获取成功", $relList);
    }

    public function GetBussineByBillCodes($billCodeList)
    {
        $relList = BusinessFlowModel::GetBussineByBillCodes($billCodeList);
        if (!$relList || !$relList->count()) {
            return $this->Response(false, "获取失败");
        }
        $returnData = array();
        foreach ($relList as $relInfo) {
            $returnData[$relInfo->bill_code] = $relInfo;
        }
        return $this->Response(true, "获取成功", $returnData);
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
