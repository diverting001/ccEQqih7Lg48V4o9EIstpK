<?php
/**
 * neigou_service-stock
 *
 * @package     api
 * @author      xupeng
 * @since       Version
 * @filesource
 */

namespace App\Api\Model\Account;

use Mockery\Exception;

/**
 * 账户 model
 *
 * @package     api
 * @category    Model
 * @author      xupeng
 */
class Account
{
    /**
     * 获取账户信息
     *
     * @param   $accountId       mixed      账户ID
     * @return  array
     */
    public function getAccountInfo($accountId)
    {
        $return = array();

        if (empty($accountId))
        {
            return $return;
        }

        $db = app('api_db')->table('server_accounts');

        if ( ! is_array($accountId))
        {
            $where = [
                'account_id' => $accountId
            ];

            $db->where($where);
        }
        else
        {
            $db->whereIn('account_id', $accountId);
        }

        $return = $db->get()->toArray();

        if (empty($return))
        {
            return $return;
        }

        foreach ($return as & $v)
        {
            $v = get_object_vars($v);
        }

        return is_array($accountId) ? $return : current($return);
    }

    // --------------------------------------------------------------------

    /**
     * 获取账户信息
     *
     * @param   $accountCode       string      账号编码
     * @return  array
     */
    public function getAccountInfoByCode($accountCode)
    {
        $return = array();

        if (empty($accountCode))
        {
            return $return;
        }

        $where = [
            'account_code' => $accountCode,
        ];
        $return = app('api_db')->table('server_accounts')->where($where)->first();

        return $return ? get_object_vars($return) : array();
    }

    // --------------------------------------------------------------------

    /**
     * 获取账户列表
     *
     * @param   $accountCode       string      账号编码
     * @return  array
     */
    public function getAccountListByCode($accountCode)
    {
        $return = array();

        if ( ! is_array($accountCode))
        {
            $where = [
                'account_code' => $accountCode
            ];

            $result = app('api_db')->table('server_accounts')->where($where)->get()->toArray();
        }
        else
        {
            $result = app('api_db')->table('server_accounts')->whereIn('account_code', $accountCode)->get()->toArray();
        }

        if (empty($result))
        {
            return $return;
        }

        foreach ($result as $v)
        {
            $v = get_object_vars($v);
            $return[$v['account_code']] = $v;
        }

        return is_array($accountCode) ? $return : current($return);
    }

    // --------------------------------------------------------------------

    /**
     * 获取账户信息
     *
     * @param   $channel    string      渠道
     * @param   $name       string      名称
     * @return  array
     */
    public function getAccountInfoByName($channel, $name)
    {
        $return = array();

        if (empty($channel) OR empty($name))
        {
            return $return;
        }

        $where = [
            'channel'   => $channel,
            'name'      => $name,
        ];
        $return = app('api_db')->table('server_accounts')->where($where)->first();

        return $return ? get_object_vars($return) : array();
    }

    // --------------------------------------------------------------------

    /**
     * 获取账户列表 find name
     *
     * @param   $name       string      名称
     * @return  array
     */
    public function getAccountListFindName($name)
    {
        $return = array();

        $where = array(
            ['name', 'like', "%". $name. "%"],
        );

        $result = app('api_db')->table('server_accounts')->where($where)->get()->toArray();

        if (empty($result))
        {
            return $return;
        }

        foreach ($result as $v)
        {
            $return[] = get_object_vars($v);
        }

        return $return;
    }

    // --------------------------------------------------------------------

    /**
     * 添加账户信息
     *
     * @param   $data       array      账户数据
     * @return  boolean
     */
    public function createAccount($data)
    {
        if (empty($data))
        {
            return false;
        }

        if ( ! isset($data['create_time']))
        {
            $data['create_time'] = time();
        }

        $data['update_time'] = time();

        return app('api_db')->table('server_accounts')->insertGetId($data);
    }

    // --------------------------------------------------------------------

    /**
     * 授信额度更新
     *
     * @param   $accountId  int     账户ID
     * @param   $data       array   数据
     * @return  boolean
     */
    public function updateAccount($accountId, $data)
    {
        if (empty($accountId) OR empty($data))
        {
            return false;
        }

        $where = array(
            'account_id' => $accountId,
        );

        if ( ! isset($data['update_time']))
        {
            $data['update_time'] = time();
        }

        $res = app('api_db')->table('server_accounts')->where($where)->update($data);

        return $res !== false ? true : false;
    }

    // --------------------------------------------------------------------

    /**
     * 添加账户信息
     *
     * @param   $filter       array      账户数据
     * @return  boolean
     */
    public function getAccountCount($filter)
    {
        $db = app('api_db')->table('server_accounts');

        if ( ! empty($filter))
        {
            $where = array();
            if ( ! empty($filter['account_code']))
            {
                if (is_array($filter['account_code']))
                {
                    $db->whereIn('account_code', $filter['account_code']);
                }
                else
                {
                    $where['account_code'] = $filter['account_code'];
                }
            }

            if ( ! empty($filter['name']))
            {
                $where[] = ['name', 'like', "%". $filter['name']. "%"];
            }

            if ($filter['status'] !== null)
            {
                $where['status'] = $filter['status'];
            }

            if ( ! empty($where))
            {
                $db->where($where);
            }
        }

       return $db->count();
    }

    // --------------------------------------------------------------------

    /**
     * 添加账户列表
     *
     * @param   $filter     array   账户数据
     * @param   $limit      int     数量
     * @param   $offset     int     偏移量
     * @return  array
     */
    public function getAccountList($filter, $limit = 20, $offset = 0)
    {
        $return = array();

        $db = app('api_db')->table('server_accounts');

        if ( ! empty($filter))
        {
            if ( ! empty($filter['account_code']))
            {
                if (is_array($filter['account_code']))
                {
                    $db->whereIn('account_code', $filter['account_code']);
                }
                else
                {
                    $where['account_code'] = $filter['account_code'];
                }
            }

            if ( ! empty($filter['name']))
            {
                $where[] = ['name', 'like', "%". $filter['name']. "%"];
            }

            if (isset($filter['status']) && $filter['status'] !== null)
            {
                $where['status'] = $filter['status'];
            }

            if ( ! empty($where))
            {
                $db->where($where);
            }
        }

        $result = $db->limit($limit)->offset($offset)->orderBy('account_id', 'DESC')->get()->toArray();

        if (empty($result))
        {
            return $return;
        }

        foreach ($result as $v)
        {
            $return[] = get_object_vars($v);
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
     * @return  array
     */
    public function getBillList($startDate, $endDate, $filter = array())
    {
        $return = array();

        $where = [
            ['start_date', '>=', $startDate],
            ['end_date', '<=', $endDate],
        ];

        $db = app('api_db')->table('server_account_bills');

        if ( ! empty($filter))
        {
            if ( ! empty($filter['account_id']))
            {
                if (is_array($filter['account_id']))
                {
                    $db->whereIn('account_id', $filter['account_id']);
                }
                else
                {
                    $where['account_id'] = $filter['account_id'];
                }
            }
        }

        $result =$db->where($where)->orderBy('bill_id', 'DESC')->get()->toArray();

        if (empty($result))
        {
            return $return;
        }

        foreach ($result as $v)
        {
            $return[] = get_object_vars($v);
        }

        return $return;
    }

    // --------------------------------------------------------------------

    /**
     * 创建账单
     *
     * @param   $accountId      int         账户ID
     * @param   $amount         double      核对金额
     * @param   $finalAmount    double      核对金额
     * @param   $startDate      double      核对金额
     * @param   $endDate        double      核对金额
     * @param   $memo           string      备注
     * @param   $operator       string      操作人
     * @return  boolean
     */
    public function createBill($accountId, $amount, $finalAmount, $startDate, $endDate, $memo = '', $operator = '')
    {
        $data = array(
            'account_id'    => $accountId,
            'amount'        => $amount,
            'final_amount'  => $finalAmount,
            'start_date'    => $startDate,
            'end_date'      => $endDate,
            'create_time'   => time(),
            'update_time'   => time(),
            'memo'          => $memo,
            'operator'      => $operator,
        );

        return app('api_db')->table('server_account_bills')->insertGetId($data);
    }

    // --------------------------------------------------------------------

    /**
     * 余额充值
     *
     * @param   $accountId      int         账户ID
     * @param   $amount         double      金额
     * @return  boolean
     */
    public function balanceRecharge($accountId, $amount)
    {
        if (empty($accountId) OR $amount <= 0)
        {
            return false;
        }

        $where = array(
            'account_id'    => $accountId,
        );

        $updateData = array(
            'update_time'   => time(),
        );

        return app('api_db')->table('server_accounts')->where($where)->increment('balance', $amount, $updateData);
    }

    // --------------------------------------------------------------------

    /**
     * 授信额度更新
     *
     * @param   $accountId      int         账户ID
     * @param   $amount         double      金额
     * @return  boolean
     */
    public function updateCreditLimit($accountId, $amount)
    {
        if (empty($accountId) OR $amount < 0)
        {
            return false;
        }

        $where = array(
            'account_id'    => $accountId,
        );

        $updateData = array(
            'credit_limit'  => $amount,
            'update_time'   => time(),
        );

        return app('api_db')->table('server_accounts')->where($where)->update($updateData);
    }

    // --------------------------------------------------------------------

    /**
     * 获取账户记录数量
     *
     * @param   $filter  array      账户ID
     * @return  int
     */
    public function getRecordCount($filter)
    {
        $db = app('api_db')->table('server_account_records');

        $where = self::_parseRecordFilter($filter);

        if ( ! empty($where['where']))
        {
            $db->where($where['where']);
        }

        if ( ! empty($where['where_in']))
        {
            foreach ($where['where_in'] as $field => $item)
            {
                $db->whereIn($field, $item);
            }
        }

        return $db->count();
    }

    // --------------------------------------------------------------------

    /**
     * 获取支付记录列表
     *
     * @param   $filter     array   过滤条件
     * @param   $limit      int     数量
     * @param   $offset     int     起始位置
     * @return  array
     */
    public function getRecordList($filter, $limit = 20, $offset = 0)
    {
        $return = array();

        $db = app('api_db')->table('server_account_records');

        $where = self::_parseRecordFilter($filter);

        if ( ! empty($where['where']))
        {
            $db->where($where['where']);
        }

        if ( ! empty($where['where_in']))
        {
            foreach ($where['where_in'] as $field => $item)
            {
                $db->whereIn($field, $item);
            }
        }

        $result = $db->limit($limit)->offset($offset)->orderBy('id', 'DESC')->get()->toArray();

        if (empty($result))
        {
            return $return;
        }

        foreach ($result as $v)
        {
            $return[] = get_object_vars($v);
        }

        return $return;
    }


    // --------------------------------------------------------------------

    /**
     * 获取支付记录列表
     *
     * @param   $filter     array   过滤条件
     * @return  array
     */
    public function getAccountRecordTotalAmount($filter)
    {
        $return = array();

        $db = app('api_db')->table('server_account_records');

        $where = self::_parseRecordFilter($filter);

        if ( ! empty($where['where']))
        {
            $db->where($where['where']);
        }

        if ( ! empty($where['where_in']))
        {
            foreach ($where['where_in'] as $field => $item)
            {
                $db->whereIn($field, $item);
            }
        }

        $result = $db->select('account_id', app('api_db')->raw('sum(amount) as total_amount'))->groupBy('account_id')->get()->toArray();

        if (empty($result))
        {
            return $return;
        }

        foreach ($result as $v)
        {
            $v = get_object_vars($v);
            $return[$v['account_id']] = $v['total_amount'];
        }

        return $return;
    }

    // --------------------------------------------------------------------

    /**
     * 账户扣款
     *
     * @param   $accountId      string     账户ID
     * @param   $amount         double     扣款金额
     * @return  boolean
     */
    public function deduct($accountId, $amount)
    {
        if (empty($accountId) OR $amount <= 0) {
            return false;
        }

        $where = 'account_id = ? AND status = 1 AND balance + credit_limit >= ?';
        /*
        $where = [
            'account_id' => $accountId,
            'status' => 1,
            ['balance + credit_limit', '>=', $amount],
        ];*/

        $updateData = array(
            'update_time' => time(),

        );
        $result = app('api_db')->table('server_accounts')->whereRaw($where, [$accountId, $amount])->decrement('balance', $amount, $updateData);

        return $result ? true : false;
    }


    // --------------------------------------------------------------------

    /**
     * 账户退款
     *
     * @param   $accountId      string     账户ID
     * @param   $amount         double     扣款金额
     * @return  boolean
     */
    public function refund($accountId, $amount)
    {
        if (empty($accountId) OR $amount <= 0) {
            return false;
        }

        $where = [
            'account_id' => $accountId,
            'status' => 1,
        ];

        $updateData = array(
            'update_time' => time(),

        );
        $result = app('api_db')->table('server_accounts')->where($where)->increment('balance', $amount, $updateData);

        return $result ? true : false;
    }

    // --------------------------------------------------------------------

    /**
     * 添加账户记录
     *
     * @param   $data       array      账户数据
     * @return  boolean
     */
    public function addAccountRecord($data)
    {
        if (empty($data))
        {
            return false;
        }

        if ( ! isset($data['create_time']))
        {
            $data['create_time'] = time();
        }

        return app('api_db')->table('server_account_records')->insertGetId($data);
    }

    // --------------------------------------------------------------------

    /**
     * 解析记录过滤条件
     *
     * @param   $filter     array   过滤条件
     * @return  array
     */
    private static function _parseRecordFilter($filter)
    {
        $return = array();

        if (empty($filter))
        {
            return $return;
        }

        $allowField = array('type', 'account_id', 'sn', 'trade_type', 'trade_bn', 'create_time');
        foreach ($filter as $field => $item)
        {
            if ( ! in_array($field, $allowField))
            {
                continue;
            }

            if ($field == 'create_time')
            {
                if ( ! is_array($item))
                {
                    $item = array($item);
                }

                if (isset($item[0]) && $item[0])
                {
                    $return['where'][] = ['create_time', '>=', $item[0]];
                }

                if (isset($item[1]) && $item[1])
                {
                    $return['where'][] = ['create_time', '<=', $item[1]];
                }
                continue;
            }

            if (is_array($item))
            {
                $return['where_in'][$field] = $item;
            }
            else
            {
                $return['where'][$field] = $item;
            }

        }
        return $return;
    }

}
