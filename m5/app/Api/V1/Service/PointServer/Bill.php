<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-01-23
 * Time: 10:32
 */

namespace App\Api\V1\Service\PointServer;

use App\Api\Model\PointServer\Bill as BillModel;

class Bill
{
    /**
     * 根据业务生成一个业务流水号
     */
    public function Create($billInfo)
    {
        do {
            $billCode = date('YmdHis') . rand(1000, 9999);
            $billCodeIsset = BillModel::FindByBillCode($billCode);
        } while ($billCodeIsset);

        $createStatus = BillModel::Create(array(
            "bill_code" => $billCode,
            "bill_type" => $billInfo['bill_type'],
        ));

        if ($createStatus) {
            return $this->Response(true, "成功", array("bill_code" => $billCode));
        }

        return $this->Response(false, "账单生成失败");
    }

    public function QueryByTransfer($billList)
    {
        $billList = BillModel::QueryByTransfer($billList);
        if ($billList->count() <= 0) {
            return $this->Response(false, "账单查询失败");
        }
        return $this->Response(true, "成功", $billList);
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
