<?php
/**
 * 发票服务
 * Created by PhpStorm.
 * User: chuanbin
 * Date: 2018/9/26
 * Time: 11:33
 */

namespace App\Api\V1\Controllers;


use App\Api\Common\Controllers\BaseController;
use App\Api\Model\Invoice\Invoice;
use Illuminate\Http\Request;

class InvoiceController extends BaseController
{
    /**
     * 创建发票
     * @return array
     */
    public function Create(Request $request)
    {
        $data = $this->getContentArray($request);
        $invoice_mdl = new Invoice();
        $res = $invoice_mdl->create_invoice($data);
        if ($res['code'] == 0) {
            $this->setErrorMsg('请求成功');
            return $this->outputFormat($res['data']);
        } else {
            $this->setErrorMsg('请求失败');
            return $this->outputFormat(null, 404);
        }
    }

    /**
     * 获取一个source_id的发票详情
     * source order offline
     * source_id 对应ID
     * @return array
     */
    public function GetDetail(Request $request)
    {
        $map = $this->getContentArray($request);
        $invoice_mdl = new Invoice();
        $result = $invoice_mdl->getDetail($map);
        if (!empty($result)) {
            $this->setErrorMsg('请求成功');
            return $this->outputFormat($result);
        } else {
            $this->setErrorMsg('请求失败');
            return $this->outputFormat(null, 404);
        }
    }

    /**
     * 查询列表
     * @return array
     */
    public function getList(Request $request)
    {
        $data = $this->getContentArray($request);
        $obj = new Invoice();
        $result = $obj->getSearchPageList($data['where'], $data['page'], $data['limit']);
        if (!empty($result)) {
            $this->setErrorMsg('请求成功');
            return $this->outputFormat($result);
        } else {
            $this->setErrorMsg('请求失败');
            return $this->outputFormat(null, 404);
        }
    }

    /**
     * 财务审核通过操作
     * @return array
     */
    public function FinanceVerify(Request $request)
    {
        $data = $this->getContentArray($request);
        $obj = new Invoice();
        $map['sn'] = $data['sn'];
        //待审核的时候可以操作
        $map['apply_status'] = 2;
        $map['finance_check_status'] = 2;

        $set['apply_status'] = 1;
        $set['finance_check_status'] = 1;
        $set['finance_remark'] = $data['finance_remark'];
        $rzt = $obj->update($map, $set);//保存数据
        if ($rzt) {
            $obj->add_log($data['sn'], $data['member_id'], '财务 ' . $data['member_name'] . '审核通过');
            $this->setErrorMsg('请求成功');
            return $this->outputFormat($data['sn']);
        } else {
            $this->setErrorMsg('请求失败');
            return $this->outputFormat(null, 404);
        }
    }

    /**
     * 财务审核失败操作
     * @return array
     */
    public function FinanceVerifyFail(Request $request)
    {
        $data = $this->getContentArray($request);
        $obj = new Invoice();
        $map['sn'] = $data['sn'];
        //待审核的时候可以操作
        $map['apply_status'] = 2;
        $map['finance_check_status'] = 2;

        $set['apply_status'] = 0;
        $set['finance_check_status'] = 0;
        $set['finance_remark'] = $data['finance_remark'];
        $rzt = $obj->update($map, $set);//保存数据
        if ($rzt) {
            $obj->add_log($data['sn'], $data['member_id'], '财务 ' . $data['member_name'] . '拒绝');
            $this->setErrorMsg('请求成功');
            return $this->outputFormat($data['sn']);
        } else {
            $this->setErrorMsg('请求失败');
            return $this->outputFormat(null, 404);
        }
    }

    /**
     * 确认发票信息
     * @return array
     */
    public function SaveDelivery(Request $request)
    {
        $data = $this->getContentArray($request);
        $obj = new Invoice();
        $map['sn'] = $data['sn'];
        //待审核的时候可以操作
        $map['apply_status'] = 1;
        $map['finance_check_status'] = 1;
        $set = $data['data'];
        $rzt = $obj->update($map, $set);//保存数据
        if ($rzt) {
            $obj->add_log($data['sn'], $data['member_id'], '客服 ' . $data['member_name'] . ' 确认发票信息');
            $this->setErrorMsg('请求成功');
            return $this->outputFormat($data['sn']);
        } else {
            $this->setErrorMsg('请求失败');
            return $this->outputFormat(null, 404);
        }
    }
}
