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
     * @param   $orderId   mixed  订单ID
     * @return  array
     */
    public function getOrderInvoiceRecord($orderId)
    {
        $return = array();

        if ( ! is_array($orderId))
        {
            $where = array(
                array('order_id', $orderId),
            );

            $result = $this->_db->table(self::TABLE_ORDER_INVOICE_RECORD)->where($where)->get()->toArray();
        }
        else
        {
            $result = $this->_db->table(self::TABLE_ORDER_INVOICE_RECORD)->whereIn('order_id', $orderId)->get()->toArray();
        }

        if ( ! empty($result))
        {
            foreach ($result as $v)
            {
                $return[] = get_object_vars($v);
            }
        }

        return $return;
    }

    // --------------------------------------------------------------------

    /**
     * 获取订单开票记录详情
     *
     * @param   $invoiceId      mixed       发票ID
     * @return  array
     */
    public function getOrderInvoiceRecordInfo($invoiceId)
    {
        $return = array();

        if ( ! is_array($invoiceId))
        {
            $where = array(
                array('invoice_id', $invoiceId),
            );

            $result = $this->_db->table(self::TABLE_ORDER_INVOICE_RECORD)->where($where)->get()->toArray();
        }
        else
        {
            $result = $this->_db->table(self::TABLE_ORDER_INVOICE_RECORD)->whereIn('invoice_id', $invoiceId)->get()->toArray();
        }

        if ( ! empty($result))
        {
            foreach ($result as $v)
            {
                $return[] = get_object_vars($v);
            }
        }

        return is_array($invoiceId) ? $return : current($return);
    }

    // --------------------------------------------------------------------

    /**
     * 添加发票记录
     *
     * @param   $orderId        string  订单ID
     * @param   $platform       string  平台
     * @param   $sn             string  流水号
     * @param   $payType        string  支付类型(POINT:积分 CASH:现金）
     * @param   $type           int     类型（1:正常 2:换开 3:废弃）
     * @param   $applyId        int     申请ID
     * @param   $applyData      mixed   申请内容
     * @param   $invoiceData    mixed   开票信息
     * @param   $status         int     状态（1:成功 0:失败)
     * @param   $confirmStatus  int     确认状态（1：已确认 0：未确认）
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
        $invoiceData = NULL,
        $status = 1,
        $confirmStatus = 1
    )
    {
        $now = time();
        $addRecordData = array(
            'order_id' => $orderId,
            'platform' => $platform,
            'sn' => $sn,
            'pay_type' => $payType,
            'type' => $type,
            'apply_id' => $applyId,
            'apply_data' => $applyData ? serialize($applyData) : '',
            'invoice_data' => $invoiceData ? serialize($invoiceData) : null,
            'status' => $status,
            'confirm_status' => $confirmStatus,
            'create_time' => $now,
            'update_time' => $now,
        );

        if ( ! app('api_db')->table(self::TABLE_ORDER_INVOICE_RECORD)->insertGetId($addRecordData))
        {
            return false;
        }

        return true;
    }

    // --------------------------------------------------------------------

    /**
     * 更新发票记录
     *
     * @param   $invoiceId      mixed     发票ID
     * @param   $updateData     array   更新数据
     * @return  boolean
     */
    public function updateInvoiceRecord($invoiceId, $updateData)
    {
        if (empty($invoiceId) OR empty($updateData))
        {
            return false;
        }

        if ( ! isset($updateData['update_time']))
        {
            $updateData['update_time'] = time();
        }

        if (isset($updateData['apply_data']) && is_array($updateData['apply_data']))
        {
            $updateData['apply_data'] = serialize($updateData['apply_data']);
        }

        if (isset($updateData['invoice_data']) && is_array($updateData['invoice_data']))
        {
            $updateData['invoice_data'] = serialize($updateData['invoice_data']);
        }

        if ( ! is_array($invoiceId))
        {
            $where = array(
                'invoice_id' => $invoiceId,
            );
            $result = $this->_db->table(self::TABLE_ORDER_INVOICE_RECORD)->where($where)->update($updateData);
        }
        else
        {
            $result = $this->_db->table(self::TABLE_ORDER_INVOICE_RECORD)->whereIn('invoice_id', $invoiceId)->update($updateData);
        }

        return $result ? true : false;
    }

    // --------------------------------------------------------------------

    /**
     * 更新发票记录
     *
     * @param   $invoiceId      int         发票ID
     * @param   $confirmStatus  int         确认状态
     * @param   $status         mixed       状态
     * @param   $limit          int         数量
     * @param   $offset         int         起始位置
     * @return  array
     */
    public function getUnProcessOrderInvoice($invoiceId = 0, $confirmStatus = 1, $status = null, $limit = 100, $offset = 0)
    {
        $return = array();

        $where = array(
            ['invoice_id', '>', $invoiceId],
            ['confirm_status', '=', $confirmStatus]
        );

        $where[] = array('status', '=', $status);

        $data = $this->_db->table(self::TABLE_ORDER_INVOICE_RECORD)->where($where)->limit($limit)->offset($offset)->orderBy('invoice_id', 'ASC')->get()->toArray();

        if (empty($data))
        {
            return $return;
        }

        foreach ($data as $v)
        {
            $return[] = get_object_vars($v);
        }

        return $return;
    }

}
