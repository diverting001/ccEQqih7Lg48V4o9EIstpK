<?php

namespace App\Api\Model\Message;

use Illuminate\Support\Facades\DB;

class Channel
{
    const TYPE_SMS = 1; //短信
    const TYPE_EMAIL = 2; //邮件
    const TYPE_MEMBER_KEY = 3; // 站内信
    const TYPE_QYWX = 4; //企业微信
    const TYPE_QYWXTEXT = 5; //企业微信文本
    public function findPlatform($channel)
    {
        $db = app('api_db');
        return $db->table('server_message_channel as channel')
            ->leftJoin('server_message_platform_config as config', 'config.id', '=', 'channel.platform_config_id')
            ->where('channel', $channel)
            ->first(['platform_id', 'platform_config', 'channel.id']);
    }

    /**
     * Notes:查询渠道信息
     * @param int $id
     * @return mixed
     * Author: liuming
     * Date: 2021/1/15
     */
    public function findChannelRows($where = []){
        $db = app('api_db');
        return $db->table('server_message_channel as channel')
            ->leftJoin('server_message_platform_config as config', 'config.id', '=', 'channel.platform_config_id')
            ->where($where)
            ->first(['platform_id', 'platform_config', 'channel.id','channel.type','channel.channel']);
    }

    /**
     * Notes: 获取channel列表
     * @param array $where
     * @return mixed
     * Author: liuming
     * Date: 2021/1/18
     */
    public function findList($where = [],$exWhere = []){
        $db = app('api_db');
        $query = $db->table('server_message_channel as channel')
            ->leftJoin('server_message_platform_config as config', 'config.id', '=', 'channel.platform_config_id')
            ->select(['channel','platform_id', 'platform_config', 'channel.id','channel.type','created_at']);
        if ($where){
            $query->where($where);
        }

        if ($exWhere){
            foreach ($exWhere as $whereV){
                if ($whereV['express'] == 'in'){
                    $query->whereIn($whereV['key'],$whereV['value']);
                }else{
                    $query->where($whereV['key'],$whereV['express'],$whereV['value']);
                }
            }
        }
        return $query->get();
    }

    /**
     * Notes: 获取渠道的模板数量
     * @param array $channelIdList
     * @return mixed
     * Author: liuming
     * Date: 2021/1/18
     */
    public function findTemplateCountByChannelIdList($channelIdList = []){
        $db = app('api_db');
        return $db->table('server_message_template_channel')
            ->select(DB::raw('count(*) as count, channel_id'))
            ->whereIn('channel_id',$channelIdList)
            ->groupBy('channel_id')
            ->get();
    }

    /**
     * Notes: 判断渠道是否存在
     * @param $field
     * @param $value
     * @return mixed
     * Author: liuming
     * Date: 2021/1/20
     */
    public function channelExists($field, $value)
    {
        return $this->db->table('server_message_channel')->where($field, $value)->exists();
    }


    /**
     * Notes: 获取模板绑定的channel信息
     * @param $templateId
     * @return array
     * Author: liuming
     * Date: 2021/1/29
     */
    public function findChannelByTemplateId($templateId)
    {
        $db = app('api_db');
        $list = $db->table('server_message_channel as channel')
            ->select('channel.*')
            ->leftJoin('server_message_template_channel as template_channel','template_channel.channel_id','=','channel.id')
            ->where('template_channel.template_id',$templateId)
            ->get();
        return $list;
    }


    /**
     * Notes: 获取channelIds
     * @param $templateId
     * @return mixed
     * Author: liuming
     * Date: 2021/3/15
     */
    public function findChannelIdListByTemplateId($templateId){
        $db = app('api_db');
        return $db->table('server_message_template_channel')
            ->select(['channel_id'])
            ->where('template_id',$templateId)
            ->get();
    }

    public function findChannelQywxChannelId()
    {
        $db = app('api_db');
        return $db->table('server_message_channel as channel')
            ->where('type', self::TYPE_QYWX)
            ->where('channel', '企业微信图片消息')
            ->first(['id','channel']);
    }

    /**
     * 通过id获取渠道列表
     * @param $ids
     * @param string $fields
     * @return mixed
     */
    public function getListByIds($ids, $fields = '*')
    {
        $db = app('api_db');
        return $db->table('server_message_channel')
            ->whereIn('id', $ids)
            ->select($fields)
            ->get();
    }
}
