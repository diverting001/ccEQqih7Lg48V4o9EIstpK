<?php
/**
 * neigou_service-stock
 *
 * @package     api
 * @author      xupeng
 * @since       Version
 * @filesource
 */

namespace App\Api\V1\Service\Account;

use App\Api\Model\Account\Account as AccountModel;

/**
 * 账户 Service
 *
 * @package     api
 * @category    Service
 * @author      xupeng
 */
class Account
{
    /**
     * @var array 记录类型列表
     */
    static $_recordTypeList = array(
        'deduct'        => '交易扣款',
        'refund'        => '交易退款',
        'recharge'      => '账户充值',
        'credit_limit'  => '授信额度调整',
        'check_bill'    => '账单核对',
    );

    /**
     * 获取账户详情
     *
     * @param   $accountCode       string      编码
     * @return  array
     */
    public function getAccountInfo($accountCode)
    {
        $accountModel = new AccountModel();

        // 获取账户信息
        $return = $accountModel->getAccountInfoByCode($accountCode);

        $return['enable_balance'] = $return['balance'] + $return['credit_limit'];

        return $return;
    }

    // --------------------------------------------------------------------

    /**
     * 创建账户
     *
     * @param   $channel        string      渠道
     * @param   $name           string      名称
     * @param   $creditLimit    double      授信额度
     * @param   $ownName        string      持卡人信息
     * @param   $memo           string      备注
     * @param   $status         int         默认状态
     * @return  array
     */
    public function createAccount($channel, $name, $creditLimit = 0.00, $ownName = '', $memo = '', $status = null)
    {
        $accountModel = new AccountModel();

        // 生成 code
        $accountCode = self::_createAccountCode();

        // 创建账户信息
        $data = array(
            'channel' => $channel,
            'name' => $name,
            'account_code' => $accountCode,
            'balance' => 0,
            'credit_limit' => $creditLimit,
            'own_name' => $ownName,
            'status' => $status !== null ? $status : 1,
            'memo' => $memo ? $memo : '', // 备注
        );

        $accountId = $accountModel->createAccount($data);

        if ($accountId <= 0)
        {
            return array();
        }

        return $this->getAccountInfo($accountCode);
    }


    // --------------------------------------------------------------------

    /**
     * 更新账户基本信息
     *
     * @param   $accountCode    string  编码
     * @param   $data           array   更新数据
     * @return  mixed
     */
    public function updateAccount($accountCode, $data)
    {
        $accountModel = new AccountModel();

        // 获取账户信息
        $accountInfo = $accountModel->getAccountInfoByCode($accountCode);

        if (empty($accountInfo))
        {
            return false;
        }

        $allowFields = array('name', 'statement_date', 'own_name', 'status', 'memo', 'custom_name');

        foreach ($data as $field => $val)
        {
            if ( ! in_array($field, $allowFields))
            {
                unset($data[$field]);
            }
        }

        if ( ! $accountModel->updateAccount($accountInfo['account_id'], $data))
        {
            return false;
        }

        return $this->getAccountInfo($accountCode);
    }

    // --------------------------------------------------------------------

    /**
     * 获取账户列表
     *
     * @return  array
     */
    public function getAccountList($page = 1, $pageSize = 20, $filter = array())
    {
        $accountModel = new AccountModel();

        // 获取总数量
        $count = $accountModel->getAccountCount($filter);

        $totalPage = ceil($count / $pageSize);

        $return = array(
            'page'          => $page,
            'page_size'     => $pageSize,
            'total_count'   => $count,
            'total_page'    => $totalPage,
            'data'          => array(),
        );

        $offset = ($page - 1) * $pageSize;

        $return['data'] = $accountModel->getAccountList($filter, $pageSize, $offset);

        if ( ! empty($return['data']))
        {
            foreach ($return['data'] as & $v)
            {
                $v['enable_balance'] = number_format($v['balance'] + $v['credit_limit'], 2, '.', '');
            }
        }

        return $return;
    }

    // --------------------------------------------------------------------

    /**
     * 获取账户详情
     *
     * @return  array
     */
    public function getRecordList($filter, $page = 1, $pageSize = 20)
    {
        $accountModel = new AccountModel();

        $accountIds = array();
        if ( ! empty($filter['account_code']))
        {
            $accountCode = is_array($filter['account_code']) ? $filter['account_code'] : array($filter['account_code']);
            $accountList = $accountModel->getAccountListByCode($accountCode);

            if (empty($accountList))
            {
                return array();
            }

            foreach ($accountList as $v)
            {
                $accountIds[] = $v['account_id'];
            }
        }

        if (empty($accountIds) && ! empty($filter['name']))
        {
            $accountList = $accountModel->getAccountListFindName($filter['name']);

            if (empty($accountList))
            {
                return array();
            }

            foreach ($accountList as $v)
            {
                $accountIds[] = $v['account_id'];
            }
        }

        if ( ! empty($accountIds))
        {
            $filter['account_id'] = count($accountIds) > 1 ? $accountIds : current($accountIds);
        }

        // 获取总数量
        $count = $accountModel->getRecordCount($filter);

        $totalPage = ceil($count / $pageSize);

        $return = array(
            'page'          => $page,
            'page_size'     => $pageSize,
            'total_count'   => $count,
            'total_page'    => $totalPage,
            'data'          => array(),
        );

        $offset = ($page - 1) * $pageSize;

        $return['data'] = $accountModel->getRecordList($filter, $pageSize, $offset);

        if ( ! empty($return['data']))
        {
            $accountIds = array();
            foreach ($return['data'] as $v)
            {
                $accountIds[] = $v['account_id'];
            }

            $accountList = array();
            $accountIds = array_unique($accountIds);
            $result = $accountModel->getAccountInfo($accountIds);

            if ( ! empty($result))
            {
                foreach ($result as $v)
                {
                    $accountList[$v['account_id']] = $v;
                }
            }

            foreach ($return['data'] as & $v)
            {
                $v['account_code'] = isset($accountList[$v['account_id']]) ? $accountList[$v['account_id']]['account_code'] : '';
                $v['account_name'] = isset($accountList[$v['account_id']]) ? $accountList[$v['account_id']]['name'] : '';
                $v['custom_name'] = isset($accountList[$v['account_id']]) ? $accountList[$v['account_id']]['custom_name'] : '';
                $v['type_name'] = self::$_recordTypeList[$v['type']] ? self::$_recordTypeList[$v['type']] : $v['type'];
            }
        }

        return $return;
    }

    /**
     * 获取账户每日汇总金额
     *
     * @param   $filter array   过滤
     * @return  array
     */
    public function getDayAmountList($filter)
    {
        $return = array();
        $accountModel = new AccountModel();
        $accountIds = array();
        if (empty($filter['account_code'])) {
            return $return;
        }
        $accountCode = is_array($filter['account_code']) ? $filter['account_code'] : array($filter['account_code']);
        $accountList = $accountModel->getAccountListByCode($accountCode);
        if (empty($accountList)) {
            return array();
        }
        $id_code_mapping = [];
        foreach ($accountList as $v) {
            $accountIds[] = $v['account_id'];
            $id_code_mapping[$v['account_id']] = $v['account_code'];
        }
        $filter['account_id'] = count($accountIds) > 1 ? $accountIds : current($accountIds);
        $filter['type'] = array('deduct', 'refund');
        $recordList = $accountModel->getRecordList($filter, 99999999);
        foreach ($recordList as $recordInfo) {
            $return[$id_code_mapping[$recordInfo['account_id']]][date('Y-m-d', $recordInfo['create_time'])] = array(
                'account_id' => $recordInfo['account_id'],
                'account_code' => $id_code_mapping[$recordInfo['account_id']],
                'custom_name' => $accountList[$id_code_mapping[$recordInfo['account_id']]]['custom_name'],
                'total_amount' => bcadd($return[$id_code_mapping[$recordInfo['account_id']]][date('Y-m-d', $recordInfo['create_time'])]['total_amount'], $recordInfo['amount'], 2),
            );
        }
        foreach ($return as &$item) {
            foreach ($item as &$sub_item) {
                $sub_item['total_amount'] = abs($sub_item['total_amount']);
            }
        }
        return $return;
    }

    // --------------------------------------------------------------------

    /**
     * 获取账户详情
     *
     * @param   $filter array   过滤
     * @return  array
     */
    public function getAccountRecordTotalAmount($filter)
    {
        $return = array();

        $accountModel = new AccountModel();

        $accountIds = array();

        if ( ! empty($filter['account_code']))
        {
            $accountCode = is_array($filter['account_code']) ? $filter['account_code'] : array($filter['account_code']);
            $accountList = $accountModel->getAccountListByCode($accountCode);

            if (empty($accountList))
            {
                return array();
            }

            foreach ($accountList as $v)
            {
                $accountIds[] = $v['account_id'];
            }

            $filter['account_id'] = count($accountIds) > 1 ? $accountIds : current($accountIds);
        }

        $result = $accountModel->getAccountRecordTotalAmount($filter);

        if ( ! empty($result))
        {
            $accountIds = array_keys($result);
            $accountList = $accountModel->getAccountInfo($accountIds);

            foreach ($accountList as $v)
            {
                $return[$v['account_code']] = array(
                    'account_id' => $v['account_id'],
                    'account_code' => $v['account_code'],
                    'name' => $v['name'],
                    'total_amount' => bcmul($result[$v['account_id']], -1, 2),
                );
            }
        }

        return $return;
    }

    // --------------------------------------------------------------------

    /**
     * 获取账单列表
     *
     * @param   $startDate      string      开始时间
     * @param   $endDate        string      结束时间
     * @param   $filter         array       过滤条件
     * @return  mixed
     */
    public function getBillList($startDate, $endDate, $filter = array())
    {
        if (empty($startDate) OR empty($endDate))
        {
            return false;
        }

        $accountModel = new AccountModel();

        $accountIds = array();
        if ( ! empty($filter['account_code']))
        {
            $accountCode = is_array($filter['account_code']) ? $filter['account_code'] : array($filter['account_code']);
            $accountList = $accountModel->getAccountListByCode($accountCode);

            if (empty($accountList))
            {
                return array();
            }

            foreach ($accountList as $v)
            {
                $accountIds[] = $v['account_id'];
            }
        }

        if (empty($accountIds) && ! empty($filter['name']))
        {
            $accountList = $accountModel->getAccountListFindName($filter['name']);

            if (empty($accountList))
            {
                return array();
            }

            foreach ($accountList as $v)
            {
                $accountIds[] = $v['account_id'];
            }
        }

        if ( ! empty($accountIds))
        {
            $filter['account_id'] = count($accountIds) > 1 ? $accountIds : current($accountIds);
        }

        $accountModel = new AccountModel();

        // 获取账户记录列表
        $billList = $accountModel->getBillList($startDate, $endDate, $filter);

        if ( ! empty($billList))
        {
            $accountIds = array();
            foreach ($billList as $v)
            {
                $accountIds[] = $v['account_id'];
            }

            $accountList = array();
            $accountIds = array_unique($accountIds);
            $result = $accountModel->getAccountInfo($accountIds);

            if ( ! empty($result))
            {
                foreach ($result as $v)
                {
                    $accountList[$v['account_id']] = $v;
                }
            }

            foreach ($billList as & $v)
            {
                $v['account_code'] = isset($accountList[$v['account_id']]) ? $accountList[$v['account_id']]['account_code'] : '';
                $v['account_name'] = isset($accountList[$v['account_id']]) ? $accountList[$v['account_id']]['name'] : '';
            }
        }

        return $billList;
    }

    // --------------------------------------------------------------------

    /**
     * 账户扣款批量
     *
     * @param   $list       array       账户扣款
     * @param   $errMsg     string      错误信息
     * @return  mixed
     */??????????
    public function deductBatch($list, & $errMsg = '')
    {
        app('db')->beginTransaction();
        foreach ($list as & $v)
        {
            $result = $this->deduct($v['account_code'], $v['amount'], $v['trade_type'], $v['trade_bn'], $v['trade_category'], $v['memo'], $errMsg);

            if ( ! $result)
            {
                app('db')->rollback();
                return false;
            }

            $v['sn'] = $result['sn'];
        }

        // 提交
        app('db')->commit();

        return $list;
    }

    // --------------------------------------------------------------------

    /**
     * 账户退款批量
     *
     * @param   $list       array       账户扣款
     * @param   $errMsg     string      错误信息
     * @return  mixed
     */
    public function refundBatch($list, & $errMsg = '')
    {
        app('db')->beginTransaction();
        foreach ($list as & $v)
        {
            $result = $this->refund($v['account_code'], $v['amount'], $v['trade_type'], $v['trade_bn'], $v['trade_category'], $v['memo'], $errMsg);

            if ( ! $result)
            {
                app('db')->rollback();
                return false;
            }

            $v['sn'] = $result['sn'];
        }

        // 提交
        app('db')->commit();

        return $list;
    }

    // --------------------------------------------------------------------

    /**
     * 账户扣款
     *
     * @param   $accountCode    string      账户扣款
     * @param   $amount         double      金额
     * @param   $tradeType      string      交易类型
     * @param   $tradeBn        string      交易编码
     * @param   $tradeCategory  string      交易分类
     * @param   $memo           string      备注
     * @param   $errMsg         string      错误信息
     * @return  mixed
     */
    public function deduct($accountCode, $amount, $tradeType, $tradeBn, $tradeCategory, $memo, & $errMsg = '')
    {
        if (empty($accountCode) OR $amount <= 0 OR empty($tradeBn))
        {
            $errMsg = '参数错误';

            return false;
        }

        $accountModel = new AccountModel();

        $tradeType OR $tradeType = 'ORDER';
        $tradeCategory OR $tradeCategory = '';

        // 检查余额
        $accountInfo = $this->getAccountInfo($accountCode);

        // 查询记录
        $filter = array(
            'account_id'    => $accountInfo['account_id'],
            'type'          => 'deduct',
            'trade_type'    => $tradeType,
            'trade_bn'      => $tradeBn,
        );
        $recordList = $accountModel->getRecordList($filter);

        if ( ! empty($recordList))
        {
            return array('sn' => $recordList[0]['sn']);
        }

        // 检查余额
        if ($accountInfo['enable_balance'] < $amount)
        {
            $errMsg = '余额不足';
            \Neigou\Logger::General('account.deduct.failed.balance', array('account_code' => $accountCode, 'time' => date('Y-m-d H:i:s'), 'amount' => $amount));
            return false;
        }

        // 账户扣款
        if ( ! $accountModel->deduct($accountInfo['account_id'], $amount))
        {
            $errMsg = '扣款失败';

            return false;
        }

        // 交易流水号
        $tradeSn = $this->_createTradeSn();

        // 添加记录
        $result = $this->_addAccountRecord($accountInfo['account_id'], 'deduct', $amount * -1, $accountInfo['balance'], $tradeType, $tradeBn, $tradeSn, $tradeCategory, 'SYSTEM', $memo);

        return $result;
    }

    // --------------------------------------------------------------------

    /**
     * 账户退款
     *
     * @param   $accountCode    string      账户扣款
     * @param   $amount         double      金额
     * @param   $tradeType      string      交易类型
     * @param   $tradeBn        string      交易编码
     * @param   $tradeCategory  string      交易分类
     * @param   $memo           string      备注
     * @param   $errMsg         string      错误信息
     * @return  mixed
     */
    public function refund($accountCode, $amount, $tradeType, $tradeBn, $tradeCategory, $memo, & $errMsg = '')
    {
        if (empty($accountCode) OR $amount <= 0 OR empty($tradeBn))
        {
            $errMsg = '参数错误';

            return false;
        }

        $accountModel = new AccountModel();

        $tradeType OR $tradeType = 'ORDER';
        $tradeCategory OR $tradeCategory = '';

        // 获取账户信息
        $accountInfo = $this->getAccountInfo($accountCode);

        // 获取账户扣款记录
        $filter = array(
            'account_id'    => $accountInfo['account_id'],
            'type'          => 'deduct',
            'trade_type'    => $tradeType,
            'trade_bn'      => $tradeBn,
        );
        $deductRecordList = $accountModel->getRecordList($filter);

        if (empty($deductRecordList))
        {
            return true;
        }

        $totalAmount = $deductRecordList[0]['amount'];

        // 获取已退款的记录
        $filter = array(
            'account_id'    => $accountInfo['account_id'],
            'type'          => 'refund',
            'trade_type'    => $tradeType,
            'trade_bn'      => $tradeBn,
        );
        $refundRecordList = $accountModel->getRecordList($filter);

        $refundTotalAmount = 0;

        if ( ! empty($refundRecordList))
        {
            foreach ($refundRecordList as $v)
            {
                $refundTotalAmount = bcadd($refundTotalAmount, $v['amount'], 2);
            }
        }

        // 检查金额
        if (bcadd(bcadd($refundTotalAmount , $amount, 2), $totalAmount, 2) > 0)
        {
            if ( ! empty($refundRecordList))
            {
                return $refundRecordList[count($refundRecordList)-1];
            }

            $errMsg = '退款金额超出';
            return false;
        }

        // 退款操作失败
        if ( ! $accountModel->refund($accountInfo['account_id'], $amount))
        {
            $errMsg = '退款操作失败';
            return false;
        }

        // 交易流水号
        $tradeSn = $this->_createTradeSn();

        // 添加记录
        $result = $this->_addAccountRecord($accountInfo['account_id'], 'refund', $amount, $accountInfo['balance'], $tradeType, $tradeBn, $tradeSn, $tradeCategory, 'SYSTEM', $memo);

        return $result;
    }

    // --------------------------------------------------------------------

    /**
     * 账户余额充值
     *
     * @param   $accountCode        string      分销商标识
     * @param   $rechargeAmount     double      金额
     * @param   $cert               mixed       交易凭证
     * @param   $operator           string      操作人
     * @param   $memo               string      备注
     * @param   $errMsg             string      错误信息
     * @return  mixed
     */
    public function recharge($accountCode, $rechargeAmount, $cert = '', $operator = '', $memo = '', & $errMsg = '')
    {
        if (empty($accountCode) OR $rechargeAmount <= 0)
        {
            $errMsg = '参数错误';

            return false;
        }

        $accountModel = new AccountModel();

        // 获取账户信息
        $accountInfo = $accountModel->getAccountInfoByCode($accountCode);

        if (empty($accountInfo))
        {
            $errMsg = '未获取账户信息';

            return false;
        }

        // 账户充值
        $result = $this->_recharge($accountInfo['account_id'], $rechargeAmount, $cert, $operator, $memo, $errMsg);

        return $result;
    }

    // --------------------------------------------------------------------

    /**
     * 更新授信额度
     *
     * @param   $accountCode        string      分销商标识
     * @param   $creditLimit        double      授信额度
     * @param   $cert               mixed       交易凭证
     * @param   $operator           string      操作人
     * @param   $memo               string      备注
     * @param   $errMsg             string      错误信息
     * @return  mixed
     */
    public function updateCreditLimit($accountCode, $creditLimit, $cert = '', $operator = '', $memo = '', & $errMsg = '')
    {
        if (empty($accountCode))
        {
            $errMsg = '参数错误';

            return false;
        }

        $accountModel = new AccountModel();

        // 获取账户信息
        $accountInfo = $accountModel->getAccountInfoByCode($accountCode);

        if (empty($accountInfo))
        {
            $errMsg = '未获取账户信息';

            return false;
        }

        // 更新授信额度
        if ( ! $accountModel->updateCreditLimit($accountInfo['account_id'], $creditLimit))
        {
            $errMsg = '更新授信的额度';

            return false;
        }

        $changeCreditLimit = $creditLimit - $accountInfo['credit_limit'];

        // 交易流水号
        $tradeSn = $this->_createTradeSn();

        // 交易编码
        $tradeBn = 'CL'. $tradeSn;

        // 添加账户记录
        $this->_addAccountRecord($accountInfo['account_id'], 'credit_limit', $changeCreditLimit, $creditLimit, 'CREDIT_LIMIT', $tradeBn, $tradeSn, NULL, $operator, $memo, $cert);

        return true;
    }

    // --------------------------------------------------------------------

    /**
     * 添加账单
     *
     * @param   $accountCode    string      账户编码
     * @param   $amount         double      金额
     * @param   $finalAmount    double      核对金额
     * @param   $startDate      string      开始日期
     * @param   $endDate        string      结束日期
     * @param   $memo           string      备注
     * @param   $operator       string      操作人
     * @return  mixed
     */
    public function createBill($accountCode, $amount, $finalAmount, $startDate, $endDate, $memo, $operator = '')
    {
        $accountModel = new AccountModel();

        // 获取账户信息
        $accountInfo = $accountModel->getAccountInfoByCode($accountCode);

        if (empty($accountInfo))
        {
            return false;
        }

        // 检查对账时间
        if (strtotime($accountInfo['last_bill_date']) > strtotime($startDate))
        {
            return false;
        }
        // 添加账单
        $result = $accountModel->createBill($accountInfo['account_id'], $amount, $finalAmount, $startDate, $endDate, $memo, $operator);

        if ( ! $result)
        {
            return false;
        }

        // 核对金额
        $amount = bcsub($amount, $finalAmount, 2);
        if (abs($amount) > 0)
        {
            if ($amount > 0)
            {
                // 余额充值
                if ( ! $accountModel->balanceRecharge($accountInfo['account_id'], $amount))
                {
                    return false;
                }
            }
            else
            {
                // 账户扣款
                if ( ! $accountModel->deduct($accountInfo['account_id'], abs($amount)))
                {
                    return false;
                }
            }
        }

        $endDate = date('Y-m-d', strtotime($endDate) + 86400);
        // 更新账户
        if ( ! $accountModel->updateAccount($accountInfo['account_id'], array('last_bill_date' => $endDate)))
        {
            return false;
        }

        // 交易流水号
        $tradeSn = $this->_createTradeSn();

        // 交易编码
        $tradeBn = 'CB'. $tradeSn;

        // 添加账户记录
        $this->_addAccountRecord($accountInfo['account_id'], 'check_bill', $amount, $accountInfo['balance'], 'CHECK_BILL', $tradeBn, $tradeSn, NULL, $operator);

        return true;
    }

    // --------------------------------------------------------------------

    /**
     * 账户余额充值
     *
     * @param   $accountId      int     账户ID
     * @param   $rechargeAmount double  充值金额
     * @param   $cert           mixed   交易凭证
     * @param   $operator       string  操作人
     * @param   $memo           string  备注
     * @param   $errMsg         string  错误信息
     * @return  mixed
     */
    private function _recharge($accountId, $rechargeAmount, $cert = '', $operator = '', $memo = '', & $errMsg = '')
    {
        if ($accountId <= 0 OR $rechargeAmount <= 0)
        {
            $errMsg = '参数错误';

            return false;
        }

        $accountModel = new AccountModel();

        // 余额充值
        if ( ! $accountModel->balanceRecharge($accountId, $rechargeAmount))
        {
            $errMsg = '余额充值失败';

            return false;
        }

        // 交易流水号
        $tradeSn = $this->_createTradeSn();

        // 交易编码
        $tradeBn = 'RC'. $tradeSn;

        // 获取账户信息
        $accountInfo = $accountModel->getAccountInfo($accountId);

        // 添加账户记录
        $sn = $this->_addAccountRecord($accountId, 'recharge', $rechargeAmount, $accountInfo['balance'], 'RECHARGE', $tradeBn, $tradeSn, NULL, $operator, $memo, $cert);

        return array('sn' => $sn);
    }

    // --------------------------------------------------------------------

    /**
     * 添加账户记录
     *
     * @param   $accountId          int     账户ID
     * @param   $type               string  类型
     * @param   $amount             double  金额
     * @param   $beforeBalance      double  交易前余额
     * @param   $tradeType          string  交易类型
     * @param   $tradeBn            string  交易编码
     * @param   $tradeCategory      string  交易分类
     * @param   $tradeSn            string  交易流水号
     * @param   $operator           string  操作人
     * @param   $memo               string  备注
     * @param   $cert               mixed   凭证
     * @return  mixed
     */
    private function _addAccountRecord($accountId, $type, $amount, $beforeBalance, $tradeType, $tradeBn, $tradeSn, $tradeCategory, $operator = '', $memo = '', $cert = '')
    {
        $accountModel = new AccountModel();

        // 流水号
        $sn = $this->_createAccountSn();

        $afterBalance = bcadd($beforeBalance, $amount, 2);

        $data = array(
            'sn' => $sn,
            'type' => $type,
            'account_id' => $accountId,
            'amount' => $amount,
            'before_balance' => $beforeBalance,
            'after_balance' => $afterBalance,
            'trade_type' => $tradeType ? $tradeType : 'ORDER',
            'trade_bn' => $tradeBn ? $tradeBn : null,
            'trade_sn' => $tradeSn ? $tradeSn : $sn,
            'trade_category' => $tradeCategory ? $tradeCategory : null,
            'operator' => $operator ? $operator : '',
            'memo' => $memo ? $memo : '',
            'cert' => is_array($cert) ? json_encode($cert) : $cert,
        );

        // 添加账户记录
        $accountModel->addAccountRecord($data);

        return array('sn' => $sn);
    }

    // --------------------------------------------------------------------

    /**
     * 生成编码
     *
     * @return  string
     */
    private static function _createAccountCode()
    {
        return 'ACT_'. date('ymdHi'). mt_rand(100, 999);
    }

    // --------------------------------------------------------------------

    /**
     * 生成流水号
     *
     * @return  string
     */
    private static function _createAccountSn()
    {
        return date('YmdHis') . mt_rand(1000, 9999);
    }

    // --------------------------------------------------------------------

    /**
     * 生成交易流水号
     *
     * @return  string
     */
    private static function _createTradeSn()
    {
        return date('YmdHis') . mt_rand(10, 99);
    }

}
