<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-01-31
 * Time: 11:31
 */

namespace App\Api\V1\Controllers\ScenePoint;

use App\Api\Common\Controllers\BaseController;
use App\Api\Logic\PointServer\AdaperPoint;
use App\Api\Logic\Service;
use App\Api\V1\Service\PointScene\MemberAccount as MemberAccountServer;
use App\Api\V1\Service\PointScene\Scene as SceneServer;
use Illuminate\Http\Request;

class MemberAccountController extends BaseController
{
    /**
     * 查询公司下所有场景积分
     */
    public function QueryAll(Request $request)
    {
        $queryFilter = $this->getContentArray($request);
        if (empty($queryFilter['company_id']) || empty($queryFilter['member_id']) || empty($queryFilter['channel'])) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }

        $memberServer = new MemberAccountServer();
        $res          = $memberServer->QueryAll($queryFilter);
        if ($res['status']) {
            $this->setErrorMsg('请求成功');
            $adaperPoint = AdaperPoint::getInstance();
            foreach ($res['data'] as $key => $account) {
                $res['data'][$key]['point']         = $adaperPoint->GetPoint(
                    $account['point'],
                    $queryFilter['channel'],
                    AdaperPoint::RATE_TYPE_OUT
                );
                $res['data'][$key]['used_point']    = $adaperPoint->GetPoint(
                    $account['used_point'],
                    $queryFilter['channel'],
                    AdaperPoint::RATE_TYPE_OUT
                );
                $res['data'][$key]['frozen_point']  = $adaperPoint->GetPoint(
                    $account['frozen_point'],
                    $queryFilter['channel'],
                    AdaperPoint::RATE_TYPE_OUT
                );
                $res['data'][$key]['overdue_point'] = $adaperPoint->GetPoint(
                    $account['overdue_point'],
                    $queryFilter['channel'],
                    AdaperPoint::RATE_TYPE_OUT
                );
            }
            return $this->outputFormat($res['data'], 0);
        } else {
            $this->setErrorMsg($res['msg']);
            return $this->outputFormat(array(), 400);
        }
    }

    /**
     * 查询用户所有公司下积分账户
     */
    public function QueryAllCompany(Request $request)
    {
        $queryFilter = $this->getContentArray($request);
        if (empty($queryFilter['member_id'])) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }

        $page     = $queryFilter['page'] ?? 1;
        $pageSize = $queryFilter['page_size'] ?? 10;

        $memberServer = new MemberAccountServer();

        $serverLogic = new Service();

        $res = $memberServer->QueryAllCompany($queryFilter, $page, $pageSize);
        if ($res['status']) {
            $this->setErrorMsg('请求成功');
            $adaperPoint    = AdaperPoint::getInstance();
            $companyChannel = [];
            foreach ($res['data']['list'] as $key => $account) {
                if (isset($companyChannel[$account['company_id']])) {
                    $channelInfo = $companyChannel[$account['company_id']];
                } else {
                    $channelListRes                         = $serverLogic->ServiceCall(
                        'get_channel_list',
                        ['company_id' => $account['company_id']]
                    );
                    $channelInfo                            = current($channelListRes['data']);
                    $companyChannel[$account['company_id']] = $channelInfo;
                }

                $res['data']['list'][$key]['point']         = $adaperPoint->GetPoint(
                    $account['point'],
                    $channelInfo['channel'],
                    AdaperPoint::RATE_TYPE_OUT
                );
                $res['data']['list'][$key]['used_point']    = $adaperPoint->GetPoint(
                    $account['used_point'],
                    $channelInfo['channel'],
                    AdaperPoint::RATE_TYPE_OUT
                );
                $res['data']['list'][$key]['frozen_point']  = $adaperPoint->GetPoint(
                    $account['frozen_point'],
                    $channelInfo['channel'],
                    AdaperPoint::RATE_TYPE_OUT
                );
                $res['data']['list'][$key]['overdue_point'] = $adaperPoint->GetPoint(
                    $account['overdue_point'],
                    $channelInfo['channel'],
                    AdaperPoint::RATE_TYPE_OUT
                );
            }
            return $this->outputFormat($res['data'], 0);
        } else {
            $this->setErrorMsg($res['msg']);
            return $this->outputFormat(array(), 400);
        }
    }

    public function QueryByCompany(Request $request)
    {
        $queryFilter = $this->getContentArray($request);
        if (empty($queryFilter['member_id'])) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }

        $page     = $queryFilter['page'] ?? 1;
        $pageSize = $queryFilter['page_size'] ?? 10;

        $memberServer = new MemberAccountServer();

        $serverLogic = new Service();

        $res = $memberServer->QueryByCompany($queryFilter, $page, $pageSize);
        if ($res['status']) {
            $this->setErrorMsg('请求成功');
            $adaperPoint    = AdaperPoint::getInstance();
            $companyChannel = [];
            foreach ($res['data']['list'] as $key => $account) {
                if (isset($companyChannel[$account['company_id']])) {
                    $channelInfo = $companyChannel[$account['company_id']];
                } else {
                    $channelListRes                         = $serverLogic->ServiceCall(
                        'get_channel_list',
                        ['company_id' => $account['company_id']]
                    );
                    $channelInfo                            = current($channelListRes['data']);
                    $companyChannel[$account['company_id']] = $channelInfo;
                }

                $res['data']['list'][$key]['point']         = $adaperPoint->GetPoint(
                    $account['point'],
                    $channelInfo['channel'],
                    AdaperPoint::RATE_TYPE_OUT
                );
                $res['data']['list'][$key]['used_point']    = $adaperPoint->GetPoint(
                    $account['used_point'],
                    $channelInfo['channel'],
                    AdaperPoint::RATE_TYPE_OUT
                );
                $res['data']['list'][$key]['frozen_point']  = $adaperPoint->GetPoint(
                    $account['frozen_point'],
                    $channelInfo['channel'],
                    AdaperPoint::RATE_TYPE_OUT
                );
                $res['data']['list'][$key]['overdue_point'] = $adaperPoint->GetPoint(
                    $account['overdue_point'],
                    $channelInfo['channel'],
                    AdaperPoint::RATE_TYPE_OUT
                );
            }
            return $this->outputFormat($res['data'], 0);
        } else {
            $this->setErrorMsg($res['msg']);
            return $this->outputFormat(array(), 400);
        }
    }


    /**
     * 获取从当前时间到指定时间内过期的积分
     */
    public function QueryByOverdueTime(Request $request)
    {
        $queryFilter = $this->getContentArray($request);
        if (
            empty($queryFilter['company_id']) ||
            empty($queryFilter['member_id']) ||
            empty($queryFilter['channel']) ||
            empty($queryFilter['end_time'])
        ) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }

        $memberServer = new MemberAccountServer();
        $res          = $memberServer->QueryByOverdueTime($queryFilter);
        if ($res['status']) {
            $this->setErrorMsg('请求成功');
            $adaperPoint = AdaperPoint::getInstance();
            foreach ($res['data'] as $key => $account) {
                $res['data'][$key]['point']        = $adaperPoint->GetPoint(
                    $account['point'],
                    $queryFilter['channel'],
                    AdaperPoint::RATE_TYPE_OUT
                );
                $res['data'][$key]['used_point']   = $adaperPoint->GetPoint(
                    $account['used_point'],
                    $queryFilter['channel'],
                    AdaperPoint::RATE_TYPE_OUT
                );
                $res['data'][$key]['frozen_point'] = $adaperPoint->GetPoint(
                    $account['frozen_point'],
                    $queryFilter['channel'],
                    AdaperPoint::RATE_TYPE_OUT
                );
            }
            return $this->outputFormat($res['data'], 0);
        } else {
            $this->setErrorMsg($res['msg']);
            return $this->outputFormat(array(), 400);
        }
    }

    /**
     * 用户下单
     */
    public function CreateOrder(Request $request)
    {
        $frozenData = $this->getContentArray($request);
        if (
            empty($frozenData['order_id']) ||
            empty($frozenData['system_code']) ||
            empty($frozenData['company_id']) ||
            empty($frozenData['channel']) ||
            empty($frozenData['member_id']) ||
            empty($frozenData['point']) ||
            !is_array($frozenData['account_list']) ||
            count($frozenData['account_list']) < 1 ||
            $frozenData['point'] <= 0
        ) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }

        $frozenData['overdue_time'] = $frozenData['overdue_time'] ?? 0;
        $adaperPoint                = AdaperPoint::getInstance();
        $frozenData['float_lenght'] = $adaperPoint->GetFloatLengthByChannel($frozenData['channel']);
        $frozenData['point']        = $adaperPoint->GetPoint(
            $frozenData['point'],
            $frozenData['channel'],
            AdaperPoint::RATE_TYPE_INT
        );
        foreach ($frozenData['account_list'] as $key => $info) {
            $frozenData['account_list'][$key]['point'] = $adaperPoint->GetPoint(
                $frozenData['account_list'][$key]['point'],
                $frozenData['channel'],
                AdaperPoint::RATE_TYPE_INT
            );
        }
        $frozenData['business_type'] = 'createOrder';
        $frozenData['business_bn']   = $frozenData['order_id'];

        $memberServer = new MemberAccountServer();

        $res = $memberServer->CreateOrder($frozenData);
        $this->setErrorMsg($res['msg'] ? $res['msg'] : "积分锁定失败");
        if ($res['status']) {
            foreach ($res['data']['frozen_data'] as $key => $info) {
                $res['data']['frozen_data'][$key]['point'] = $adaperPoint->GetPoint(
                    $info['point'],
                    $frozenData['channel'],
                    AdaperPoint::RATE_TYPE_OUT
                );
            }
            return $this->outputFormat($res['data'], 0);
        } else {
            return $this->outputFormat(array(), 400);
        }
    }

    /**
     * 用户确认订单
     */
    public function OrderConfirm(Request $request)
    {
        $confirmData = $this->getContentArray($request);
        if (
            empty($confirmData['order_id']) ||
            empty($confirmData['system_code']) ||
            empty($confirmData['company_id']) ||
            empty($confirmData['member_id']) ||
            empty($confirmData['point']) ||
            empty($confirmData['channel']) ||
            !is_array($confirmData['account_list']) ||
            count($confirmData['account_list']) < 1 ||
            $confirmData['point'] <= 0
        ) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }

        $adaperPoint                 = AdaperPoint::getInstance();
        $confirmData['float_lenght'] = $adaperPoint->GetFloatLengthByChannel($confirmData['channel']);
        $confirmData['point']        = $adaperPoint->GetPoint(
            $confirmData['point'],
            $confirmData['channel'],
            AdaperPoint::RATE_TYPE_INT
        );

        foreach ($confirmData['account_list'] as $key => $info) {
            $confirmData['account_list'][$key]['point'] = $adaperPoint->GetPoint(
                $confirmData['account_list'][$key]['point'],
                $confirmData['channel'],
                AdaperPoint::RATE_TYPE_INT
            );
        }

        $confirmData['business_type']        = "confirmOrder";
        $confirmData['business_bn']          = $confirmData['order_id'];
        $confirmData['source_business_type'] = "createOrder";
        $confirmData['source_business_bn']   = $confirmData['order_id'];

        $memberServer = new MemberAccountServer();

        $res = $memberServer->OrderConfirm($confirmData);
        $this->setErrorMsg($res['msg']);
        if ($res['status']) {
            return $this->outputFormat($res['data'], 0);
        } else {
            return $this->outputFormat(array(), 400);
        }
    }

    /**
     * 订单取消
     */
    public function OrderCancel(Request $request)
    {
        $frozenData = $this->getContentArray($request);
        if (
            empty($frozenData['company_id']) ||
            empty($frozenData['member_id']) ||
            empty($frozenData['order_id']) ||
            empty($frozenData['system_code']) ||
            empty($frozenData['channel'])
        ) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }

        $frozenData['business_type'] = 'createOrder';
        $frozenData['business_bn']   = $frozenData['order_id'];

        $accountServer = new MemberAccountServer();
        $res           = $accountServer->ReleaseFrozen($frozenData);
        $this->setErrorMsg($res['msg']);
        if ($res['status']) {
            $adaperPoint = AdaperPoint::getInstance();
            foreach ($res['data'] as $key => $info) {
                $res['data'][$key]['point'] = $adaperPoint->GetPoint(
                    $res['data'][$key]['point'],
                    $frozenData['channel'],
                    AdaperPoint::RATE_TYPE_OUT
                );
            }
            return $this->outputFormat($res['data'], 0);
        } else {
            return $this->outputFormat(array(), 400);
        }
    }

    /**
     * 用户场景积分退还
     */
    public function OrderRefund(Request $request)
    {
        $refundData = $this->getContentArray($request);
        if (
            empty($refundData['refund_id']) ||
            empty($refundData['order_id']) ||
            empty($refundData['system_code']) ||
            empty($refundData['channel']) ||
            empty($refundData['point']) ||
            !is_array($refundData['account_list']) ||
            count($refundData['account_list']) < 1 ||
            $refundData['point'] <= 0
        ) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }

        $adaperPoint = AdaperPoint::getInstance();

        $refundData['float_lenght'] = $adaperPoint->GetFloatLengthByChannel($refundData['channel']);

        $refundData['total_point'] = $adaperPoint->GetPoint(
            $refundData['point'],
            $refundData['channel'],
            AdaperPoint::RATE_TYPE_INT
        );

        foreach ($refundData['account_list'] as $key => $info) {
            $refundData['account_list'][$key]['point'] = $adaperPoint->GetPoint(
                $refundData['account_list'][$key]['point'],
                $refundData['channel'],
                AdaperPoint::RATE_TYPE_INT
            );
        }

        $refundData['business_type'] = 'refundOrder';
        $refundData['business_bn']   = $refundData['refund_id'];

        $refundData['source_business_type'] = "confirmOrder";
        $refundData['source_business_bn']   = $refundData['order_id'];

        $memberServer = new MemberAccountServer();

        $res = $memberServer->OrderRefund($refundData);

        $this->setErrorMsg($res['msg']);
        if ($res['status']) {
            return $this->outputFormat($res['data'], 0);
        } else {
            return $this->outputFormat(array(), 400);
        }
    }

    public function GetMemberRecord(Request $request)
    {
        $queryData = $this->getContentArray($request);
        if (
        empty($queryData['channel'])
        ) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }

        $queryData['page']      = $queryData['page'] ? $queryData['page'] : 1;
        $queryData['page_size'] = $queryData['page_size'] ? $queryData['page_size'] : 10;

        $memberServer = new MemberAccountServer();

        $res = $memberServer->RecordList($queryData);
        $this->setErrorMsg($res['msg']);
        if ($res['status']) {
            $adaperPoint = AdaperPoint::getInstance();
            foreach ($res['data']['data'] as $key => $record) {
                $res['data']['data'][$key]['point'] = $adaperPoint->GetPoint(
                    $record['point'],
                    $queryData['channel'],
                    AdaperPoint::RATE_TYPE_OUT
                );
            }
            return $this->outputFormat($res['data'], 0);
        } else {
            return $this->outputFormat($res['data'], 400);
        }
    }

    /**
     * 查看可消耗场景
     */
    public function WithRule(Request $request)
    {
        $queryData = $this->getContentArray($request);
        if (
            empty($queryData['company_id']) ||
            empty($queryData['member_id']) ||
            empty($queryData['channel']) ||
            empty($queryData['filter_data'])
        ) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }

        $sceneServer = new SceneServer();
        $res         = $sceneServer->WithRule($queryData);
        $this->setErrorMsg($res['msg']);
        if ($res['status']) {
            return $this->outputFormat($res['data'], 0);
        } else {
            return $this->outputFormat(array(), 400);
        }
    }

    /**
     * 查询场景积分消费列表
     *
     * @param Request $request
     * @return array
     */
    public function GetScenePointConsumeList(Request $request)
    {
        $requestData = $this->getContentArray($request);

        if (empty($requestData['channel']) || empty($requestData['company_ids'])) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }

        if ($requestData['pageSize'] > 100) {
            $this->setErrorMsg('单次查询不能大于100条');
            return $this->outputFormat([], 400);
        }

        $memberServer = new MemberAccountServer();

        $res = $memberServer->GetScenePointConsumeList($requestData);
        $this->setErrorMsg($res['msg']);

        if ($res['status']) {
            $adaperPoint = AdaperPoint::getInstance();
            foreach ($res['data']['list'] as $key => $record) {
                $res['data']['list'][$key]['point'] = $adaperPoint->GetPoint(
                    $record['point'],
                    $requestData['channel'],
                    AdaperPoint::RATE_TYPE_OUT
                );
            }
            return $this->outputFormat($res['data'], 0);
        } else {
            return $this->outputFormat($res['data'], 400);
        }
    }

    // 用户积分转账 - 冻结
    public function TransferFrozen(Request $request){
        $frozenData = $this->getContentArray($request);

        if (empty($frozenData['channel']) ||
            empty($frozenData['transfer_code']) ||
            empty($frozenData['system_code']) ||
            empty($frozenData['pay_company_id']) ||
            empty($frozenData['pay_member_id']) ||
            empty($frozenData['transfer_point']) ||
            empty($frozenData['overdue_time']) ||
            empty($frozenData['scene_id'])
        ){
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }

        $frozenData['business_type'] = 'pointTransfer';
        $frozenData['business_bn'] = $frozenData['transfer_code'];

        // 渠道积分转化比例
        $AdaperPointObj = AdaperPoint::getInstance();

        $frozenData['transfer_point'] = $AdaperPointObj->GetPoint($frozenData['transfer_point'], $frozenData['channel'], AdaperPoint::RATE_TYPE_INT);

        $memberServer = new MemberAccountServer();

        $res = $memberServer->TransferFrozen($frozenData);

        $this->setErrorMsg($res['msg']);

        if ($res['status']){
            return $this->outputFormat($res['data']);
        }else{
            return $this->outputFormat($res['data'], 400);
        }
    }

    // 用户积分转账 - 释放冻结
    public function ReleaseTransferFrozen(Request $request){
        $frozenData = $this->getContentArray($request);

        if (empty($frozenData['channel']) ||
            empty($frozenData['transfer_code']) ||
            empty($frozenData['system_code']) ||
            empty($frozenData['pay_company_id']) ||
            empty($frozenData['pay_member_id']) ||
            empty($frozenData['transfer_point'])
        ){
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }

        $frozenData['business_type'] = 'pointTransfer';
        $frozenData['business_bn'] = $frozenData['transfer_code'];

        $memberServer = new MemberAccountServer();

        $releaseRes = $memberServer->ReleaseTransferFrozen($frozenData);

        $this->setErrorMsg($releaseRes['msg']);

        if ($releaseRes['status']){
            return $this->outputFormat($releaseRes['data']);
        }else{
            return $this->outputFormat($releaseRes['data'], 400);
        }
    }

    // 用户积分转账 - 转账
    public function ScenePointTransfer(Request $request){
        $transferData = $this->getContentArray($request);

        if (empty($transferData['channel']) ||
            empty($transferData['transfer_code']) ||
            empty($transferData['system_code']) ||
            empty($transferData['pay_company_id']) ||
            empty($transferData['pay_member_id']) ||
            empty($transferData['receive_company_id']) ||
            empty($transferData['receive_member_id']) ||
            empty($transferData['scene_id']) ||
            empty($transferData['transfer_point']) ||
            empty($transferData['business_frozen_code']) ||
            empty($transferData['overdue_time'])
        ){
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }

        $transferData['business_type'] = 'pointTransfer';
        $transferData['business_bn'] = $transferData['transfer_code'];

        // 渠道积分转化比例
        $AdaperPointObj = AdaperPoint::getInstance();

        $transferData['transfer_point'] = $AdaperPointObj->GetPoint($transferData['transfer_point'], $transferData['channel'], AdaperPoint::RATE_TYPE_INT);

        // 子账户积分过期处理方式
        $transferData['overdue_func'] = $transferData['overdue_func'] ?? 'inaction';

        $memberServer = new MemberAccountServer();

        $res = $memberServer->ScenePointTransfer($transferData);

        $this->setErrorMsg($res['msg']);

        if ($res['status']){
            return $this->outputFormat($res['data']);
        }else{
            return $this->outputFormat($res['data'], 400);
        }
    }

    // 查询积分转账付款子账户支付详情
    public function queryTransferPaySonAccount(Request $request){
        $queryData = $this->getContentArray($request);

        if (empty($queryData['business_type']) ||
            empty($queryData['business_bn']) ||
            empty($queryData['system_code']) ||
            empty($queryData['channel'])
        ){
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }

        $memberSever = new MemberAccountServer();

        $res = $memberSever->getTransferPaySonAccount($queryData);

        $this->setErrorMsg($res['msg']);

        if ($res['status']){
            $AdaperPointObj = AdaperPoint::getInstance();

            foreach ($res['data'] as &$val){
                $val['point'] = $AdaperPointObj->GetPoint($val['point'], $queryData['channel'], AdaperPoint::RATE_TYPE_OUT);
            }

            return $this->outputFormat($res['data']);
        }

        return $this->outputFormat($res['data'], 400);
    }
}
