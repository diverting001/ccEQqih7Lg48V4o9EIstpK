<?php
namespace App\Api\Logic\CustomerCare\V1;

class AfterSale
{

    public function update(&$afterSaleModel,$where,$update){
        if(!$where || !$update){
            return false;
        }
        return $afterSaleModel->update($where, $update);
    }

    public function createLog(&$afterSaleModel,$param){
        $log['after_sale_bn'] = $param['after_sale_bn'];
        $log['operator_name'] = $param['operator_name'];
        $log['desc'] = $param['desc'];
        $log['status'] = $param['status'];
        $log['type'] = $param['operator_type'];
        $log['create_time'] = $param['create_time'] ? $param['create_time'] : time();
        return $afterSaleModel->addLog($log);
    }

    public function createWareHouse(&$afterSaleModel,$orderInfo,$param,$data){

        $time = time();
        $insert = array(
            'after_sale_bn' => $param['after_sale_bn'],
            'warehouse_bn' => date('YmdHi') . mt_rand(100, 999),
            'status' => 0,
            'wms_code' => $orderInfo['wms_code'],
            'order_id' => $orderInfo['order_id'],
            'after_type' => $data['after_type'],
            'express_code' => $param['express_code'],
            'express_no' => $param['express_no'],
            'create_time' => $param['create_time'] ? $param['create_time'] : $time,
            'update_time' => $param['update_time'] ? $param['update_time'] : $time
        );

        return $afterSaleModel->addWareHouse($insert);
    }

    public function formatParams($param = array()){
        if (isset($param['status']) && !empty($param['status'])) {
            $update['status'] = $param['status'];
        }

        $update['is_reissue'] = empty($param['is_reissue']) ? 0 : $param['is_reissue'];

        if (isset($param['customer_reason']) && !empty($param['customer_reason'])) {
            $update['customer_reason'] = $param['customer_reason'];
        }

        if (isset($param['service_reason']) && !empty($param['service_reason'])) {
            $update['service_reason'] = $param['service_reason'];
        }

        if (isset($param['service_desc']) && !empty($param['service_desc'])) {
            $update['service_desc'] = $param['service_desc'];
        }

        // 是否虚入
        if (isset($param['service_is_simulate_storage']) && !empty($param['service_is_simulate_storage'])) {
            $update['service_is_simulate_storage'] = intval($param['service_is_simulate_storage']);
        }

        //订单代号
        if (isset($param['express_code']) && !empty($param['express_code'])) {
            $update['express_code'] = $param['express_code'];
        }

        if (isset($param['express_no']) && !empty($param['express_no'])) {
            $update['express_no'] = $param['express_no'];
        }

        if (isset($param['express_pay_method']) && !empty($param['express_pay_method'])) {
            $update['express_pay_method'] = $param['express_pay_method'];
        }

        if (isset($param['update_time']) && !empty($param['update_time'])) {
            $update['update_time'] = $param['update_time'];
        } else {
            $update['update_time'] = time();
        }

        if (isset($param['pop_return_money']) && !empty($param['pop_return_money'])) {
            $update['pop_return_money'] = $param['pop_return_money'];
        }

        if (isset($param['pop_return_desc']) && !empty($param['pop_return_desc'])) {
            $update['pop_return_desc'] = $param['pop_return_desc'];
        }

        if (isset($param['refund_id']) && !empty($param['refund_id'])) {
            $update['refund_id'] = $param['refund_id'];
        }

        //寄回地址
        if (isset($param['shipping_address']) && !empty($param['shipping_address'])) {
            $update['shipping_address'] = $param['shipping_address'];
        }

        //寄回人姓名
        if (isset($param['shipping_member_name']) && !empty($param['shipping_member_name'])) {
            $update['shipping_member_name'] = $param['shipping_member_name'];
        }

        //寄回人电话
        if (isset($param['shipping_mobile']) && !empty($param['shipping_mobile'])) {
            $update['shipping_mobile'] = $param['shipping_mobile'];
        }

        if (isset($param['refund_extend']) && !empty($param['refund_extend'])) {
            $update['refund_extend'] = $param['refund_extend'];
        }

        if (isset($param['refund_memo']) && !empty($param['refund_memo'])) {
            $update['refund_memo'] = $param['refund_memo'];
        }

        //图片
        if (isset($param['pic']) && !empty($param['pic'])) {
            $update['pic'] = $param['pic'];
        }

        //换货时的收货信息
        if (isset($param['ship_province']) && !empty($param['ship_province'])) {
            $update['ship_province'] = $param['ship_province'];
        }
        if (isset($param['ship_city']) && !empty($param['ship_city'])) {
            $update['ship_city'] = $param['ship_city'];
        }
        if (isset($param['ship_county']) && !empty($param['ship_county'])) {
            $update['ship_county'] = $param['ship_county'];
        }
        if (isset($param['ship_town']) && !empty($param['ship_town'])) {
            $update['ship_town'] = $param['ship_town'];
        }
        if (isset($param['ship_addr']) && !empty($param['ship_addr'])) {
            $update['ship_addr'] = $param['ship_addr'];
        }
        if (isset($param['ship_name']) && !empty($param['ship_name'])) {
            $update['ship_name'] = $param['ship_name'];
        }
        if (isset($param['ship_mobile']) && !empty($param['ship_mobile'])) {
            $update['ship_mobile'] = $param['ship_mobile'];
        }

        if (isset($param['actual_reason_type']) && !empty($param['actual_reason_type'])) {
            $update['actual_reason_type'] = $param['actual_reason_type'];
        }
        if (!empty($param['is_pickup'])) {
            $update['is_pickup'] = $param['is_pickup'];
        }
        $update['is_sync_fail'] = 1;

        // 审核操作记录时间
        if (isset($param['status']) && in_array($param['status'], array(2, 3))){
            $update['review_time'] = time();
        }

        return $update;
    }
}
