<?php
/**
 * Created by PhpStorm.
 * User: chuanbin
 * Date: 2019-06-11
 * Time: 18:55
 */

namespace App\Api\Model\Bill;


class Payment
{
    /**
     * 查询一条支付单
     * @param $bill_id
     * @return mixed
     */
    public static function GetInfoByAppId($app_id){
//        $sql = "select * from server_bills_app  where app_id in (:app_id)";
//        echo $app_id;
        $app_info = app('api_db')->table('server_bills_app')->whereIn('app_id',$app_id)->get()->all();
        return $app_info;
    }

    public static function getAppRelationByCode($code){
        $list = app('api_db')->table('server_bills_code_relation')->whereIn('code',$code)->get()->all();
        return $list;
    }

    public static function addCodeRelation($data){
        $rzt = app('api_db')->table('server_bills_code_relation')->insert($data);
        return $rzt;
    }

}