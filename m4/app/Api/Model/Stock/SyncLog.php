<?php

namespace App\Api\Model\Stock;

class SyncLog
{

    public static function Save($data)
    {
        $sql = "INSERT INTO `server_stock_sync_log` (`product_bn`,`branch_id`,`type`, `time_str`, `time`, `text`)VALUES( ?,?,?,?,?,?);";
        $res = app('api_db')->insert($sql,
            [$data['product_bn'], $data['branch_id'], $data['type'], date('Y-m-d H:i:s'), time(), $data['text']]);
        return $res;
    }

}
