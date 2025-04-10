<?php

namespace App\Api\V1\Controllers\Member;

use App\Api\Common\Controllers\BaseController;
use App\Api\Model\Member\Invoice as Invoice;
use Illuminate\Http\Request;

class InvoiceController extends BaseController
{

    public function __construct()
    {
        $this->invoice_model = new Invoice();
    }

    /*
     * 获取用户发票信息
     */
    public function Get(Request $request)
    {
        $param = $this->getContentArray($request);
        if (empty($param['member_id'])) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat($param, 10001);
        }

        $data = $this->invoice_model->getRow($param['member_id']);
        $this->setErrorMsg('success');
        return $this->outputFormat(empty($data) ? array() : $data, 0);
    }

    public function Save(Request $request)
    {
        $param = $this->getContentArray($request);
        if (empty($param['member_id'])) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat($param, 10001);
        }

        $res = $this->invoice_model->save($param);
        if ($res == false) {
            $this->setErrorMsg('提交失败');
            return $this->outputFormat($param, 10002);
        }
        $this->setErrorMsg('success');
        return $this->outputFormat(empty($data) ? array() : $data, 0);
    }
}
