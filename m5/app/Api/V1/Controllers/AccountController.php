<?php
/**
 * neigou_service-stock
 *
 * @package     api
 * @author      xupeng
 * @since       Version
 * @filesource
 */

namespace App\Api\V1\Controllers;

use App\Api\Common\Controllers\BaseController;
use App\Api\V1\Service\Account\Account as AccountLogic;
use Illuminate\Http\Request;

/**
 * 账户相关
 *
 * @package     api
 * @category    Controller
 * @author        xupeng
 */
class AccountController extends BaseController
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
        if (empty($params['name']) OR empty($params['channel']))
        {
            $this->setErrorMsg('请求参数错误');

            return $this->outputFormat(null, 400);
        }

        // 渠道
        $channel = $params['channel'];

        // 编码
        $name = $params['name'];

        // 授信额度
        $creditLimit = isset($params['credit_limit']) ? $params['credit_limit'] : 0;

        // 持卡人信息
        $ownName = $params['own_name'] ? $params['own_name'] : '';

        // 备注
        $memo = $params['memo'] ? $params['memo'] : '';

        // 状态
        $status = isset($params['status']) ? $params['status'] : null;

        $accountLogic = new AccountLogic();

        // 创建账户信息
        $accountInfo = $accountLogic->createAccount($channel, $name, $creditLimit, $ownName, $memo, $status);

        $this->setErrorMsg('请求成功');

        return $this->outputFormat($accountInfo);
    }

    // --------------------------------------------------------------------

    /**
     * 更新账户
     *
     * @return array
     */
    public function updateAccount(Request $request)
    {
        $params = $this->getContentArray($request);

        // 参数验证
        if (empty($params['account_code']) OR empty($params['data']))
        {
            $this->setErrorMsg('请求参数错误');

            return $this->outputFormat(null, 400);
        }

        // 账户编码
        $accountCode = $params['account_code'];

        $data = $params['data'];

        $accountLogic = new AccountLogic();

        // 更新账户信息
        $accountInfo = $accountLogic->updateAccount($accountCode, $data);

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

        // 审核
        if (empty($params['account_code']))
        {
            $this->setErrorMsg('请求参数错误');

            return $this->outputFormat(null, 400);
        }

        // 编码
        $accountCode = $params['account_code'];

        $accountLogic = new AccountLogic();

        // 获取账户信息
        $accountInfo = $accountLogic->getAccountInfo($accountCode);

        $this->setErrorMsg('请求成功');

        return $this->outputFormat($accountInfo);
    }

    // --------------------------------------------------------------------

    /**
     * 账户余额充值
     *
     * @return array
     */
    public function recharge(Request $request)
    {
        $params = $this->getContentArray($request);

        if (empty($params['account_code']) OR $params['recharge_amount'] <= 0)
        {
            $this->setErrorMsg('请求参数错误');

            return $this->outputFormat(null, 400);
        }

        // 编码
        $accountCode = $params['account_code'];

        // 操作人
        $operator = isset($params['operator']) ? $params['operator'] : '';

        // 凭证
        $cert = isset($params['cert']) ? $params['cert'] : '';

        // 操作备注
        $memo = isset($params['memo']) ? $params['memo'] : '';

        $accountLogic = new AccountLogic();

        // 账户余额充值
        $errMsg = '';
        $result = $accountLogic->recharge($accountCode, $params['recharge_amount'], $cert, $operator, $memo, $errMsg);
        if ( ! $result)
        {
            $errMsg OR $errMsg = '账户余额充值失败';
            $this->setErrorMsg($errMsg);

            return $this->outputFormat(null, 400);
        }

        $this->setErrorMsg('请求成功');

        return $this->outputFormat($result);
    }

    // --------------------------------------------------------------------

    /**
     * 更新授信额度
     *
     * @return array
     */
    public function updateCreditLimit(Request $request)
    {
        $params = $this->getContentArray($request);

        // 审核
        if (empty($params['account_code']) OR $params['credit_limit'] < 0)
        {
            $this->setErrorMsg('请求参数错误');

            return $this->outputFormat(null, 400);
        }

        // 编码
        $accountCode = $params['account_code'];

        // 凭证
        $cert = isset($params['cert']) ? $params['cert'] : '';

        // 操作人
        $operator = isset($params['operator']) ? $params['operator'] : '';

        // 操作备注
        $memo = isset($params['memo']) ? $params['memo'] : '';

        $accountLogic = new AccountLogic();

        // 更新授信额度
        $errMsg = '';
        if ( ! $accountLogic->updateCreditLimit($accountCode, $params['credit_limit'], $cert, $operator, $memo, $errMsg))
        {
            $errMsg OR $errMsg = '更新授信额度';
            $this->setErrorMsg($errMsg);

            return $this->outputFormat(null, 400);
        }

        // 获取账户信息
        $accountInfo = $accountLogic->getAccountInfo($accountCode);

        $this->setErrorMsg('请求成功');

        return $this->outputFormat($accountInfo);
    }

    // --------------------------------------------------------------------

    /**
     * 账户扣款
     *
     * @return array
     */
    public function deductBatch(Request $request)
    {
        $paramsList = $this->getContentArray($request);

        foreach ($paramsList as $param)
        {
            // trade_category 交易分类
            if (empty($param['account_code']) OR $param['amount'] == 0 OR empty($param['trade_type']) OR empty($param['trade_bn']))
            {
                $this->setErrorMsg('请求参数错误');

                return $this->outputFormat(null, 400);
            }
        }

        $accountLogic = new AccountLogic();

        // 账户余额扣款
        $errMsg = '';
        $result = $accountLogic->deductBatch($paramsList, $errMsg);
        if ( ! $result)
        {
            $errMsg OR $errMsg = '账户余额扣款失败';
            $this->setErrorMsg($errMsg);

            return $this->outputFormat(null, 400);
        }

        $this->setErrorMsg('请求成功');

        return $this->outputFormat($result);
    }

    // --------------------------------------------------------------------

    /**
     * 账户退款
     *
     * @return array
     */
    public function refundBatch(Request $request)
    {
        $paramsList = $this->getContentArray($request);

        foreach ($paramsList as $param)
        {
            // trade_category 交易分类
            if (empty($param['account_code']) OR $param['amount'] == 0 OR empty($param['trade_type']) OR empty($param['trade_bn']))
            {
                $this->setErrorMsg('请求参数错误');

                return $this->outputFormat(null, 400);
            }
        }

        $accountLogic = new AccountLogic();

        // 账户余额退款
        $errMsg = '';
        $result = $accountLogic->refundBatch($paramsList, $errMsg);
        if ( ! $result)
        {
            $errMsg OR $errMsg = '账户退款失败';
            $this->setErrorMsg($errMsg);

            return $this->outputFormat(null, 400);
        }

        $this->setErrorMsg('请求成功');

        return $this->outputFormat($result);
    }


    // --------------------------------------------------------------------

    /**
     * 获取账户列表
     *
     * @return array
     */
    public function getAccountList(Request $request)
    {
        $params = $this->getContentArray($request);

        // 过滤
        $filter = $params['filter'];

        // 页数
        $page = isset($params['page']) ? $params['page']: 1;

        // 每夜数量
        $pageSize = isset($params['page_size']) && $params['page_size'] > 0 ? $params['page_size'] : 20;

        $accountLogic = new AccountLogic();

        // 获取账单列表
        $accountBillList = $accountLogic->getAccountList($page, $pageSize, $filter);

        $this->setErrorMsg('请求成功');

        return $this->outputFormat($accountBillList);
    }

    // --------------------------------------------------------------------

    /**
     * 获取账单列表
     *
     * @return array
     */
    public function getBillList(Request $request)
    {
        $params = $this->getContentArray($request);

        // 请求参数
        if (empty($params['start_date']) OR empty($params['end_date']))
        {
            $this->setErrorMsg('请求参数错误');

            return $this->outputFormat(null, 400);
        }

        // 开始时间
        $startDate = date('Y-m-d', strtotime($params['start_date']));

        // 结束时间
        $endDate = date('Y-m-d', strtotime($params['end_date']));

        // 过滤条件
        $filter = $params['filter'];

        $accountLogic = new AccountLogic();

        // 获取账单列表
        $accountBillList = $accountLogic->getBillList($startDate, $endDate, $filter);

        $this->setErrorMsg('请求成功');

        return $this->outputFormat($accountBillList);
    }

    // --------------------------------------------------------------------

    /**
     * 获取账户记录
     *
     * @return array
     */
    public function getRecordList(Request $request)
    {
        $params = $this->getContentArray($request);

        /*
         * filter 过滤条件
         * account_code string or array 账户编码
         * name string 账户名称(模糊查询)
         * type string or array 类型 (deduct、refund、recharge、credit_limit、check_bill)
         * trade_bn string OR array  交易编号
         * create_time strong or array 范围查询：开始时间,结束时间
         */

        $filter = $params['filter'];

        // 页数
        $page = isset($params['page']) ? $params['page']: 1;

        // 每页数量
        $pageSize = isset($params['page_size']) && $params['page_size'] > 0 ? $params['page_size'] : 20;

        $accountLogic = new AccountLogic();

        // 获取账单列表
        $accountBillList = $accountLogic->getRecordList($filter, $page, $pageSize);

        $this->setErrorMsg('请求成功');

        return $this->outputFormat($accountBillList);
    }

    /**
     * 获取账户记录
     *
     * @return array
     */
    public function getDayAmountList(Request $request)
    {
        $params = $this->getContentArray($request);

        /*
         * filter 过滤条件
         * account_code string or array 账户编码
         * name string 账户名称(模糊查询)
         * type string or array 类型 (deduct、refund、recharge、credit_limit、check_bill)
         * trade_bn string OR array  交易编号
         * create_time strong or array 范围查询：开始时间,结束时间
         */

        $accountLogic = new AccountLogic();
        // 获取账单列表
        $list = $accountLogic->getDayAmountList($params);
        $this->setErrorMsg('请求成功');
        return $this->outputFormat($list);
    }

    // --------------------------------------------------------------------

    /**
     * 获取账户记录总金额
     *
     * @return array
     */
    public function getAccountRecordTotalAmount(Request $request)
    {
        $params = $this->getContentArray($request);

        /*
         * filter 过滤条件
         * account_code string or array 账户编码
         * create_time string or array 范围查询：开始时间,结束时间
         */

        $filter = $params['filter'];

        $filter['type'] = array('deduct', 'refund');

        $accountLogic = new AccountLogic();

        // 获取账户总金额
        $accountTotalAmount = $accountLogic->getAccountRecordTotalAmount($filter);

        $this->setErrorMsg('请求成功');

        return $this->outputFormat($accountTotalAmount);
    }

    // --------------------------------------------------------------------

    /**
     * 添加账单
     *
     * @return array
     */
    public function createBill(Request $request)
    {
        $params = $this->getContentArray($request);

        // 请求参数
        if (empty($params['account_code']) OR empty($params['start_date']) OR empty($params['end_date']))
        {
            $this->setErrorMsg('请求参数错误');

            return $this->outputFormat(null, 400);
        }

        // 金额
        $amount = $params['amount'];

        // 核对金额
        $finalAmount = $params['final_amount'];

        // 备注
        $memo = $params['memo'];

        // 开始日期
        $startDate = $params['start_date'];

        // 结束日期
        $endDate = $params['end_date'];

        // 操作人
        $operator = $params['operator'];

        $accountLogic = new AccountLogic();

        if (strtotime($startDate) > strtotime($endDate))
        {
            $this->setErrorMsg('开始时间不能大于结束时间');

            return $this->outputFormat(null, 400);
        }

        // 生成账单
        if ( ! $accountLogic->createBill($params['account_code'], $amount, $finalAmount, $startDate, $endDate, $memo, $operator))
        {
            $this->setErrorMsg('生成账单失败');

            return $this->outputFormat(null, 400);
        }

        $this->setErrorMsg('请求成功');

        return $this->outputFormat(true);
    }

}
