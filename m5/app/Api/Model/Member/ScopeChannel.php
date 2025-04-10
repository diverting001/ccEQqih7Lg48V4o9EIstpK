<?php
/**
 *Create by PhpStorm
 *User:liangtao
 *Date:2021-7-14
 */

namespace App\Api\Model\Member;


class ScopeChannel
{
    private $_db;
    private $_table_scope_channel = 'server_member_scope_channel';

    public function __construct()
    {
        $this->_db = app( 'api_db' );
    }

    /**
     * 获取人员权限渠道
     * @return array
     */
    public function GetAllChannelList()
    {
        $channelData = $this->_db->table( $this->_table_scope_channel )->get()->toArray();

        $channels = [];

        if ( empty( $channelData ) )
        {
            return $channels;
        }

        foreach ( $channelData as $item )
        {
            $channels[] = $item->channel;
        }

        return $channels;
    }
}