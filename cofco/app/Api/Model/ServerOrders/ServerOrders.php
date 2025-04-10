<?php
/**
 * Created by PhpStorm.
 * User: guke
 * Date: 2018/4/26
 * Time: 14:27
 */

namespace app\Api\Model\ServerOrders;

class ServerOrders
{
    /*
     * @todo ServiceOrders更新
     */
    public static function Update($where, $update_data)
    {
        if (empty($where) || empty($update_data)) {
            return false;
        }
        if (!isset($update_data['last_modified'])) {
            $update_data['last_modified'] = time();
        }
        $res = app('api_db')->table('server_orders')->where($where)->update($update_data);
        return $res;
    }

}
