<?php
/**
 * neigou_service-stock
 * @package     api
 * @author      xupeng
 * @since       Version
 * @filesource
 */

namespace App\Api\V1\Controllers;

use App\Api\Common\Controllers\BaseController;
use App\Api\V1\Service\Order\Review as OrderReviewLogic;
use Illuminate\Http\Request;

/**
 * 订单审核 Controller
 *
 * @package     api
 * @category    Controller
 * @author        xupeng
 */
class OrderReviewController extends BaseController
{
    /**
     * 创建订单审核
     *
     * @return array
     */
    public function createOrderReview(Request $request)
    {
        $params = $this->getContentArray($request);

        // 审核
        if (empty($params['order_id']) OR empty($params['review_source']))
        {
            $this->setErrorMsg('请求参数错误');
            return $this->outputFormat(null, 400);
        }

        // 订单ID
        $orderId = $params['order_id'];

        // 审核方
        $reviewSource = $params['review_source'];

        $errMsg = '';
        $orderReviewLogic = new OrderReviewLogic();

        // 保存订单审核信息
        $reviewInfo = $orderReviewLogic->createOrderReview($orderId, $reviewSource, $errMsg);
        if (empty($reviewInfo))
        {
            \Neigou\Logger::General('service_order_review_failed', array('method' => __FUNCTION__, 'order_id' => $orderId, 'errMsg' => $errMsg));

            $errMsg OR $errMsg = '';
            $this->setErrorMsg($errMsg);
            return $this->outputFormat(null, 404);
        }

        $this->setErrorMsg('请求成功');
        return $this->outputFormat($reviewInfo);
    }

    // --------------------------------------------------------------------

    /**
     * 获取订单审核信息
     *
     */
    public function getOrderReviewInfo(Request $request)
    {
        $params = $this->getContentArray($request);

        // 验证请求参数
        if (empty($params['order_id'])) {
            $this->setErrorMsg('请求参数错误');
            return $this->outputFormat(null, 400);
        }

        $orderReviewLogic = new OrderReviewLogic();

        // 获取订单审核信息
        $reviewInfo = $orderReviewLogic->getOrderReviewInfo($params['order_id']);

        $this->setErrorMsg('请求成功');
        return $this->outputFormat($reviewInfo);
    }

    // --------------------------------------------------------------------

    /**
     * 获取订单审核列表
     *
     */
    public function getOrderReviewList(Request $request)
    {
        $params = $this->getContentArray($request);

        $orderReviewLogic = new OrderReviewLogic();

        // 筛选条件
        $filter = $params['filter'];

        // 页
        $page = isset($params['page']) ? intval($params['page']) : 1;

        // 每页数量
        $perPage = isset($params['per_page']) ? intval($params['per_page']) : 30;

        // 获取订单审核信息
        $reviewList = $orderReviewLogic->getOrderReviewList($filter, $page, $perPage);

        $this->setErrorMsg('请求成功');
        return $this->outputFormat($reviewList);
    }

    // --------------------------------------------------------------------

    /**
     * 获取用户相关审核信息
     *
     */
    public function getMemberReviewInfo(Request $request)
    {
        $params = $this->getContentArray($request);
        // 验证请求参数
        if (empty($params['member_id']) OR empty($params['company_id']))
        {
            $this->setErrorMsg('请求参数错误');
            return $this->outputFormat(null, 400);
        }

        $orderReviewLogic = new OrderReviewLogic();

        // 获取用户相关审核信息
        $reviewInfo = $orderReviewLogic->getMemberReviewInfo($params['member_id'], $params['company_id']);

        $this->setErrorMsg('请求成功');
        return $this->outputFormat($reviewInfo);
    }

    // --------------------------------------------------------------------

    /**
     * 提交审核
     *
     */
    public function submitReview(Request $request)
    {
        $params = $this->getContentArray($request);

        // 验证请求参数
        if (empty($params['order_id'])) {
            $this->setErrorMsg('请求参数错误');
            return $this->outputFormat(null, 400);
        }

        $orderReviewLogic = new OrderReviewLogic();

        // 错误信息
        $errMsg = '';
        // 获取订单审核信息
        $reviewInfo = $orderReviewLogic->submitReview($params['order_id'], $errMsg);

        if (empty($reviewInfo)) {
            \Neigou\Logger::General('service_order_review_failed', array('method' => __FUNCTION__, 'order_id' => $params['order_id'], 'errMsg' => $errMsg));
            $errMsg OR $errMsg = '提交审核失败';
            $this->setErrorMsg($errMsg);
            return $this->outputFormat(null, 404);
        }
        $this->setErrorMsg('请求成功');
        return $this->outputFormat($reviewInfo);
    }

    // --------------------------------------------------------------------

    /**
     * 订单审核通过操作
     *
     */
    public function approve(Request $request)
    {
        $params = $this->getContentArray($request);

        // 验证请求参数
        if (empty($params['order_id'])) {
            $this->setErrorMsg('请求参数错误');
            return $this->outputFormat(null, 400);
        }

        $orderReviewLogic = new OrderReviewLogic();

        $errMsg = '';
        // 审核通过
        $reviewInfo = $orderReviewLogic->approve($params['order_id'], $errMsg);
        if (empty($reviewInfo)) {
            \Neigou\Logger::General('service_order_review_failed', array('method' => __FUNCTION__, 'order_id' => $params['order_id'], 'errMsg' => $errMsg));
            $errMsg OR $errMsg = '操作失败';
            $this->setErrorMsg($errMsg);
            return $this->outputFormat(null, 404);
        }

        $this->setErrorMsg('请求成功');
        return $this->outputFormat($reviewInfo);
    }

    // --------------------------------------------------------------------

    /**
     * 扣除资金池
     *
     */
    public function deny(Request $request)
    {
        $params = $this->getContentArray($request);

        // 验证请求参数
        if (empty($params['order_id'])) {
            $this->setErrorMsg('请求参数错误');
            return $this->outputFormat(null, 400);
        }

        $orderReviewLogic = new OrderReviewLogic();

        $errMsg = '';
        // 审核通过
        $reviewInfo = $orderReviewLogic->deny($params['order_id'], $errMsg);
        if (empty($reviewInfo)) {
            \Neigou\Logger::General('service_order_review_failed', array('method' => __FUNCTION__, 'order_id' => $params['order_id'], 'errMsg' => $errMsg));
            $errMsg OR $errMsg = '操作失败';
            $this->setErrorMsg($errMsg);
            return $this->outputFormat(null, 404);
        }

        $this->setErrorMsg('请求成功');
        return $this->outputFormat($reviewInfo);
    }

}
