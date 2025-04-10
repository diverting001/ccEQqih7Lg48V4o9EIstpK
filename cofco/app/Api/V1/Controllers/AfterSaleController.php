<?php

namespace App\Api\V1\Controllers;

use App\Api\Common\Controllers\BaseController;
use App\Api\Logic\Mq;
use App\Api\Model\AfterSale\AfterSale;
use App\Api\Logic\AfterSaleNotify;
use App\Api\Logic\AfterSaleStatistics as Statistics;
use App\Api\Logic\Service as Service;
use App\Api\Model\AfterSale\V2\AfterSaleProducts;
use App\Api\Logic\CustomerCare\V2\AfterSale as AfterSaleV2Logic;
use Illuminate\Http\Request;

class AfterSaleController extends BaseController
{

    public function __construct()
    {
        $this->AfterSaleModel = new AfterSale();
    }

    /*
     * 创建售后订单
     */
    public function create(Request $request)
    {
        $param = $this->getContentArray($request);
        \Neigou\Logger::Debug('after_sale_create', array('data' => json_encode($param)));
        if (
            empty($param['order_id'])
            || empty($param['member_id'])
            || empty($param['product_id'])
            || empty($param['product_bn'])
            || empty($param['product_num'])
            || empty($param['after_type'])
            || empty($param['status'])
            || empty($param['customer_reason'])
            || empty($param['operator_type'])
            || empty($param['operator_name'])
            || empty($param['ship_name'])
            || empty($param['ship_mobile'])
        ) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat($param, 10001);
        }

        if ($param['after_type'] == 2 && (
                empty($param['ship_province'])
                || empty($param['ship_city'])
                || empty($param['ship_county'])
                || empty($param['ship_addr']))
        ) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat($param, 10001);
        }

        //检查订单是否存在
        $service_logic = new Service();
        $orderData = $service_logic->ServiceCall('order_info', ['order_id' => $param['order_id']]);
        if ('SUCCESS' == $orderData['error_code']) {
            $orderInfo = $orderData['data'];
        } else {
            $this->setErrorMsg('订单信息错误');
            return $this->outputFormat(null, 10002);
        }

        //订单号只能使用子单
        if (!empty($orderInfo['split_orders'])) {
            $this->setErrorMsg('订单号不能为主单');
            return $this->outputFormat(null, 10002);
        }

        //状态判断
        if ($orderInfo['pay_status'] != 2 || $orderInfo['status'] != 3) {
            $this->setErrorMsg('订单状态错误');
            return $this->outputFormat(null, 10002);
        }

        $orderBn = array();
        $bnNum = array();
        foreach ($orderInfo['items'] as $item) {
            $orderBn[] = $item['bn'];
            $bnNum[$item['bn']] = $item['nums'];
        }

        //货品检查
        if (!in_array($param['product_bn'], $orderBn)) {
            $this->setErrorMsg('参数提交错误');
            return $this->outputFormat(null, 10003);
        }

        //【根订单】货品总数量检查
        $totalRootProductNum = $this->AfterSaleModel->getAllRootOrderSaleNum($orderInfo['root_pid'],
            $param['product_bn']);
        if ($totalRootProductNum === false) {
            $this->setErrorMsg('参数提交错误');
            return $this->outputFormat(null, 10003);
        }

        if ($bnNum[$param['product_bn']] - $totalRootProductNum < $param['product_num']) {
            $this->setErrorMsg('货品数量超出总商品个数');
            return $this->outputFormat(null, 10004);
        }

        //单次提交数量检查
        if ($param['product_num'] > $bnNum[$param['product_bn']]) {
            $this->setErrorMsg('货品数量提交错误');
            return $this->outputFormat(null, 10004);
        }

        //货品数量提交总数量检查
        $totalProductNum = $this->AfterSaleModel->getAllAfterSaleOrder($param['order_id'], $param['product_bn']);

        if ($totalProductNum === false) {
            $this->setErrorMsg('参数提交错误');
            return $this->outputFormat(null, 10003);
        }

        if ($bnNum[$param['product_bn']] - $totalProductNum < $param['product_num']) {
            $this->setErrorMsg('货品数量超出总个数');
            return $this->outputFormat(null, 10004);
        }

        //是否存在有未处理的售后单
        $afterSaleList = $this->AfterSaleModel->getList('where',
            array(array('order_id', $param['order_id']), array('product_id', $param['product_id'])));
        foreach ($afterSaleList as $list) {
            if (in_array($list['status'], array(1, 2, 4, 5, 9, 10, 11, 12, 14))) {
                $this->setErrorMsg('存在有未完成的售后单');
                return $this->outputFormat(null, 10005);
            }
            if (($list['status'] == 7 && $list['is_reissue'] == 1) || ($list['status'] == 13 && $list['is_reissue'] == 1)) {
                $this->setErrorMsg('存在有未完成的售后单');
                return $this->outputFormat(null, 10005);
            }
        }

        //人员检查
        if ($orderInfo['member_id'] != $param['member_id']) {
            $this->setErrorMsg('人员信息错误');
            return $this->outputFormat(null, 10006);
        }

        $time = time();
//        $afterSaleBn = date('YmdHi') . mt_rand(100, 999);
        $after_sale_v2_logic = new AfterSaleV2Logic();
        $afterSaleBn =$after_sale_v2_logic->getAfterSaleBn();

        //add
        $insert = array(
            'after_sale_bn' => $afterSaleBn,
            'order_id' => $param['order_id'],
            'root_pid' => $orderInfo['root_pid'],
            'company_id' => $orderInfo['company_id'],
            'member_id' => $param['member_id'],
            'product_id' => $param['product_id'],
            'product_bn' => $param['product_bn'],
            'product_num' => $param['product_num'],
            'status' => $param['status'],
            'after_type' => $param['after_type'],
            'customer_reason' => $param['customer_reason'],
            'pop_owner_id' => $orderInfo['pop_owner_id'],
            'wms_code' => $orderInfo['wms_code'],
            'ship_province' => $param['ship_province'],
            'ship_city' => $param['ship_city'],
            'ship_county' => $param['ship_county'],
            'ship_town' => $param['ship_town'],
            'ship_addr' => $param['ship_addr'],
            'ship_name' => $param['ship_name'],
            'ship_mobile' => $param['ship_mobile'],
            'pic' => $param['pic'],
            'create_time' => $time,
            'update_time' => $time,
        );

        $res = $this->AfterSaleModel->create($insert);
        if ($res === false) {
            $this->setErrorMsg('提交失败');
            return $this->outputFormat(null, 10007);
        }

        //货品入库
        $product = array(
            'after_sale_bn'=>$afterSaleBn,
            'product_bn'=>$param['product_bn'],
            'product_id'=>$param['product_id'],
            'nums'=>$param['product_num'],
            'create_time'=>$time,
        );
        $product_model = new AfterSaleProducts();
        $product_result = $product_model->create($product);
        if ($product_result === false) {
            app('db')->rollback();
            $error = '创建售后单货品失败';
            return false;
        }

        //通知业务新增
        $post_data = array(
            'order_id' => $param['order_id'],
            'member_id' => $param['member_id'],
            'product_id' => $param['product_id'],
            'product_bn' => $param['product_bn'],
            'product_num' => $param['product_num'],
            'status' => $param['status'],
            'pop_owner_id' => $orderInfo['pop_owner_id'],
            'wms_code' => $orderInfo['wms_code'],
            'customer_reason' => $param['customer_reason'],
            'after_sale_bn' => $afterSaleBn,
            'return_type' => $param['after_type'],
            'ship_province' => $param['ship_province'],
            'ship_city' => $param['ship_city'],
            'ship_county' => $param['ship_county'],
            'ship_town' => $param['ship_town'],
            'ship_addr' => $param['ship_addr'],
            'ship_name' => $param['ship_name'],
            'ship_mobile' => $param['ship_mobile'],
            'pic' => $param['pic'],
            'create_time' => $time,
            'update_time' => $time,
        );

        $err = '';
        $afterLogic = new AfterSaleNotify();
        $thirdResult = $afterLogic->createThird($post_data, $err);

        if (!$thirdResult) {
            $this->setErrorMsg('通知失败' . $err);
            return $this->outputFormat(null, 10008);
        }

        //更新状态
        $this->AfterSaleModel->update(array('after_sale_bn' => $afterSaleBn), array('is_sync_fail' => 0));

        //写入Log
        $desc = "";
        switch ($param['operator_type']) {
            case 1:
                $desc = "客户发起申请";
                break;
            case 2:
                $desc = "POP客户发起申请";
                break;
            case 3:
                $desc = "MIS发起申请";
                break;
            default:
                $desc = "客户发起申请";
        }
        $log['after_sale_bn'] = $afterSaleBn;
        $log['operator_name'] = $param['operator_name'];
        $log['desc'] = $desc;
        $log['status'] = $param['status'];
        $log['type'] = $param['operator_type'];
        $log['create_time'] = $time;
        $this->AfterSaleModel->addLog($log);
        \Neigou\Logger::General('after_sale_create_log', array(
            'data' => json_encode($insert),
            'remark' => json_encode($post_data),
            'sparam1' => $err,
            'reason' => json_encode($log)
        ));
        $this->setErrorMsg('success');
        return $this->outputFormat(array('after_sale_bn' => $afterSaleBn), 0);
    }


    /*
     * 更新数据
     */
    public function update(Request $request)
    {
        $param = $this->getContentArray($request);
        if (empty($param['after_sale_bn']) || empty($param['operator_name']) || empty($param['operator_type']) || empty($param['desc'])) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat(null, 20001);
        }
        \Neigou\Logger::Debug('after_sale_update_notify_update_1', array('data' => json_encode($param)));
        //判断是否存在
        $data = $this->AfterSaleModel->getOne(array('after_sale_bn' => $param['after_sale_bn']));
        if (empty($data)) {
            $this->setErrorMsg('数据错误');
            return $this->outputFormat(null, 20002);
        }

        //检查订单是否存在
        $service_logic = new Service();
        $orderData = $service_logic->ServiceCall('order_info', ['order_id' => $data['order_id']]);
        if ('SUCCESS' == $orderData['error_code']) {
            $orderInfo = $orderData['data'];
        } else {
            $this->setErrorMsg('订单信息错误');
            return $this->outputFormat(null, 20003);
        }
        \Neigou\Logger::Debug('after_sale_update_notify_update_2', array('data' => json_encode($orderData)));

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

        $update['is_sync_fail'] = 1;

        //创建仓库单
        $time = time();
        if ($param['status'] == 4) {
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
            $res = $this->AfterSaleModel->addWareHouse($insert);
            if ($res === false) {
                $this->setErrorMsg('仓库单创建失败');
                return $this->outputFormat(null, 20004);
            }
        }

        //更新售后单
        $where['after_sale_bn'] = $param['after_sale_bn'];
        $res = $this->AfterSaleModel->update($where, $update);
        if ($res === false) {
            $this->setErrorMsg('更新失败');
            return $this->outputFormat(null, 20004);
        }

        if ($param['status'] == 6) {
            Mq::AfterSaleFinish($param['after_sale_bn']);
        }

        //通知业务
        $err = '';
        $afterLogic = new AfterSaleNotify();
        $wmsCode = $orderInfo['wms_code'];
        $res = $afterLogic->updateThird($wmsCode, $param, $err);
        if (!$res) {
            $this->setErrorMsg('通知失败' . $err);
            return $this->outputFormat(null, 20005);
        }
        \Neigou\Logger::Debug('after_sale_update_notify_update_3', array('data' => json_encode($orderData)));
        //更新状态
        $this->AfterSaleModel->update(array('after_sale_bn' => $param['after_sale_bn']), array('is_sync_fail' => 0));

        //写入Log
        $log['after_sale_bn'] = $param['after_sale_bn'];
        $log['operator_name'] = $param['operator_name'];
        $log['desc'] = $param['desc'];
        $log['status'] = $param['status'];
        $log['type'] = $param['operator_type'];
        $log['create_time'] = $param['create_time'] ? $param['create_time'] : time();
        $this->AfterSaleModel->addLog($log);
        \Neigou\Logger::Debug('after_sale_update_notify_update_4', array('data' => json_encode($orderData)));
        $this->setErrorMsg('success');
        return $this->outputFormat(null, 0);
    }

    /*
     * 查询售后单数据
     */
    public function getSearchList(Request $request)
    {
        $param = $this->getContentArray($request);
        $res = $this->AfterSaleModel->getSearchPageList($param['_str'], $param['page'], $param['limit'], $param['order']);
        $this->setErrorMsg('success');
        return $this->outputFormat($res, 0);
    }

    /*
     * 获取售后单数据
     */
    public function getList(Request $request)
    {
        $param = $this->getContentArray($request);
        if (($param['condition'] != 'where' && $param['condition'] != 'whereIn') || empty($param['data'])) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat($param, 0);
        }

        $condition = $param['condition'];
        unset($param['condition']);

        $filter = array();
        if ($condition == 'whereIn') {
            foreach ($param['data'] as $key => $value) {
                $filter['key'] = $key;
                $filter['value'] = $value;
            }
        } else {
            foreach ($param['data'] as $key => $value) {
                $filter[] = array($key, $value);
            }
        }
        $res = $this->AfterSaleModel->getList($condition, $filter);
        $this->setErrorMsg('success');
        return $this->outputFormat($res, 0);
    }

    /*
     * 获取单条售后单数据
     */
    public function getOne(Request $request)
    {
        $param = $this->getContentArray($request);
        if (empty($param['after_sale_bn'])) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat(null, 10001);
        }
        $res = $this->AfterSaleModel->getOne($param);
        $this->setErrorMsg('success');
        return $this->outputFormat($res, 0);
    }

    /*
     * 新增日志记录
     */
    public function addLog(Request $request)
    {
        $param = $this->getContentArray($request);
        if (empty($param['after_sale_bn']) || empty($param['operator_name'] || empty($param['type']) || empty($param['desc']))) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat(null, 30001);
        }

        $res = $this->AfterSaleModel->addLog($param);
        if (!$res) {
            $this->setErrorMsg('提交失败');
            return $this->outputFormat(null, 30002);
        }

        $this->setErrorMsg('success');
        return $this->outputFormat(null, 0);
    }

    /*
     * 查询操作日志
     */
    public function getLog(Request $request)
    {
        $param = $this->getContentArray($request);
        if (empty($param['after_sale_bn'])) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat(null, 40001);
        }
        $res = $this->AfterSaleModel->getLog($param);
        $this->setErrorMsg('success');
        return $this->outputFormat($res, 0);
    }


    /*
     * 新增备注记录
     */
    public function addRemark(Request $request)
    {
        $param = $this->getContentArray($request);
        if (
            empty($param['source_bn']) ||
            empty($param['source_type']) ||
            empty($param['remark']) ||
            empty($param['operator_name']) ||
            empty($param['create_time'])
        ) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat(null, 30001);
        }

        $addData = array(
            'source_bn' => $param['source_bn'],
            'source_type' => $param['source_type'],
            'remark' => $param['remark'],
            'operator_name' => $param['operator_name'],
            'create_time' => $param['create_time'],
        );

        $res = $this->AfterSaleModel->addRemark($addData);
        if (!$res) {
            $this->setErrorMsg('提交失败');
            return $this->outputFormat(null, 30002);
        }

        $this->setErrorMsg('success');
        return $this->outputFormat(null, 0);
    }

    /*
     * 查询备注信息
     */
    public function getRemark(Request $request)
    {
        $param = $this->getContentArray($request);
        if (empty($param['source_bn'])) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat(null, 40001);
        }
        $res = $this->AfterSaleModel->getRemark($param);
        $this->setErrorMsg('success');
        return $this->outputFormat($res, 0);
    }


    /*
     * 获取仓库单
     */
    public function wareHouseSearchList(Request $request)
    {
        $param = $this->getContentArray($request);
        $res = $this->AfterSaleModel->getWareHousePageList($param['_str'], $param['page'], $param['limit']);
        $this->setErrorMsg('success');
        return $this->outputFormat($res, 0);
    }

    public function getWareHouseList(Request $request)
    {
        $param = $this->getContentArray($request);
        if (!$param['_str']) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat(null, 40001);
        }
        $res = $this->AfterSaleModel->getWareHouseList($param['_str']);
        $this->setErrorMsg('success');
        return $this->outputFormat($res, 0);
    }

    public function getWareHouse(Request $request)
    {
        $param = $this->getContentArray($request);
        if ((empty($param['after_sale_bn'])) && (empty($param['warehouse_bn']))) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat(null, 40001);
        }

        if ($param['after_sale_bn'] && isset($param['status'])) {
            $where = array(
                array('after_sale_bn', $param['after_sale_bn']),
                array('status', $param['status'])
            );
        }

        if ($param['after_sale_bn'] && !isset($param['status'])) {
            $where = array(
                array('after_sale_bn', $param['after_sale_bn'])
            );
        }

        if ($param['warehouse_bn']) {
            $where = array(
                array('warehouse_bn', $param['warehouse_bn'])
            );
        }

        $res = $this->AfterSaleModel->getWareHouse($where);
        $this->setErrorMsg('success');
        return $this->outputFormat($res, 0);
    }

    /*
     * 创建仓库单
     */
    public function addWareHouseOrder(Request $request)
    {
        $param = $this->getContentArray($request);
        if (empty($param['after_sale_bn'])) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat(null, 50001);
        }

        //判断单号是否存在该售后单
        $afterData = $this->AfterSaleModel->getOne(array('after_sale_bn' => $param['after_sale_bn']));
        if (empty($afterData)) {
            $this->setErrorMsg('售后单号有误');
            return $this->outputFormat(null, 50002);
        }

        //状态判断
        if ($afterData['status'] != 4 && $afterData['status'] != 12 && $afterData['status'] != 14) {
            $this->setErrorMsg('状态有误');
            return $this->outputFormat(null, 50003);
        }

        $insert = array(
            'after_sale_bn' => $param['after_sale_bn'],
            'warehouse_bn' => date('YmdHi') . mt_rand(100, 999),
            'status' => $param['status'],
            'product_num' => $param['product_num'],
            'desc' => $param['desc'],
            'wms_code' => $param['wms_code'],
            'order_id' => $param['order_id'],
            'after_type' => $param['after_type'],
            'is_full' => $param['is_full'] ? $param['is_full'] : 0,
            'express_pay_method' => $param['express_pay_method'],
            'express_code' => $param['express_code'],
            'express_no' => $param['express_no'],
            'create_time' => $param['create_time'] ? $param['create_time'] : time(),
            'update_time' => $param['update_time'] ? $param['update_time'] : time()
        );
        $res = $this->AfterSaleModel->addWareHouse($insert);
        $this->setErrorMsg('success');
        return $this->outputFormat($res, 0);
    }

    /*
     * 更新仓库单
     */
    public function updateWareHouse(Request $request)
    {
        $param = $this->getContentArray($request);
        if (empty($param['after_sale_bn']) || empty($param['warehouse_bn'])) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat(null, 50001);
        }

        //判断单号是否存在该售后单
        $afterData = $this->AfterSaleModel->getOne(array('after_sale_bn' => $param['after_sale_bn']));
        if (empty($afterData)) {
            $this->setErrorMsg('售后单号有误');
            return $this->outputFormat(null, 50002);
        }

        $warehouseData = $this->AfterSaleModel->getWareHouse(array('warehouse_bn' => $param['warehouse_bn']));
        if (empty($warehouseData)) {
            $this->setErrorMsg('仓库单号有误');
            return $this->outputFormat(null, 50002);
        }

        $save = array();
        if (isset($param['status']) && $param['status'] !== '') {
            $save['status'] = $param['status'];
        }

        if ($param['product_num']) {
            $save['product_num'] = $param['product_num'];
        }

        if ($param['desc']) {
            $save['desc'] = $param['desc'];
        }

        if ($param['is_full']) {
            $save['is_full'] = $param['is_full'];
        }

        if ($param['express_pay_method']) {
            $save['express_pay_method'] = $param['express_pay_method'];
        }

        $save['update_time'] = time();

        $where['warehouse_bn'] = $param['warehouse_bn'];

        $res = $this->AfterSaleModel->updateWareHouse($where, $save);
        $this->setErrorMsg('success');
        return $this->outputFormat($res, 0);
    }

    /*
     * 获取货品的提交次数
     */
    public function getProductNum(Request $request)
    {
        $param = $this->getContentArray($request);
        if (empty($param['order_id']) || empty($param['product_bn'])) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat(null, 60001);
        }

        $totalProductNum = $this->AfterSaleModel->getAllAfterSaleOrder($param['order_id'], $param['product_bn']);
        $this->setErrorMsg('success');
        return $this->outputFormat(array('total_product_num' => $totalProductNum), 0);
    }

    /*
     * 图片上传
     */
    public function createImage(Request $request)
    {
        $param = $this->getContentArray($request);
        if (empty($param['url']) || empty($param['create_time'])) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat(null, 70001);
        }
        $res = $this->AfterSaleModel->createImage($param);
        if (!$res) {
            $this->setErrorMsg('提交失败');
            return $this->outputFormat(null, 70002);
        }
        $this->setErrorMsg('success');
        return $this->outputFormat(array('image_id' => $res), 0);
    }

    public function getImage(Request $request)
    {
        $param = $this->getContentArray($request);
        if (empty($param)) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat(null, 80001);
        }

        //TODO 业务扩展需将图片ID返回
        $is_new = 0;
        if (isset($param['is_new']) && $param['is_new']) {
            $data = explode(',', $param['images_string']);
            $is_new = 1;
        } else {
            if (!is_array($param)) {
                $data = explode(',', $param);
            } else {
                $data = $param;
            }
        }

        $res = $this->AfterSaleModel->getImage($data, $is_new);
        $this->setErrorMsg('success');
        return $this->outputFormat(array('image_url' => $res), 0);
    }

    /*
     * 获取补偿券规则
     */
    public function backRuleList()
    {
        $res = $this->AfterSaleModel->voucherRuleList();
        $this->setErrorMsg('success');
        return $this->outputFormat($res, 0);
    }

    /*
     * 统计
     */
    public function createStatistics(Request $request)
    {
        $param = $this->getContentArray($request);
        $err = '';
        $StatisticsLogic = new Statistics();
        $res = $StatisticsLogic->create($param, $err);
        if (!$res) {
            $this->setErrorMsg($err);
            return $this->outputFormat(null, 90002);
        }
        $this->setErrorMsg('success');
        return $this->outputFormat($res, 0);
    }

    public function updateStatistics(Request $request)
    {
        $param = $this->getContentArray($request);
        $err = '';
        $StatisticsLogic = new Statistics();
        $res = $StatisticsLogic->update($param, $err);
        if (!$res) {
            $this->setErrorMsg($err);
            return $this->outputFormat(null, 90002);
        }
        $this->setErrorMsg('success');
        return $this->outputFormat($res, 0);
    }

    public function getStatistics(Request $request)
    {
        $param = $this->getContentArray($request);
        $err = '';
        $StatisticsLogic = new Statistics();
        $res = $StatisticsLogic->get($param, $err);
        if ($res === false) {
            $this->setErrorMsg($err);
            return $this->outputFormat(null, 90002);
        }
        $this->setErrorMsg('success');
        return $this->outputFormat($res, 0);
    }

    public function getStatisticsList(Request $request)
    {
        $param = $this->getContentArray($request);
        $err = '';
        $StatisticsLogic = new Statistics();
        $res = $StatisticsLogic->getList($param, $err);
        if ($res === false) {
            $this->setErrorMsg($err);
            return $this->outputFormat(null, 90002);
        }
        $this->setErrorMsg('success');
        return $this->outputFormat($res, 0);
    }

    public function getStatisticsStatus(Request $request)
    {
        $param = $this->getContentArray($request);
        $err = '';
        $StatisticsLogic = new Statistics();
        $res = $StatisticsLogic->getStatus($param, $err);
        if ($res === false) {
            $this->setErrorMsg($err);
            return $this->outputFormat(null, 90002);
        }
        $this->setErrorMsg('success');
        return $this->outputFormat($res, 0);
    }

    /*
     * 批量分配客服
     */
    public function appoint(Request $request)
    {
        $param = $this->getContentArray($request);
        if (!$param['after_sale_bns'] || !is_array($param['after_sale_bns']) || !$param['customer_service']) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat(null, 90010);
        }
        $res = $this->AfterSaleModel->appoint($param);
        if ($res === false) {
            $this->setErrorMsg('更新失败');
            return $this->outputFormat(null, 90011);
        }
        $this->setErrorMsg('success');
        return $this->outputFormat(null, 0);
    }

}
