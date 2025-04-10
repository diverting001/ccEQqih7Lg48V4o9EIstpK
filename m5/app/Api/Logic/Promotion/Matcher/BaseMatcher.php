<?php
/**
 * Created by PhpStorm.
 * User: chuanbin
 * Date: 2020-06-09
 * Time: 11:39
 */

namespace App\Api\Logic\Promotion\Matcher;


class BaseMatcher
{
    public function isBatchLimit($config):bool
    {
        return false;
    }

    //统一返回
    public function output($code,$msg,$data){
        $ret['status'] = $code;
        $ret['msg'] = $msg;
        $ret['data'] = $data;
        return $ret;
    }

}