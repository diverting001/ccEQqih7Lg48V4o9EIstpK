<?php
/**
 * neigou_service-stock
 *
 * @package     api
 * @author      xupeng
 * @since       Version
 * @filesource
 */

namespace App\Api\Model\Payment;

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
     * @param   $paId       string      账户ID
     * @return  array
     */
    public function getAccountInfo($paId)
    {
        $return = array();

        if (empty($paId))
        {
            return $return;
        }

        $where = [
            'pa_id' => $paId,
        ];
        $return = app('api_db')->table('server_payment_accounts')->where($where)->first();

        return $return ? get_object_vars($return) : array();
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

        return app('api_db')->table('server_payment_accounts')->insertGetId($data);
    }

    // --------------------------------------------------------------------

    /**
     * 获取账户信息-名称
     *
     * @param   $name       string      名称
     * @return  array
     */
    public function getAccountInfoByName($name)
    {
        $where = [
            'name' => $name,
        ];
        $return = app('api_db')->table('server_payment_accounts')->where($where)->first();

        return $return ? get_object_vars($return) : array();
    }

    // --------------------------------------------------------------------

    /**
     * 获取支付账户数量
     *
     * @param   $filter     array       过滤
     * @return  int
     */
    public function getPaymentAccountCount($filter)
    {
        $db = app('api_db')->table('server_payment_accounts');

        if ( ! empty($filter['pa_id']))
        {
            $paIds = is_array($filter['pa_id']) ? $filter['pa_id'] : array($filter['pa_id']);

            $db->whereIn('pa_id', $paIds);
        }

        return $db->count();
    }

    // --------------------------------------------------------------------

    /**
     * 获取支付账户列表
     *
     * @param   $limit      int     数量
     * @param   $offset     int     起始位置
     * @param   $filter     array   过滤
     * @return  array
     */
    public function getPaymentAccountList($limit = 20, $offset = 0, $filter = array())
    {
        $return = array();

        $db = app('api_db')->table('server_payment_accounts');

        if ( ! empty($filter['pa_id']))
        {
            $paIds = is_array($filter['pa_id']) ? $filter['pa_id'] : array($filter['pa_id']);

            $db->whereIn('pa_id', $paIds);
        }

        $result = $db->limit($limit)->offset($offset)->orderBy('pa_id', 'DESC')->get()->toArray();

        if (empty($result))
        {
            return $return;
        }

        foreach ($result as $v)
        {
            $v = get_object_vars($v);
            $return[$v['pa_id']] = $v;
        }

        return $return;
    }

    // --------------------------------------------------------------------

    /**
     * 获取支付账户列表
     *
     * @param   $filter     array   过滤
     * @return  array
     */
    public function getPaymentAccountRecordList($filter = array())
    {
        $return = array();

        $where = array();

        $db = app('api_db')->table('server_payment_account_records');

        if ( ! empty($filter['trade_type']))
        {
            $where['trade_type'] = $filter['trade_type'];
        }

        if ( ! empty($filter['trade_bn']))
        {
            $where['trade_bn'] = $filter['trade_bn'];
        }

        if ( ! empty($filter['source']))
        {
            $where['source'] = $filter['source'];
        }

        if ( ! empty($filter['source_bn']))
        {
            $where['source_bn'] = $filter['source_bn'];
        }

        if ( ! empty($filter['type']))
        {
            $where['type'] = $filter['type'];
        }

        if ( ! empty($where))
        {
            $db->where($where);
        }

        if ( ! empty($filter['pa_id']))
        {
            $paIds = is_array($filter['pa_id']) ? $filter['pa_id'] : array($filter['pa_id']);

            $db->whereIn('pa_id', $paIds);
        }

        $result = $db->orderBy('id', 'DESC')->get()->toArray();

        if (empty($result))
        {
            return $return;
        }

        foreach ($result as $v)
        {
            $v = get_object_vars($v);
            $return[] = $v;
        }

        return $return;
    }

    // --------------------------------------------------------------------

    /**
     * 绑定账户
     *
     * @param   $paId           int     支付账户ID
     * @param   $accountCode    string  账户编码
     * @return  boolean
     */
    public function bindAccount($paId, $accountCode)
    {
        if (empty($paId) OR empty($accountCode))
        {
            return false;
        }

        $bindInfo = $this->getPaymentAccountBind($paId);

        if ( ! empty($bindInfo))
        {
            if ($bindInfo['account_code'] == $accountCode && $bindInfo['status'] == 1)
            {
                return true;
            }
            $data = array(
                'account_code'  => $accountCode,
                'status'        => 1,
                'update_time'   => time(),
            );

            $where = [
                'pa_id' => $paId,
            ];

            return app('api_db')->table('server_payment_account_bind')->where($where)->update($data);
        }

        $data = array(
            'pa_id'         => $paId,
            'account_code'  => $accountCode,
            'status'        => 1,
            'create_time'   => time(),
            'update_time'   => time(),
        );

        return app('api_db')->table('server_payment_account_bind')->insert($data);
    }

    // --------------------------------------------------------------------

    /**
     * 绑定规则
     *
     * @param   $paId           int     支付账户ID
     * @param   $ruleId         int     绑定规则
     * @return  boolean
     */
    public function bindRule($paId, $ruleId)
    {
        if (empty($paId) OR empty($ruleId))
        {
            return false;
        }

        $ruleList = $this->getPaymentAccountRule($paId);

        if ( ! in_array($ruleId, $ruleList))
        {
            $data = array(
                'pa_id'     => $paId,
                'rule_id'   => $ruleId,
            );

            return app('api_db')->table('server_payment_account_rule')->insert($data);
        }

        return true;
    }

    // --------------------------------------------------------------------

    /**
     * 获取支付账户列表
     *
     * @param   $paId     mixed   支付账户ID
     * @return  array
     */
    public function getPaymentAccountBind($paId)
    {
        $return = array();

        if (is_array($paId))
        {
            $result = app('api_db')->table('server_payment_account_bind')->whereIn('pa_id', $paId)->get()->toArray();
        }
        else
        {
            $result = app('api_db')->table('server_payment_account_bind')->where('pa_id', '=', $paId)->get()->toArray();
        }


        if (empty($result))
        {
            return $return;
        }

        foreach ($result as $v)
        {
            $v = get_object_vars($v);
            if ($v['status'] == 1)
            {
                $return[$v['pa_id']] = $v;
            }
        }

        return is_array($paId) ? $return : current($return);
    }


    // --------------------------------------------------------------------

    /**
     * 获取支付账户列表
     *
     * @param   $paId     mixed   支付账户ID
     * @return  array
     */
    public function getPaymentAccountRule($paId)
    {
        $return = array();

        if (is_array($paId))
        {
            $result = app('api_db')->table('server_payment_account_rule')->whereIn('pa_id', $paId)->get()->toArray();
        }
        else
        {
            $result = app('api_db')->table('server_payment_account_rule')->where('pa_id', '=', $paId)->get()->toArray();
        }

        if (empty($result))
        {
            return $return;
        }

        foreach ($result as $v)
        {
            $return[$v->pa_id][] = $v->rule_id;
        }

        return is_array($paId) ? $return : current($return);
    }

    // --------------------------------------------------------------------

    /**
     * 保存账户记录
     *
     * @param   $data     array   数据
     * @return  array
     */
    public function addAccountRecord($data)
    {
        $data['create_time'] = time();

        return app('api_db')->table('server_payment_account_records')->insertGetId($data);
    }

}
