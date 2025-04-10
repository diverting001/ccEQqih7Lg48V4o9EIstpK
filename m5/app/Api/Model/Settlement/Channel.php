<?php
/**
 * neigou_service
 *
 * @package     api
 * @author      xupeng
 * @since       Version
 * @filesource
 */

namespace App\Api\Model\Settlement;

/**
 * 账户 model
 *
 * @package     api
 * @category    Model
 * @author      xupeng
 */
class Channel
{
    /**
     * 创建渠道
     *
     * @param   $data       array      账户数据
     * @return  boolean
     */
    public function createChannel($data)
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

        return app('api_db')->table('server_settlement_channels')->insertGetId($data);
    }

    // --------------------------------------------------------------------

    /**
     * 更新渠道
     *
     * @param   $scId       int        通道ID
     * @param   $data       array      账户数据
     * @return  boolean
     */
    public function updateChannel($scId, $data)
    {
        if (empty($data))
        {
            return false;
        }

        if ( ! isset($data['update_time']))
        {
            $data['update_time'] = time();
        }

        return app('api_db')->table('server_settlement_channels')->where(['sc_id' => $scId])->update($data);
    }

    // --------------------------------------------------------------------

    /**
     * 获取渠道信息-名字
     *
     * @param   $scId       int      渠道ID
     * @return  array
     */
    public function getChannelInfo($scId)
    {
        $where = [
            'sc_id' => $scId,
        ];

        $return = app('api_db')->table('server_settlement_channels')->where($where)->first();

        return $return ? get_object_vars($return) : array();
    }

    // --------------------------------------------------------------------

    /**
     * 获取渠道信息-名字
     *
     * @param   $name       string      名称
     * @return  array
     */
    public function getChannelInfoByName($name)
    {
        $where = [
            'name' => $name,
        ];

        $return = app('api_db')->table('server_settlement_channels')->where($where)->first();

        return $return ? get_object_vars($return) : array();
    }

    // --------------------------------------------------------------------

    /**
     * 绑定支付方式
     *
     * @param   $scId           string      渠道ID
     * @param   $companyId      int         公司ID
     * @param   $paymentType    string      支付类型
     * @param   $paymentItem    string      支付方式
     * @return  mixed
     */
    public function addBindPayment($scId, $companyId, $paymentType, $paymentItem)
    {
        $data = array(
            'sc_id'         => $scId,
            'company_id'    => $companyId,
            'payment_type'  => $paymentType,
            'payment_item'  => $paymentItem,
            'create_time'   => time(),
        );

        $result = app('api_db')->table('server_settlement_channel_payment')->insert($data);

        return $result ? true : false;
    }

    // --------------------------------------------------------------------

    /**
     * 删除支付方式
     *
     * @param   $id     mixed   ID
     * @return  mixed
     */
    public function deleteBindPayment($id)
    {
        $db = app('api_db')->table('server_settlement_channel_payment');

        if (is_array($id))
        {
            $db->whereIn('id', $id);
        }
        else
        {
            $db->where(['id' => $id]);
        }

        $result = $db->delete();

        return $result ? true : false;
    }

    // --------------------------------------------------------------------

    /**
     * 更新支付方式
     *
     * @param   $id     mixed   ID
     * @param   $scId   int     通道ID
     * @return  mixed
     */
    public function updateBindPaymentById($id, $scId)
    {
        $db = app('api_db')->table('server_settlement_channel_payment');

        $result = $db->where(['id' => $id])->update(array('sc_id' => $scId));

        return $result !== false ? true : false;
    }
    // --------------------------------------------------------------------

    /**
     * 绑定规则
     *
     * @param   $scId   int  渠道ID
     * @param   $srId   int  规则ID
     * @return  mixed
     */
    public function bindRule($scId, $srId)
    {
        $where = [
            'sc_id' => $scId,
        ];

        $result = app('api_db')->table('server_settlement_channel_rule')->where($where)->delete();

        $data = array(
            'sc_id'         => $scId,
            'sr_id'         => $srId,
            'create_time'   => time(),
        );

        $result = app('api_db')->table('server_settlement_channel_rule')->insert($data);

        return $result ? true : false;
    }

    // --------------------------------------------------------------------

    /**
     * 绑定支付账户
     *
     * @param   $scId           int         渠道ID
     * @param   $accountType    string      账号类型
     * @param   $accountBn      string      账户编码
     * @return  mixed
     */
    public function bindAccount($scId, $accountType, $accountBn)
    {
        $where = [
            'sc_id' => $scId,
        ];

        $result = app('api_db')->table('server_settlement_channel_account')->where($where)->delete();

        if ( ! empty($accountBn))
        {
            if (is_array($accountBn))
            {
                foreach ($accountBn as $bn)
                {
                    $data = array(
                        'sc_id' => $scId,
                        'account_type' => $accountType,
                        'account_bn' => $bn,
                    );
                    $result = app('api_db')->table('server_settlement_channel_account')->insert($data);
                }
            }
            else
            {
                $data = array(
                    'sc_id'         => $scId,
                    'account_type'  => $accountType,
                    'account_bn'    => $accountBn,
                );

                $result = app('api_db')->table('server_settlement_channel_account')->insert($data);
            }
        }

        return $result !== false ? true : false;
    }

    // --------------------------------------------------------------------

    /**
     * 获取渠道记录数量
     *
     * @param   $filter       array      类型
     * @return  array
     */
    public function getChannelCount($filter = array())
    {
        $db = app('api_db')->table('server_settlement_channels');

        if ( ! empty($filter))
        {
            $where = array();
            if ( ! empty($filter['sc_id']))
            {
                if (is_array($filter['sc_id']))
                {
                    $db->whereIn('sc_id', $filter['sc_id']);
                }
                else
                {
                    $where['sc_id'] = $filter['sc_id'];
                }
            }

            if ( ! empty($filter['name']))
            {
                $where[] = ['name', 'like', '%'. $filter['name']. '%'];
            }

            if ( ! empty($filter['code']))
            {
                $where['code'] = $filter['code'];
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
     * 获取渠道记录列表
     *
     * @param   $limit      int     数量
     * @param   $offset     int     起始位置
     * @param   $filter     array   过滤
     * @return  array
     */
    public function getChannelList($limit = 20, $offset = 0, $filter = array())
    {
        $return = array();

        $db = app('api_db')->table('server_settlement_channels');
        if ( ! empty($filter))
        {
            $where = array();
            if ( ! empty($filter['sc_id']))
            {
                if (is_array($filter['sc_id']))
                {
                    $db->whereIn('sc_id', $filter['sc_id']);
                }
                else
                {
                    $where['sc_id'] = $filter['sc_id'];
                }
            }

            if ( ! empty($filter['name']))
            {
                $where[] = ['name', 'like', '%'. $filter['name']. '%'];
            }

            if ( ! empty($filter['code']))
            {
                $where['code'] = $filter['code'];
            }

            if ( ! empty($where))
            {
                $db->where($where);
            }
        }

        $result = $db->offset($offset)->limit($limit)->orderBy('sc_id', 'DESC')->get()->toArray();

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
     * 获取渠道记录列表
     *
     * @param   $channelIds     mixed     渠道ID
     * @return  array
     */
    public function getChannelPaymentById($channelIds)
    {
        $return = array();

        if ( ! is_array($channelIds))
        {
            $where = array(
                array('sc_id', $channelIds),
            );

            $result = app('api_db')->table('server_settlement_channel_payment')->where($where)->get()->toArray();
        }
        else
        {
            $result = app('api_db')->table('server_settlement_channel_payment')->whereIn('sc_id', $channelIds)->get()->toArray();
        }

        if (empty($result))
        {
            return $return;
        }

        foreach ($result as $v)
        {
            $v = get_object_vars($v);
            $return[$v['sc_id']] = $v;
        }

        return is_array($channelIds) ? $return : current($return);
    }

    // --------------------------------------------------------------------

    /**
     * 获取账户记录列表
     *
     * @param   $channelIds     mixed     渠道ID
     * @return  array
     */
    public function getChannelAccountById($channelIds)
    {
        $return = array();

        if ( ! is_array($channelIds))
        {
            $where = array(
                array('sc_id', $channelIds),
            );

            $result = app('api_db')->table('server_settlement_channel_account')->where($where)->get()->toArray();
        }
        else
        {
            $result = app('api_db')->table('server_settlement_channel_account')->whereIn('sc_id', $channelIds)->get()->toArray();
        }

        if (empty($result))
        {
            return $return;
        }

        foreach ($result as $v)
        {
            $v = get_object_vars($v);
            $return[$v['sc_id']][$v['account_type']][] = $v['account_bn'];
        }

        return is_array($channelIds) ? $return : current($return);
    }

    // --------------------------------------------------------------------

    /**
     * 获取规则记录列表
     *
     * @param   $channelIds     mixed     渠道ID
     * @return  array
     */
    public function getChannelRuleById($channelIds)
    {
        $return = array();

        if ( ! is_array($channelIds))
        {
            $where = array(
                array('sc_id', $channelIds),
            );

            $result = app('api_db')->table('server_settlement_channel_rule')->where($where)->get()->toArray();
        }
        else
        {
            $result = app('api_db')->table('server_settlement_channel_rule')->whereIn('sc_id', $channelIds)->get()->toArray();
        }

        if (empty($result))
        {
            return $return;
        }

        foreach ($result as $v)
        {
            $v = get_object_vars($v);
            $return[$v['sc_id']] = $v['sr_id'];
        }

        return is_array($channelIds) ? $return : current($return);
    }

    // --------------------------------------------------------------------

    /**
     * 获取支付
     *
     * @param   $companyId     mixed     公司ID
     * @return  array
     */
    public function getPaymentByCompany($companyId)
    {
        $return = array();

        if ( ! is_array($companyId))
        {
            $where = array(
                array('company_id', $companyId),
            );

            $result = app('api_db')->table('server_settlement_channel_payment')->where($where)->get()->toArray();
        }
        else
        {
            $result = app('api_db')->table('server_settlement_channel_payment')->whereIn('company_id', $companyId)->get()->toArray();
        }

        if (empty($result))
        {
            return $return;
        }

        foreach ($result as $v)
        {
            $v = get_object_vars($v);
            $return[$v['company_id']][] = $v;
        }

        return is_array($companyId) ? $return : current($return);
    }

    // --------------------------------------------------------------------

    /**
     * 获取渠道记录列表
     *
     * @param   $filter     array   过滤
     * @param   $limit      int     数量
     * @param   $offset     int     起始位置
     * @return  array
     */
    public function getChannelRecordList($filter = array(), $limit = 20, $offset = 0)
    {
        $return = array();

        $db = app('api_db')->table('server_settlement_channel_records');
        if ( ! empty($filter))
        {
            $where = array();
            if ( ! empty($filter['sc_id']))
            {
                if (is_array($filter['sc_id']))
                {
                    $db->whereIn('sc_id', $filter['sc_id']);
                }
                else
                {
                    $where['sc_id'] = $filter['sc_id'];
                }
            }

            if ( ! empty($filter['type']))
            {
                if (is_array($filter['type']))
                {
                    $db->whereIn('type', $filter['type']);
                }
                else
                {
                    $where['type'] = $filter['type'];
                }
            }

            if ( ! empty($filter['trade_type']))
            {
                $where['trade_type'] = $filter['trade_type'];
            }

            if ( ! empty($filter['trade_bn']))
            {
                $where['trade_bn'] = $filter['trade_bn'];
            }

            if ( ! empty($where))
            {
                $db->where($where);
            }
        }

        $result = $db->offset($offset)->limit($limit)->orderBy('id', 'DESC')->get()->toArray();

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
     * 新增渠道记录
     *
     * @param   $data   array   数据
     * @return  mixed
     */
    public function addChannelRecord($data)
    {
        if (empty($data))
        {
            return false;
        }

        if ( ! isset($data['create_time']))
        {
            $data['create_time'] = time();
        }

        return app('api_db')->table('server_settlement_channel_records')->insertGetId($data);
    }

}
