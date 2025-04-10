<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-11-28
 * Time: 14:44
 */

namespace App\Api\V3\Controllers;


use App\Api\Common\Controllers\BaseController;
use App\Api\V3\Service\Point\Point;
use App\Api\Model\Point\Record as PointRecord;
use App\Api\Model\Point\Refund as PointRefund;
use Illuminate\Http\Request;

/**
 * 积分服务
 * Class PointController
 * @package App\Api\V3\Controllers
 */
class PointController extends BaseController
{

    /**
     * 获取用户积分账户
     * @param Request $request
     * @return array
     */
    public function GetMemberPoint(Request $request)
    {
        $requestData = $this->getContentArray($request);

        if (
            empty($requestData['company_id']) ||
            empty($requestData['member_id']) ||
            empty($requestData['channel'])
        ) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }

        $pointService = new Point();

        $pointDataRes = $pointService->GetMemberPoint([
            'company_id' => intval($requestData['company_id']),
            'member_id'  => intval($requestData['member_id']),
            'channel'    => $requestData['channel']
        ]);

        $this->setErrorMsg($pointDataRes['msg']);

        if (!$pointDataRes['status']) {
            return $this->outputFormat([], 501);
        }

        return $this->outputFormat($pointDataRes['data'], 0);
    }

    /**
     * 锁定用户积分
     * @param Request $request
     * @return array
     */
    public function LockMemberPoint(Request $request)
    {
        $requestData = $this->getContentArray($request);

        if (
            empty($requestData['company_id']) ||
            empty($requestData['member_id']) ||
            empty($requestData['channel']) ||
            empty($requestData['system_code']) ||
            empty($requestData['account_list'])
        ) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }

        $recordInfo = PointRecord::GetPointRecord([
            'use_type' => $requestData['use_type'],
            'use_obj'  => $requestData['use_obj'],
        ]);
        if (empty($recordInfo)) {
            $saveRes = PointRecord::AddPointRecord([
                'channel'     => $requestData['channel'],
                'use_type'    => $requestData['use_type'],
                'use_obj'     => $requestData['use_obj'],
                'money'       => $requestData['money'],
                'point'       => $requestData['point'],
                'company_id'  => $requestData['company_id'],
                'member_id'   => $requestData['member_id'],
                'status'      => 1,
                'create_time' => time(),
                'update_time' => time(),
            ]);
            if (!$saveRes) {
                $this->setErrorMsg('积分锁定记录保存失败');
                return $this->outputFormat([], 400);
            }
        } else {
            if ($recordInfo->status == 5) {
                $save_res = PointRecord::UpdatePointRecord(
                    [
                        'channel'     => $requestData['channel'],
                        'use_type'    => $requestData['use_type'],
                        'use_obj'     => $requestData['use_obj'],
                        'money'       => $requestData['money'],
                        'point'       => $requestData['point'],
                        'status'      => 1,
                        'update_time' => time(),
                    ],
                    [
                        'id'     => $recordInfo->id,
                        'status' => 5
                    ]
                );
                if (!$save_res) {
                    $this->setErrorMsg('积分锁定记录修改失败');
                    return $this->outputFormat([], 400);
                }
            } else {
                $this->setErrorMsg('积分锁定记录异常');
                return $this->outputFormat([], 400);
            }
        }


        $pointService = new Point();

        $lockDataRes = $pointService->LockMemberPoint([
            'system_code'     => $requestData['system_code'],
            'company_id'      => intval($requestData['company_id']),
            'member_id'       => intval($requestData['member_id']),
            'use_type'        => $requestData['use_type'],
            'use_obj'         => $requestData['use_obj'],
            'channel'         => $requestData['channel'],
            'money'           => $requestData['money'],
            'point'           => $requestData['point'],
            "overdue_time"    => $requestData['overdue_time'],
            'third_point_pwd' => $requestData['third_point_pwd'] ?? '',
            'account_list'    => $requestData['account_list'],
            'memo'            => $requestData['memo'] ?? '',
        ]);

        $this->setErrorMsg($lockDataRes['msg']);

        if (!$lockDataRes['status']) {
            PointRecord::UpdatePointRecord(
                [
                    'status'      => 5,
                    'update_time' => time(),
                ],
                [
                    'use_type' => $requestData['use_type'],
                    'use_obj'  => $requestData['use_obj'],
                    'status'   => 1
                ]
            );
            $code = 501;
            if($lockDataRes['msg'] == '密码错误'){
                $code = 511;
            }
            return $this->outputFormat([], $code);
        }

        PointRecord::UpdatePointRecord(
            [
                'status'      => 2,
                'update_time' => time(),
                'trade_no'    => $lockDataRes["data"]['trade_no']
            ],
            [
                'use_type' => $requestData['use_type'],
                'use_obj'  => $requestData['use_obj'],
                'status'   => 1
            ]
        );

        return $this->outputFormat($lockDataRes['data'], 0);
    }


    /**
     * 确认用户积分
     * @param Request $request
     * @return array
     */
    public function ConfirmMemberPoint(Request $request)
    {
        $requestData = $this->getContentArray($request);

        if (
            empty($requestData['member_id']) ||
            empty($requestData['channel']) ||
            empty($requestData['use_obj'])
        ) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }

        $recordInfo = PointRecord::GetPointRecord([
            'use_type' => $requestData['use_type'],
            'use_obj'  => $requestData['use_obj'],
        ]);
        if (empty($recordInfo)) {
            $this->setErrorMsg('积分锁定记录不存在');
            return $this->outputFormat([], 400);
        }
        if ($recordInfo->status != 2) {
            $this->setErrorMsg('积分锁定状态异常');
            return $this->outputFormat([], 400);
        }

        $pointService = new Point();

        $confirmDataRes = $pointService->ConfirmMemberPoint([
            'system_code'  => $requestData['system_code'],
            'use_type'     => $requestData['use_type'],
            'use_obj'      => $requestData['use_obj'],
            'company_id'   => $requestData['company_id'],
            'member_id'    => $requestData['member_id'],
            'channel'      => $requestData['channel'],
            'point'        => $requestData['point'],
            'money'        => $requestData['money'],
            'memo'         => $requestData['memo'] ?? '',
            'account_list' => $requestData['account_list'],
            'channel'      => $requestData['channel']
        ]);

        $this->setErrorMsg($confirmDataRes['msg']);

        if (!$confirmDataRes['status']) {
            return $this->outputFormat([], 501);
        }

        PointRecord::UpdatePointRecord(
            [
                'status'      => 3,
                'update_time' => time(),
            ],
            [
                'id'     => $recordInfo->id,
                'status' => 2
            ]
        );

        return $this->outputFormat($confirmDataRes['data'], 0);
    }


    /**
     * 取消用户积分
     * @param Request $request
     * @return array
     */
    public function CancelMemberPoint(Request $request)
    {
        $requestData = $this->getContentArray($request);

        if (
            empty($requestData['member_id']) ||
            empty($requestData['channel']) ||
            empty($requestData['use_obj'])
        ) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }

        $recordInfo = PointRecord::GetPointRecord([
            'use_type' => $requestData['use_type'],
            'use_obj'  => $requestData['use_obj'],
        ]);
        if (empty($recordInfo)) {
            $this->setErrorMsg('积分锁定记录不存在');
            return $this->outputFormat([], 400);
        }
        if ($recordInfo->status != 2) {
            $this->setErrorMsg('积分锁定状态异常');
            return $this->outputFormat([], 400);
        }

        $pointService = new Point();

        $cancelDataRes = $pointService->CancelMemberPoint([
            'system_code' => $requestData['system_code'],
            'company_id'  => $requestData['company_id'],
            'member_id'   => $requestData['member_id'],
            'use_type'    => $requestData['use_type'],
            'use_obj'     => $requestData['use_obj'],
            'memo'        => $requestData['memo'] ?? '',
            'channel'     => $requestData['channel'],
        ]);

        $this->setErrorMsg($cancelDataRes['msg']);

        if (!$cancelDataRes['status']) {
            return $this->outputFormat([], 501);
        }

        PointRecord::UpdatePointRecord(
            [
                'status'      => 4,
                'update_time' => time(),
            ],
            [
                'id'     => $recordInfo->id,
                'status' => 2
            ]
        );

        return $this->outputFormat($cancelDataRes['data'], 0);
    }


    /**
     * 退还用户积分
     * @param Request $request
     * @return array
     */
    public function RefundMemberPoint(Request $request)
    {
        $requestData = $this->getContentArray($request);

        if (
            empty($requestData['member_id']) ||
            empty($requestData['channel']) ||
            empty($requestData['use_obj']) ||
            empty($requestData['refund_id'])
        ) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }

        $recordInfo = PointRecord::GetPointRecord([
            'use_type' => $requestData['use_type'],
            'use_obj'  => $requestData['use_obj'],
        ]);
        if (empty($recordInfo)) {
            $this->setErrorMsg('积分使用记录不存在');
            return $this->outputFormat([], 400);
        }
        if ($recordInfo->status != 3) {
            $this->setErrorMsg('积分使用状态异常');
            return $this->outputFormat([], 400);
        }


        $refundList = PointRefund::GetPointRefundRecord([
            'use_type' => $requestData['use_type'],
            'use_obj'  => $requestData['use_obj'],
        ]);

        $totalPoint = $requestData['total_money'];
        if (!empty($refundList)) {
            foreach ($refundList as $refund) {
                if ($refund->status != 3) {
                    $totalPoint += $refund->money;
                }
            }
        }

        if (bccomp($totalPoint, $recordInfo->money, 3) === 1) {
            $this->setErrorMsg('返还积分超出锁定积分');
            return $this->outputFormat([], 400);
        }

        $refundId = PointRefund::AddPointRefundRecord([
            'channel'     => $requestData['channel'],
            'use_type'    => $requestData['use_type'],
            'use_obj'     => $requestData['use_obj'],
            'money'       => $requestData['money'],
            'point'       => $requestData['point'],
            'company_id'  => $requestData['company_id'],
            'member_id'   => $requestData['member_id'],
            'status'      => 1,
            'refund_id'   => $requestData['refund_id'],
            'create_time' => time(),
            'update_time' => time(),
        ]);

        $pointService = new Point();

        $refundDataRes = $pointService->RefundMemberPoint([
            'system_code'  => $requestData['system_code'],
            'refund_id'    => $requestData['refund_id'],
            'use_type'     => $requestData['use_type'],
            'use_obj'      => $requestData['use_obj'],
            'company_id'   => $requestData['company_id'],
            'member_id'    => $requestData['member_id'],
            'channel'      => $requestData['channel'],
            'point'        => $requestData['point'],
            'money'        => $requestData['money'],
            'account_list' => $requestData['account_list'],
            'memo'         => $requestData['memo'],
        ]);

        $this->setErrorMsg($refundDataRes['msg']);

        if (!$refundDataRes['status']) {
            PointRefund::UpdatePointRefundRecord(
                [
                    'status'      => 3,
                    'update_time' => time(),
                ],
                [
                    'id'     => $refundId,
                    'status' => 1
                ]
            );
            return $this->outputFormat([], 501);
        }

        PointRefund::UpdatePointRefundRecord(
            [
                'status'      => 2,
                'update_time' => time(),
            ],
            [
                'id'     => $refundId,
                'status' => 1
            ]
        );

        return $this->outputFormat($refundDataRes['data'], 0);
    }


    /**
     * 用户积分流水
     * @param Request $request
     * @return array
     */
    public function GetMemberRecord(Request $request)
    {
        $requestData = $this->getContentArray($request);

        if (
            empty($requestData['member_id']) ||
            empty($requestData['channel'])
        ) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }


        $pointService = new Point();

        $pointDataRes = $pointService->GetMemberRecord([
            'member_id'   => $requestData['member_id'],
            'channel'     => $requestData['channel'],
            'system_code' => $requestData['system_code'] ?? '',
            'company_id'  => $requestData['company_id'] ?? '',
            'scene_id'    => $requestData['scene_id'] ?? '',
            'accounts'    => $requestData['accounts'] ?? '',
            'begin_time'  => $requestData['begin_time'] ?? 0,
            'end_time'    => $requestData['end_time'] ?? 0,
            'record_type' => $requestData['record_type'] ? $requestData['record_type'] : 'all',
            'rowNum'      => $requestData['rowNum'] ? $requestData['rowNum'] : 10,
            'page'        => $requestData['page'] ? $requestData['page'] : 1
        ]);

        $this->setErrorMsg($pointDataRes['msg']);

        if (!$pointDataRes['status']) {
            return $this->outputFormat([], 501);
        }

        return $this->outputFormat($pointDataRes['data'], 0);
    }
}
