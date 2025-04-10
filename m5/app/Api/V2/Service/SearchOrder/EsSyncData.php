<?php
namespace App\Api\V2\Service\SearchOrder;
use App\Api\Model\Order\Order as OrderModel;
use App\Api\V2\Service\SearchOrder\OrderDataSource\Es;

/**
 * Class EsSyncData
 * @package App\Api\V2\Service\SearchOrder
 * MQ订单消息 - 根据order_id同步数据到ES
 * 无需区分订单变更的消息类型，根据order_id & order_id关联的root_pid找到所有主单+子单一并创建/覆盖更新到ES
 */
class EsSyncData{
    public function SyncOrderData($order_ids){
        if (empty($order_ids)){
            return true;
        }

        // 获取MQ—order_id订单信息
        $where['order_id'] = [
            'type' => 'in',
            'value' => $order_ids
        ];

        $order_list = OrderModel::GetOrderList('order_id,root_pid', $where, count($order_ids));

        // 去重后的root_pid列表
        $root_pid_list = $this->objToArr($order_list, true, 'root_pid');

        // 获取所有主、子订单
        $where_full['root_pid'] = [
            'type' => 'in',
            'value' => $root_pid_list
        ];

        $full_order_list = OrderModel::GetFullOrderList($where_full);
        $full_order_list = $this->objToArr($full_order_list);

        // 格式化订单数据，添加商品信息
        $format_order_list = $this->formatOrderList($full_order_list);

        // 格式化为ES的索引结构数据，同步到ES
        $es = new Es();

        $err_msg = '';

        $sync_res = $es->CoverEsDoc($format_order_list, $err_msg);

        return $sync_res;
    }

    public function formatOrderList($order_list){
        $order_id_list = array_column($order_list, 'order_id');

        // 获取order_items
        $order_items_list = OrderModel::GetOrderItems($order_id_list);

        $format_list = array();

        foreach ($order_list as $order){
            $order_id = $order['order_id'];

            $product_list = [];

            foreach ($order_items_list[$order_id] as $item){
                $product_list[] = [
                    'bn' => $item->bn,
                    'name' => $item->name,
                    'nums' => $item->nums,
                    'price' => $item->price
                ];
            }

            $format_list[] = [
                'company_id' => $order['company_id'],
                'confirm_status' => $order['confirm_status'],
                'create_source' => $order['create_source'],
                'create_time' => $order['create_time'],
                'member_id' => $order['member_id'],
                'order_amount' => $order['final_amount'],
                'order_id' => $order['order_id'],
                'order_status' => $order['status'],
                'parent_id' => empty($order_id['pid']) ? '' : $order_id['pid'],
                'pay_status' => $order['pay_status'],
                'pop_owner_id' => $order['pop_owner_id'],
                'root_pid' => $order['root_pid'],
                'ship_status' => $order['ship_status'],
                'split' => $order['split'],
                'system_code' => $order['system_code'],
                'update_time' => $order['last_modified'],
                'order_category' => $order['order_category'],
                'extend_info_code' => $order['extend_info_code'],
                'product_list' => $product_list,
            ];
        }

        return $format_list;
    }

    // 对象转数组
    public function objToArr($obj, $is_column = false, $column = ''){
        $arr = json_decode(json_encode($obj), true);

        if ($is_column){
            $arr = array_unique(array_column($arr, $column));
        }

        return $arr;
    }
}
