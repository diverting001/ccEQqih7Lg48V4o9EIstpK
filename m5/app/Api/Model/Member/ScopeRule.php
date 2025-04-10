<?php
/**
 *Create by PhpStorm
 *User:liangtao
 *Date:2021-7-14
 */

namespace App\Api\Model\Member;

class ScopeRule
{
    private $_db;
    private $_table_scope_rule_channel = 'server_member_scope_rule';

    public function __construct()
    {
        $this->_db = app('api_db');
    }

    public function findRuleByBn($ruleBn = '')
    {
        $return = [];

        if (!$ruleBn) {
            return $return;
        }

        $data = $this->_db->table($this->_table_scope_rule_channel)->where("rule_bn", $ruleBn)->first();
        if (empty($data)) {
            return $return;
        }

        return get_object_vars($data);
    }

    public function getRuleListByBn($ruleBns = [])
    {
        $return = [];

        if (!$ruleBns) {
            return $return;
        }

        $data = $this->_db->table($this->_table_scope_rule_channel)->whereIn("rule_bn", $ruleBns)->get();
        if (empty($data)) {
            return $return;
        }

        $format = [];

        foreach ($data as $item) {
            $format[$item->channel][] = get_object_vars($item);
        }

        return $format;
    }

    public function create($data = [])
    {
        if (empty($data)) {
            return false;
        }

        return $this->_db->table($this->_table_scope_rule_channel)->insert($data);
    }

    public function upateByRuleByBn($ruleBn, $data)
    {
        return $this->_db->table($this->_table_scope_rule_channel)->where("rule_bn", $ruleBn)->update($data);
    }
}
