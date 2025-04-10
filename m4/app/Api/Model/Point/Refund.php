<?php

namespace App\Api\Model\Point;
class Refund{

    /*
     * @todo 保存记录
     */
    public static function AddPointRefundRecord($save_data){
        if(empty($save_data)) return;
        $id = app('api_db')->table('server_point_member_refund_record')-> insertGetId ($save_data);
        return $id;
    }

    public static function GetPointRefundRecord($where){
        if(empty($where)) return;
        $sql = "select * from  `server_point_member_refund_record` where `use_type` = :use_type and use_obj = :use_obj ";
        $record_list   = app('api_db')->select($sql,$where);
        return $record_list;
    }

    public static function UpdatePointRefundRecord($update_data,$where){
        if(empty($update_data) || empty($where)) return false;
        $res = app('api_db')->table('server_point_member_refund_record')-> where ($where)->update($update_data);
        return $res;
    }


}
