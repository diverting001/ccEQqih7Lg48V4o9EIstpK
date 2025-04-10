<?php
/**
 * neigou_service-stock
 * @package     api
 * @author      xupeng
 * @since       Version
 * @filesource
 */

namespace App\Api\Model\Freight;

/**
 * 运费 Model
 *
 * @package     api
 * @category    model
 * @author        xupeng
 */
class Freight
{
    /**
     * 获取店铺运费的规则
     *
     * @param   $shopId     int|array   店铺ID
     * @param   $status     int    状态
     * @return  array
     */
    public function getShopFreight($shopId, $status = 1)
    {
        $return = array();

        if (empty($shopId)) {
            return $return;
        }

        is_array($shopId) OR $shopId = array($shopId);

        $where = [
            'status' => $status,
        ];

        $result = app('api_db')->table('server_freight_shop')->where($where)->whereIn('shop_id', $shopId)->get();

        if (empty($result)) {
            return $return;
        }

        foreach ($result as $v) {
            $return[$v->shop_id] = get_object_vars($v);
        }

        return $return;
    }

    // --------------------------------------------------------------------

    /**
     * 获取店铺运费的规则
     *
     * @param   $freightId     int|array    运费ID
     * @param   $status        int          状态
     * @return  array
     */
    public function getFreightInfo($freightId, $status = 1)
    {
        $return = array();

        if (empty($freightId)) {
            return $return;
        }

        is_array($freightId) OR $freightId = array($freightId);

        $where = [
            'status' => $status,
        ];

        $result = app('api_db')->table('server_freights')->where($where)->whereIn('id', $freightId)->get();

        if (empty($result)) {
            return $return;
        }

        foreach ($result as $v) {
            $return[$v->id] = get_object_vars($v);
        }

        return $return;
    }

}
