<?php

namespace App\Api\Model\Address;

class Address
{

    public function __construct()
    {
        $this->_db = app('api_db');
    }

    public function create($data)
    {
        $address_id = $this->_db->table('server_member_address')->insertGetId($data);
        if ($data['def_addr'] == 1) {
            $this->setDefAddr($address_id, $data['member_id']);
        }
        return $address_id;
    }

    public function update($id, $save)
    {
        if (empty($id)) {
            return false;
        }
        $res = $this->_db->table('server_member_address')->where('addr_id', $id)->update($save);
        if ($res !== false && $save['def_addr'] == 1) {
            $this->setDefAddr($id, $save['member_id']);
        }
        return $res;
    }

    private function setDefAddr($def_address_id, $member_id = null)
    {
        if (empty($member_id)) {
            $member_id = $this->_db->table('server_member_address')->where('addr_id',
                $def_address_id)->value('member_id');
        }
        $this->_db->table('server_member_address')->where([
            ['addr_id', '!=', $def_address_id],
            ['member_id', $member_id]
        ])->update(['def_addr' => 0]);
    }

    public function delete($id, $member_id)
    {
        if (empty($id) || empty($member_id)) {
            return false;
        }

        return $this->_db->table('server_member_address')->where([
            ['addr_id', $id],
            ['member_id', $member_id],
        ])->delete();
    }

    public function getRow($id)
    {
        if (empty($id)) {
            return array();
        }
        $list = $this->_db->table('server_member_address')->where('addr_id', $id)->first();
        return get_object_vars($list);
    }

    public function getList($member_id)
    {
        if (empty($member_id)) {
            return array();
        }
        $list = $this->_db->table('server_member_address')->where('member_id', $member_id)->orderBy('def_addr',
            'desc')->orderBy('addr_id', 'desc')->get()->toArray();
        foreach ($list as $item) {
            $return[] = get_object_vars($item);
        }
        return $return;
    }

    public function search($data)
    {
        if (empty($data) || !is_array($data)) {
            return array();
        }

        $filter = array();
        foreach ($data as $key => $item) {
            $filter[] = array($key, $item);
        }

        $list = $this->_db->table('server_member_address')->where($filter)->orderBy('addr_id',
            'desc')->get()->toArray();
        foreach ($list as $item) {
            $return[] = get_object_vars($item);
        }
        return $return;
    }

    public function count($member_id)
    {
        return $this->_db->table('server_member_address')->where('member_id', $member_id)->count();
    }

}
