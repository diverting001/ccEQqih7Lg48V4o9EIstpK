<?php

namespace App\Api\Model\Point;

class Point
{

    /**
     * 获取公司可用的积分渠道
     * @param $company_id
     */
    public static function GetCompanyChannel($company_id)
    {
        if (empty($company_id)) {
            return;
        }
        $sql = "select cc.company_id,c.channel,c.point_name,c.exchange_rate,c.point_type,c.point_version from server_point_channel_company as cc 
                  left join server_point_channel as c on c.channel = cc.channel where company_id = :company_id and cc.status = 1";
        $point_channel_list = app('api_db')->select($sql, ['company_id' => $company_id]);
        return $point_channel_list;
    }

    /**
     * 获取积分渠道信息
     * @param $channel
     */
    public static function GetChannelInfo($channel)
    {
        if (empty($channel)) {
            return;
        }
        $sql = "select * from server_point_channel where channel = :channel";
        $point_channel_info = app('api_db')->selectOne($sql, ['channel' => $channel]);
        return $point_channel_info;
    }

    public static function GetCompanyIdsByChannel($channel,$page,$pageSize){
        $sceneDb = app('api_db')->table('server_point_channel_company');
        $sceneDb = $sceneDb->where('channel', $channel);
        $sceneDb = $sceneDb->where('status', 1);
        $list = $sceneDb->forPage($page , $pageSize)->get()->toArray();
        if(count($list)>0){
            return array_column($list,'company_id');
        }
        return false;
    }

    /**
     * 获取公司积分渠道
     * 如果channel不为空获取1条信息, 为空获取多条信息
     *
     * @param int $company_id
     * @param int $channel
     * @return mixed
     * @author liuming
     */
    public static function GetCompanyPoin($company_id = 0, $channel = 0)
    {
        if (empty($channel)) {
            $sql = "select * from server_point_channel_company where company_id = :company_id";
            $company_point = app('api_db')->select($sql, ['company_id' => $company_id]);
        } else {
            $sql = "select * from server_point_channel_company where company_id = :company_id and channel = :channel";
            $company_point = app('api_db')->selectOne($sql, ['company_id' => $company_id, 'channel' => $channel]);
        }
        return $company_point;
    }

    /**
     * 获取公司积分渠道
     * @param $save_data
     * @return mixed
     */
    public static function AddCompanyPoin($save_data)
    {
        $sql = "INSERT INTO `server_point_channel_company` (`company_id`,`channel`,`status`) VALUE (:company_id,:channel,:status)";
        $res = app('api_db')->insert($sql, $save_data);
        return $res;
    }

    /**
     * 批量添加积分渠道下的公司
     * @param array $data
     * @return mixed
     */
    public static function BatchAddCompanyPoint($data = [])
    {
        return app('api_db')->table('server_point_channel_company')->insert($data);
    }

    /**
     * 获取公司积分渠道
     * @param $where
     * @param $save_data
     * @return mixed
     */
    public static function UpdateCompanyPoin($where, $save_data)
    {
        return app('api_db')->table('server_point_channel_company')->where($where)->update($save_data);
    }

    /**
     * 根据channel获取积分可配置类型值
     *
     * @param string $channel
     * @return mixed
     * @author liuming
     */
    public static function GetAdapterTypeByChannel($channel = '')
    {
        $sql = 'SELECT adapter_type FROM server_point_channel WHERE channel = :channel';
        return app('api_db')->selectOne($sql, ['channel' => $channel]);
    }

    /**
     * 获取所有积分渠道
     * @return array
     */
    public static function GetAllChannels()
    {
        $return = array();
        $list = app('api_db')->table('server_point_channel')->get()->toArray();
        foreach ($list as $item) {
            $return[] = get_object_vars($item);
        }
        return $return;
    }
}
