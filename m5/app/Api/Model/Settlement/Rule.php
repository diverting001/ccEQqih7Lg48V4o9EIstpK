<?php
/**
 * neigou_service-stock
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
class Rule
{
    /**
     * 获取规则信息通过名称
     *
     * @param   $name       string      名称
     * @return  array
     */
    public function getRuleInfoByName($name)
    {
        $where = [
            'name' => $name,
        ];

        $return = app('api_db')->table('server_settlement_channel_rules')->where($where)->first();

        return $return ? get_object_vars($return) : array();
    }

    // --------------------------------------------------------------------

    /**
     * 获取规则信息
     *
     * @param   $srId   int     规则ID
     * @return  array
     */
    public function getRuleInfo($srId)
    {
        $return = array();

        if (empty($srId))
        {
            return $return;
        }

        $where = [
            'sr_id' => $srId,
        ];
        $return = app('api_db')->table('server_settlement_channel_rules')->where($where)->first();

        return $return ? get_object_vars($return) : array();
    }

    // --------------------------------------------------------------------

    /**
     * 创建规则
     *
     * @param   $data       array      账户数据
     * @return  boolean
     */
    public function addRule($data)
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

        return app('api_db')->table('server_settlement_channel_rules')->insertGetId($data);
    }

    // --------------------------------------------------------------------

    /**
     * 获取规则数量
     *
     * @param   $filter     array   过滤条件
     * @return  array
     */
    public function getRuleCount($filter = array())
    {
        $db = app('api_db')->table('server_settlement_channel_rules');

        if ( ! empty($filter))
        {
            $where = array();
            if ( ! empty($filter['sr_id']))
            {
                if (is_array($filter['sr_id']))
                {
                    $db->whereIn('sr_id', $filter['sr_id']);
                }
                else
                {
                    $where['sr_id'] = $filter['sr_id'];
                }
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
     * 获取列表
     *
     * @param   $limit      int     页数
     * @param   $offset     int     起始位置
     * @param   $filter     array   过滤
     * @return  array
     */
    public function getRuleList($limit = 20, $offset = 0, $filter = array())
    {
        $return = array();

        $db =  app('api_db')->table('server_settlement_channel_rules');
        if ( ! empty($filter))
        {
            $where = array();
            if ( ! empty($filter['sr_id']))
            {
                if (is_array($filter['sr_id']))
                {
                    $db->whereIn('sr_id', $filter['sr_id']);
                }
                else
                {
                    $where['sr_id'] = $filter['sr_id'];
                }
            }

            if ( ! empty($where))
            {
                $db->where($where);
            }
        }

        $result = $db->limit($limit)->offset($offset)->orderBy('sr_id', 'ASC')->get()->toArray();

        if ( ! empty($result))
        {
            foreach ($result as $v)
            {
                $return[$v->sr_id] = get_object_vars($v);
            }
        }

        return $return;
    }

}
