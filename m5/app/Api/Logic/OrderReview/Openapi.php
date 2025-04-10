<?php

namespace App\Api\Logic\OrderReview;

class Openapi
{
    /**
     * 获取订单审核
     *
     * @param   $orderReviewInfo    array   订单审核信息
     * @return  array
     */
    public function getOrderReview($orderReviewInfo)
    {
        $isCancelReview = 0;
        if (in_array($orderReviewInfo['review_status'], array('ready', 'process', 'reviewing')))
        {
            $isCancelReview = 0;
        }

        $orderReviewInfo['is_cancel_review'] = $isCancelReview;

        return $orderReviewInfo;
    }

    // --------------------------------------------------------------------

    /**
     * 提交审核
     *
     * @return boolean
     */
    public function submitReview()
    {
        return true;
    }

}
