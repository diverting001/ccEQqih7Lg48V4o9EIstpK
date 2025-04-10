<?php

namespace App\Api\Model\AfterSale\V2;

class AfterSaleOperatorLog
{
    private static $_channel_name = 'service';

    public function __construct()
    {
        $this->_db = app('api_db');
    }

    public function create($param)
    {
        if (empty($param)) {
            return false;
        }
        return $this->_db->table('server_after_sales_log')->insert($param);
    }
}