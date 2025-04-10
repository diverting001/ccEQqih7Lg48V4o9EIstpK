<?php
/**
 * neigou_service-stock
 * @package     api
 * @author      xupeng
 * @since       Version
 * @filesource
 */

namespace App\Api\Model\Order;

/**
 * 分销商 model
 *
 * @package     api
 * @category    Controller
 * @author        xupeng
 */
class Review
{
    /**
     * 获取订单审核详情
     *
     * @param   $orderId       string      订单ID
     * @return  array
     */
    public function getOrderReviewInfo($orderId)
    {
        $return = array();

        if (empty($orderId)) {
            return $return;
        }

        $where = [
            'order_id' => $orderId,
        ];
        $return = app('api_db')->table('server_order_review')->where($where)->first();

        return $return ? get_object_vars($return) : array();
    }

    // --------------------------------------------------------------------

    /**
     * 获取订单审核数量
     *
     * @param   $filter  array      过滤条件
     * @return  int
     */
    public function getOrderReviewCount($filter)
    {
        $db = app('api_db')->table('server_order_review');

        if ( ! empty($filter))
        {
            foreach ($filter as $field => $value)
            {
                if ($value === "")
                {
                    continue;
                }
                if ( ! is_array($value))
                {
                    $value = array($value);
                }
                $db->whereIn($field, $value);
            }
        }

        return $db->count('id');
    }

    // --------------------------------------------------------------------

    /**
     * 获取订单审核详情
     *
     * @param   $filter     array       过滤条件
     * @param   $limit      int         数量
     * @param   $offset     int         起始位置
     * @return  array
     */
    public function getOrderReviewList($filter, $limit = 30, $offset = 0)
    {
        $return = array();

        $db = app('api_db')->table('server_order_review');

        if ( ! empty($filter))
        {
            foreach ($filter as $field => $value)
            {
                if ($value === "")
                {
                    continue;
                }
                if ( ! is_array($value))
                {
                    $value = array($value);
                }
                $db->whereIn($field, $value);
            }
        }

        $result = $db->limit($limit)->offset($offset)->orderBy('id', 'desc')->get()->toArray();

        if (empty($result))
        {
            return $return;
        }

        foreach ($result as $v)
        {
            $return[$v->order_id] = get_object_vars($v);
        }

        return $return;

    }

    // --------------------------------------------------------------------

    /**
     * 添加订单审核记录
     *
     * @param   $orderId            string      订单ID
     * @param   $companyId          string      公司ID
     * @param   $memberId           string      用户ID
     * @param   $reviewSource       string      审核源
     * @param   $reviewStatus       string      审核状态
     * @param   $reviewStatusMsg    string      审核状态描述
     * @return  boolean
     */
    public function addOrderReview($orderId, $companyId, $memberId, $reviewSource, $reviewStatus = 'ready', $reviewStatusMsg = '')
    {
        if (empty($orderId) OR empty($reviewSource)) {
            return false;
        }

        $addData = array(
            'order_id'          => $orderId,
            'company_id'        => $companyId,
            'member_id'         => $memberId,
            'review_source'     => $reviewSource,
            'review_status'     => $reviewStatus,
            'review_status_msg' => $reviewStatusMsg,
            'review_record'     => '',
            'status'            => 1,
            'create_time'       => time(),
            'update_time'       => time(),
        );

        return app('api_db')->table('server_order_review')->insert($addData);
    }

    // --------------------------------------------------------------------

    /**
     * 更新订单审核数据
     *
     * @param   $orderId    string      订单ID
     * @param   $data       array       数据
     * @return  boolean
     */
    public function updateOrderReviewData($orderId, $data)
    {
        if (empty($orderId) OR empty($data)) {
            return false;
        }

        $updateData = array();

        foreach ($data as $k => $v) {
            if (in_array($k, array('review_status', 'review_status_msg', 'review_record', 'status'))) {
                $updateData[$k] = $v;
            }
        }

        if (empty($updateData)) {
            return true;
        }

        $updateData['update_time'] = time();

        $where = [
            'order_id' => $orderId,
        ];

        return app('api_db')->table('server_order_review')->where($where)->update($updateData);
    }

}
