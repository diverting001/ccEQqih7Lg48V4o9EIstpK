<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-11-29
 * Time: 16:03
 */

namespace App\Api\V3\Controllers\ScenePoint;


use App\Api\Common\Controllers\BaseController;
use App\Api\V3\Service\ScenePoint\MemberAccount as MemberAccountServer;
use App\Api\V3\Service\ScenePoint\BusinessFlow;
use Illuminate\Http\Request;

/**
 * 与积分服务对接的api
 * Class ScenePointController
 * @package App\Api\V3\Controllers
 */
class ScenePointApiController extends BaseController
{
    /**
     * 查询公司下所有场景积分
     */
    public function GetMemberAccount(Request $request)
    {
        $requestData = $this->getContentArray($request);

        if (
            empty($requestData['company_id']) ||
            empty($requestData['member_id'])
        ) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }

        $accountServer = new MemberAccountServer();

        $accountRes = $accountServer->GetMemberAccount([
            'company_id' => $requestData['company_id'],
            'member_id'  => $requestData['member_id']
        ]);

        $this->setErrorMsg($accountRes['msg']);

        if (!$accountRes['status']) {
            return $this->outputFormat([], 400);
        }

        return $this->outputFormat($accountRes['data']);
    }

    public function LockMemberPoint(Request $request)
    {
        $requestData = $this->getContentArray($request);
        if (
            empty($requestData['order_id']) ||
            empty($requestData['system_code']) ||
            empty($requestData['company_id']) ||
            empty($requestData['member_id']) ||
            empty($requestData['point']) ||
            !is_array($requestData['account_list']) ||
            count($requestData['account_list']) < 1 ||
            $requestData['point'] <= 0
        ) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }

        $requestData['business_type'] = 'createOrder';
        $requestData['business_bn']   = $requestData['order_id'];

        $accountServer = new MemberAccountServer();

        $accountRes = $accountServer->LockMemberPoint([
            'business_type' => 'createOrder',
            'business_bn'   => $requestData['order_id'],
            'system_code'   => $requestData['system_code'],
            'company_id'    => $requestData['company_id'],
            'money'         => $requestData['money'],
            'point'         => $requestData['point'],
            'overdue_time'  => $requestData['overdue_time'] ?? 0,
            'account_list'  => $requestData['account_list'],
            'memo'          => $requestData['memo'],
        ]);

        $this->setErrorMsg($accountRes['msg']);

        if (!$accountRes['status']) {
            return $this->outputFormat([], 400);
        }

        return $this->outputFormat($accountRes['data']);
    }


    public function ConfirmMemberPoint(Request $request)
    {
        $requestData = $this->getContentArray($request);
        if (
            empty($requestData['order_id']) ||
            empty($requestData['system_code']) ||
            empty($requestData['company_id']) ||
            empty($requestData['member_id']) ||
            empty($requestData['point']) ||
            empty($requestData['channel']) ||
            !is_array($requestData['account_list']) ||
            count($requestData['account_list']) < 1 ||
            $requestData['point'] <= 0
        ) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }

        $requestData['business_type']        = "confirmOrder";
        $requestData['business_bn']          = $requestData['order_id'];
        $requestData['source_business_type'] = "createOrder";
        $requestData['source_business_bn']   = $requestData['order_id'];

        $accountServer = new MemberAccountServer();

        $res = $accountServer->ConfirmMemberPoint($requestData);

        $this->setErrorMsg($res['msg']);

        if (!$res['status']) {
            return $this->outputFormat(array(), 400);
        }

        return $this->outputFormat($res['data']);
    }

    public function CancelMemberPoint(Request $request)
    {
        $requestData = $this->getContentArray($request);
        if (
            empty($requestData['company_id']) ||
            empty($requestData['member_id']) ||
            empty($requestData['order_id']) ||
            empty($requestData['system_code']) ||
            empty($requestData['channel'])
        ) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }

        $requestData['business_type'] = 'createOrder';
        $requestData['business_bn']   = $requestData['order_id'];

        $accountServer = new MemberAccountServer();

        $res = $accountServer->CancelMemberPoint($requestData);

        $this->setErrorMsg($res['msg']);

        if (!$res['status']) {
            return $this->outputFormat(array(), 400);
        }

        return $this->outputFormat($res['data']);
    }

    public function RefundMemberPoint(Request $request)
    {
        $requestData = $this->getContentArray($request);
        if (
            empty($requestData['refund_id']) ||
            empty($requestData['order_id']) ||
            empty($requestData['system_code']) ||
            empty($requestData['channel']) ||
            empty($requestData['point']) ||
            !is_array($requestData['account_list']) ||
            count($requestData['account_list']) < 1 ||
            $requestData['point'] <= 0
        ) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }

        $requestData['business_type'] = 'refundOrder';
        $requestData['business_bn']   = $requestData['refund_id'];

        $requestData['source_business_type'] = "confirmOrder";
        $requestData['source_business_bn']   = $requestData['order_id'];

        $accountServer = new MemberAccountServer();

        $res = $accountServer->RefundMemberPoint($requestData);

        $this->setErrorMsg($res['msg']);

        if (!$res['status']) {
            return $this->outputFormat(array(), 400);
        }

        return $this->outputFormat($res['data']);
    }

    public function GetMemberRecord(Request $request)
    {
        $requestData = $this->getContentArray($request);

        $requestData['page']      = $requestData['page'] ?? 1;
        $requestData['page_size'] = $requestData['page_size'] ?? 10;

        $memberServer = new MemberAccountServer();

        $res = $memberServer->GetMemberRecord($requestData);

        $this->setErrorMsg($res['msg']);

        if (!$res['status']) {
            return $this->outputFormat($res['data'], 400);
        }

        return $this->outputFormat($res['data']);
    }

    public function MemberPointWithRule(Request $request)
    {
        $requestData = $this->getContentArray($request);

        if (
            empty($requestData['company_id']) ||
            empty($requestData['member_id']) ||
            empty($requestData['channel']) ||
            empty($requestData['filter_data'])
        ) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }

        $memberServer = new MemberAccountServer();

        $res = $memberServer->MemberPointWithRule($requestData);

        $this->setErrorMsg($res['msg']);

        if (!$res['status']) {
            return $this->outputFormat($res['data'], 400);
        }

        return $this->outputFormat($res['data']);
    }

    /**
     * @param  Request  $request
     * @return array
     */
    public function GetMemberSceneAccount(Request $request)
    {
        $requestData = $this->getContentArray($request);

        if (empty($requestData['accounts'])) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }

        $accountServer = new MemberAccountServer();

        $accountRes = $accountServer->GetMemberSceneAccount($requestData['accounts']);

        $this->setErrorMsg($accountRes['msg']);

        if (!$accountRes['status']) {
            return $this->outputFormat([], 400);
        }

        return $this->outputFormat($accountRes['data']);
    }

    public function GetMemberBusinessFlow(Request $request)
    {
        $requestData = $this->getContentArray($request);
        $type = $requestData['type'] ?: "transfer";
        $businessFlow = new BusinessFlow();
        $businessFlowRes = $businessFlow->GetBusinessFlow($type, $requestData['account']);
        if (!$businessFlowRes['status']) {
            return $this->outputFormat([], 400);
        }

        return $this->outputFormat($businessFlowRes['data']);
    }

}
