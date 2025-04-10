<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-09-25
 * Time: 10:24
 */

namespace App\Api\Logic\Rule;


abstract class SyncRule
{
    abstract public function run($channel, $filter = [], $page = 1, $pageSize = 20);

    protected function getRuleBn()
    {
        do {
            $ruleBn       = date('YmdHis') . rand(1000, 9999);
            $accountIsset = app('api_db')->table('server_rules')->where('rule_bn', $ruleBn)->first();
        } while ($accountIsset);

        return $ruleBn;
    }
}
