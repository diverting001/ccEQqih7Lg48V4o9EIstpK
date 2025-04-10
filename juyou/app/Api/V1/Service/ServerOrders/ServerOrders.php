<?php

namespace App\Api\V1\Service\ServerOrders;

use App\Api\Model\ServerOrders\ServerOrders as ServerOrdersModel;

/*
 * @todo 订单列表
 */

class ServerOrders
{
    /*
     * @todo 设置退款状态
     */
    public function Refand($request_data)
    {
        if (empty($request_data)) {
            return false;
        }
        $where = [
            'order_id' => $request_data['order_id'],
        ];

        $data = [
            'refund_status' => $request_data['refund_status']
        ];

        return ServerOrdersModel::Update($where, $data);
    }

    /*
     * @todo 获取订单ids
     */
    public function supplierOrderIds($request_data)
    {
        if (empty($request_data)) {
            return ['res' => false];
        }
        $data['res'] = 1;
        $data['list'] = app('api_db')->select('SELECT o.order_id,o.root_pid from server_orders o LEFT JOIN server_order_items oi on o.order_id=oi.order_id where oi.bn like \'' . $request_data['supplier_bn'] . '-%\' and create_time>' . $request_data['min_create_time']);
        return $data;
    }
}
