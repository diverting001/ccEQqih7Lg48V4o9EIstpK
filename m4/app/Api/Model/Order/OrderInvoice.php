<?php
/**
 * Created by PhpStorm.
 * User: chuanbin
 * Date: 2018/9/26
 * Time: 11:35
 */

namespace App\Api\Model\Order;

class OrderInvoice
{
    /**
     * 订单发票记录表
     */
    const TABLE_ORDER_INVOICE_RECORD = 'server_order_invoice_record';

    /**
     * Invoice constructor.
     *
     * @param   string $db
     */
    public function __construct($db = '')
    {
        $this->_db = app('api_db');
    }

    // --------------------------------------------------------------------

    /**
     * 获取订单开票记录（流水号）
     *
     * @param   $orderId   string  订单ID
     * @return  array
     */
    public function getOrderInvoiceRecord($orderId)
    {
        $return = array();

        $where = array(
            array('order_id', $orderId),
        );

        $result = $this->_db->table(self::TABLE_ORDER_INVOICE_RECORD)->where($where)->get()->toArray();

        if (!empty($result)) {
            foreach ($result as $v) {
                $return[] = get_object_vars($v);
            }
        }

        return $return;
    }

    // --------------------------------------------------------------------

    /**
     * 添加发票记录
     *
     * @param   $orderId    string  订单ID
     * @param   $platform   string  平台
     * @param   $sn         string  流水号
     * @param   $payType    string  支付类型(POINT:积分 CASH:现金）
     * @param   $type       int     类型（1:正常 2:换开 3:废弃）
     * @param   $applyId    int     申请ID
     * @param   $applyData  mixed   申请内容
     * @param   $status     int     状态（1:成功 0:失败)
     * @return  boolean
     */
    public function addInvoiceRecord(
        $orderId,
        $platform,
        $sn,
        $payType,
        $type = 1,
        $applyId,
        $applyData = '',
        $status = 1
    ) {
        $now = time();
        $addRecordData = array(
            'order_id' => $orderId,
            'platform' => $platform,
            'sn' => $sn,
            'pay_type' => $payType,
            'type' => $type,
            'apply_id' => $applyId,
            'apply_data' => $applyData ? serialize($applyData) : '',
            'status' => $status,
            'create_time' => $now,
            'update_time' => $now,
        );

        if (!app('api_db')->table(self::TABLE_ORDER_INVOICE_RECORD)->insertGetId($addRecordData)) {
            return false;
        }

        return true;
    }

}
