<?php



namespace App\Api\Model\Express;

/**
 * 物流 model
 *
 * @package     api
 * @category    Model
 * @author        xupeng
 */
class channel
{

    public static function getConfigByChannel($channel) {
        if (empty($channel)) {
            return array();
        }

        $where = array(
            'channel'=>$channel
        );

        $return =app('api_db')->table('server_express_channel')->where($where)->first();

        return $return ? get_object_vars($return) : array();

    }

    public static function getDefaultChannel($isExternal) {

        $where = array(
            'is_default'=>1,
            'is_external'=>$isExternal ? 1 : 0
        );

        $return = app('api_db')->table('server_express_channel')->where($where)->first();

        return $return ? get_object_vars($return) : array();

    }

    public static function getChannelByFilter($filter) {

        $return = app('api_db')->table('server_express_channel')->where($filter)->first();

        return $return ? get_object_vars($return) : array();

    }

    public static function getChannelListByFilter($filter) {

        $return = app('api_db')->table('server_express_channel')->where($filter)->get()->map(function ($value) {
            return (array)$value;
        })->toArray();;

        return $return;

    }


}
