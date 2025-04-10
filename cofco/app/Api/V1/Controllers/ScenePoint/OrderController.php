<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-08-05
 * Time: 14:54
 */

namespace App\Api\V1\Controllers\ScenePoint;


use App\Api\Common\Controllers\BaseController;
use App\Api\Logic\PointServer\AdaperPoint;
use Illuminate\Http\Request;
use App\Api\V1\Service\PointScene\MemberAccount as MemberAccountServer;

class OrderController extends BaseController
{
    public function RecordGet(Request $request)
    {
        $requestData = $this->getContentArray($request);
        $orderId = $requestData['order_id'] ?? '';
        $systemCode = $requestData['system_code'] ?? '';
        $pointChannel = $requestData['channel'] ?? '';
        if (!$orderId || !$systemCode || !$pointChannel) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }


        $accountServer = new MemberAccountServer();
        $frozenRes = $accountServer->getFrozen([
            'business_type' => 'createOrder',
            'business_bn' => $orderId,
            'system_code' => $systemCode
        ]);
        if (!$frozenRes['status']) {
            $this->setErrorMsg($frozenRes['msg']);
            return $this->outputFormat([], 400);
        }

        //追加公司账户ID
        $frozenData = $frozenRes['data'];
        $accountList = array_column($frozenData, 'account');
        $accountRes = $accountServer->QueryByAccountList($accountList);
        if (!$accountRes['status']) {
            $this->setErrorMsg($accountRes['msg']);
            return $this->outputFormat([], 400);
        }

        $accountData = $accountRes['data'];
        $adaperPoint = AdaperPoint::getInstance();
        foreach ($frozenData as &$frozenInfo) {
            unset($frozenInfo['account_id']);
            $memberAccount = $accountData[$frozenInfo['account']];
            $frozenInfo['member_id'] = $memberAccount->member_id;
            $frozenInfo['company_id'] = $memberAccount->company_id;
            $frozenInfo['scene_id'] = $memberAccount->scene_id;
            $frozenInfo['frozen_point'] = $adaperPoint->GetPoint(
                $frozenInfo['frozen_point'],
                $pointChannel,
                AdaperPoint::RATE_TYPE_OUT
            );
            $frozenInfo['finish_point'] = $adaperPoint->GetPoint(
                $frozenInfo['finish_point'],
                $pointChannel,
                AdaperPoint::RATE_TYPE_OUT
            );
            $frozenInfo['release_point'] = $adaperPoint->GetPoint(
                $frozenInfo['release_point'],
                $pointChannel,
                AdaperPoint::RATE_TYPE_OUT
            );
        }
        $this->setErrorMsg('查询成功');
        return $this->outputFormat($frozenData);
    }
}
