<?php

namespace App\Api\V1\Service\Order;

use App\Api\Model\Order\Order as OrderModel;
use App\Api\Model\Order\OrderInvoice as OrderInvoiceModel;
use App\Api\Logic\Service as Service;

/*
 * @todo 订单列表
 */

class Invoice
{
    /*
     * @todo 获取订单列表
     */
    public function apply($orderId, $invoiceData = null, $payments = null, & $errMsg = '')
    {
        // 获取订单信息
        $orderInfo = OrderModel::GetOrderInfoById($orderId);

        if (empty($orderInfo)) {
            $errMsg = '未查询到订单信息';
            return false;
        }

        $orderInfo = get_object_vars($orderInfo);
        if ($invoiceData === null) {
            $orderExtendData = $orderInfo['extend_data'] ? json_decode($orderInfo['extend_data'], true) : array();
            $invoiceData = $orderExtendData['invoice_service_info'];
        }

        // 检查开票信息
        if (empty($invoiceData) OR empty($invoiceData['member_invoice_data']) OR empty($invoiceData['product_invoice_data'])) {
            $errMsg = '无开票信息';
            return false;
        }

        // 获取订单的商品信息
        $orderItems = OrderModel::GetOrderItemsByOrderId($orderInfo['order_id']);

        if (empty($orderItems)) {
            $errMsg = '订单商品信息不存在';
            return false;
        }

        // 获取订单发票申请记录
        $orderInvoiceModel = new OrderInvoiceModel();
        $invoiceRecord = $orderInvoiceModel->getOrderInvoiceRecord($orderInfo['order_id']);

        if (!empty($invoiceRecord)) {
            $applyIds = array();
            foreach ($invoiceRecord as $record) {
                $applyIds[] = $record['apply_id'];
            }

            // 获取发票申请记录
            $invoiceApply = $this->_getInvoiceServiceApply($applyIds);

            if (empty($invoiceApply)) {
                $errMsg = '未获取到发票申请记录';
                return false;
            }

            foreach ($invoiceApply as $applyInfo) {
                // 申请状态(1:待处理 2:进行中 3:完成 4:废弃)
                if (in_array($applyInfo['apply_type'], array(1, 2)) && $applyInfo['apply_status'] != 4) {
                    $errMsg = '订单已申请发票';
                    return false;
                }
            }
        }

        if ($payments === null) {
            $payments['cash'] = $orderInfo['payment'];
            $payments['point'] = $orderInfo['point_channel'];
        }

        // 获取开票平台
        $platforms = $this->_getInvoicePlatform($payments);

        if (empty($platforms)) {
            $errMsg = '获取开票平台失败';
            return false;
        }

        $items = array();
        foreach ($orderItems as $item) {
            $items[$item->bn] = get_object_vars($item);
        }

        $totalAmount = 0;
        $invoiceApply = array();

        foreach ($platforms as $platform) {
            $platformItem = array();
            $platformAmount = 0;
            foreach ($platform['payments'] as $platformPayment) {
                if (!in_array($invoiceData['member_invoice_data']['type'], $platform['platform_data']['type'])) {
                    continue;
                }
                // 积分支付
                if ($platformPayment['pay_type'] == 'POINT') {
                    foreach ($invoiceData['product_invoice_data'] as $productInvoiceData) {
                        if (!isset($items[$productInvoiceData['product_bn']])) {
                            continue;
                        }

                        $item = $items[$productInvoiceData['product_bn']];

                        if ($item['point_amount'] <= 0) {
                            continue;
                        }

                        $pointAmount = round(($item['point_amount'] / $item['nums']) * $productInvoiceData['nums'], 2);
                        if (!isset($platformItem[$item['bn']])) {
                            $platformItem[$item['bn']] = array(
                                'bn' => $item['bn'],
                                'name' => $item['name'],
                                'tax_bn' => $productInvoiceData['goods_invoice_info']['invoice_tax_code'],
                                'spec' => $productInvoiceData['spec_info'] ? $productInvoiceData['spec_info'] : '',
                                'unit' => $productInvoiceData['unit'] ? $productInvoiceData['unit'] : '',
                                'price' => round($pointAmount / $productInvoiceData['nums'], 2),
                                'quantity' => $productInvoiceData['nums'],
                                'amount' => $pointAmount,
                                'pmt_amount' => 0,
                                'tax_rate' => $productInvoiceData['goods_invoice_info']['invoice_tax'],
                            );
                        } else {
                            $sumAmount = round((($item['amount'] - $item['pmt_amount']) / $item['nums']) * $productInvoiceData['nums'],
                                2);

                            $platformItem[$item['bn']]['price'] = round($sumAmount / $productInvoiceData['nums'], 2);
                            $platformItem[$item['bn']]['amount'] = $sumAmount;
                        }

                        $platformAmount += $pointAmount;
                    }
                } elseif ($platformPayment['pay_type'] == 'CASH') {
                    foreach ($invoiceData['product_invoice_data'] as $productInvoiceData) {
                        if (!isset($items[$productInvoiceData['product_bn']])) {
                            continue;
                        }

                        $item = $items[$productInvoiceData['product_bn']];

                        if ($item['amount'] - $item['pmt_amount'] - $item['point_amount'] <= 0) {
                            continue;
                        }

                        $amount = round((($item['amount'] - $item['pmt_amount'] - $item['point_amount']) / $item['nums']) * $productInvoiceData['nums'],
                            2);
                        if (!isset($platformItem[$item['bn']])) {
                            $platformItem[$item['bn']] = array(
                                'bn' => $item['bn'],
                                'name' => $item['name'],
                                'tax_bn' => $productInvoiceData['goods_invoice_info']['invoice_tax_code'],
                                'spec' => $productInvoiceData['spec_info'] ? $productInvoiceData['spec_info'] : '',
                                'unit' => $productInvoiceData['unit'] ? $productInvoiceData['unit'] : '',
                                'price' => round($amount / $productInvoiceData['nums'], 2),
                                'quantity' => $productInvoiceData['nums'],
                                'amount' => $amount,
                                'pmt_amount' => 0,
                                'tax_rate' => $productInvoiceData['goods_invoice_info']['invoice_tax'],
                            );
                        } else {
                            $sumAmount = round((($item['amount'] - $item['pmt_amount']) / $item['nums']) * $productInvoiceData['nums'],
                                2);
                            $platformItem[$item['bn']]['price'] = round($sumAmount / $productInvoiceData['nums'], 2);
                            $platformItem[$item['bn']]['amount'] = $sumAmount;
                        }

                        $platformAmount += $amount;
                    }
                }
            }

            $totalAmount += $platformAmount;

            if (empty($platformItem)) {
                continue;
            }

            // 发票申请信息
            $invoiceApplyInfo = array(
                'type' => 'ORDER',
                // 类型
                'perform' => $platform['platform_data']['platform_code'],
                // 发票平台
                'bn' => $orderId,
                // 订单号
                'invoice_type' => $invoiceData['member_invoice_data']['title'],
                // 发票类型(INDIVIDUAL:个人 COMPANY:公司)
                'member_id' => $orderInfo['member_id'],
                // 用户ID
                'company_id' => $orderInfo['company_id'],
                // 公司ID
                'invoice_name' => $invoiceData['member_invoice_data']['company_name'] ? $invoiceData['member_invoice_data']['company_name'] : '',
                // 开票单位名称
                'invoice_tax_id' => $invoiceData['member_invoice_data']['tax_number'] ? $invoiceData['member_invoice_data']['tax_number'] : '',
                // 税号
                'invoice_addr' => $invoiceData['member_invoice_data']['company_addr'] ? $invoiceData['member_invoice_data']['company_addr'] : '',
                //公司地址
                'ship_type' => $invoiceData['member_invoice_data']['type'],
                // 发票种类(ORDINARY:普通 SPECIAL:专用 ELECTRONIC:电子）
                'ship_email' => $invoiceData['member_invoice_data']['email'] ? $invoiceData['member_invoice_data']['email'] : '',
                // 邮箱
                'ship_name' => $invoiceData['member_invoice_data']['ship_name'] ? $invoiceData['member_invoice_data']['ship_name'] : $orderInfo['ship_name'],
                // 收货人名称
                'ship_tel' => $invoiceData['member_invoice_data']['ship_tel'] ? $invoiceData['member_invoice_data']['ship_tel'] : $orderInfo['ship_mobile'],
                // 收货人电话
                'ship_addr' => $invoiceData['member_invoice_data']['ship_addr'] ? $invoiceData['member_invoice_data']['ship_addr'] : $orderInfo['ship_addr'],
                // 收货人地址
                'remark' => $invoiceData['member_invoice_data']['remark'] ? $invoiceData['member_invoice_data']['remark'] : ''
                // 备注
            );

            // 流水号
            $invoiceApplyInfo['sn'] = $this->_createSerialNumber();
            $invoiceApplyInfo['invoice_price'] = $platformAmount;
            $invoiceApplyInfo['items'] = $platformItem;
            $invoiceApply[] = $invoiceApplyInfo;
        }

        if (empty($invoiceApply)) {
            $errMsg = '未找到开票的信息';
            return false;
        }

        if ($totalAmount - $orderInfo['final_amount'] > 0.01) {
            \Neigou\Logger::General('service_order_apply_invoice_failed',
                array('errMsg' => '发票金额超出订单总金额', 'data' => $invoiceApply));
            $errMsg = '发票金额超出订单总金额';
            return false;
        }

        $status = true;
        foreach ($invoiceApply as $info) {
            $result = $this->_invoiceServiceApply($info, $errMsg);

            $invoiceStatus = $result['apply_id'] > 0 ? 1 : 0;

            // 添加发票记录
            if (!$orderInvoiceModel->addInvoiceRecord($orderInfo['order_id'], $info['perform'], $info['sn'],
                $info['pay_type'], 1, $result['apply_id'], $info, $invoiceStatus)) {
                $status = false;
            }
        }

        if ($status == false) {
            $errMsg OR $errMsg = '添加发票记录失败';
            return false;
        }

        return true;
    }

    /*
     * @todo 获取订单列表
     */
    public function change($orderId, $invoiceData, $payments = null, & $errMsg = '')
    {
        // 获取订单信息
        $orderInfo = OrderModel::GetOrderInfoById($orderId);
        if (empty($orderInfo)) {
            $errMsg = '未查询到订单信息';
            return false;
        }

        $orderInfo = get_object_vars($orderInfo);

        // 检查开票信息
        if (empty($invoiceData) OR empty($invoiceData['member_invoice_data']) OR empty($invoiceData['product_invoice_data'])) {
            $errMsg = '换开开票信息错误';
            return false;
        }

        // 获取订单的商品信息
        $orderItems = OrderModel::GetOrderItemsByOrderId($orderInfo['order_id']);

        if (empty($orderItems)) {
            $errMsg = '订单商品信息不存在';
            return false;
        }

        // 获取订单发票申请记录
        $orderInvoiceModel = new OrderInvoiceModel();
        $invoiceRecord = $orderInvoiceModel->getOrderInvoiceRecord($orderInfo['order_id']);

        if (empty($invoiceRecord)) {
            $errMsg = '未获取到发票申请记录';
            return false;
        }

        $applyIds = array();
        $orderInvoiceRecord = array();
        foreach ($invoiceRecord as $record) {
            $applyIds[] = $record['apply_id'];
            $orderInvoiceRecord[$record['platform']] = $record;
        }

        // 获取发票申请记录
        $invoiceApply = $this->_getInvoiceServiceApply($applyIds);

        if (empty($invoiceApply)) {
            $errMsg = '未获取到发票申请记录';
            return false;
        }

        foreach ($invoiceApply as $applyInfo) {
            // 申请状态(1:待处理 2:进行中 3:完成 4:废弃)
            if ($applyInfo['apply_status'] != 3) {
                $errMsg = '申请未完成，无法换开发票';
                return false;
            }
        }

        if ($payments === null) {
            $payments['cash'] = $orderInfo['payment'];
            $payments['point'] = $orderInfo['point_channel'];
        }

        // 获取开票平台
        $platforms = $this->_getInvoicePlatform($payments);

        if (empty($platforms)) {
            $errMsg = '获取开票平台失败';
            return false;
        }

        $items = array();
        foreach ($orderItems as $item) {
            $items[$item->bn] = get_object_vars($item);
        }

        $totalAmount = 0;
        $invoiceApply = array();
        foreach ($platforms as $platform) {
            $platformItem = array();
            $platformAmount = 0;
            foreach ($platform['payments'] as $platformPayment) {
                if (!in_array($invoiceData['member_invoice_data']['type'], $platform['platform_data']['type'])) {
                    continue;
                }
                // 积分支付
                if ($platformPayment['pay_type'] == 'POINT') {
                    foreach ($invoiceData['product_invoice_data'] as $productInvoiceData) {
                        if (!isset($items[$productInvoiceData['product_bn']])) {
                            continue;
                        }

                        $item = $items[$productInvoiceData['product_bn']];

                        if ($item['point_amount'] <= 0) {
                            continue;
                        }

                        $pointAmount = round(($item['point_amount'] / $item['nums']) * $productInvoiceData['nums'], 2);
                        if (!isset($platformItem[$item['bn']])) {
                            $platformItem[$item['bn']] = array(
                                'bn' => $item['bn'],
                                'name' => $item['name'],
                                'tax_bn' => $productInvoiceData['goods_invoice_info']['invoice_tax_code'],
                                'spec' => $productInvoiceData['spec_info'] ? $productInvoiceData['spec_info'] : '',
                                'unit' => $productInvoiceData['unit'] ? $productInvoiceData['unit'] : '',
                                'price' => round($pointAmount / $productInvoiceData['nums'], 2),
                                'quantity' => $productInvoiceData['nums'],
                                'amount' => $pointAmount,
                                'pmt_amount' => 0,
                                'tax_rate' => $productInvoiceData['goods_invoice_info']['invoice_tax'],
                            );
                        } else {
                            $sumAmount = round((($item['amount'] - $item['pmt_amount']) / $item['nums']) * $productInvoiceData['nums'],
                                2);
                            $platformItem[$item['bn']]['price'] = round($sumAmount / $productInvoiceData['nums'], 2);
                            $platformItem[$item['bn']]['amount'] = $sumAmount;
                        }

                        $platformAmount += $pointAmount;
                    }
                } elseif ($platformPayment['pay_type'] == 'CASH') {
                    foreach ($invoiceData['product_invoice_data'] as $productInvoiceData) {
                        if (!isset($items[$productInvoiceData['product_bn']])) {
                            continue;
                        }

                        $item = $items[$productInvoiceData['product_bn']];

                        if ($item['amount'] - $item['pmt_amount'] - $item['point_amount'] <= 0) {
                            continue;
                        }

                        $amount = round((($item['amount'] - $item['pmt_amount'] - $item['point_amount']) / $item['nums']) * $productInvoiceData['nums'],
                            2);
                        if (!isset($platformItem[$item['bn']])) {
                            $platformItem[$item['bn']] = array(
                                'bn' => $item['bn'],
                                'name' => $item['name'],
                                'tax_bn' => $productInvoiceData['goods_invoice_info']['invoice_tax_code'],
                                'spec' => $productInvoiceData['spec_info'] ? $productInvoiceData['spec_info'] : '',
                                'unit' => $productInvoiceData['unit'] ? $productInvoiceData['unit'] : '',
                                'price' => round($amount / $productInvoiceData['nums'], 2),
                                'quantity' => $productInvoiceData['nums'],
                                'amount' => $amount,
                                'pmt_amount' => 0,
                                'tax_rate' => $productInvoiceData['goods_invoice_info']['invoice_tax'],
                            );
                        } else {
                            $sumAmount = round((($item['amount'] - $item['pmt_amount']) / $item['nums']) * $productInvoiceData['nums'],
                                2);
                            $platformItem[$item['bn']]['price'] = round($sumAmount / $productInvoiceData['nums'], 2);
                            $platformItem[$item['bn']]['amount'] = $sumAmount;
                        }

                        $platformAmount += $amount;
                    }
                }
            }

            $totalAmount += $platformAmount;

            if (empty($platformItem)) {
                continue;
            }

            // 发票申请信息
            $invoiceApplyInfo = array(
                'type' => 'ORDER',
                // 类型
                'perform' => $platform['platform_data']['platform_code'],
                // 发票平台
                'bn' => $orderId,
                // 订单号
                'invoice_type' => $invoiceData['member_invoice_data']['title'],
                // 发票类型(INDIVIDUAL:个人 COMPANY:公司)
                'member_id' => $orderInfo['member_id'],
                // 用户ID
                'company_id' => $orderInfo['company_id'],
                // 公司ID
                'invoice_name' => $invoiceData['member_invoice_data']['company_name'] ? $invoiceData['member_invoice_data']['company_name'] : '',
                // 开票单位名称
                'invoice_tax_id' => $invoiceData['member_invoice_data']['tax_number'] ? $invoiceData['member_invoice_data']['tax_number'] : '',
                // 税号
                'invoice_addr' => $invoiceData['member_invoice_data']['company_addr'] ? $invoiceData['member_invoice_data']['company_addr'] : '',
                //公司地址
                'ship_type' => $invoiceData['member_invoice_data']['type'],
                // 发票种类(ORDINARY:普通 SPECIAL:专用 ELECTRONIC:电子）
                'ship_email' => $invoiceData['member_invoice_data']['email'] ? $invoiceData['member_invoice_data']['email'] : '',
                // 邮箱
                'ship_name' => $invoiceData['member_invoice_data']['ship_name'] ? $invoiceData['member_invoice_data']['ship_name'] : $orderInfo['ship_name'],
                // 收货人名称
                'ship_tel' => $invoiceData['member_invoice_data']['ship_tel'] ? $invoiceData['member_invoice_data']['ship_tel'] : $orderInfo['ship_mobile'],
                // 收货人电话
                'ship_addr' => $invoiceData['member_invoice_data']['ship_addr'] ? $invoiceData['member_invoice_data']['ship_addr'] : $orderInfo['ship_addr'],
                // 收货人地址
                'remark' => $invoiceData['member_invoice_data']['remark'] ? $invoiceData['member_invoice_data']['remark'] : ''
                // 备注
            );

            // 流水号
            $invoiceApplyInfo['sn'] = $this->_createSerialNumber();
            $invoiceApplyInfo['apply_id'] = $orderInvoiceRecord[$platform['platform_data']['platform_code']]['apply_id'];
            $invoiceApplyInfo['invoice_price'] = $platformAmount;
            $invoiceApplyInfo['items'] = $platformItem;
            $invoiceApply[] = $invoiceApplyInfo;
        }

        if (empty($invoiceApply)) {
            $errMsg = '未找到开票的信息';
            return false;
        }

        if ($totalAmount > ($orderInfo['final_amount'] + $orderInfo['point_amount'])) {
            $errMsg = '发票金额超出订单总金额';
            return false;
        }

        $status = true;
        foreach ($invoiceApply as $info) {
            $result = $this->_invoiceServiceChange($info, $errMsg);

            if (empty($result)) {
                $errMsg OR $errMsg = '换开申请失败';
                return false;
            }

            $invoiceStatus = $result['apply_id'] > 0 ? 1 : 0;

            // 添加发票记录
            if (!$orderInvoiceModel->addInvoiceRecord($orderInfo['order_id'], $info['perform'], $info['sn'],
                $info['pay_type'], 2, $result['apply_id'], $info, $invoiceStatus)) {
                $status = false;
            }
        }

        if ($status == false) {
            $errMsg OR $errMsg = '添加发票记录失败';
            return false;
        }

        return true;
    }

    /*
        * @todo 订单作废
        */
    public function cancel($orderId, & $errMsg = '')
    {
        // 获取订单信息
        $orderInfo = OrderModel::GetOrderInfoById($orderId);

        if (empty($orderInfo)) {
            $errMsg = '未查询到订单信息';
            return false;
        }

        $orderInfo = get_object_vars($orderInfo);

        // 获取订单发票申请记录
        $orderInvoiceModel = new OrderInvoiceModel();
        $invoiceRecord = $orderInvoiceModel->getOrderInvoiceRecord($orderInfo['order_id']);

        if (empty($invoiceRecord)) {
            $errMsg = '未获取到发票申请记录';
            return false;
        }

        $applyIds = array();
        foreach ($invoiceRecord as $record) {
            $applyIds[] = $record['apply_id'];
        }
        // 获取发票申请记录
        $invoiceApply = $this->_getInvoiceServiceApply($applyIds);

        if (empty($invoiceApply)) {
            $errMsg = '未获取到发票申请记录';
            return false;
        }

        foreach ($invoiceApply as $applyInfo) {
            // 申请状态(1:待处理  2:进行中 3:完成 4:异常 5:已提交待返回 6.换开已作废待重新提交 7.换开已提交待返回)
            if ($applyInfo['apply_status'] != 3) {
                $errMsg = '申请未完成，无法作废发票';
                return false;
            }
        }

        $status = true;
        foreach ($invoiceApply as $v) {
            if ($v['status'] != 1) {
                continue;
            }
            $info = array(
                'type' => 'ORDER',
                'sn' => $this->_createSerialNumber(),
                'bn' => $orderId,
                'perform' => $v['perform'],
                'apply_id' => $v['apply_id'],
            );
            $result = $this->_invoiceServiceCancel($info, $errMsg);

            $invoiceStatus = $result['apply_id'] > 0 ? 1 : 0;

            // 添加发票记录
            if (!$orderInvoiceModel->addInvoiceRecord($orderInfo['order_id'], $info['perform'], $info['sn'],
                $info['pay_type'], 3, $result['apply_id'], $info, $invoiceStatus)) {
                $status = false;
            }
        }

        if ($status == false) {
            $errMsg OR $errMsg = '添加发票记录失败';
            return false;
        }

        return true;
    }

    // 生成流水号
    private function _createSerialNumber()
    {
        $timeInfo = explode(" ", microtime());
        return date('YmdHis') . $timeInfo[1] . rand(1000, 9999);
    }

    // 获取发票服务申请记录
    private function _getInvoiceServiceApply($applyIds)
    {
        $service_logic = new Service();

        $result = $service_logic->ServiceCall('invoice_detail', array('apply_id' => $applyIds));

        if ($result['error_code'] != 'SUCCESS' OR empty($result['data'])) {
            \Neigou\Logger::General('service_message_order_update', array('applyId' => $applyIds, 'result' => $result));
            return false;
        }

        return $result['data'];
    }

    // 获取发票开票平台
    private function _getInvoicePlatform($payments)
    {
        $requestData = array(
            'payments' => array($payments['cash'], $payments['point'])
        );

        $requestData['data'] = base64_encode(json_encode($requestData));
        $requestData['token'] = \App\Api\Common\Common::GetEcStoreSign($requestData);

        $url = config('neigou.STORE_DOMIN') . '/openapi/invoice/getInvoicePlatformData';

        $_curl = new \Neigou\Curl();
        $_curl->time_out = 10;
        $result = $_curl->Post($url, $requestData);
        $result = json_decode($result, true);

        return $result['Data'] ? $result['Data'] : array();
    }

    // 发票服务申请
    private function _invoiceServiceApply($invoiceApply, & $errMsg = '')
    {
        $service_logic = new Service();

        $result = $service_logic->ServiceCall('invoice_apply', $invoiceApply);


        if ($result['error_code'] != 'SUCCESS' OR empty($result['data'])) {
            $errMsg = is_array($result['error_msg']) && !empty($result['error_msg']) ? current($result['error_msg']) : '';
            \Neigou\Logger::General('service_order_invoice_apply_failed',
                array('applyId' => $invoiceApply, 'result' => $result));
            return false;
        }

        return $result['data'];
    }

    // 发票服务换开申请
    private function _invoiceServiceChange($invoiceChangeApply, & $errMsg = '')
    {
        $service_logic = new Service();

        $result = $service_logic->ServiceCall('invoice_change', $invoiceChangeApply);


        if ($result['error_code'] != 'SUCCESS' OR empty($result['data'])) {
            $errMsg = is_array($result['error_msg']) && !empty($result['error_msg']) ? current($result['error_msg']) : '';
            \Neigou\Logger::General('service_order_invoice_change_failed',
                array('applyId' => $invoiceChangeApply, 'result' => $result));
            return false;
        }

        return $result['data'];
    }

    // 发票服务作废申请
    private function _invoiceServiceCancel($invoiceChangeApply, & $errMsg = '')
    {
        $service_logic = new Service();

        $result = $service_logic->ServiceCall('invoice_cancel', $invoiceChangeApply);

        if ($result['error_code'] != 'SUCCESS' OR empty($result['data'])) {
            $errMsg = is_array($result['error_msg']) && !empty($result['error_msg']) ? current($result['error_msg']) : '';
            \Neigou\Logger::General('service_order_invoice_cancel_failed',
                array('applyId' => $invoiceChangeApply, 'result' => $result));
            return false;
        }

        return $result['data'];
    }

    public function getOrderInvoice($orderId)
    {
        $service_logic = new Service();

        $result = $service_logic->ServiceCall('invoice_detail', array('type' => 'ORDER', 'bn' => $orderId));

        if ($result['error_code'] != 'SUCCESS' OR empty($result['data'])) {
            return array();
        }

        return $result['data'];
    }
}
