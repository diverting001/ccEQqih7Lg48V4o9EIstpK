<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-05-14
 * Time: 20:05
 */

namespace App\Api\Model\PushMessage;


class PushMessageTypeConf
{
    /**
     * @param $systemCode
     * @return mixed
     */
    public static function getConfBySystemCodeAndType($systemCode, $messageType)
    {
        return app('api_db')
            ->table('server_push_message_type_conf')
            ->where('system_code', $systemCode)
            ->where('message_type', $messageType)
            ->first();
    }
}
