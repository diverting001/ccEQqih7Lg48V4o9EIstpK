<?php

namespace App\Api\Logic\CustomerCare\V2;

use App\Api\Logic\Service as OrderService;
use App\Api\Model\AfterSale\V2\AfterSaleProducts;
use App\Api\Model\AfterSale\V2\AfterSale;

class AfterSaleCheck
{

    /**订单参数创建检查
     * @param array $params
     * @param string $error
     * @return bool
     */
    public function checkCreateParams($params = array(), &$error = '')
    {
        if (!$params['order_id']) {
            $error = '订单号不能为空';
            return false;
        }

        if (!$params['type'] || !in_array($params['type'], array(1, 2))) {
            $error = '申请类型错误';
            return false;
        }

        if (!$params['reason_desc']) {
            $error = '申请原因错误';
            return false;
        }

        //商品检查
        if (!$this->checkProducts($params['products_data'], $error)) {
            return false;
        }

        //换货地址检查
        if ($params['type'] == 2 && !$this->checkRegionInfo($params['region_info'], $error)) {
            return false;
        }

        //联系人检查
        if (!$this->checkMemeberInfo($params['contact_info'], $error)) {
            return false;
        }

        //操作人信息检查
        if (!$this->checkOperator($params['operator_info'], $error)) {
            return false;
        }

        return true;
    }

    /**
     * 检查售后单的合理性
     * @param array $params
     */
    public function checkCreateLegal($params = array(), $order_info = array(), &$error = '')
    {

        //订单号只能使用子单
        if (!empty($order_info['split_orders'])) {
            $error = '订单号不能为主单';
            return false;
        }

        //状态判断
        if ($order_info['pay_status'] != 2 || $order_info['status'] != 3) {
            $error = '订单状态错误';
            return false;
        }

        $order_product_bn = array();
        $product_bn_num = array();
        foreach ($order_info['items'] as $item) {
            $order_product_bn[] = $item['bn'];
            $product_bn_num[$item['bn']] = $item['nums'];
        }

        $product_exists = true;
        $product_num = true;
        $apply_product_data = array();
        foreach ($params['products_data'] as $product_item) {
            //申请商品合理性检查
            if (!in_array($product_item['product_bn'], $order_product_bn)) {
                $product_exists = false;
                break;
            }

            //单次提交货品总数量
            if ($product_item['nums'] > $product_bn_num[$product_item['product_bn']]) {
                $product_num = false;
                break;
            }

            $apply_product_data[] = array(
                'apply_product_bn' => $product_item['product_bn'],
                'apply_product_num' => $product_item['nums']
            );
        }

        if (!$product_exists) {
            $error = '售后商品不存在';
            return false;
        }

        if (!$product_num) {
            $error = '售后商品数量超限';
            return false;
        }

        //根订单货品数量检查
        $totalRootProductNumCheck = $this->checkRootProductNum($order_info, $apply_product_data, $product_bn_num, $error);
        if ($totalRootProductNumCheck === false) {
            return false;
        }

        return true;
    }

    /** 检查在途的货品状态
     * 1、有在途的货品退换货都不可以
     * 2、所有售后超过订单数量则不可以
     * 2、无论换多少次都是可以进行售后
     * @param string $root_pid
     * @return bool
     */
    private function checkRootProductNum($order_info = array(), $apply_product_data = array(), $order_product_num = array(), &$error = '')
    {
        $AfterSale = new AfterSale();
        $after_sale_data = $AfterSale->getListByRootPid($order_info['root_pid']);
        if (!$after_sale_data) {
            return true;
        }

        $productMdl = new AfterSaleProducts();
        $after_product_data = $productMdl->getListByAfterSaleBns(array_keys($after_sale_data));
        foreach ($after_product_data as $after_sale_bn => &$product_section) {
            foreach ($product_section as &$product_item_section) {
                $product_item_section['status'] = $after_sale_data[$after_sale_bn]['status'];
                $product_item_section['is_reissue'] = $after_sale_data[$after_sale_bn]['is_reissue'];
                $product_item_section['after_type'] = $after_sale_data[$after_sale_bn]['after_type'];
            }
        }

        $unfinished_bn = '';
        $total_product_num_data = array();

        $finish_product_num_data = array();

        foreach ($apply_product_data as $apply_item) {

            $apply_bn = $apply_item['apply_product_bn'];
            $apply_total_num = 0;
            $finish_total_num = 0;
            foreach ($after_product_data as $after_product) {
                foreach ($after_product as $after_product_item) {
                    //在途判断
                    if ($apply_bn == $after_product_item['product_bn']) {
                        if (!$unfinished_bn &&
                            (
                                in_array($after_product_item['status'], array(1, 2, 4, 5, 9, 10, 11, 12, 14)) ||
                                ($after_product_item['status'] == 7 && $after_product_item['is_reissue'] == 1) ||
                                ($after_product_item['status'] == 13 && $after_product_item['is_reissue'] == 1)
                            )
                        ) {
                            $unfinished_bn = $apply_bn . '_货品存在未完成售后';
                        }

                        //退货总数量判断
                        if (
                            $after_product_item['after_type'] == 1 &&
                            (
                                in_array($after_product_item['status'], array(1, 2, 4, 5, 6, 9, 10, 11, 12, 14)) ||
                                ($after_product_item['status'] == 7 && $after_product_item['is_reissue'] == 1) ||
                                ($after_product_item['status'] == 13 && $after_product_item['is_reissue'] == 1)
                            )
                        ) {
                            $apply_total_num += $after_product_item['nums'];
                        }

                        //退货完成数量判断
                        if($after_product_item['after_type'] == 1 && $after_product_item['status'] == 6){
                            $finish_total_num += $after_product_item['nums'];
                        }
                    }
                }
            }

            //在途或者完成的数量
            $total_product_num_data[$apply_bn] = $apply_total_num;

            //完成的数量
            $finish_product_num_data[$apply_bn] = $finish_total_num;
        }

        //状态判断
        if ($unfinished_bn) {
            $error = $unfinished_bn;
            return false;
        }

        //数量判断
        $beyond_bn = '';
        foreach ($apply_product_data as $apply_item) {
            $product_bn = $apply_item['apply_product_bn'];
            if ($apply_item['apply_product_num'] + $total_product_num_data[$product_bn] > $order_product_num[$product_bn]) {
                $beyond_bn = $product_bn;
            }
        }

        if ($beyond_bn) {
            $error = $beyond_bn . '_货品申请数量超限';
            return false;
        }

        //换货商品完成数量判断
        $finish_bn = '';
        foreach ($apply_product_data as $apply_item) {
            $product_bn = $apply_item['apply_product_bn'];
            if ($apply_item['apply_product_num'] + $finish_product_num_data[$product_bn] > $order_product_num[$product_bn]) {
                $finish_bn = $product_bn;
            }
        }

        if ($finish_bn) {
            $error = $finish_bn . '_货品申请数量超限';
            return false;
        }

        return true;

    }

    public function checkOrderInfo($order_id, &$error = '')
    {
        $service_logic = new OrderService();
        $order_data = $service_logic->ServiceCall('order_info', ['order_id' => $order_id]);
        if ('SUCCESS' != $order_data['error_code']) {
            $error = '订单信息错误';
            return false;
        }

        return $order_data['data'];
    }

    private function checkProducts($product = array(), &$error = '')
    {

        if (!$product) {
            $error = '货品不能为空';
            return false;
        }

        $is_true = true;
        foreach ($product as $item) {

            if (!$item['product_id']) {
                $error = '货品ID不能为空';
                $is_true = false;
                break;
            }

            if (!$item['product_bn']) {
                $error = '货品BN不能为空';
                $is_true = false;
                break;
            }

            if (!$item['nums']) {
                $error = '货品数量不能为空';
                $is_true = false;
                break;
            }

            if (!is_numeric($item['nums']) || $item['nums'] < 1) {
                $error = '货品数量错误';
                $is_true = false;
                break;
            }
        }

        if (!$is_true) {
            return false;
        }

        return true;
    }

    private function checkOperator($operator = array(), &$error = '')
    {
        if (!$operator) {
            $error = '操作人信息不能为空';
            return false;
        }

        if (!$operator['operator_name']) {
            $error = '操作者名称不能为空';
            return false;
        }

        if (!$operator['operator_desc']) {
            $error = '操作者步骤不能为空';
            return false;
        }

        return true;
    }

    private function checkMemeberInfo($memberInfo = array(), &$error = '')
    {
        if (!$memberInfo) {
            $error = '联系人信息不能为空';
            return false;
        }

        if (!$memberInfo['member_name']) {
            $error = '联系人名称不能为空';
            return false;
        }

        if (!$memberInfo['mobile']) {
            $error = '联系人电话不能为空';
            return false;
        }

        return true;
    }

    //换货地址检查
    private function checkRegionInfo($regionInfo = array(), &$error = '')
    {
        if (!$regionInfo) {
            $error = '换货地址不能为空';
            return false;
        }
        if (!$regionInfo['province']) {
            $error = '换货地址省份不能为空';
            return false;
        }
        if (!$regionInfo['city']) {
            $error = '换货地址城市不能为空';
            return false;
        }
        if (!$regionInfo['county']) {
            $error = '换货地址区县不能为空';
            return false;
        }
//        if (!$regionInfo['town']) {
//            $error = '换货地址乡镇不能为空';
//            return false;
//        }
        if (!$regionInfo['addr']) {
            $error = '换货详细地址不能为空';
            return false;
        }
        return true;
    }

}