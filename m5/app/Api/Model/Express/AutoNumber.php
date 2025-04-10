<?php

namespace App\Api\Model\Express;


class AutoNumber
{
    /**
     * @param $num
     * @return array
     */
    public static function getAutoNumberByNum($num)
    {
        if (empty($num)) {
            return array();
        }

        $return = app('api_db')->table('server_express_auto_number')->where(array('num'=>$num))->first();

        return $return ? get_object_vars($return) : array();
    }

    /**
     * @param $data
     * @return false
     */
    public static function addAutoNumber($num, $corpCodes) {
        if (empty($num) || empty($corpCodes)) {
            return false;
        }

        $data = array(
            'num' => $num,
            'corp_codes' => $corpCodes,
            'create_time' => time()
        );
        return app('api_db')->table('server_express_auto_number')->insert($data);
    }
}
