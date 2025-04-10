<?php
/**
 * neigou_service
 * @package     api
 * @author      xupeng
 * @since       Version
 * @filesource
 */

namespace App\Api\V1\Controllers;

use App\Api\Common\Controllers\BaseController;
use App\Api\V1\Service\Settlement\Channel as SettlementChannelLogic;
use App\Api\V1\Service\Settlement\Rule as SettlementRuleLogic;
use Illuminate\Http\Request;

/**
 * 结算相关
 *
 * @package     api
 * @category    Controller
 * @author      xupeng
 */
class SettlementController extends BaseController
{
    /**
     * 创建规则
     *
     * @return array
     */
    public function createRule(Request $request)
    {
        $params = $this->getContentArray($request);

        if (empty($params['name']) OR empty($params['rule_type']) OR empty($params['rule_item']) OR empty($params['scope_type']) OR empty($params['scope_item'])) {
            $this->setErrorMsg('请求参数错误');
            return $this->outputFormat(null, 400);
        }
        // 名称
        $name = $params['name'];

        // 结算类型
        $ruleType = $params['rule_type'];

        // 结算规则
        $ruleItem = $params['rule_item'];

        // 类型
        $scopeType = $params['scope_type'];

        // 店铺ID
        $scopeItem = $params['scope_item'];

        $settlementRuleLogic = new SettlementRuleLogic();

        // 创建规则
        $result = $settlementRuleLogic->createRule($name, $ruleType, $ruleItem, $scopeType, $scopeItem, $params['memo']);

        $this->setErrorMsg('请求成功');
        return $this->outputFormat($result);
    }

    // --------------------------------------------------------------------

    /**
     * 获取规则信息
     *
     * @return array
     */
    public function getRuleInfo(Request $request)
    {
        $params = $this->getContentArray($request);

        if (empty($params['sr_id']) ) {
            $this->setErrorMsg('请求参数错误');
            return $this->outputFormat(null, 400);
        }

        $settlementRuleLogic = new SettlementRuleLogic();

        // 获取规则详情
        $result = $settlementRuleLogic->getRuleInfo($params['sr_id']);

        $this->setErrorMsg('请求成功');
        return $this->outputFormat($result);
    }

    // --------------------------------------------------------------------

    /**
     * 获取规则列表
     *
     * @return array
     */
    public function getRuleList(Request $request)
    {
        $params = $this->getContentArray($request);

        // 页码
        $page = isset($params['page']) ? $params['page'] : 1;

        // 每页数量
        $pageSize = isset($params['page_size']) ? $params['page_size'] : 20;

        // 过滤条件
        $filter = isset($params['filter']) ? $params['filter'] : array();

        $settlementRuleLogic = new SettlementRuleLogic();

        // 获取规则列表
        $result = $settlementRuleLogic->getRuleList($page, $pageSize, $filter);

        $this->setErrorMsg('请求成功');
        return $this->outputFormat($result);
    }

    // --------------------------------------------------------------------

    /**
     * 创建结算通道
     *
     * @return array
     */
    public function createChannel(Request $request)
    {
        $params = $this->getContentArray($request);

        // 参数验证
        if (empty($params['name'])) {
            $this->setErrorMsg('请求参数错误');
            return $this->outputFormat(null, 400);
        }

        // 名称
        $name = $params['name'];

        // 备注
        $memo = $params['memo'] ? $params['memo'] : '';

        $settlementChannelLogic = new SettlementChannelLogic();

        // 创建渠道信息
        $channelInfo = $settlementChannelLogic->createChannel($name, $memo);

        $this->setErrorMsg('请求成功');
        return $this->outputFormat($channelInfo);
    }

    /**
     * 更新结算通道
     *
     * @return array
     */
    public function updateChannel(Request $request)
    {
        $params = $this->getContentArray($request);

        // 参数验证
        if (empty($params['sc_id'])) {
            $this->setErrorMsg('请求参数错误');
            return $this->outputFormat(null, 400);
        }

        // 名称
        $name = $params['name'];

        // 备注
        $memo = $params['memo'] ? $params['memo'] : null;

        $settlementChannelLogic = new SettlementChannelLogic();

        // 创建渠道信息
        $channelInfo = $settlementChannelLogic->updateChannel($params['sc_id'], $name, $memo);

        $this->setErrorMsg('请求成功');
        return $this->outputFormat($channelInfo);
    }

    // --------------------------------------------------------------------

    /**
     * 获取渠道信息
     *
     * @return array
     */
    public function getChannelInfo(Request $request)
    {
        $params = $this->getContentArray($request);

        if (empty($params['sc_id'])) {
            $this->setErrorMsg('请求参数错误');
            return $this->outputFormat(null, 400);
        }

        // 通道ID
        $scId = $params['sc_id'];

        $settlementChannelLogic = new SettlementChannelLogic();

        // 获取渠道信息
        $channelInfo = $settlementChannelLogic->getChannelInfo($scId);

        $this->setErrorMsg('请求成功');
        return $this->outputFormat($channelInfo);
    }

    // --------------------------------------------------------------------

    /**
     * 绑定支付 by company
     *
     * @return array
     */
    public function bindPaymentByCompany(Request $request)
    {
        $params = $this->getContentArray($request);

        if (empty($params['company_id'])) {
            $this->setErrorMsg('请求参数错误');
            return $this->outputFormat(null, 400);
        }

        // 支付列表
        $companyId = $params['company_id'];

        // 支付列表
        $paymentList = $params['payment_list'];

        $settlementLogic = new SettlementChannelLogic();

        // 绑定支付
        $result = $settlementLogic->bindPaymentByCompany($companyId, $paymentList);

        $this->setErrorMsg('请求成功');
        return $this->outputFormat($result);
    }

    // --------------------------------------------------------------------

    /**
     * 绑定支付规则
     *
     * @return array
     */
    public function bindRule(Request $request)
    {
        $params = $this->getContentArray($request);

        if (empty($params['sc_id']) OR empty($params['sr_id'])) {
            $this->setErrorMsg('请求参数错误');
            return $this->outputFormat(null, 400);
        }

        // 结算通道ID
        $scId = $params['sc_id'];

        // 规则ID
        $srId = $params['sr_id'];

        $settlementLogic = new SettlementChannelLogic();

        // 绑定支付
        $result = $settlementLogic->bindRule($scId, $srId);

        $this->setErrorMsg('请求成功');
        return $this->outputFormat($result);
    }

    // --------------------------------------------------------------------

    /**
     * 绑定账户
     *
     * @return array
     */
    public function bindAccount(Request $request)
    {
        $params = $this->getContentArray($request);

        if (empty($params['sc_id']) OR empty($params['account_type']) OR empty($params['account_bn'])) {
            $this->setErrorMsg('请求参数错误');
            return $this->outputFormat(null, 400);
        }

        // 结算通道ID
        $scId = $params['sc_id'];

        // 账户类型
        $accountType = $params['account_type'];

        // 账户编码
        $accountBn = $params['account_bn'];

        $settlementLogic = new SettlementChannelLogic();

        // 绑定支付
        $result = $settlementLogic->bindAccount($scId, $accountType, $accountBn);

        $this->setErrorMsg('请求成功');
        return $this->outputFormat($result);
    }

    // --------------------------------------------------------------------

    /**
     * 获取渠道列表
     *
     * @return array
     */
    public function getChannelList(Request $request)
    {
        $params = $this->getContentArray($request);

        // 页数
        $page = $params['page'];

        // 每页数量
        $pageSize = $params['page_size'];

        // 过滤
        $filter = $params['filter'];

        $settlementChannelLogic = new SettlementChannelLogic();

        // 获取渠道列表
        $channelList = $settlementChannelLogic->getChannelList($page, $pageSize, $filter);

        $this->setErrorMsg('请求成功');
        return $this->outputFormat($channelList);
    }


    // --------------------------------------------------------------------

    /**
     * 获取支付列表 by company
     *
     * @return array
     */
    public function getPaymentByCompany(Request $request)
    {
        $params = $this->getContentArray($request);

        if (empty($params['company_id'])) {
            $this->setErrorMsg('请求参数错误');
            return $this->outputFormat(null, 400);
        }
        
        // 公司ID
        $companyId = $params['company_id'];

        $settlementChannelLogic = new SettlementChannelLogic();

        // 获取渠道列表
        $channelList = $settlementChannelLogic->getPaymentByCompany($companyId);

        $this->setErrorMsg('请求成功');
        return $this->outputFormat($channelList);
    }

    // --------------------------------------------------------------------

    /**
     * 支付
     *
     * $data = array(
            'company_id' => $this->request_data['company_id'],
            'order_id' => $this->request_data['temp_order_id'],
            'payment_list' => array(
                'PAYMENT' => array('cash' => $cashMoney),
                'POINT' => array($this->request_data['point_channel'] => $pointAmount)
            ),
            'final_amount'  => $this->request_data['final_amount'], // 订单最终金额
            'pmt_amount'    => $this->request_data['pmt_amount'], // 优惠金额
            'cost_freight'  => $this->request_data['cost_freight'],
            'goods_list'    => $goods_list,
    );
     * @return array
     */
    public function orderPay(Request $request)
    {
        $params = $this->getContentArray($request);

        if (empty($params['company_id']) OR empty($params['order_id'])) {
            $this->setErrorMsg('请求参数错误');
            return $this->outputFormat(null, 400);
        }

        // 公司ID
        $companyId = $params['company_id'];

        // 订单信息
        $orderId = $params['order_id'];

        // 订单信息
        $orderInfo = $params['order_info'];

        // 支付信息
        $paymentList = $params['payment_list'];

        $settlementLogic = new SettlementChannelLogic();

        // 支付付款
        $errMsg = '';
        $errCode = 400;
        $result = $settlementLogic->orderPay($companyId, $paymentList, $orderId, $orderInfo, $errMsg, $errCode);

        if ( ! $result)
        {
            $errMsg OR $errMsg = '支付配置异常，暂时无法下单，请联系管理员解决后重试。';
            $this->setErrorMsg($errMsg);
            return $this->outputFormat(null, $errCode);
        }

        $this->setErrorMsg('请求成功');
        return $this->outputFormat(array('result' => $result));
    }

    // --------------------------------------------------------------------

    /**
     * 支付
     *
     * $data = array(
    'company_id' => $this->request_data['company_id'],
    'order_id' => $this->request_data['temp_order_id'],
    'payment_list' => array(
    'PAYMENT' => array('cash' => $cashMoney),
    'POINT' => array($this->request_data['point_channel'] => $pointAmount)
    ),
    'final_amount'  => $this->request_data['final_amount'], // 订单最终金额
    'pmt_amount'    => $this->request_data['pmt_amount'], // 优惠金额
    'cost_freight'  => $this->request_data['cost_freight'],
    'goods_list'    => $goods_list,
    );
     * @return array
     */
    public function orderRefund(Request $request)
    {
        $params = $this->getContentArray($request);

        if (empty($params['order_id'])) {
            $this->setErrorMsg('请求参数错误');
            return $this->outputFormat(null, 400);
        }

        // 订单信息
        $orderId = $params['order_id'];

        // 列表
        $itemList = $params['refund_list'];

        $settlementLogic = new SettlementChannelLogic();

        // 支付付款
        $errMsg = '';
        $result = $settlementLogic->orderRefund($orderId, $itemList, $errMsg);

        if ( ! $result)
        {
            $errMsg OR $errMsg = '结算通道订单退款失败';
            $this->setErrorMsg($errMsg);
            return $this->outputFormat(null, 400);
        }

        $this->setErrorMsg('请求成功');
        return $this->outputFormat(array('result' => $result));
    }

}
