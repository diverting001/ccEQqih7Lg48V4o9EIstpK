<?php

namespace App\Api\Model\Express\V2;


class Express
{
    //无信息
    const STATUS_EMPTY = 0;
    //揽收
    const STATUS_COLLECT = 1;
    //在途
    const STATUS_UNDERWAY = 2;
    //签收
    const STATUS_RECEIVED = 3;
    //退回签收
    const STATUS_RETURN_RECEIVED = 4;
    //派送中
    const STATUS_DELIVERY = 5;
    //退回途中
    const STATUS_RETURN_UNDERWAY = 6;
    //转投
    const STATUS_FORWARDING = 7;
    //清关
    const STATUS_CUSTOMS_CLEARANCE = 8;
    //拒签
    const STATUS_REFUSAL = 9;
    //问题
    const STATUS_BLOCKED = 10;



    //需要拉取
    const NEED_PULL = 1;
    //无需拉取
    const NO_NEED_PULL = 0;

    //需要订阅
    const NEED_SUBSCRIBE = 1;
    //不需要订阅
    const NO_NEED_SUBSCRIBE = 0;

    /**
     * 状态
     * @var string[]
     */
    public static $statusMsg = array(
        self::STATUS_EMPTY => '无轨迹',
        self::STATUS_COLLECT => '已揽收',
        self::STATUS_UNDERWAY => '在途',
        self::STATUS_DELIVERY => '派送中',
        self::STATUS_RECEIVED => '已签收',
        self::STATUS_REFUSAL => '拒签',
        self::STATUS_RETURN_UNDERWAY => '退回途中',
        self::STATUS_RETURN_RECEIVED => '已退回签收',
        self::STATUS_CUSTOMS_CLEARANCE => '清关',
        self::STATUS_FORWARDING => '转投',
        self::STATUS_BLOCKED => '疑难',
    );

    /**
     * @param $filed
     * @param $where
     * @param $limit
     * @param $order
     * @return array
     */
    public static function getExpressList(
        $filed = '*',
        $where = [],
        $limit = 20,
        $order = 'id asc'
    )
    {
        if (empty($where)) {
            return array();
        }

        if ($filed != '*') {
            $filed = explode(',', $filed);
        }

        if (is_array($where)) {
            $return = app('api_db')->table('server_express')->select($filed)->where($where)->limit($limit)->orderByRaw($order)->get()->map(function ($value) {
                return (array)$value;
            })->toArray();
        } else {
            $return = app('api_db')->table('server_express')->select($filed)->whereRaw($where)->limit($limit)->orderByRaw($order)->get()->map(function ($value) {
                return (array)$value;
            })->toArray();
        }

        return $return;
    }

    /**
     * 获取物流详情
     *
     * @param   $company    string      物流公司
     * @param   $num        string      物流单号
     * @return  array
     */
    public static function getExpressDetail($company, $num)
    {
        $return = array();

        if (empty($company) or empty($num)) {
            return $return;
        }

        $where = [
            'company' => $company,
            'num' => $num
        ];

        $return = app('api_db')->table('server_express')->where($where)->first();

        return $return ? get_object_vars($return) : array();
    }

    /**
     * 获取物流详情
     *
     * @param   $company    string      物流公司
     * @param   $num        string      物流单号
     * @return  array
     */
    public static function getChannelExpressDetail($channelCompany, $num)
    {
        $return = array();

        if (empty($channelCompany) or empty($num)) {
            return $return;
        }

        $where = [
            'channel_company' => $channelCompany,
            'num' => $num
        ];

        $return = app('api_db')->table('server_express')->where($where)->first();

        return $return ? get_object_vars($return) : array();
    }


    /**
     * 新增物流信息
     * @param $data
     * @return bool
     */
    public static function addExpress($data)
    {
        if (empty($data['company']) or empty($data['num'])) {
            return false;
        }

        if (!app('api_db')->table('server_express')->insert($data)) {
            return false;
        }

        return true;
    }


    /**
     * @param $id
     * @param $data
     * @return bool
     */
    public static function updateExpressById($id, $data)
    {
        if (empty($id) or empty($data)) {
            return false;
        }

        $where = array(
            'id' => $id,
        );

        $data['update_time'] = time();

        if (!app('api_db')->table('server_express')->where($where)->update($data)) {
            return false;
        }

        return true;
    }

}
