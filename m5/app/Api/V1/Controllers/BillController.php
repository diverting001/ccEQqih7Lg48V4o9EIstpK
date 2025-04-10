<?php
/**
 * Created by PhpStorm.
 * User: chuanbin
 * Date: 2019-05-30
 * Time: 14:13
 */

namespace App\Api\V1\Controllers;


use App\Api\Common\Controllers\BaseController;
use App\Api\Model\Bill\Bill;
use App\Api\V1\Service\Bill\Create;
use App\Api\V1\Service\Bill\Pay;
use Illuminate\Http\Request;
use Neigou\Logger;

class BillController extends BaseController
{
    /**
     * 获取Bill ID
     * @return array
     */
    public function CreateBillId()
    {
        $billMdl = new Bill();
        $bill_id = $billMdl->GetBillId();
        $this->setErrorMsg('成功');
        return $this->outputFormat(['bill_id' => $bill_id]);
    }

    /**
     * 创建单据
     * @return array
     */
    public function Create(Request $request)
    {
        $data = $this->getContentArray($request);
        $res = Create::CreateBill($data, $msg);
        if (!$res) {
            Logger::General('service.bill.create.err', ['request_data' => $data, 'response' => $res]);
            $this->setErrorMsg($msg);
            return $this->outputFormat([], 400);
        } else {
            $this->setErrorMsg('请求成功');
            return $this->outputFormat($data);
        }
    }


    /**
     * 设置单据支付状态
     * @return array
     */
    public function setPayed(Request $request)
    {
        $request = $this->getContentArray($request);
        $bill_id = $request['bill_id'];
        $set = $request['set'];
        $obj = new Pay();
        $res = $obj->doPay($bill_id, $set, $msg);
        if (!$res) {
            Logger::General('service.bill.setPayed.err', ['request_data' => $request, 'response' => $res]);
            $this->setErrorMsg($msg);
            return $this->outputFormat([], 400);
        } else {
            $this->setErrorMsg('请求成功');
            return $this->outputFormat(['bill_id' => $request['bill_id']]);
        }
    }

    /**
     * 设置退款单状态
     * @return array
     */
    public function setRefunded(Request $request)
    {
        $request = $this->getContentArray($request);
        $bill_id = $request['bill_id'];
        $obj = new Pay();
        $res = $obj->setRefunded($bill_id, $request['data'], $msg);
        if (!$res) {
            Logger::General('service.bill.setRefunded.err', ['request_data' => $request, 'response' => $res]);
            $this->setErrorMsg($msg);
            return $this->outputFormat([], 400);
        } else {
            $this->setErrorMsg('请求成功');
            return $this->outputFormat(['bill_id' => $request['bill_id']]);
        }
    }

    /**
     * 查询单据
     * @return array
     */
    public function Get(Request $request)
    {
        $bill_id = $this->getContentArray($request);
        $bill_id = current($bill_id);
        $obj = new \App\Api\V1\Service\Bill\Bill();
        $rzt = $obj->GetInfoByBillId($bill_id);
        if (!$rzt) {
            Logger::General('service.bill.get.err', ['bill_id' => $bill_id, 'response' => $rzt]);
            $this->setErrorMsg('查询失败');
            return $this->outputFormat([], 400);
        } else {
            $this->setErrorMsg('请求成功');
            return $this->outputFormat($rzt);
        }
    }
    /**
     * 查询支付方式
     * @return array
     */
    public function GetAppsByCode(Request $request)
    {
        $code = $this->getContentArray($request);
        $code = current($code);
        $obj = new \App\Api\V1\Service\Bill\App();
        $rzt = $obj->GetListByCode($code);
        if (!$rzt) {
            Logger::General('service.bill.app.get.err', ['code' => $code, 'response' => $rzt]);
            $this->setErrorMsg('查询失败');
            return $this->outputFormat([], 400);
        } else {
            $this->setErrorMsg('请求成功');
            return $this->outputFormat($rzt);
        }
    }
}
