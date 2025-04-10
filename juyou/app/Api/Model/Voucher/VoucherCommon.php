<?php
/**
 * Created by PhpStorm.
 * User: chuanbin
 * Date: 2017/11/9
 * Time: 19:47
 */

namespace App\Api\Model\Voucher;


class VoucherCommon
{
    private $_db;

    public function __construct()
    {
        $this->_db = app('api_db')->connection('neigou_store');
    }

    public function getMemberIdListByMemberId($member_id = 0){
        if(!$member_id) return array();
        $sql = 'select * from sdb_b2c_cas_members where member_id = :member_id';
        $result = $this -> _db -> selectOne($sql,array($member_id));
        if(!$result) return array();
        $guid = $result->guid;
        $sql = 'select * from sdb_b2c_cas_members where guid = :guid';
        $result = $this->_db -> select($sql,array($guid));
        $member_id = array();
        foreach ($result as $k => $v) {
            $member_id[] = $v->member_id;
        }
        return $member_id;
    }

    public function getMemberList($guid = 0){
        if(!$guid) return array();
        $sql = 'select * from sdb_b2c_cas_members where guid = :guid';
        $result = $this->_db  -> select($sql,array($guid));
        $member_id = array();
        foreach ($result as $k => $v) {
            $member_id[] = $v->member_id;
        }
        return $member_id;
    }

}