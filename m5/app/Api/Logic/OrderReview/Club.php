<?php

namespace App\Api\Logic\OrderReview;

class Club
{
    /**
     * 获取订单审核
     *
     * @return array
     */
    public function getMemberOrderReview($memberId, $companyId, $orderId = array())
    {
        $return = array();

        // 获取订单审核详情
        $data = array(
            'order_id' => is_array($orderId) ? implode(',', $orderId) : $orderId,
            'member_id' => $memberId,
            'company_id' => $companyId,
        );

        $result = $this->_request('Review', 'getMemberOrderReview', $data);

        if ( ! empty($result['Data']))
        {
            $orderList = $result['Data']['data'];
            foreach ($orderList as $k => $v)
            {
                $isCancelReview = 0;

                if ( ! empty($v['review_process_record']))
                {
                    $reviewRecord = $v['review_process_record'];
                    if (empty($reviewRecord) OR $v['review_status'] == 0)
                    {
                        $isCancelReview = 1;
                    }
                    elseif ($v['review_status'] == 1)
                    {
                        if (in_array($reviewRecord[0]['review_status'], array(0, 1)))
                        {
                            $isCancelReview = 1;
                        }
                    }
                }
                else
                {
                    $isCancelReview = 1;
                }

                $v['is_cancel_review'] = $isCancelReview;

                $return[$v['order_id']] = $v;
            }
        }

        return $return;
    }

    // --------------------------------------------------------------------

    /**
     * 获取订单审核详情
     *
     * @return array
     */
    public function getOrderReview($orderId)
    {
        // 获取订单审核详情
        $data = array(
            'order_id' => $orderId,
        );

        $result = $this->_request('Review', 'getOrderReviewDetail', $data);

        if ( ! empty($result['Data']))
        {
            $isCancelReview = 0;
            if (in_array($result['Data']['review_status'], array('ready', 'process', 'reviewing')))
            {
                if ( ! empty($result['Data']['review_record']))
                {
                    $reviewRecord = $result['Data']['review_record'];
                    if (empty($reviewRecord) OR $reviewRecord['review_status'] == 0)
                    {
                        $isCancelReview = 1;
                    }
                    elseif ($reviewRecord['review_status'] == 1)
                    {
                        if (empty($reviewRecord['review_process_record']) OR in_array($reviewRecord['review_process_record'][0]['review_status'], array(0, 1))) {
                            $isCancelReview = 1;
                        }
                    }
                }
                else
                {
                    $isCancelReview = 1;
                }
            }

            $result['Data']['is_cancel_review'] = $isCancelReview;
        }

        return ! empty($result['Data']) ? $result['Data'] : array();
    }

    // --------------------------------------------------------------------

    /**
     * 获取用户审核相关信息
     *
     * @return array
     */
    public function getMemberReviewInfo($memberId, $companyId)
    {
        $data = array(
            'member_id' => $memberId,
            'company_id' => $companyId,
        );

        $result = $this->_request('Review', 'getMemberReviewModel', $data);

        return ! empty($result['Data']) ? $result['Data'] : array();
    }

    // --------------------------------------------------------------------

    /**
     * 提交审核
     *
     * @return boolean
     */
    public function submitReview($orderId)
    {
        // 获取订单审核详情
        $data = array(
            'order_id' => $orderId,
        );

       $result = $this->_request('Review', 'createOrderReview', $data);

       return $result ? true : false;
    }

    private function _request($class, $method,  $requestData)
    {
        $return = array();

        if (empty($class) OR empty($method) OR empty($requestData)) {
            return false;
        }

        $curl = new \Neigou\Curl();
        $curl->time_out = 20;
        $request_params = array(
            'class_obj' => $class,
            'method'    => $method,
        );

        $request_params = array_merge($request_params, $requestData);
        $request_params['token'] = self::_getToken($request_params);

        $result = $curl->Post(config('neigou.CLUB_DOMAIN'). '/Qiye/OpenApi/apirun', $request_params);

        \Neigou\Logger::General('club_order_review', array('sender' => $request_params, 'remark' => $result));
        if ($curl->GetHttpCode() == 200) {
            $return = json_decode($result, true);
        }

        return $return;
    }

    // 获取 token
    private static function _getToken($arr)
    {
        ksort($arr);
        $sign_ori_string = "";
        foreach ($arr as $key => $value)
        {
            if ( ! empty($value) && ! is_array($value))
            {
                if ( ! empty($sign_ori_string))
                {
                    $sign_ori_string .= "&$key=$value";
                }
                else
                {
                    $sign_ori_string = "$key=$value";
                }
            }
        }
        $sign_ori_string .= ("&key=" . config('neigou.CLUB_SIGN'));

        return strtoupper(md5($sign_ori_string));
    }

}
