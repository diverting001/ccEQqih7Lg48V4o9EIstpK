<?php
/**
 * Created by PhpStorm.
 * User: chuanbin
 * Date: 2017/10/18
 * Time: 19:53
 */

namespace App\Api\V2\Controllers;

use App\Api\Common\Controllers\BaseController;
use App\Api\Model\Point\Point as PointModel;
use App\Api\V2\Service\Point\Point as PointService;
use App\Api\V2\Service\ScenePoint\ScenePoint as ScenePointService;
use Illuminate\Http\Request;


class PointController extends BaseController
{

    const PASSWORD_ERROR = 511;

    private function GetPointService($channel)
    {
        $pointChannelInfo = PointModel::GetChannelInfo($channel);
        if (!$pointChannelInfo) {
            return false;
        }
        switch ($pointChannelInfo->point_version) {
            case 1:
                return new PointService();
            case 2:
                return new ScenePointService();
            default:
                return false;
        }
    }

    /*
     * 个人积分查询接口
     */
    public function GetMemberPoint(Request $request)
    {
        $member_data = $this->getContentArray($request);
        if (empty($member_data['company_id']) || empty($member_data['member_id']) || empty($member_data['channel'])) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }
        $point_service = $this->GetPointService($member_data['channel']);
        if (!$point_service) {
            $this->setErrorMsg('渠道错误');
            return $this->outputFormat([], 400);
        }
        $data       = [
            'company_id' => $member_data['company_id'],
            'member_id'  => $member_data['member_id'],
            'channel'    => $member_data['channel']
        ];
        $point_data = $point_service->GetMemberPoint($data);
        if (!$point_data) {
            $this->setErrorMsg($point_service->GetErrorMsg());
            return $this->outputFormat([], 500);
        } else {
            if ($point_data['Result'] != 'true') {
                $this->setErrorMsg($point_data['ErrorMsg']);
                return $this->outputFormat([], 501);
            } else {
                $this->setErrorMsg('请求成功');
                return $this->outputFormat($point_data['Data'], 0);
            }
        }
    }

    /**
     * 获取从当前时间到指定时间内过期的积分
     */
    public function GetMemberPointByOverdueTime(Request $request)
    {
        $member_data = $this->getContentArray($request);
        if (
            empty($member_data['company_id']) ||
            empty($member_data['member_id']) ||
            empty($member_data['channel']) ||
            empty($member_data['end_time'])
        ) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }
        $point_service = $this->GetPointService($member_data['channel']);
        if (!$point_service) {
            $this->setErrorMsg('渠道错误');
            return $this->outputFormat([], 400);
        }
        $data       = [
            'company_id' => $member_data['company_id'],
            'member_id'  => $member_data['member_id'],
            'channel'    => $member_data['channel'],
            'start_time' => $member_data['start_time'],
            'end_time'   => $member_data['end_time'],
        ];
        $point_data = $point_service->GetMemberPointByOverdueTime($data);
        if (!$point_data) {
            $this->setErrorMsg($point_service->GetErrorMsg());
            return $this->outputFormat([], 500);
        } else {
            if ($point_data['Result'] != 'true') {
                $this->setErrorMsg($point_data['ErrorMsg']);
                return $this->outputFormat([], 501);
            } else {
                $this->setErrorMsg('请求成功');
                return $this->outputFormat($point_data['Data'], 0);
            }
        }
    }

    /*
     * 积分锁定
     */
    public function LockMemberPoint(Request $request)
    {
        $lock_data = $this->getContentArray($request);
        if (
            empty($lock_data['company_id']) ||
            empty($lock_data['member_id']) ||
            empty($lock_data['channel']) ||
            empty($lock_data['system_code']) ||
            empty($lock_data['account_list'])
        ) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }
        $point_service = $this->GetPointService($lock_data['channel']);

        $data     = [
            'system_code'     => $lock_data['system_code'],
            'company_id'      => $lock_data['company_id'],
            'member_id'       => $lock_data['member_id'],
            'use_type'        => $lock_data['use_type'],
            'use_obj'         => $lock_data['use_obj'],
            'channel'         => $lock_data['channel'],
            'money'           => $lock_data['money'],
            'point'           => $lock_data['point'],
            "overdue_time"    => $lock_data['overdue_time'],
            'third_point_pwd' => $lock_data['third_point_pwd'],
            'account_list'    => $lock_data['account_list'],
            'memo'            => isset($lock_data['memo']) && $lock_data['memo'] ? $lock_data['memo'] : '',
        ];
        $lock_res = $point_service->LockMemberPoint($data);
        if (!$lock_res) {
            $errorMsg = $point_service->GetErrorMsg();
            $this->setErrorMsg($errorMsg ? $errorMsg : "请求超时");
            return $this->outputFormat([], 500);
        } else {
            if ($lock_res['Result'] != 'true') {
                if ($lock_res['ErrorMsg'] == self::PASSWORD_ERROR) {
                    $this->setErrorMsg('密码错误');
                    return $this->outputFormat([], self::PASSWORD_ERROR);
                } else {
                    $this->setErrorMsg($lock_res['ErrorMsg'] ? $lock_res['ErrorMsg'] : "请求超时");
                    return $this->outputFormat([], 501);
                }
            } else {
                $this->setErrorMsg('请求成功');
                return $this->outputFormat($lock_res['Data'], 0);
            }
        }
    }

    /*
     * @todo 取消积分锁定
     */
    public function CancelLockMemberPoint(Request $request)
    {
        $lock_data = $this->getContentArray($request);
        if (empty($lock_data['member_id']) || empty($lock_data['channel']) || empty($lock_data['use_obj'])) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }

        $point_service = $this->GetPointService($lock_data['channel']);

        $data = [
            'system_code' => $lock_data['system_code'],
            'company_id'  => $lock_data['company_id'],
            'member_id'   => $lock_data['member_id'],
            'use_type'    => $lock_data['use_type'],
            'use_obj'     => $lock_data['use_obj'],
            'memo'        => empty($lock_data['memo']) ? '' : $lock_data['memo'],
            'channel'     => $lock_data['channel'],
        ];

        $lock_res = $point_service->CancelLockMemberPoint($data);
        if (!$lock_res) {
            $errorMsg = $point_service->GetErrorMsg();
            $this->setErrorMsg($errorMsg ? $errorMsg : '');
            return $this->outputFormat([], 500);
        } else {
            if ($lock_res['Result'] != 'true') {
                $this->setErrorMsg($lock_res['ErrorMsg'] ? $lock_res['ErrorMsg'] : '');
                return $this->outputFormat([], 501);
            } else {
                $this->setErrorMsg('请求成功');
                return $this->outputFormat($lock_res['Data'], 0);
            }
        }
    }

    /*
     * @todo 确认积分锁定正式使用
     */
    public function ConfirmLockMemberPoint(Request $request)
    {
        $lock_data = $this->getContentArray($request);
        if (empty($lock_data['member_id']) || empty($lock_data['channel']) || empty($lock_data['use_obj'])) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }

        $point_service = $this->GetPointService($lock_data['channel']);

        $data = [
            'system_code'  => $lock_data['system_code'],
            'use_type'     => $lock_data['use_type'],
            'use_obj'      => $lock_data['use_obj'],
            'company_id'   => $lock_data['company_id'],
            'member_id'    => $lock_data['member_id'],
            'channel'      => $lock_data['channel'],
            'point'        => $lock_data['point'],
            'money'        => $lock_data['money'],
            'memo'         => $lock_data['memo'],
            'account_list' => $lock_data['account_list'],
            'channel'      => $lock_data['channel']
        ];

        $lock_res = $point_service->ConfirmLockMemberPoint($data);
        if (!$lock_res) {
            $errorMsg = $point_service->GetErrorMsg();
            $this->setErrorMsg($errorMsg ? $errorMsg : "确认失败");
            return $this->outputFormat([], 500);
        } else {
            if ($lock_res['Result'] != 'true') {
                $this->setErrorMsg($lock_res['ErrorMsg'] ? $lock_res['ErrorMsg'] : '确认失败');
                return $this->outputFormat([], 501);
            } else {
                $this->setErrorMsg('请求成功');
                return $this->outputFormat($lock_res['Data'], 0);
            }
        }
    }

    /**
     * 退还积分
     */
    public function RefundPoint(Request $request)
    {
        $refund_point_data = $this->getContentArray($request);
        if (empty($refund_point_data['member_id']) || empty($refund_point_data['channel']) || empty($refund_point_data['use_obj'])) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }

        $point_service = $this->GetPointService($refund_point_data['channel']);

        $data = [
            'system_code'  => $refund_point_data['system_code'],
            'refund_id'    => $refund_point_data['refund_id'],
            'use_type'     => $refund_point_data['use_type'],
            'use_obj'      => $refund_point_data['use_obj'],
            'company_id'   => $refund_point_data['company_id'],
            'member_id'    => $refund_point_data['member_id'],
            'channel'      => $refund_point_data['channel'],
            'point'        => $refund_point_data['point'],
            'money'        => $refund_point_data['money'],
            'account_list' => $refund_point_data['account_list'],
            'memo'         => $refund_point_data['memo'],
        ];

        $lock_res = $point_service->RefundMemberPoint($data);
        if (!$lock_res) {
            $errorMsg = $point_service->GetErrorMsg();
            $this->setErrorMsg($errorMsg ? $errorMsg : "退款失败");
            return $this->outputFormat([], 500);
        } else {
            if ($lock_res['Result'] != 'true') {
                $this->setErrorMsg($lock_res['ErrorMsg'] ? $lock_res['ErrorMsg'] : '退款失败');
                return $this->outputFormat([], 501);
            } else {
                $this->setErrorMsg('请求成功');
                return $this->outputFormat($lock_res['Data'], 0);
            }
        }
    }

    /**
     * 获取积分锁定记录
     */
    public function GetLockRecord(Request $request)
    {
        $lock_data = $this->getContentArray($request);
        if (empty($lock_data['use_obj']) || empty($lock_data['use_type'])) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }
        $point_service = $this->GetPointService($lock_data['channel']);
        $data          = [
            'use_obj'  => $lock_data['use_obj'],
            'use_type' => $lock_data['use_type'],
        ];
        $record_data   = $point_service->GetLockRecord($data);
        if (!$record_data) {
            $this->setErrorMsg($point_service->GetErrorMsg());
            return $this->outputFormat([], 500);
        } else {
            $this->setErrorMsg('请求成功');
            return $this->outputFormat($record_data, 0);
        }
    }

    /*
     * @todo 获取员工积分记录列表
     * @author 刘明
     */
    public function GetMemberRecord(Request $request)
    {
        $member_data = $this->getContentArray($request);
        if (empty($member_data['member_id']) || empty($member_data['channel'])) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }

        $point_service = $this->GetPointService($member_data['channel']);
        $data          = [
            'system_code' => $member_data['system_code'],
            'company_id'  => $member_data['company_id'],
            'member_id'   => $member_data['member_id'],
            'channel'     => $member_data['channel'],
            'scene_id'    => $member_data['scene_id'],
            'begin_time'  => $member_data['begin_time'],
            'end_time'    => $member_data['end_time'],
            'record_type' => $member_data['record_type'] ? $member_data['record_type'] : 'all',
            'rowNum'      => $member_data['rowNum'] ? $member_data['rowNum'] : 10,
            'page'        => $member_data['page'] ? $member_data['page'] : 1
        ];
        $point_data    = $point_service->GetMemberRecord($data);
        if (!$point_data) {
            $this->setErrorMsg($point_service->GetErrorMsg());
            return $this->outputFormat([], 500);
        } else {
            if ($point_data['Result'] != 'true') {
                $this->setErrorMsg($point_data['ErrorMsg']);
                return $this->outputFormat([], 501);
            } else {
                $this->setErrorMsg('请求成功');
                return $this->outputFormat($point_data['Data'], 0);
            }
        }
    }

    /**
     * 这部分代码应该属于场景积分  不属于积分服务
     * @param Request $request
     * @return array
     */
    public function WithRule(Request $request)
    {
        $member_data = $this->getContentArray($request);
        if (empty($member_data['member_id']) || empty($member_data['channel']) || empty($member_data['filter_data'])) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }
        $point_service = $this->GetPointService($member_data['channel']);

        $data = [
            'system_code' => $member_data['system_code'] ? $member_data['system_code'] : 'NEIGOU',
            'company_id'  => $member_data['company_id'],
            'member_id'   => $member_data['member_id'],
            'channel'     => $member_data['channel'],
            'filter_data' => $member_data['filter_data'],
        ];

        $point_data = $point_service->WithRule($data);
        if (!$point_data) {
            $this->setErrorMsg($point_service->GetErrorMsg());
            return $this->outputFormat([], 500);
        } else {
            if ($point_data['Result'] != 'true') {
                $this->setErrorMsg($point_data['ErrorMsg']);
                return $this->outputFormat([], 501);
            } else {
                $this->setErrorMsg('请求成功');
                return $this->outputFormat($point_data['Data'], 0);
            }
        }
    }

}
