<?php



namespace App\Api\Model\Express;

/**
 * 物流 model
 *
 * @package     api
 * @category    Model
 * @author        xupeng
 */
class Dlycorp
{

    /**
     * @param $corp_code
     * @param $channel
     * @return array
     */
    public static function getDlycorpByCode($corp_code)
    {
        $return = array();

        if (empty($corp_code))
        {
            return $return;
        }

        $where = [
            'corp_code'   => $corp_code,
        ];

        $return = app('api_db')->connection('neigou_store')->table('sdb_b2c_dlycorp')->where($where)->first();

        return $return ? get_object_vars($return) : array();
    }

    /**
     * @param $corp_code
     * @param $channel
     * @return array
     */
    public static function getDlycorpMapping($code, $channel, $code_type = 'channel')
    {
        $return = array();

        if (empty($code) OR empty($channel))
        {
            return $return;
        }

        if ($code_type == 'channel') {

            $where = [
                'logi_code'   => $code,
                'channel'       => $channel
            ];
        } else {
            $where = [
                'corp_code'   => $code,
                'channel'       => $channel
            ];
        }

        $return = app('api_db')->connection('neigou_store')->table('sdb_b2c_dlycorp_mapping')->where($where)->first();

        return $return ? get_object_vars($return) : array();
    }

}
