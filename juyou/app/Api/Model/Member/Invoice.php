<?php

namespace App\Api\Model\Member;

class Invoice
{
    public function __construct()
    {
        $this->_db = app('api_db');
    }

    public function getRow($member_id = 0)
    {
        if (empty($member_id)) {
            return array();
        }
        $list = $this->_db->table('server_member_invoice')->where('member_id', $member_id)->first();
        $data = get_object_vars($list);
        unset($data['id']);
        unset($data['member_id']);
        return $data;
    }

    public function save($param)
    {
        if (empty($param) || empty($param['member_id'])) {
            return false;
        }

        $time = time();
        $invoice = $this->getRow($param['member_id']);
        if ($invoice) {
            $save = array(
                'type' => $param['type'],
                'title' => $param['title'],
                'company_name' => $param['company_name'],
                'tax_number' => $param['tax_number'],
                'email' => $param['email'],
                'update_time' => $time,
            );
            $res = $this->_db->table('server_member_invoice')->where('member_id', $param['member_id'])->update($save);
        } else {
            $insert = array(
                'member_id' => $param['member_id'],
                'type' => $param['type'],
                'title' => $param['title'],
                'tax_number' => $param['tax_number'],
                'company_name' => $param['company_name'],
                'email' => $param['email'],
                'create_time' => $time,
                'update_time' => $time,
            );
            $res = $this->_db->table('server_member_invoice')->insertGetId($insert);
        }
        return $res;
    }
}
