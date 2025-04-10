<?php
/**
 * neigou_service-stock
 * @package     api
 * @author      xupeng
 * @since       Version
 * @filesource
 */

namespace App\Api\Model\Order;

/**
 * 分销商 model
 *
 * @package     api
 * @category    Controller
 * @author        xupeng
 */
class ConfirmConfig
{
    /**
     * 获取配置列表
     *
     * @return  array
     */
    public function getConfigList()
    {
        $return = array();

        $where = [
            'status' => 1,
        ];
        $data = app('api_db')->table('server_order_confirm_config')->where($where)->orderBy('weight', 'DESC')->get()->toArray();

        if (empty($data))
        {
            return $return;
        }

        foreach ($data as $v)
        {
            $return[] = get_object_vars($v);
        }

        return $return;
    }

}
