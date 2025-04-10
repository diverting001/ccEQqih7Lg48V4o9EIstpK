<?php

namespace App\Api\Logic\CustomerCare\V2;

use App\Api\Model\AfterSale\V2\AfterSale as AfterSaleModel;
use App\Api\Model\AfterSale\V2\AfterSaleProducts;
use App\Api\Logic\AfterSaleNotify;
use App\Api\Model\AfterSale\V2\AfterSaleOperatorLog;

class AfterSale
{

    protected $allow_list_filter_fields = array(
        'after_sale_bn',
        'root_pid',
        'order_id',
        'create_time',
        'company_id',
        'status',
        'after_type',
        'service_name',
        'pop_owner_id',
        'aggregation_status',
        'is_reissue',
    );

    protected $allow_filter_condition = array(
        'eq', 'neq', 'between', 'in', 'like','not_in','egt','elt','gt','lt',
    );

    public function getAfterSaleList($params = array())
    {
        $page = $params['page'];
        $limit = $params['limit'];
        $filter = $params['filter_data'];
        $order = $params['order_data'];

        $afterSaleMdl = new AfterSaleModel();
        $after_sale_list = $afterSaleMdl->getList($page, $limit, $filter, $order);
        if (empty($after_sale_list)) {
            return array(
                'page' => $page,
                'limit' => $limit,
                'total_count' => 0,
                'after_sale_list' => []
            );
        }

        $after_sale_bns = [];
        foreach ($after_sale_list as $list) {
            $after_sale_bns[] = $list['after_sale_bn'];
        }

        $productModel = new AfterSaleProducts();
        $product_data = $productModel->getListByAfterSaleBns($after_sale_bns);
        foreach ($after_sale_list as &$list_item) {
            $list_item['product_data'] = $product_data[$list_item['after_sale_bn']] ? $product_data[$list_item['after_sale_bn']] : [];
        }

        $total_count = $afterSaleMdl->getCount($filter);
        return array(
            'page' => $page,
            'limit' => $limit,
            'total_count' => $total_count,
            'after_sale_list' => $after_sale_list
        );
    }

    public function find($params = array())
    {
        $filter = $params['filter_data'];
        $order = $params['order_data'];
        $afterSaleMdl = new AfterSaleModel();
        $after_sale_data = $afterSaleMdl->find($filter, $order);

        $productModel = new AfterSaleProducts();
        $product_data = $productModel->getListByAfterSaleBns($after_sale_data['after_sale_bn']);
        $after_sale_data['product_data'] = !empty($product_data) ? $product_data[$after_sale_data['after_sale_bn']] : [];

        return $after_sale_data;
    }

    /**
     * 创建售后单
     * @param array $params
     * @param array $order_info
     * @param string $error
     * @return bool
     */
    public function create($params = array(), $order_info = array(), &$error = '')
    {

        $ship_province = $params['region_info'] ? ($params['region_info']['province'] ? $params['region_info']['province'] : '') : '';
        $ship_city = $params['region_info'] ? ($params['region_info']['city'] ? $params['region_info']['city'] : '') : '';
        $ship_county = $params['region_info'] ? ($params['region_info']['county'] ? $params['region_info']['county'] : '') : '';
        $ship_town = $params['region_info'] ? ($params['region_info']['town'] ? $params['region_info']['town'] : '') : '';
        $ship_addr = $params['region_info'] ? ($params['region_info']['addr'] ? $params['region_info']['addr'] : '') : '';

        $ship_name = $params['contact_info'] ? ($params['contact_info']['member_name'] ? $params['contact_info']['member_name'] : '') : '';
        $ship_mobile = $params['contact_info'] ? ($params['contact_info']['mobile'] ? $params['contact_info']['mobile'] : '') : '';

        $time = time();
        $after_sale_bn = $this->getAfterSaleBn();

        //add
        $insert = array(
            'after_sale_bn' => $after_sale_bn,
            'order_id' => $params['order_id'],
            'root_pid' => $order_info['root_pid'],
            'company_id' => $order_info['company_id'],
            'member_id' => $order_info['member_id'],
            'status' => 1,
            'after_type' => $params['type'],
            'customer_reason' => $params['reason_desc'],
            'pop_owner_id' => $order_info['pop_owner_id'],
            'wms_code' => $order_info['wms_code'],
            'ship_province' => $ship_province,
            'ship_city' => $ship_city,
            'ship_county' => $ship_county,
            'ship_town' => $ship_town,
            'ship_addr' => $ship_addr,
            'ship_name' => $ship_name,
            'ship_mobile' => $ship_mobile,
            'pic' => $params['images'],
            'is_sync_fail' => 0,
            'create_time' => $time,
            'update_time' => $time,
        );

//        app('db')->beginTransaction();

        //主单
        $afterSaleMdl = new AfterSaleModel();
        $res = $afterSaleMdl->create($insert);
        if ($res === false) {
//            app('db')->rollback();
            $error = '创建售后单失败';
            return false;
        }

        //货品入库
        $product = array();
        foreach ($params['products_data'] as $product_item) {
            $product[] = array(
                'after_sale_bn' => $after_sale_bn,
                'product_bn' => $product_item['product_bn'],
                'product_id' => $product_item['product_id'],
                'nums' => $product_item['nums'],
                'create_time' => $time,
            );
        }
        $product_model = new AfterSaleProducts();
        $product_result = $product_model->create($product);
        if ($product_result === false) {
//            app('db')->rollback();
            $error = '创建售后单货品失败';
            return false;
        }

        //操作记录
        $add_log = array(
            'after_sale_bn' => $after_sale_bn,
            'operator_name' => $params['operator_info']['operator_name'],
            'operator_type' => $params['operator_info']['operator_type'],
            'operator_desc' => $params['operator_info']['operator_desc'],
            'status' => 1,
            'create_time' => $time
        );
        $log_result = $this->createOperatorLog($add_log);
        if ($log_result === false) {
//            app('db')->rollback();
            $error = '创建售后单日志失败';
            return false;
        }

        //通知业务新增
        $post_data = array(
            'order_id' => $order_info['order_id'],
            'member_id' => $order_info['member_id'],
            'status' => 1,
            'pop_owner_id' => $order_info['pop_owner_id'],
            'wms_code' => $order_info['wms_code'],
            'customer_reason' => $params['reason_desc'],
            'after_sale_bn' => $after_sale_bn,
            'return_type' => $params['type'],
            'ship_province' => $ship_province,
            'ship_city' => $ship_city,
            'ship_county' => $ship_county,
            'ship_town' => $ship_town,
            'ship_addr' => $ship_addr,
            'ship_name' => $ship_name,
            'ship_mobile' => $ship_mobile,
            'pic' => $params['pic'],
            'products_data' => $product,
            'create_time' => $time,
            'update_time' => $time,
        );

        $notify_logic = new AfterSaleNotify();
        $third_result = $notify_logic->createThird($post_data, $error);
        if (!$third_result) {
//            app('db')->rollback();
            $error = '创建三方售后失败';
            return false;
        }

//        app('db')->commit();

        return $after_sale_bn;
    }

    private function createOperatorLog($params = array())
    {
        $add = array(
            'after_sale_bn' => $params['after_sale_bn'],
            'operator_name' => $params['operator_name'],
            'type' => $params['operator_type'],
            'desc' => $params['operator_desc'],
            'status' => $params['status'],
            'create_time' => $params['create_time'],
        );

        $log_model = new AfterSaleOperatorLog();

        return $log_model->create($add);
    }


    /**
     * 格式化条件
     * @param array $params
     * @return array
     */
    public function format_condition($params = array())
    {

        $filter_array = [];

        foreach ($params['filter_data'] as $field => $values) {
            $type = strtolower($values['type']);
            if (!$type || !$values['value'] || !in_array($type, $this->allow_filter_condition)) {
                continue;
            }

            $filter_array[$field] = array(
                'type' => $type,
                'value' => $values['value']
            );
        }

        $order_by = '';
        if ($params['order_data']) {
            foreach ($params['order_data'] as $field => $order) {
                $order_by .= $field . ' ' . $order;
            }
        }

        return [
            'page' => $params['page'] ? $params['page'] : 1,
            'limit' => $params['limit'] ? $params['limit'] : 50,
            'filter_data' => $filter_array,
            'order_data' => $order_by
        ];
    }

    /**
     * 检查条件筛选项是否存在
     * @param array $params
     * @return bool
     */
    public function check_list_condition($params = array())
    {

        if (!$params['filter_data']) {
            return false;
        }

        $is_exist = true;
        foreach ($params['filter_data'] as $filter => $values) {
            if (!in_array($filter, $this->allow_list_filter_fields)) {
                $is_exist = false;
            }
        }

        return $is_exist ? true : false;
    }

    /**
     * 生成售后单号
     * @return string
     */
    public function getAfterSaleBn()
    {
        return date('YmdHi') . mt_rand(100, 999);
    }


}
