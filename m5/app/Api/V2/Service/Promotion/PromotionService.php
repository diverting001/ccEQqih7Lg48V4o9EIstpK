<?php
/**
 * Created by PhpStorm.
 * User: chuanbin
 * Date: 2020-03-12
 * Time: 16:23
 */

namespace App\Api\V2\Service\Promotion;


class PromotionService
{
    public static function filter_result($calc_result){
        $calc['limit_buy'] = [];
        $calc['freeshipping'] = [];
        $calc['present'] = [];
        foreach ($calc_result as $r_key=>$r_value){
            if($calc['limit_buy']['status']==false){
                if($r_value['data']['limit_buy']){
                    $calc['limit_buy']['data'] = $r_value['data']['limit_buy'];
                    $calc['limit_buy']['status'] = $r_value['status'];
                    $calc['limit_buy']['msg'] = $r_value['msg'];
                    $calc['limit_buy']['code'] = $r_value['code'];
                } else {
                    $calc['limit_buy']['data'] = [];
                    $calc['limit_buy']['status'] = false;
                    $calc['limit_buy']['msg'] = '不限购';
                    $calc['limit_buy']['code'] = 'Y999';
                }
            }

            if($calc['freeshipping']['status']==false){
                if($r_value['data']['freeshipping']){
                    $calc['freeshipping']['status'] = true;
                    $calc['freeshipping']['msg'] = '免邮';
                    $calc['freeshipping']['data'] = [];
                    $calc['freeshipping']['code'] = $r_value['code'];
                } else {
                    $calc['freeshipping']['status'] = false;
                    $calc['freeshipping']['msg'] = '不免邮';
                    $calc['freeshipping']['data'] = [];
                    $calc['freeshipping']['code'] = 'Y999';
                }
            }


            if($calc['present']['status']==false) {
                if ($r_value['data']['present']) {
                    $calc['present']['data'] = $r_value['data']['present'];
                    $calc['present']['status'] = $r_value['status'];
                    $calc['present']['msg'] = $r_value['msg'];
                    $calc['present']['code'] = $r_value['code'];
                } else {
                    $calc['present']['status'] = false;
                    $calc['present']['msg'] = '无赠品';
                    $calc['present']['data'] = [];
                    $calc['present']['code'] = 'Y999';
                }
            }
        }
        return $calc;
    }


}