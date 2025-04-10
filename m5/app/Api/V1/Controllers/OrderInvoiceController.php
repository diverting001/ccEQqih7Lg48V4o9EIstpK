<?php

namespace App\Api\V1\Controllers;

use App\Api\Common\Controllers\BaseController;
use App\Api\V1\Service\Order\Invoice as OrderInvoice;
use Illuminate\Http\Request;

class OrderInvoiceController extends BaseController
{
    /**
     * 订单发票申请
     */
    public function apply(Request $request)
    {
        $data = $this->getContentArray($request);

        if (empty($data['order_id'])) {
            $this->setErrorMsg('订单号错误');

            return $this->outputFormat([], 400);
        }

        // 开票信息
        $invoiceData = $data['invoice_service_info'];

        if (empty($invoiceData) OR empty($invoiceData['member_invoice_data']) OR empty($invoiceData['product_invoice_data'])) {
            $this->setErrorMsg('开票信息错误');
            \Neigou\Logger::General('service_order_invoice_apply_failed', array('errMsg' => '开票信息错误', 'data' => $data));
            return $this->outputFormat([], 402);
        }

        $orderInvoiceService = new OrderInvoice();

        // 发票申请
        $errMsg = '';
        $invoiceApply = $orderInvoiceService->apply($data['order_id'], $invoiceData, null, $errMsg);

        if (empty($invoiceApply)) {
            $errMsg OR $errMsg = '发票申请失败';
            \Neigou\Logger::General('service_order_invoice_apply_failed', array('errMsg' => $errMsg, 'data' => $data));
            $this->setErrorMsg($errMsg);

            return $this->outputFormat([], 403);
        }

        return $this->outputFormat($invoiceApply);
    }

    // --------------------------------------------------------------------

    /**
     * 订单发票换开
     *
     * @return  string
     */
    public function change(Request $request)
    {
        $data = $this->getContentArray($request);

        if (empty($data['order_id'])) {
            $this->setErrorMsg('订单号错误');

            return $this->outputFormat([], 400);
        }

        // 开票信息
        $invoiceData = $data['invoice_service_info'];

        if (empty($invoiceData) OR empty($invoiceData['member_invoice_data']) OR empty($invoiceData['product_invoice_data'])) {
            \Neigou\Logger::General('service_order_invoice_change_failed',
                array('errMsg' => '开票信息错误', 'data' => $data));
            $this->setErrorMsg('开票信息错误');
            return $this->outputFormat([], 402);
        }

        $orderInvoiceService = new OrderInvoice();

        // 发票申请
        $errMsg = '';
        $invoiceApply = $orderInvoiceService->change($data['order_id'], $invoiceData, null, $errMsg);

        if (empty($invoiceApply)) {
            $errMsg OR $errMsg = '发票换开失败';
            $this->setErrorMsg($errMsg);

            return $this->outputFormat([], 403);
        }

        return $this->outputFormat($invoiceApply);
    }

    // --------------------------------------------------------------------

    /**
     * 订单发票作废
     *
     * @return  string
     */
    public function cancel(Request $request)
    {
        $data = $this->getContentArray($request);

        if (empty($data['order_id'])) {
            $this->setErrorMsg('订单号错误');

            return $this->outputFormat([], 400);
        }

        $orderInvoiceService = new OrderInvoice();

        // 发票作废申请
        $errMsg = '';
        $invoiceApply = $orderInvoiceService->cancel($data['order_id'], $errMsg);

        if (empty($invoiceApply)) {
            $errMsg OR $errMsg = '发票申请失败';
            \Neigou\Logger::General('service_order_invoice_cancel_failed', array('errMsg' => $errMsg, 'data' => $data));
            $this->setErrorMsg($errMsg);

            return $this->outputFormat([], 403);
        }

        return $this->outputFormat($invoiceApply);
    }

    // --------------------------------------------------------------------

    /**
     * 订单发票撤销
     *
     * @return  string
     */
    public function revoke(Request $request)
    {
        $data = $this->getContentArray($request);

        if (empty($data['order_id'])) {
            $this->setErrorMsg('订单号错误');

            return $this->outputFormat([], 400);
        }

        $orderInvoiceService = new OrderInvoice();

        // 发票撤销申请
        $errMsg = '';
        $invoiceApply = $orderInvoiceService->revoke($data['order_id'], $errMsg);

        if (empty($invoiceApply)) {
            $errMsg OR $errMsg = '发票撤回失败';
            \Neigou\Logger::General('service_order_invoice_revoke_failed', array('errMsg' => $errMsg, 'data' => $data));
            $this->setErrorMsg($errMsg);

            return $this->outputFormat([], 403);
        }

        return $this->outputFormat($invoiceApply);
    }

    // --------------------------------------------------------------------

    /**
     * 获取订单发票 详情
     *
     * @return  string
     */
    public function getOrderInvoiceDetail(Request $request)
    {
        $data = $this->getContentArray($request);

        $orderInvoiceService = new OrderInvoice();

        // 发票申请
        $invoiceApply = $orderInvoiceService->getOrderInvoice($data['order_id']);
        return $this->outputFormat($invoiceApply);
    }

    // --------------------------------------------------------------------

    /**
     * 获取订单发票记录
     *
     * @return  string
     */
    public function getOrderInvoiceRecord(Request $request)
    {
        $data = $this->getContentArray($request);

        $orderInvoiceService = new OrderInvoice();

        // 发票申请记录
        $invoiceRecord = $orderInvoiceService->getOrderInvoiceRecord($data['order_id']);
        return $this->outputFormat($invoiceRecord);
    }

    // --------------------------------------------------------------------

    /**
     * 订单发票预申请
     *
     */
    public function preApplyBatch(Request $request)
    {
        $data = $this->getContentArray($request);

        if (empty($data['order_list'])) {

            $this->setErrorMsg('');
            return $this->outputFormat([], 400);
        }

        // 开票信息
        $invoiceData = $data['invoice_service_info'];

        if (empty($invoiceData) OR empty($invoiceData['invoice_info']) OR empty($invoiceData['invoice_data'])) {
            $this->setErrorMsg('开票信息错误');
            \Neigou\Logger::General('service_order_invoice_pre_apply_failed', array('errMsg' => '开票信息错误', 'data' => $data));
            return $this->outputFormat([], 402);
        }

        $orderInvoiceService = new OrderInvoice();
        // 发票申请
        $errMsg = '';
        $invoiceApply = $orderInvoiceService->preApplyBatch($data['order_list'], $invoiceData, $errMsg);

        if (empty($invoiceApply)) {
            $errMsg OR $errMsg = '发票申请失败';
            \Neigou\Logger::General('service_order_invoice_pre_apply_failed', array('errMsg' => $errMsg, 'data' => $data));
            $this->setErrorMsg($errMsg);

            return $this->outputFormat([], 403);
        }

        return $this->outputFormat($invoiceApply);
    }

    // --------------------------------------------------------------------

    /**
     * 发票确认
     *
     */
    public function confirm(Request $request)
    {
        $data = $this->getContentArray($request);

        if (empty($data['order_id'])) {

            $this->setErrorMsg('');
            return $this->outputFormat([], 400);
        }

        $orderId = array_filter(explode(',', $data['order_id']));

        $orderInvoiceService = new OrderInvoice();

        // 发票申请
        $errMsg = '';
        if ( ! $orderInvoiceService->confirm($orderId, $errMsg))
        {
            $errMsg OR $errMsg = '发票确认失败';
            \Neigou\Logger::General('service_order_invoice_confirm_failed', array('errMsg' => $errMsg, 'data' => $data));
            $this->setErrorMsg($errMsg);

            return $this->outputFormat([], 403);
        }

        return $this->outputFormat(array());
    }

}

