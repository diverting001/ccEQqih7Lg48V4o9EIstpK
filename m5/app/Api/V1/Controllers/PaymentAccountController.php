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
use App\Api\V1\Service\Payment\Account as PaymentAccountLogic;
use Illuminate\Http\Request;

/**
 * 支付账户
 *
 * @package     api
 * @category    Controller
 * @author      xupeng
 */
class PaymentAccountController extends BaseController
{
    /**
     * 创建账户
     *
     * @return array
     */
    public function createAccount(Request $request)
    {
        $params = $this->getContentArray($request);

        // 参数验证
        if (empty($params['name']) OR empty($params['rule_ids']))
        {
            $this->setErrorMsg('请求参数错误');
            return $this->outputFormat(null, 400);
        }

        // 名称
        $name = $params['name'];

        // 规则ID
        $ruleIds = $params['rule_ids'];

        // 账户编码
        $accountCode = $params['account_code'];

        // 备注
        $memo = $params['memo'] ? $params['memo'] : '';

        $accountLogic = new PaymentAccountLogic();

        // 创建账户信息
        $accountInfo = $accountLogic->createAccount($name, $ruleIds, $accountCode, $memo);

        $this->setErrorMsg('请求成功');
        return $this->outputFormat($accountInfo);
    }

    // --------------------------------------------------------------------

    /**
     * 获取账户信息
     *
     * @return array
     */
    public function getAccountInfo(Request $request)
    {
        $params = $this->getContentArray($request);

        if (empty($params['pa_id'])) {
            $this->setErrorMsg('请求参数错误');
            return $this->outputFormat(null, 400);
        }

        // 账号ID
        $paId = $params['pa_id'];

        $accountLogic = new PaymentAccountLogic();

        // 获取账户信息
        $accountInfo = $accountLogic->getAccountInfo($paId);

        $this->setErrorMsg('请求成功');
        return $this->outputFormat($accountInfo);
    }

    // --------------------------------------------------------------------

    /**
     * 获取账户支付列表
     *
     * @return array
     */
    public function getAccountList(Request $request)
    {
        $params = $this->getContentArray($request);

        // 页码
        $page = isset($params['page']) ? $params['page'] : 1;

        // 页数
        $pageSize = isset($params['page_size']) ? $params['page_size'] : 20;

        $filter = isset($params['filter']) ? $params['filter'] : array();

        $accountLogic = new PaymentAccountLogic();

        $result = $accountLogic->getPaymentAccountList($page, $pageSize, $filter);

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

        if (empty($params['pa_id']) OR empty($params['account_code'])) {
            $this->setErrorMsg('请求参数错误');
            return $this->outputFormat(null, 400);
        }

        // 账号ID
        $paId = $params['pa_id'];

        // 账户编码
        $accountCode = $params['account_code'];

        $accountLogic = new PaymentAccountLogic();

        // 获取账户信息
        $accountInfo = $accountLogic->bindAccount($paId, $accountCode);

        $this->setErrorMsg('请求成功');
        return $this->outputFormat($accountInfo);
    }

    // --------------------------------------------------------------------

    /**
     * 订单支付
     *
     * @return array
     */
    public function orderPay(Request $request)
    {
        $params = $this->getContentArray($request);

        if (empty($params['order_id']) OR empty($params['pay_list'])) {
            $this->setErrorMsg('请求参数错误');
            return $this->outputFormat(null, 400);
        }

        $accountLogic = new PaymentAccountLogic();

        // 订单付款
        $errMsg = '';
        $result = $accountLogic->orderPay($params['order_id'], $params['pay_list'], $params['source'], $errMsg);

        if (empty($result))
        {
            $errMsg OR $errMsg = '支付配置异常，暂时无法下单，请联系管理员解决后重试。';
            $this->setErrorMsg($errMsg);
            return $this->outputFormat(null, 400);
        }

        $this->setErrorMsg('请求成功');
        return $this->outputFormat($result);
    }

    // --------------------------------------------------------------------

    /**
     * 订单退款
     *
     * @return array
     */
    public function orderRefund(Request $request)
    {
        $params = $this->getContentArray($request);

        if (empty($params['order_id']) OR empty($params['refund_list'])) {
            $this->setErrorMsg('请求参数错误');
            return $this->outputFormat(null, 400);
        }

        $accountLogic = new PaymentAccountLogic();

        // 订单退款
        $errMsg = '';
        $result = $accountLogic->orderRefund($params['order_id'], $params['refund_list'], $params['source'], $errMsg);

        if (empty($result))
        {
            $errMsg OR $errMsg = '账户退款失败';
            $this->setErrorMsg($errMsg);
            return $this->outputFormat(null, 400);
        }

        $this->setErrorMsg('请求成功');
        return $this->outputFormat($result);
    }

}
