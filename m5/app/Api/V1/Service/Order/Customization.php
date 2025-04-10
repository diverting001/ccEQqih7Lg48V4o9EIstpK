<?php
namespace App\Api\V1\Service\Order;
use App\Api\Model\Order\Order as OrderModel;
use App\Api\Logic\OrderCustomization\Salyut AS SalyutLogic;

/**
 * 定制 Service
 *
 * @package     api
 * @category    Controller
 * @author      xupeng
 */
class Customization
{

    /**
     * 获取订单审核详情
     *
     * @params  $orderIds       mixed      订单ID
     * @params  $errMsg         string      错误信息
     * @return  mixed
     */
    public function getOrderCustomizationInfo($orderId, & $errMsg = '')
    {
        $return = array();
        if (empty($orderId)) {
            $errMsg = '参数错误';
            return false;
        }
        $orderList = $this->getOrderList($orderId);
        if (empty($orderList)) {
            $errMsg = '获取订单失败';
            return false;
        }
        $orderInfo = $orderList[$orderId];
        if ($orderInfo['extend_info_code'] != 'customization') {
            return $return;
        }
        if ($orderInfo['pay_status'] != 2 OR $orderInfo['status'] != 1) {
            $errMsg = '订单状态异常';
            return $return;
        }
        if ($orderInfo['confirm_status'] == 2) {
            $errMsg = '已定制完成，请勿重复操作！';
            return $return;
        }
        $items = array();
        foreach ($orderInfo['items'] as $item) {
            $items[] = array(
                'bn' => $item['bn'],
                'nums' => $item['nums'],
            );
        }

        $orderData = array(
            'order_id' => $orderInfo['order_id'],
            'member_id' => $orderInfo['member_id'],
            'company_id' => $orderInfo['company_id'],
            'items' => $items,
        );

        // salyut
        $result = array();
        if ($orderInfo['wms_code'] == 'SALYUT') {
            $salyutLogic = new SalyutLogic();
            $result = $salyutLogic->getOrderCustomizationInfo($orderData, $errMsg);
        }
        if (empty($result)) {
            return $return;
        }
        $return = array(
            'type' => $result['type'] ? : '',
            'content' => $result['content'] ? : '',
            'desc' => $result['desc'] ? : '',
            'extend_data' => $result['extend_data'] ? : array(),
            'supplier_bn' => $result['supplier_bn'] ? : '',
            'env' => $result['env'] ? : array(),
        );

        return $return;
    }

    // --------------------------------------------------------------------

    /**
     * 获取订单定制信息
     *
     * @params  $orderId        string      订单ID
     * @params  $orderCustomizationData     array   订单定制数据
     * @return  mixed
     */
    public function getOrderCustomizationResult($orderId, $orderCustomizationData, & $errMsg = '')
    {
        if (empty($orderId)) {
            $errMsg = '参数错误';
            return false;
        }
        $orderList = $this->getOrderList($orderId);
        $orderInfo = $orderList[$orderId];
        if (empty($orderInfo)) {
            $errMsg = '订单信息错误';
            return false;
        }
        $items = array();
        foreach ($orderInfo['items'] as $item) {
            $items[] = array(
                'bn' => $item['bn'],
                'nums' => $item['nums'],
            );
        }
        $orderData = array(
            'order_id' => $orderInfo['order_id'],
            'member_id' => $orderInfo['member_id'],
            'company_id' => $orderInfo['company_id'],
            'items' => $items,
        );
        $result = array();
        if ($orderInfo['wms_code'] == 'SALYUT') {
            $salyutLogic = new SalyutLogic();
            $result = $salyutLogic->getOrderCustomizationResult($orderData, $orderCustomizationData, $errMsg);
        }
        $return = array();
        if (empty($result)) {
            $errMsg = '获取定制结果失败';
            return $return;
        }
        $return = array(
            'status' => $result['status'] ? : 1,
            'type' => $result['type'] ? : '',
            'content' => $result['content'] ? : '',
            'bn' => $result['bn'] ? : '',
            'desc' => $result['desc'] ? : '',
            'extend_data' => $result['extend_data'] ? : array(),
            'supplier_bn' => $result['supplier_bn'] ? : '',
        );

        return $return;
    }

    // 获取订单列表
    private function getOrderList($orderId)
    {
        $return = array();
        $where = array();
        if (is_array($orderId)) {
            $where['order_id'] = array('type' => 'in', 'value' => $orderId);
        } else {
            $where['order_id'] = array('type' => 'eq', 'value' => $orderId);
        }

        // 获取订单列表
        $orderList = OrderModel::GetOrderList('order_id,member_id,company_id,pay_status,status,confirm_status,extend_info_code,wms_code,extend_data', $where, 0);
        $orderIds = is_array($orderId) ? $orderId : array($orderId);
        $orderItems = OrderModel::GetOrderItems($orderIds);

        foreach ($orderList as $orderInfo) {
            $orderInfo = get_object_vars($orderInfo);
            $itemData = $orderItems[$orderInfo['order_id']];
            $items = array();
            foreach ($itemData as $item) {
                $items[] = get_object_vars($item);
            }
            $orderInfo['items'] = $items;
            $return[$orderInfo['order_id']] = $orderInfo;
        }
        return $return;
    }

}
