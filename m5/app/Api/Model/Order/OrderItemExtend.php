<?php

namespace App\Api\Model\Order;

class OrderItemExtend
{
    public static function add($data)
    {
        try {
            return app('api_db')->table('server_order_item_extend')->insert($data);
        } catch (\Exception $exception) {
            return false;
        }
    }
}
