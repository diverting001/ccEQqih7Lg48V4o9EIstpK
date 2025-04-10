<?php
/**
 * Created by PhpStorm.
 * User: Liuming
 * Date: 2020/6/17
 * Time: 11:41 AM
 */

namespace App\Api\V1\Controllers;

use App\Api\Common\Controllers\BaseController;
use App\Api\V1\Service\Refund\Refund;
use Illuminate\Http\Request;


class RefundController extends BaseController
{
    /** 获取售后单列表
     *  请求参数 : company_id,status,source,process_status,update_time 参数具体意思下方有标注
     *  返回结果 : "list": {
                        "202006151745204689": {
                                "refund_id": "202006151745204689",
                                "member_id": 577834,
                                "company_id": 18643,
                                "order_id": "202006151703394807",
                                "son_order_id": "202006151703394807",
                                "source": "5",
                                "source_id": "2020061517451593",
                                "money": "5.000",
                                "compensate": "0.000",
                                "status": 1,
                                "process_status": 2,
                                "op_member_id": 250,
                                "op_member_name": "shenzichen",
                                "mome": "测试",
                                "examine_mome": "",
                                "create_time": 1592214320,
                                "update_time": 1592214421,
                                "items": [ // 此处返回退款详情,是mis_order_refund_bill_object_assets 和 mis_order_refund_bill_object_number 表集合
                                {
                                "refund_type": 0,
                                "product_bn": "SHOP-5EE73815D0D5A-0",
                                "source": "价格保护",
                                "refund_num": 0,
                                "refund_money": 0,
                                "refund_point": 0,
                                "refund_voucher": 0,
                                "price_protection_num": 5,
                                "price_protection_money": 0,
                                "price_protection_point": "5.000"
                                }]
                        }
     * @param Request $request
     * @return array
     * @author liuming
     */
    public function GetList(Request $request)
    {
        $where_data = $this->getContentArray($request);
        if (empty($where_data)) {
            $this->setErrorMsg('请选择筛选条件');
            return $this->outputFormat([], 400);
        }

        // -------- 设置where查询条件 begin -------- //
        $where = [];
        // 公司
        if (!empty($where_data['company_id'])) {
            $where['company_id'] = ($where_data['company_id']);
        }

        // 单据状态:0待审核，1已确认，2无效',
        if (!empty($where_data['status']) && in_array($where_data['status'], array(0,1, 2, 3))) {
            $where['status'] = intval($where_data['status']);
        }

        // 来源 1 :EC售后申请 ;2:其他wms_code售后申请 3 :整单取消;4 :OTO订单售后 5:价格保护'
        if (!empty($where_data['source'])) {
            $where['source'] = intval($where_data['source']);
        }

        // 流程状态:0待处理，1处理中，2已完成，3处理失败',
        if (!empty($where_data['process_status']) && in_array($where_data['process_status'], array(1, 2, 3))) {
            $where['process_status'] = intval($where_data['process_status']);
        }

        // 创建时间
        if (!empty($where_data['create_time'])) {
            $where['create_time'] = ($where_data['create_time']);
        }

        // 更新时间
        if (!empty($where_data['update_time'])) {
            $where['update_time'] = ($where_data['update_time']);
        }

        //limit限制
        $page_size = empty($where_data['page_size']) ? 20 : intval($where_data['page_size']);
        $page_index = $where_data['page_index'] < 1 ? 1 : intval($where_data['page_index']);

        $request_data = [
            'filter' => $where,
            'page_index' => $page_index,
            'page_size' => $page_size,
        ];

        $refundService = new Refund();
        $refundList = $refundService->getList($request_data);
        $refundTotal = $refundService->getTotal($request_data['filter']);
        if (empty($refundTotal)) {
            $this->setErrorMsg('未获取到售后单');
            return $this->outputFormat([], 400);
        } else {
            $this->setErrorMsg('获取成功');
            return $this->outputFormat(['list' => $refundList, 'total' => $refundTotal]);
        }

        // -------- 设置where查询条件 end -------- //


    }
}