<?php

namespace App\Api\V1\Service\DeliveryToB\Channel;

abstract class ADelivery
{

    abstract public function GetRule();

    public function ParseRule($param, $expression)
    {
        $subtotal = $param['subtotal'];

        //begin 因PHP7.2     create_function函数被废弃,故注释该代码 -- by 刘明 2018-09-17
        //$ret = create_function('$subtotal',$expression);
        //return $ret($subtotal);
        //注释 end

        //调用内名函数返回运费 int
        $function = function ($subtotal) use ($expression) {
            return eval($expression);
        };
        return $function($subtotal);
    }
}
