<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-05-14
 * Time: 20:05
 */

namespace App\Api\Model\PushMessage;


class PushMessageConf
{
    /**
     * 获取要通知的系统配置
     * @param $systemCode
     * @return mixed
     */
    public static function getConfBySystemCode($systemCode)
    {
        return app('api_db')
            ->table('server_push_message_conf')
            ->where('system_code', $systemCode)
            ->first();
    }
}
