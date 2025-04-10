<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-11-28
 * Time: 14:52
 */

namespace App\Api\V3\Service\Point;


use App\Api\V3\Service\ServiceTrait;

use App\Api\Logic\Point\ScenePoint as ScenePointSource;   //第三方积分
use App\Api\Logic\Service;
use App\Api\Model\Point\Record as PointRecord;   //积分使用记录
use App\Api\Model\Point\Refund as PointRefund;   //积分返还记录

class NeigouScenePoint
{
    use ServiceTrait;

    /**
     * 获取用户积分账户
     * @param $paramData
     * @return array
     */
    public function GetMemberPoint($paramData)
    {
        $point_source_logic = new ScenePointSource();

        $pointData = $point_source_logic->GetMemberPoint(
            [
                'member_bn'  => $paramData['member_id'],
                'company_bn' => $paramData['company_id'],
                'channel'    => $paramData['channel'],
            ],
            $paramData['channel']
        );

        if (!$pointData || !$pointData['Result']) {
            return $this->Response(false, '积分获取失败');
        }

        return $this->Response(true, '获取成功', $pointData['Data']);
    }

    /**
     * 锁定用户积分
     * @param $paramData
     * @return array
     */
    public function LockMemberPoint($paramData)
    {
        $pointSourceLogic = new ScenePointSource();

        $lockRes = $pointSourceLogic->LockMemberPoint(
            [
                'system_code'     => $paramData['system_code'],
                'company_bn'      => $paramData['company_id'],
                'member_bn'       => $paramData['member_id'],
                'out_trade_no'    => $paramData['use_obj'],
                'money'           => $paramData['money'],
                'point'           => $paramData['point'],
                'third_point_pwd' => $paramData['third_point_pwd'],
                'overdue_time'    => $paramData['overdue_time'],
                'channel'         => $paramData['channel'],
                'memo'            => $paramData['memo'],
                'account_list'    => $paramData['account_list'],
                'extend_data'     => $paramData['extend_data'] ?? []
            ],
            $paramData['channel']
        );
        if ($lockRes['Result'] != 'true') {
            if ($lockRes['ErrorMsg'] == 511) {
                return $this->Response(false, '密码错误');
            } else {
                return $this->Response(false, $lockRes['ErrorMsg'] ?? '锁定失败');
            }
        }

        $lockData = [
            'trade_no'    => $lockRes['Data']['trade_no'],
            'company_id'  => $paramData['company_id'],
            'member_id'   => $paramData['member_id'],
            'frozen_data' => $lockRes['Data']['frozen_data']
        ];

        return $this->Response(true, '锁定成功', $lockData);
    }

    /**
     * 确认用户积分
     * @param $paramData
     * @return array
     */
    public function ConfirmMemberPoint($paramData)
    {
        $pointSourceLogic = new ScenePointSource();

        $confirmRes = $pointSourceLogic->ConfirmLockMemberPoint(
            [
                'system_code'  => $paramData['system_code'],
                'channel'      => $paramData['channel'],
                'out_trade_no' => $paramData['use_obj'],
                'member_bn'    => $paramData['member_id'],
                'company_id'   => $paramData['company_id'],
                "point"        => $paramData['point'],
                "money"        => $paramData['money'],
                "memo"         => $paramData['memo'],
                "account_list" => $paramData['account_list'],
            ],
            $paramData['channel']
        );
        if ($confirmRes['Result'] != 'true') {
            return $this->Response(false, $confirmRes['ErrorMsg'] ?? '确认失败');
        }

        $confirmData = [
            'flow_code'  => $confirmRes['Data']['flow_code'],
            'company_id' => $paramData['company_id'],
            'member_id'  => $paramData['member_id']
        ];

        return $this->Response(true, '确认成功', $confirmData);
    }

    /**
     * 取消用户积分
     * @param $paramData
     * @return array
     */
    public function CancelMemberPoint($paramData)
    {
        $pointSourceLogic = new ScenePointSource();

        $cancelRes = $pointSourceLogic->CancelLockMemberPoint(
            [
                'member_bn'    => $paramData['member_id'],
                'company_id'   => $paramData['company_id'],
                'channel'      => $paramData['channel'],
                'out_trade_no' => $paramData['use_obj'],
                'system_code'  => $paramData['system_code'],
                'memo'         => $paramData['memo'],
            ],
            $paramData['channel']
        );
        if ($cancelRes['Result'] != 'true') {
            return $this->Response(false, $cancelRes['ErrorMsg'] ?? '取消失败');
        }

        $cancelData = [
            'company_id' => $paramData['company_id'],
            'member_id'  => $paramData['member_id'],
        ];

        return $this->Response(true, '取消成功', $cancelData);
    }

    /**
     * 用户积分退还
     * @param $paramData
     * @return array
     */
    public function RefundMemberPoint($paramData)
    {
        $pointSourceLogic = new ScenePointSource();

        $refundRes = $pointSourceLogic->RefundMemberPoint(
            [
                'channel'      => $paramData['channel'],
                'refund_id'    => $paramData['refund_id'],
                'order_id'     => $paramData['use_obj'],
                'system_code'  => $paramData['system_code'],
                'member_id'    => $paramData['member_id'],
                'company_id'   => $paramData['company_id'],
                'point'        => $paramData['point'],
                'money'        => $paramData['money'],
                'account_list' => $paramData['account_list'],
                'memo'         => $paramData['memo'],
            ],
            $paramData['channel']
        );

        if ($refundRes['Result'] != 'true') {
            return $this->Response(false, $refundRes['ErrorMsg'] ?? '退还失败');
        }

        $refundData = [
            'trade_no'   => $refundRes['Data']['trade_no'],
            'company_id' => $paramData['company_id'],
            'member_id'  => $paramData['member_id']
        ];

        return $this->Response(true, '退还成功', $refundData);
    }

    /**
     * 用户积分流水
     * @param $paramData
     * @return array
     */
    public function GetMemberRecord($paramData)
    {
        $pointSourceLogic = new ScenePointSource();

        $recordRes = $pointSourceLogic->GetMemberRecord(
            [
                'system_code' => $paramData['system_code'],
                'channel'     => $paramData['channel'],
                'member_bn'   => $paramData['member_id'],
                'company_bn'  => $paramData['company_id'],
                'scene_ids'   => $paramData['scene_id'],
                'accounts'    => $paramData['accounts'],
                'begin_time'  => $paramData['begin_time'],
                'end_time'    => $paramData['end_time'],
                'record_type' => $paramData['record_type'],
                'page'        => $paramData['page'],
                'page_size'   => $paramData['rowNum'],
            ],
            $paramData['channel']
        );

        if ($recordRes['Result'] != 'true') {
            return $this->Response(false, $recordRes['ErrorMsg'] ?? '流水查询失败');
        }

        $recordData = [
            'company_id' => $paramData['company_id'],
            'member_id'  => $paramData['member_id'],
            'base'       => $recordRes['Data']['base'] ?? [],
            'data'       => $recordRes['Data']['data'] ?? [],
        ];

        return $this->Response(true, '流水查询成功', $recordData);
    }
}
