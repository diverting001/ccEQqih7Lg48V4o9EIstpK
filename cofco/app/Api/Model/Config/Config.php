<?php

namespace App\Api\Model\Config;

class Config
{
    private static $_table_name = 'sdb_b2c_config';

    public static function findValueByKey($key)
    {
        $config = app('api_db')->connection('neigou_store')->table(self::$_table_name)->where('key', $key)->first(['value']);

        return $config->value ?? '';
    }
}
