<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2020-07-02
 * Time: 10:46
 */

namespace App\Api\V2\Controllers;

use App\Api\Common\Controllers\BaseController;
use App\Api\V2\Service\Order\Order as OrderOrder;
use App\Api\V2\Service\SearchOrder\OrderData;
use Illuminate\Http\Request;

class OrderController extends BaseController
{
    /*
     * @todo 订单列表
     */
    public function GetOrderList(Request $request)
    {
        $requestData = $this->getContentArray($request);
        $where_data  = $requestData['filter'] ?? [];
        if (
        empty($where_data)
        ) {
            $this->setErrorMsg('请选择筛选条件');
            return $this->outputFormat([], 400);
        }

        $where = [];
        //公司
        if (!empty($where_data['company_id'])) {
            $where['company_id'] = ($where_data['company_id']);
        }
        //用户
        if (!empty($where_data['member_id'])) {
            $where['member_id'] = ($where_data['member_id']);
        }
        //订单平台
        if (!empty($where_data['system_code'])) {
            $where['system_code'] = trim($where_data['system_code']);
        }
        //收货人手机号
        if (!empty($where_data['ship_mobile'])) {
            $where['ship_mobile'] = trim($where_data['ship_mobile']);
        }
        //收货人姓名
        if (!empty($where_data['ship_name'])) {
            $where['ship_name'] = trim($where_data['ship_name']);
        }
        //订单状态
        if (!empty($where_data['status']) && in_array($where_data['status'], array(1, 2, 3))) {
            $where['status'] = intval($where_data['status']);
        }
        if (!empty($where_data['pay_status'])) {
            $where['pay_status'] = intval($where_data['pay_status']);
        }
        if (!empty($where_data['confirm_status'])) {
            $where['confirm_status'] = intval($where_data['confirm_status']);
        }
        if (!empty($where_data['ship_status'])) {
            $where['ship_status'] = intval($where_data['ship_status']);
        }
        if (!empty($where_data['create_time'])) {
            $where['create_time'] = ($where_data['create_time']);
        }
        if (!empty($where_data['order_id'])) {
            $where['order_id'] = ($where_data['order_id']);
        }
        if (!empty($where_data['pay_time'])) {
            $where['pay_time'] = ($where_data['pay_time']);
        }
        if (!empty($where_data['create_source'])) {
            $where['create_source'] = $where_data['create_source'];
        }
        if (!empty($where_data['project_code'])) {
            $where['project_code'] = $where_data['project_code'];
        }
        // 店铺
        if (!empty($where_data['pop_owner_id'])) {
            $where['pop_owner_id'] = $where_data['pop_owner_id'];
        }

        //仓库
        if (!empty($where_data['warehouse_name'])) {
            $where['warehouse_name'] = $where_data['warehouse_name'];
        }

        //是否使用拆单条件
        if (!empty($where_data['is_use_split']) && $where_data['is_use_split'] == 1 && !empty($where_data['split'])) {
            $where['is_use_split'] = $where_data['is_use_split'];
            $where['split'] = $where_data['split'];
        }

        //支付单
        $filter_data = [];
        if (!empty($where_data['payment_id'])) {
            $filter_data['payment_id'] = $where_data['payment_id'];
        }
        //外部交易号查询订单信息
        if (!empty($where_data['trade_no'])) {
            $filter_data['trade_no'] = $where_data['trade_no'];
        }

        $orderBy = $requestData['order_by'] && isset($requestData['order_by']) ? $requestData['order_by'] : [];
        foreach ($orderBy as $key => $val) {
            if (!in_array($key, ['order_id', 'pay_time', 'create_time'])) {
                unset($orderBy[$key]);
            }
            if (!in_array($val, ['asc', 'desc'])) {
                $orderBy[$key] = 'asc';
            }
        }

        $order_service = new OrderOrder();
        if (!empty($filter_data)) {
            $order_ids = $order_service->GetOrderIdsPayLogByParams($filter_data);
            if (!empty($order_ids)) {
                $where['order_id'] = $order_ids;
            }
        }

        //limit限制
        $page_size  = empty($requestData['page_size']) ? 20 : intval($requestData['page_size']);
        $page_index = $requestData['page_index'] < 1 ? 1 : intval($requestData['page_index']);

        //输出格式
        $output_format = empty($requestData['output_format']) ? 'valid_split_order' : trim($requestData['output_format']);

        $request_data = [
            'filter'        => $where,
            'page_index'    => $page_index,
            'page_size'     => $page_size,
            'order'         => $orderBy,
            'output_format' => $output_format
        ];

        //print_r($request_data);die;
        //订单
        $order_list = $order_service->GetOrderList($request_data);
        $total      = $order_service->GetOrderTotal($request_data['filter']);
        if (empty($total)) {
            $this->setErrorMsg('未获取订单');
            return $this->outputFormat([], 400);
        } else {
            $this->setErrorMsg('获取成功');
            return $this->outputFormat(['order_list' => $order_list, 'total' => $total]);
        }
    }

    /*
    * @todo 订单详情
    */
    public function BatchGetOrderInfo(Request $request)
    {
        $order_data = $this->getContentArray($request);
        $filter_data = [];
        if (!empty($order_data['order_id'])) {
            $filter_data = array(
                'order_id' => $order_data['order_id']
            );
        }
        if (!empty($order_data['wms_order_bn']) && !empty($order_data['wms_code'])) {
            $filter_data = array(
                'wms_order_bn' => $order_data['wms_order_bn'],
                'wms_code' => $order_data['wms_code']
            );
        }
        if (!empty($order_data['wms_delivery_bn']) && !empty($order_data['wms_code'])) {
            $filter_data = array(
                'wms_delivery_bn' => $order_data['wms_delivery_bn'],
                'wms_code' => $order_data['wms_code']
            );
        }

        if (empty($filter_data)) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }
        $order_service = new OrderOrder();
        $order_list = $order_service->BatchGetOrderInfo($filter_data);
        if (empty($order_list)) {
            $this->setErrorMsg('订单不存在');
            return $this->outputFormat([], 401);
        } else {
            $this->setErrorMsg('获取成功');
            return $this->outputFormat($order_list);
        }
    }

    // 订单搜索列表
    public function SearchOrderList(Request $request){
        $pars = $this->getContentArray($request);

        $order_data_obj = new OrderData($pars);

        // 订单list
        $order_list = $order_data_obj->GetOrderList();

        $return_data = array();

        $return_data['list'] = (isset($order_list['order_list']) && is_array($order_list['order_list'])) ? $order_list['order_list'] : array();

        $return_data['total'] = (isset($order_list['order_count']) && $order_list['order_count'] > 0) ? intval($order_list['order_count']) : 0;

        $this->setErrorMsg('请求成功');

        return $this->outputFormat($return_data);
    }

    // 订单搜索结果总数
    public function SearchCount(Request $request){
        $pars = $this->getContentArray($request);

        $order_data_obj = new OrderData($pars);

        $order_count = $order_data_obj->GetOrderCount();

        $return_data['count'] = intval($order_count);

        $this->setErrorMsg('请求成功');

        return $this->outputFormat($return_data);
    }
}
