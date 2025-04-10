<?php

namespace App\Api\V1\Controllers;

use App\Api\Common\Controllers\BaseController;
use App\Api\V1\Service\ServerOrders\ServerOrders as ServerOrdersService;
use Illuminate\Http\Request;

class ServerOrdersController extends BaseController
{

    /*
     * 设置退款状态
     */
    public function Refund($refund_status, Request $request)
    {
        $order_data = $this->getContentArray($request);
        if (empty($order_data)) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }
        if (empty($order_data['order_id'])) {
            $this->setErrorMsg('订单号不能为空');
            return $this->outputFormat([], 400);
        }
        $server_order_service = new ServerOrdersService();
        $res = $server_order_service->Refand([
            'order_id' => $order_data['order_id'],
            'refund_status' => $refund_status
        ]);
        $this->setErrorMsg('请求成功');
        $data = [
            'res' => $res === false ? "failed" :'success' ,
            'db_res' => $res
        ];
        return $this->outputFormat($data);
    }

    /*
     * @todo 获取订单ids
     */
    public function supplierOrderIds(Request $request)
    {
        $pars = $this->getContentArray($request);
        if (empty($pars)) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }
        if (empty($pars['supplier_bn'])) {
            $this->setErrorMsg('参数错误:supplier_bn');
            return $this->outputFormat([], 400);
        }
        if (empty($pars['min_create_time'])) {
            $this->setErrorMsg('参数错误:min_create_time');
            return $this->outputFormat([], 400);
        }
        $server_order_service = new ServerOrdersService();
        $res = $server_order_service->supplierOrderIds($pars);
        $this->setErrorMsg('请求成功');
        $data = [
            'res' => $res['res'] === 1 ? 'success' : 'failed',
            'list' => $res['list']
        ];
        return $this->outputFormat($data);
    }
}
