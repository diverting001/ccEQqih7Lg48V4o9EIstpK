<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-10-18
 * Time: 15:25
 */

namespace App\Api\Model\Delivery;


class Template
{
    public static function Create($tempInfo)
    {
        $tempInfo['create_time'] = time();
        $tempInfo['update_time'] = time();
        try {
            $id = app('api_db')->table('server_delivery_template')->insertGetId($tempInfo);
        } catch (\Exception $e) {
            $id = false;
        }
        return $id;
    }

    public static function Update($tempBn, $tempInfo)
    {
        $tempInfo['update_time'] = time();
        try {
            $status = app('api_db')->table('server_delivery_template')
                ->where('template_bn', $tempBn)
                ->update($tempInfo);
        } catch (\Exception $e) {
            $status = false;
        }
        return $status;
    }

    public static function Find($templateBn)
    {
        $where = array(
            'template_bn' => strval($templateBn)
        );
        return app('api_db')
            ->table('server_delivery_template')
            ->where($where)
            ->first();
    }
}
