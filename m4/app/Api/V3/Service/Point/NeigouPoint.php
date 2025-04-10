<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-11-28
 * Time: 14:52
 */

namespace App\Api\V3\Service\Point;


use App\Api\Logic\Service;
use App\Api\V3\Service\ServiceTrait;
use App\Api\Logic\Point\Point as PointSource;
use App\Api\Model\Point\Record as PointRecord;
use App\Api\Model\Point\Refund as PointRefund;
use App\Api\Model\Point\Point as PointModel;

class NeigouPoint
{
    use ServiceTrait;

    const COMMON_SCENE = 1;
    const COMMON_RULE  = 1;

    const POINT_NAME_CHANNEL_NEIGOU = 'NEIGOU';
    const DEFAULT_POINT_NAME        = '积分';

    /**
     * 获取用户积分账户
     * @param $paramData
     * @return array
     */
    public function GetMemberPoint($paramData)
    {
        $pointSourceLogic = new PointSource();

        $pointData = $pointSourceLogic->GetMemberPoint(
            [
                'member_bn'  => $paramData['member_id'],
                'company_bn' => $paramData['company_id'],
            ],
            $paramData['channel']
        );

        if (!$pointData || !$pointData['Result']) {
            return $this->Response(false, '积分获取失败');
        }

        $account     = $paramData['company_id'] . '_' . $paramData['member_id'] . '_' . self::COMMON_SCENE;
        $accountName = $this->GetPointName(array(
            'channel'    => $paramData['channel'],
            'company_id' => $paramData['company_id'],
            'member_id'  => $paramData['member_id']
        ));

        //获取积分比例
        $service = new Service();

        $channelRes = $service->ServiceCall(
            'get_channel_point',
            [
                'channel'    => $paramData['channel'],
                'member_id'  => $paramData['member_id'],
                'company_id' => $paramData['company_id']
            ]
        );

        if ('SUCCESS' != $channelRes['error_code'] || !isset($channelRes['data']['exchange_rate'])) {
            return $this->Response(false, '积分渠道错误');
        }

        return $this->Response(true, '获取成功', [
            $account => [
                "account"       => $account,
                "account_name"  => $accountName,
                "rule_bns"      => [config('neigou.ALL_PRODUCT_RULE')],
                "exchange_rate" => $channelRes['data']['exchange_rate'],
                "money"         => $pointData['Data']['money'],
                "freeze_money"  => $pointData['Data']['freeze_money'],
                "used_money"    => $pointData['Data']['used_money'],
                "overdue_money" => 0,
                "point"         => $pointData['Data']['point'],
                "used_point"    => $pointData['Data']['used_point'],
                "frozen_point"  => $pointData['Data']['freeze_point'],
                "overdue_point" => 0,
            ]
        ]);
    }

    /**
     * 锁定用户积分
     * @param $paramData
     * @return array
     */
    public function LockMemberPoint($paramData)
    {
        $pointSourceLogic = new PointSource();

        $lockRes = $pointSourceLogic->LockMemberPoint(
            [
                'member_bn'       => $paramData['member_id'],
                'company_bn'      => $paramData['company_id'],
                'out_trade_no'    => $paramData['use_obj'],
                'money'           => $paramData['money'],
                'point'           => $paramData['point'],
                'third_point_pwd' => $paramData['third_point_pwd'],
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

        $account = $paramData['company_id'] . '_' . $paramData['member_id'] . '_' . self::COMMON_SCENE;

        $lockData = [
            'trade_no'    => $lockRes['Data']['trade_no'],
            'company_id'  => $paramData['company_id'],
            'member_id'   => $paramData['member_id'],
            'frozen_data' => [
                $account => [
                    'account' => $account,
                    'money'   => $lockRes['Data']['money'],
                    'point'   => $lockRes['Data']['point'],
                ]
            ]
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
        $pointSourceLogic = new PointSource();

        $confirmRes = $pointSourceLogic->ConfirmLockMemberPoint(
            [
                'member_bn'    => $paramData['member_id'],
                'company_id'   => $paramData['company_id'],
                'out_trade_no' => $paramData['use_obj'],
            ],
            $paramData['channel']
        );
        if ($confirmRes['Result'] != 'true') {
            return $this->Response(false, $confirmRes['ErrorMsg'] ?? '确认失败');
        }

        $confirmData = [
            'flow_code'  => $paramData['use_obj'],
            'company_id' => $paramData['company_id'],
            'member_id'  => $paramData['member_id']
        ];

        return $this->Response(true, '锁定成功', $confirmData);
    }

    /**
     * 取消用户积分
     * @param $paramData
     * @return array
     */
    public function CancelMemberPoint($paramData)
    {
        $pointSourceLogic = new PointSource();

        $cancelRes = $pointSourceLogic->CancelLockMemberPoint(
            [
                'member_bn'    => $paramData['member_id'],
                'company_id'   => $paramData['company_id'],
                'out_trade_no' => $paramData['use_obj'],
                'memo'         => $paramData['memo'],
            ],
            $paramData['channel']
        );
        if ($cancelRes['Result'] != 'true') {
            return $this->Response(false, $cancelRes['ErrorMsg'] ?? '取消失败');
        }

        $cancelData = [
            'company_id' => $paramData['company_id'],
            'member_id'  => $paramData['member_id']
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
        $pointSourceLogic = new PointSource();

        $refundRes = $pointSourceLogic->RefundMemberPoint(
            [
                'member_bn'    => $paramData['member_id'],
                'company_bn'   => $paramData['company_id'],
                'out_trade_no' => $paramData['use_obj'],
                'money'        => $paramData['money'],
                'point'        => $paramData['point'],
                'refund_id'    => $paramData['refund_id'],
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
        $pointSourceLogic = new PointSource();

        $recordRes = $pointSourceLogic->GetMemberRecord(
            [
                'member_bn'  => $paramData['member_id'],
                'company_bn' => $paramData['company_id'],
                'page'       => $paramData['page'],
                'rowNum'     => $paramData['rowNum'],
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

    /**
     * 获取积分名称
     * @param $paramData
     * @return string
     */
    public function GetPointName($paramData)
    {
        if (!$this->_CheckChannel($paramData['channel']) || empty($paramData['company_id'])) {
            return PointModel::GetChannelInfo($paramData['channel'])->point_name;
        }

        $channelData = [
            'channel'    => $paramData['channel'],
            'company_bn' => $paramData['company_id'],
        ];

        if (isset($data['member_bn'])) {
            $channelData['member_bn'] = $paramData['member_id'];
        }

        $pointSourceLogic = new PointSource();

        $res = $pointSourceLogic->GetPointName($channelData);

        return $res['Data']['coin_name'] ?? self::DEFAULT_POINT_NAME;
    }

    private function _CheckChannel($channel = '')
    {
        $allowGetPointNameArr = [
            self::POINT_NAME_CHANNEL_NEIGOU,
        ];

        if (in_array($channel, $allowGetPointNameArr)) {
            return true;
        }
        return false;
    }
}
