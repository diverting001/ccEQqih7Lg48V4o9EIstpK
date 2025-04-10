<?php
/**
 * ShopEx licence
 *
 * @copyright  Copyright (c) 2005-2010 ShopEx Technologies Inc. (http://www.shopex.cn)
 * @license  http://ecos.shopex.cn/ ShopEx License
 */
namespace App\Api\Model\Goods;
use \App\Api\Model\BaseModel ;

class Transaction extends BaseModel{

    protected $table = 'sdb_b2c_pop_transaction' ;

    protected $primaryKey = 'id' ;


    public function getAmountLimit($rule, $ruleItem, $disabled = false)
    {
        if (empty($rule))
        {
            return array();
        }
        $disabled = $disabled === true ? 'true' : 'false';

        $sql = "SELECT * FROM sdb_b2c_order_amount_limit WHERE rule = '{$rule}' AND rule_item = '{$ruleItem}' AND disabled = '{$disabled}'";
        $result = $this->select($sql);
        return $result ? $result[0] : array();
    }


    /**
     * 获取渠道当前下单金额
     *
     * @param   $companyId  mixed   公司ID
     * @param   $channel    mixed   渠道
     * @param   $startTime  mixed   开始时间
     * @param   $endTime    mixed   结束时间
     * @param   $status     mixed   状态
     * @return  int
     */
    public function getOrderAmountLimitSumAmount($companyId = null, $channel = null, $startTime = null, $endTime = null, $status = 1)
    {
        $return = 0;
        if (empty($companyId) && empty($channel))
        {
            return $return;
        }

        if ( ! empty($companyId))
        {
            $where = " company_id = {$companyId}";
        }
        else
        {
            $where = " channel = '{$channel}'";
        }

        if ($startTime)
        {
            $where .= " AND create_time >=". $startTime;
        }

        if ($endTime)
        {
            $where .= " AND create_time <=". $endTime;
        }

        if ($status !== null)
        {
            $where .= " AND status =". $status;
        }

        $sql = "SELECT sum(amount) as amount FROM sdb_b2c_order_amount_limit_records WHERE ". $where;

        $result = $this->selectrow($sql);

        return $result && $result['amount'] > 0 ? floatval($result['amount']) : 0;
    }




    /**
     * 获取渠道当前下单金额
     *
     * @param   $orderId    int     订单ID
     * @param   $status     mixed   状态
     * @return  boolean
     */
    public function updateAmountLimitStatus($orderId, $status = 0)
    {
        $sql = "UPDATE sdb_b2c_order_amount_limit_records set `status` = {$status} WHERE order_id = {$orderId}";
        return $this->exec($sql);
    }

    // --------------------------------------------------------------------

    /**
     * 添加订单金额限制记录
     *
     * @param   $orderId    int     订单ID
     * @param   $companyId  int     公司ID
     * @param   $channel    string  渠道
     * @param   $amount     int     金额
     * @return  int
     */
    public function addOrderAmountLimitRecord($orderId, $companyId, $channel, $amount)
    {
        if (empty($orderId))
        {
            return false;
        }
        $data = array(
            'order_id'      => $orderId,
            'company_id'    => $companyId,
            'channel'       => $channel,
            'amount'        => $amount,
            'status'        => 1,
            'create_time'   => time(),
        );
        $this->setTable('sdb_b2c_order_amount_limit_records') ;
        $res =  $this->baseInsert($data);
        $this->setTable($this->table) ;
        return  $res ;
    }

    // --------------------------------------------------------------------

    /**
     * 获取订单金额限制记录
     *
     * @param   $id         int     ID
     * @param   $startTime  int     公司ID
     * @param   $status     mixed   状态
     * @return  array
     */
    public function getOrderAmountLimitRecordRow($id = 0, $startTime, $status = 1)
    {
        if (empty($companyId) && empty($channel))
        {
            return array();
        }
        $where = " id > {$id}";

        $where .= " AND create_time >=". $startTime;

        $where .= " AND status =". $status;

        $sql = "SELECT * FROM sdb_b2c_order_amount_limit_records WHERE ". $where. " ORDER BY id ASC";
        return  $this->selectrow($sql);
    }

}
