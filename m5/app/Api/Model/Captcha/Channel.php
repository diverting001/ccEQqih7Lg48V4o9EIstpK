<?php

namespace App\Api\Model\Captcha;

use Illuminate\Support\Facades\DB;

class Channel
{
    /**
     * 获取验证码渠道
     * @param $channelId
     * @return mixed
     */
    public function findChannelByChannelId($channelId)
    {
        return app('api_db')->table('server_captcha_channel') ->where('channel_id', $channelId) ->first(['channel_id', 'channel', 'type','config']);
    }
}
