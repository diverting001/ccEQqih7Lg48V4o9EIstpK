<?php

namespace App\Api\Model\Point;
class Record{

    /*
     * @todo 保存记录
     */
    public static function AddPointRecord($save_data){
        if(empty($save_data)) return;
        $sql = "INSERT INTO `server_point_member_record` ( `channel`, `use_type`, `use_obj`, `money`, `point`, `company_id`, `member_id`, `status`, `create_time`, `update_time`)
                  VALUES(:channel, :use_type, :use_obj , :money , :point, :company_id, :member_id, :status , :create_time, :update_time);";
        $res = app('api_db')->insert($sql,$save_data);
        return $res;
    }

    public static function GetPointRecord($where){
        if(empty($where)) return;
        $sql = "select * from  `server_point_member_record` where `use_type` = :use_type and use_obj = :use_obj ";
        $record_list   = app('api_db')->selectOne($sql,$where);
        return $record_list;
    }

    public static function UpdatePointRecord($update_data,$where){
        if(empty($update_data) || empty($where)) return false;
        $res = app('api_db')->table('server_point_member_record')-> where ($where)->update($update_data);
        return $res;
    }


}
