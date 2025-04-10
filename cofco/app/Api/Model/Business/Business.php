<?php

namespace App\Api\Model\Business;

class Business
{

    public function __construct()
    {
        $this->_db = app('api_db');
    }

    public function createCode($data)
    {
        return $this->_db->table('server_business_code')->insertGetId($data);
    }

    public function getAppRow($app_code)
    {
        if (empty($app_code)) {
            return array();
        }
        $list = $this->_db->table('server_business_code_app')->where('app_code', $app_code)->get()->toArray();
        foreach ($list as $item) {
            $return[] = get_object_vars($item);
        }
        return $return;
    }

    public function getPlatFormRow($platform_code)
    {
        if (empty($platform_code)) {
            return array();
        }
        $list = $this->_db->table('server_business_code_platform')->where('platform_code',
            $platform_code)->get()->toArray();
        foreach ($list as $item) {
            $return[] = get_object_vars($item);
        }
        return $return;
    }


}
