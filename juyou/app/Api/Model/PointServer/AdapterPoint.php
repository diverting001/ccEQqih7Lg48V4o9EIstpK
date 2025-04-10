<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-03-04
 * Time: 11:04
 */

namespace App\Api\Model\PointServer;


class AdapterPoint
{
    public static function GetAdapter($channel)
    {
        if (!$channel) {
            return null;
        }
        return app('api_db')
            ->table('server_new_point_adapter_point')
            ->where("channel", strval($channel))
            ->first();
    }
}
