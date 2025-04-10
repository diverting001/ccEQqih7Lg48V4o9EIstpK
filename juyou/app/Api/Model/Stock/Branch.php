<?php

namespace App\Api\Model\Stock;

class Branch
{

    /*
     * @todo 获取虚拟仓库
     */
    public static function SelectBranch($channel, $province = '', $city = '', $county = '', $town = '')
    {
        if (empty($channel)) {
            return [];
        }
        $sql = "select * from server_stock_branch where channel = :channel";
        $branch = app('api_db')->selectOne($sql, ['channel' => $channel]);
        return $branch;
    }

    /*
     * @todo 获取默认仓库
     */
    public static function GetDefaultBranch()
    {
        $sql = "select * from server_stock_branch where channel = 'DEFAULT'";
        $branch = app('api_db')->selectOne($sql);
        return $branch;
    }

}
