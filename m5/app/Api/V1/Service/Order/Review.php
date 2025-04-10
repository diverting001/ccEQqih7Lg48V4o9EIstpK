<?php
/**
 * neigou_service-stock
 *
 * @package     api
 * @author      xupeng
 * @since       Version
 * @filesource
 */

namespace App\Api\V1\Service\Order;

use App\Api\Model\Order\Review as OrderReviewModel;
use App\Api\Model\Order\Order as OrderModel;
use App\Api\Logic\OrderReview\Club AS ClubLogic;
use App\Api\Logic\OrderReview\Openapi AS OpenapiLogic;
use App\Api\Logic\Service as Service;

/**
 * 分销商 Controller
 *
 * @package     api
 * @category    Controller
 * @author        xupeng
 */
class Review
{
    // 审核状态描述
    private static $_reviewStatusMsg = array(
        'ready' => '待处理',
        'process' => '待审核',
        'reviewing' => '审核中',
        'approved' => '审核通过',
        'denied' => '审核拒绝',
    );

    /**
     * 保存分销商信息
     *
     * @param   $orderId        string      订单ID
     * @param   $reviewSource   string      审核源
     * @param   $errMsg         string      错误信息
     * @return  mixed
     */
    public function createOrderReview($orderId, $reviewSource = '', & $errMsg = '')
    {
        if (empty($orderId))
        {
            $errMsg = '参数错误';

            return false;
        }

        $reviewModel = new OrderReviewModel();

        $return = $reviewModel->getOrderReviewInfo($orderId);

        if ( ! empty($return))
        {
            return $return;
        }

        // 获取订单信息
        $orderInfo = OrderModel::GetOrderInfoById($orderId);

        if (empty($orderInfo))
        {
            $errMsg = '订单不存在';

            return false;
        }
        $orderInfo = get_object_vars($orderInfo);

        $reviewStatus = 'ready';
        $reviewStatusMsg = self::$_reviewStatusMsg[$reviewStatus];
        // 添加订单审核
        if ( ! $reviewModel->addOrderReview($orderId, $orderInfo['company_id'], $orderInfo['member_id'], $reviewSource, $reviewStatus, $reviewStatusMsg))
        {
            return false;
        }

        return $reviewModel->getOrderReviewInfo($orderId);
    }

    // --------------------------------------------------------------------

    /**
     * 获取订单审核详情
     *
     * @param   $orderId           string      订单ID
     * @return  mixed
     */
    public function getOrderReviewInfo($orderId)
    {
        $return = array();

        $reviewModel = new OrderReviewModel();

        $orderReviewInfo = $reviewModel->getOrderReviewInfo($orderId);

        if (empty($orderReviewInfo))
        {
            return $return;
        }

        $return = $orderReviewInfo;

        // club 提交审核
        if ($orderReviewInfo['review_source'] == 'club')
        {
            $orderReviewLogic = new ClubLogic();
            $return['review_detail'] = $orderReviewLogic->getOrderReview($orderId);
            // openapi 提交
        }
        elseif ($orderReviewInfo['review_source'] == 'third')
        {
            $orderReviewLogic = new OpenapiLogic();
            $return['review_detail'] = $orderReviewLogic->getOrderReview($orderReviewInfo);
        }

        return $return;
    }

    // --------------------------------------------------------------------

    /**
     * 获取订单审核列表
     *
     * @param   $filter     array   过滤
     * @param   $page       int     页面
     * @param   $perPage    int     每页数量
     * @return  mixed
     */
    public function getOrderReviewList($filter = array(), $page = 1, $perPage = 30)
    {
        // 筛选条件
        $data = array();

        if (isset($filter['order_id']))
        {
            $data['order_id'] = $filter['order_id'];
        }

        if (isset($filter['company_id']))
        {
            $data['company_id'] = $filter['company_id'];
        }

        if (isset($filter['member_id']))
        {
            $data['member_id'] = $filter['member_id'];
        }

        if (isset($filter['review_source']))
        {
            $data['review_source'] = $filter['review_source'];
        }

        if (isset($filter['review_status']))
        {
            $data['review_status'] = $filter['review_status'];
        }

        $reviewModel = new OrderReviewModel();

        $count = $reviewModel->getOrderReviewCount($data);

        $return = array(
            'count' => $count,
            'page' => $page,
            'per_page' => $perPage,
            'list' => array(),
        );

        if ($count <= 0)
        {
            return $return;
        }

        $offset = ($page - 1) * $perPage;

        if ($offset > $count)
        {
            return $return;
        }

        $return['list'] = $reviewModel->getOrderReviewList($data, $perPage, $offset);

        if ( ! empty($return['list']))
        {
            $orderIds = array();
            $orderList = array();
            $memberId = '';
            $companyId = '';
            foreach ($return['list'] as $orderId => $v)
            {
                $orderIds[$v['review_source']][] = $orderId;
                $memberId = $v['member_id'];
                $companyId = $v['company_id'];
            }

            foreach ($orderIds as $source => $ids)
            {
                if ($source == 'club')
                {
                    $orderReviewLogic = new ClubLogic();
                    $result = $orderReviewLogic->getMemberOrderReview($memberId, $companyId, $ids);
                    $orderList = array_merge($orderList, $result);
                }
            }

            if ( ! empty($orderList))
            {
                foreach ($return['list'] as $orderId => $v)
                {
                    if (isset($orderList[$orderId]))
                    {
                        $v['review_detail'] = $orderList[$orderId];
                    }

                    $return['list'][$orderId] = $v;
                }
            }
        }

        return $return;
    }

    // --------------------------------------------------------------------

    /**
     * 获取用户审核信息
     *
     * @param   $memberId       int     用户ID
     * @param   $companyId      int     公司ID
     * @return  mixed
     */
    public function getMemberReviewInfo($memberId, $companyId)
    {
        $return = array();

        $data = array(
            'member_id' => $memberId,
            'company_id' => $companyId,
        );
        $reviewList = $this->getOrderReviewList($data, 1, 1);

        if (empty($reviewList))
        {
            return $return;
        }

        $reviewSource = $reviewList['count'] > 0 ? $reviewList['list'][0]['review_source'] : '';

        // club 获取用户审核信息
        if ($reviewSource == 'club')
        {
            $orderReviewLogic = new ClubLogic();
            $return = $orderReviewLogic->getMemberReviewInfo($memberId, $companyId);
        }

        return $return;
    }

    // --------------------------------------------------------------------

    /**
     * 订单提交审核
     *
     * @param   $orderId    string      订单ID
     * @param   $errMsg     string      错误信息
     * @return  mixed
     */
    public function submitReview($orderId, & $errMsg = '')
    {
        if (empty($orderId))
        {
            $errMsg = '参数错误';

            return false;
        }

        $reviewModel = new OrderReviewModel();

        $reviewDetail = $reviewModel->getOrderReviewInfo($orderId);

        if (empty($reviewDetail))
        {
            $errMsg = '订单不存在';

            return false;
        }

        // 审核中
        if ($reviewDetail['review_status'] == 'reviewing')
        {
            return true;
        }

        // 审核状态
        if ( ! in_array($reviewDetail['review_status'], array('ready', 'process', 'approved', 'denied')) OR $reviewDetail['status'] != 1)
        {
            $errMsg = '订单审核状态异常';

            return false;
        }

        $result = false;
        $errMsg = '';
        // club 提交审核
        if ($reviewDetail['review_source'] == 'club')
        {
            $orderReviewLogic = new ClubLogic();
            $result = $orderReviewLogic->submitReview($orderId, $errMsg);
            // openapi 提交
        }
        elseif ($reviewDetail['review_source'] == 'third')
        {
            $orderReviewLogic = new OpenapiLogic();
            $result = $orderReviewLogic->submitReview($orderId, $errMsg);
        }

        if ( ! $result)
        {
            $errMsg OR $errMsg = '提交审核失败';

            return false;
        }

        $updateData = array(
            'review_status' => 'reviewing',
            'review_status_msg' => self::$_reviewStatusMsg['reviewing'],
        );

        if ( ! $reviewModel->updateOrderReviewData($orderId, $updateData))
        {
            $errMsg = '更新审核状态失败';

            return false;
        }

        return true;
    }

    // --------------------------------------------------------------------

    /**
     * 订单处理待审核
     *
     * @param   $orderId    string      订单ID
     * @param   $errMsg     string      错误信息
     * @return  mixed
     */
    public function process($orderId, & $errMsg = '')
    {
        if (empty($orderId))
        {
            $errMsg = '参数错误';

            return false;
        }

        $reviewModel = new OrderReviewModel();

        $reviewDetail = $reviewModel->getOrderReviewInfo($orderId);

        if (empty($reviewDetail))
        {
            $errMsg = '订单不存在';

            return false;
        }

        // 审核通过
        if ($reviewDetail['review_status'] != 'ready')
        {
            return true;
        }

        $updateData = array(
            'review_status' => 'process',
            'review_status_msg' => self::$_reviewStatusMsg['process'],
        );

        if ( ! $reviewModel->updateOrderReviewData($orderId, $updateData))
        {
            $errMsg = '更新审核状态失败';

            return false;
        }

        // 提交审核
        return $this->submitReview($orderId);
    }

    // --------------------------------------------------------------------

    /**
     * 订单审核通过操作  传递主单，需要将子单也要进行确认
     *
     * @param   $orderId    string      订单ID
     * @param   $errMsg     string      错误信息
     * @return  mixed
     */
    public function approve($orderId, & $errMsg = '')
    {
        if (empty($orderId))
        {
            $errMsg = '参数错误';

            return false;
        }

        $reviewModel = new OrderReviewModel();

        $reviewDetail = $reviewModel->getOrderReviewInfo($orderId);

        if (empty($reviewDetail))
        {
            $errMsg = '订单不存在';

            return false;
        }

        // 审核通过
        if ($reviewDetail['review_status'] == 'approved')
        {
            return true;
        }

        if ( ! in_array($reviewDetail['review_status'], array('process', 'reviewing')) OR $reviewDetail['status'] != 1)
        {
            $errMsg = '订单审核状态异常';

            return false;
        }
        // 获取订单信息
        $orderInfo = OrderModel::GetOrderInfoById($orderId);
        if (empty($orderInfo))
        {
            $errMsg = '订单不存在';
            return false;
        }
        
        if ($orderInfo->confirm_status == 1)
        {
            if ($orderInfo->split == 2 && $orderInfo->create_source == 'main') {
                //子订单进行确认
                $split_order_list = OrderModel::GetSplitOrderByRootPId($orderInfo->root_pid);
                if ( !empty($split_order_list) ) {
                    foreach ( $split_order_list as $split_order ) {
                        $res = OrderModel::OrderConfirm(['order_id' => $split_order->order_id]);
                        if ( !$res ) {
                            \Neigou\Logger::Debug('review_order_confirm_split_order', array('bn' => json_encode($split_order->order_id)));
                        }
                    }
                }
            }
            // 更新订单确认状态(已确认）
            $service_logic = new Service();
            $result = $service_logic->ServiceCall('order_confirm', ['order_id' => $orderId]);

            if ($result['error_code'] != 'SUCCESS')
            {
                $errMsg = '订单确认操作失败';

                return false;
            }



        }

        $updateData = array(
            'review_status' => 'approved',
            'review_status_msg' => self::$_reviewStatusMsg['approved'],
        );

        if ( ! $reviewModel->updateOrderReviewData($orderId, $updateData))
        {
            $errMsg = '更新审核状态失败';

            return false;
        }

        return true;
    }

    // --------------------------------------------------------------------

    /**
     * 订单审核拒绝操作
     *
     * @param   $orderId    string      订单ID
     * @param   $errMsg     string      错误信息
     * @return  mixed
     */
    public function deny($orderId, & $errMsg = '')
    {
        if (empty($orderId))
        {
            $errMsg = '参数错误';

            return false;
        }

        $reviewModel = new OrderReviewModel();

        $reviewDetail = $reviewModel->getOrderReviewInfo($orderId);

        if (empty($reviewDetail))
        {
            $errMsg = '订单不存在';

            return false;
        }

        // 审核通过
        if ($reviewDetail['review_status'] == 'denied')
        {
            return true;
        }

        if ( ! in_array($reviewDetail['review_status'], array('process', 'reviewing')) OR $reviewDetail['status'] != 1)
        {
            $errMsg = '订单审核状态异常';

            return false;
        }
        // 更新订单取消状态(已确认）
        $service_logic = new Service();

        // club 提交审核
        if ($reviewDetail['review_source'] == 'club')
        {
            $result = $service_logic->ServiceCall('order_cancel', ['order_id' => $orderId]);
            // openapi 提交
        }
        else
        {
            $result = $service_logic->ServiceCall('order_payed_cancel', ['order_id' => $orderId]);
        }

        if ($result['error_code'] != 'SUCCESS')
        {
            $errMsg = '订单取消操作失败';

            return false;
        }

        $updateData = array(
            'review_status' => 'denied',
            'review_status_msg' => self::$_reviewStatusMsg['denied'],
        );

        if ( ! $reviewModel->updateOrderReviewData($orderId, $updateData))
        {
            $errMsg = '更新审核状态失败';

            return false;
        }

        return true;
    }

}
