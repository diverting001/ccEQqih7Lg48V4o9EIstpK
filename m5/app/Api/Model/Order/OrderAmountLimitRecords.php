<?php
/**
 * ShopEx licence
 *
 * @copyright  Copyright (c) 2005-2010 ShopEx Technologies Inc. (http://www.shopex.cn)
 * @license  http://ecos.shopex.cn/ ShopEx License
 */
namespace App\Api\Model\Order;
use \App\Api\Model\BaseModel ;

class OrderAmountLimitRecords extends BaseModel{

    protected $table = 'sdb_b2c_order_amount_limit_records' ;

    protected $primaryKey = 'id' ;

    /**
     * @param $rule   规则名称
     * @param $ruleItem  规则值
     * @param bool $disabled  是否禁用：false-启用 true-禁用
     * @param int $limitType  限制类型：1-企业 2-员工
     * @return array
     */
    public function getAmountLimit($rule, $ruleItem, $disabled = false)
    {
        if (empty($rule))
        {
            return array();
        }
        $disabled = $disabled === true ? 'true' : 'false';

        $sql = "SELECT * FROM sdb_b2c_order_amount_limit WHERE rule = '{$rule}' AND rule_item = '{$ruleItem}' AND disabled = '{$disabled}' AND limit_type = 1";
        $time = time();
        $sql .= " AND ((start_time < {$time} AND end_time > {$time}) OR (start_time = 0 AND end_time = 0)) ORDER BY `power` DESC";
        $result = $this->selectrow($sql);
        return $result ? $result : array();
    }

    public function getAllAmountLimit($rule, $ruleItem, $disabled = false)
    {
        if (empty($rule)) {
            return array();
        }

        $disabled = $disabled === true ? 'true' : 'false';

        $sql = "SELECT limit_rule_id,message,per_day_limit,daily_start_time FROM sdb_b2c_order_amount_limit WHERE rule = '{$rule}' AND rule_item = '{$ruleItem}' AND disabled = '{$disabled}' AND limit_type = 1";
        $time = time();
        $sql .= " AND ((start_time < {$time} AND end_time > {$time}) OR (start_time = 0 AND end_time = 0)) ORDER BY `power` DESC";
        $result = $this->select($sql);

        return $result ?  : array();
    }

    /**
     * @param $rule   规则名称
     * @param $ruleItem  规则
     * @return array
     */
    public function getMemberAmountLimit($rule, $ruleItem)
    {
        if (empty($rule)) return array();
        $sql = "SELECT * FROM sdb_b2c_order_amount_limit WHERE rule = '{$rule}' AND rule_item = '{$ruleItem}' AND disabled = 'false' AND limit_type = 2";
        $time = time();
        $sql .= " AND ((start_time < {$time} AND end_time > {$time})  OR (start_time = 0 AND end_time = 0)) ORDER BY `power` DESC, `id` DESC limit 1";
        $result = $this->selectrow($sql);
        return $result ? $result : array();
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
    public function getOrderAmountLimitSumAmount($companyId = null, $channel = null, $startTime = null, $endTime = null, $status = 1, $limitRuleId = 0)
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

        if ($limitRuleId)
        {
            $where .= " AND limit_rule_id =". $limitRuleId;
        }

        $sql = "SELECT sum(amount) as amount FROM sdb_b2c_order_amount_limit_records WHERE ". $where;

        $result = $this->selectrow($sql);

        return $result && $result['amount'] > 0 ? floatval($result['amount']) : 0;
    }

    /**
     * 获取员工限制金额
     *
     * @param null $companyId
     * @param null $memberId
     * @param null $startTime
     * @return float|int
     */
   public function getMemberOrderAmountLimitSumAmount($companyId = null, $memberId = null, $startTime = null, $allowOrderIds = array())
   {
       if (empty($companyId) || empty($memberId)) {
           return 0;
       }
       $where = " company_id = {$companyId} AND status = 1 AND member_id = {$memberId}";
       if ($startTime) {
           $where .= "  AND create_time >= ". $startTime;
       }
       if ($allowOrderIds) {
           $where .= ' AND order_id in ('. join(',',$allowOrderIds).') ';
       }
       $sql  = sprintf("SELECT sum(amount) as amount FROM sdb_b2c_order_amount_limit_records WHERE %s",$where);
       $result = $this->selectrow($sql);
       return $result && $result['amount'] > 0 ? floatval($result['amount']) : 0;
   }

   public function getMemberOrderList($companyId, $memberId,$startTime)
   {
       if (empty($companyId) || empty($memberId)) {
           return 0;
       }
       $where = " company_id = {$companyId} AND status = 1 AND member_id = {$memberId}";
       if ($startTime) {
           $where .= "  AND create_time >= ". $startTime;
       }
       $sql  = sprintf("SELECT order_id FROM sdb_b2c_order_amount_limit_records WHERE %s",$where);
       $result = $this->select($sql);
       return $result  ?  : array();
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
     * @param   $memberId   int   员工ID
     * @return  int
     */
    public function addOrderAmountLimitRecord($orderId, $companyId, $channel, $amount, $memberId = 0, $limitRuleId = 0)
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
            'member_id'      => $memberId,
            'limit_rule_id'  => $limitRuleId
        );
        $res =  $this->baseInsert($data);
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
