<?php

namespace App\Api\V2\Service\Point;

use App\Api\Logic\Point\Point as PointSource;   //第三方积分
use App\Api\Model\Point\Record as PointRecord;   //积分使用记录
use App\Api\Model\Point\Refund as PointRefund;   //积分返还记录
use App\Api\Model\Point\Point as PointModel;

class Point
{
    //允许获取的积分名称的渠道
    const POINT_NAME_CHANNEL_NEIGOU = 'NEIGOU';
    const DEFAULT_POINT_NAME = '积分';

    const COMMON_SCENE = 1;
    const COMMON_RULE = 1;

    private $__error_msg = '';

    /*
     * 获取用户积分
     */
    public function GetMemberPoint($data)
    {
        if (empty($data['member_id']) || empty($data['channel'])) {
            $this->__error_msg = '参数错误';
            return false;
        }
        $point_source_logic = new PointSource();
        //获取用户积分
        $get_data = [
            'member_bn' => $data['member_id'],
            'company_bn' => $data['company_id'],
        ];
        $point_data = $point_source_logic->GetMemberPoint($get_data, $data['channel']);
        if ($point_data['Result']) {
            $newData = array(
                self::COMMON_SCENE => array(
                    "account" => self::COMMON_SCENE,
                    'account_name' => $this->GetPointName(array(
                        'channel' => $data['channel'],
                        'company_id' => $data['company_id'],
                        'member_id' => $data['member_id']
                    )),
                    "money" => $point_data['Data']['money'],
                    "freeze_money" => $point_data['Data']['freeze_money'],
                    "used_money" => $point_data['Data']['used_money'],
                    "overdue_money" => 0,
                    "point" => $point_data['Data']['point'],
                    "used_point" => $point_data['Data']['used_point'],
                    "frozen_point" => $point_data['Data']['freeze_point'],
                    "overdue_point" => 0,
                )
            );
            $point_data['Data'] = $newData;
        }

        return $point_data;
    }

    public function GetMemberPointByOverdueTime()
    {
        $this->__error_msg = '暂无';
        return false;
    }

    /*
     * 用户积分锁定
     */
    public function LockMemberPoint($data)
    {
        if (empty($data['member_id']) || empty($data['channel'])) {
            $this->__error_msg = '参数错误';
            return false;
        }
        //查询是否被使用
        $point_where = [
            'use_type' => $data['use_type'],
            'use_obj' => $data['use_obj'],
        ];
        $record_info = PointRecord::GetPointRecord($point_where);
        if (empty($record_info)) {    //无使用记录
            //保存记录
            $save_point_record = [
                'channel' => $data['channel'],
                'use_type' => $data['use_type'],
                'use_obj' => $data['use_obj'],
                'money' => $data['money'],
                'point' => $data['point'],
                'company_id' => $data['company_id'],
                'member_id' => $data['member_id'],
                'status' => 1,
                'create_time' => time(),
                'update_time' => time(),
            ];
            $save_res = PointRecord::AddPointRecord($save_point_record);
            if (!$save_res) {
                $this->__error_msg = '记录保存失败';
                return false;
            }
        } else {
            if ($record_info->status == 5) {  // 记录失败
                //重新修改锁定记录
                $save_point_record = [
                    'channel' => $data['channel'],
                    'use_type' => $data['use_type'],
                    'use_obj' => $data['use_obj'],
                    'money' => $data['money'],
                    'point' => $data['point'],
                    'status' => 1,
                    'update_time' => time(),
                ];
                $save_res = PointRecord::AddPointRecord($save_point_record, ['id' => $record_info->id, 'status' => 5]);
                if (!$save_res) {
                    $this->__error_msg = '记录更新失败';
                    return false;
                }
            } else {
                if ($record_info->status != 1) {

                }
            }
        }

        $items = array();
        foreach ($data['frozen_info'] as $frozenInfo) {
            foreach ($frozenInfo['product'] as $item) {
                $items[] = $item;
            }
        }

        //请求第三方进行积分锁定
        $point_source_logic = new PointSource();
        $lock_data = [
            'member_bn' => $data['member_id'],
            'company_bn' => $data['company_id'],
            'out_trade_no' => $data['use_obj'],
            'money' => $data['money'],
            'point' => $data['point'],
            'items' => $items,
            'third_point_pwd' => $data['third_point_pwd'],
        ];
        $lock_res = $point_source_logic->LockMemberPoint($lock_data, $data['channel']);
        if ($lock_res['Result'] != 'true') {
            $update_data = [
                'status' => 5,
                'update_time' => time(),
            ];
            PointRecord::UpdatePointRecord($update_data,
                ['use_type' => $data['use_type'], 'use_obj' => $data['use_obj'], 'status' => 1]);
            return $lock_res;
        } else {
            $update_data = [
                'status' => 2,
                'update_time' => time(),
                'trade_no' => $lock_res["Data"]['trade_no']
            ];
            PointRecord::UpdatePointRecord($update_data,
                ['use_type' => $data['use_type'], 'use_obj' => $data['use_obj'], 'status' => 1]);
            return $lock_res;
        }
    }


    /**
     * 积分锁定取消
     */
    public function CancelLockMemberPoint($data)
    {
        if (empty($data['member_id']) || empty($data['channel']) || empty($data['use_obj'])) {
            $this->__error_msg = '参数错误';
            return false;
        }
        //查询是否被使用
        $point_where = [
            'use_type' => $data['use_type'],
            'use_obj' => $data['use_obj'],
        ];
        $record_info = PointRecord::GetPointRecord($point_where);
        if (empty($record_info)) {
            $this->__error_msg = '积分锁定记录不存在';
            return false;
        }
        if ($record_info->status != 2) {
            $this->__error_msg = '积分锁定不可取消';
            return false;
        }
        //请求第三方进行积分锁定
        $point_source_logic = new PointSource();
        $cancel_data = [
            'member_bn' => $data['member_id'],
            'company_id' => $data['company_id'],
            'out_trade_no' => $data['use_obj'],
            'memo' => empty($data['memo']) ? '' : $data['memo'],
        ];
        $cancel_res = $point_source_logic->CancelLockMemberPoint($cancel_data, $data['channel']);
        if ($cancel_res['Result'] != 'true') {
            return $cancel_res;
        } else {
            $update_data = [
                'status' => 4,
                'update_time' => time(),
            ];
            $save_res = PointRecord::UpdatePointRecord($update_data, ['id' => $record_info->id, 'status' => 2]);
            if (!$save_res) {
                $this->__error_msg = '积分取消状态保存失败';
                return false;
            }
            return $cancel_res;
        }
    }

    /**
     * 积分锁定确认
     */
    public function ConfirmLockMemberPoint($data)
    {
        if (empty($data['member_id']) || empty($data['channel']) || empty($data['use_obj'])) {
            $this->__error_msg = '参数错误';
            return false;
        }
        //查询是否被使用
        $point_where = [
            'use_type' => $data['use_type'],
            'use_obj' => $data['use_obj'],
        ];
        $record_info = PointRecord::GetPointRecord($point_where);
        if (empty($record_info)) {
            $this->__error_msg = '积分锁定记录不存在';
            return false;
        }
        if ($record_info->status != 2) {
            $this->__error_msg = '积分锁定不可确认';
            return false;
        }
        //请求第三方进行积分锁定
        $point_source_logic = new PointSource();
        $confirm_data = [
            'member_bn' => $data['member_id'],
            'company_id' => $data['company_id'],
            'out_trade_no' => $data['use_obj'],
        ];
        $confirm_res = $point_source_logic->ConfirmLockMemberPoint($confirm_data, $data['channel']);
        if ($confirm_res['Result'] != 'true') {
            return $confirm_res;
        } else {
            $update_data = [
                'status' => 3,
                'update_time' => time(),
            ];
            $save_res = PointRecord::UpdatePointRecord($update_data, ['id' => $record_info->id, 'status' => 2]);
            if (!$save_res) {
                $this->__error_msg = '积分确认状态保存失败';
                return false;
            }
            return $confirm_res;
        }
    }

    /*
     * 用户积分退还
     */
    public function RefundMemberPoint($data)
    {
        if (empty($data['member_id']) || empty($data['channel'])) {
            $this->__error_msg = '参数错误';
            return false;
        }
        //查询是否被使用
        $point_where = [
            'use_type' => $data['use_type'],
            'use_obj' => $data['use_obj'],
        ];
        $record_info = PointRecord::GetPointRecord($point_where);
        if (empty($record_info)) {
            $this->__error_msg = '积分使用记录不存在';
            return false;
        }
        if ($record_info->status != 3) {
            $this->__error_msg = '积分未使用成功不能进行返还';
            return false;
        }
        //检查退还积分是否超出锁定积分

        $total_point = $data['total_money'];
        $refund_list = PointRefund::GetPointRefundRecord($point_where);
        if (!empty($refund_list)) {
            foreach ($refund_list as $refund) {
                if ($refund->status != 3) {
                    $total_point += $refund->money;
                }
            }
        }
        if (bccomp($total_point, $record_info->money, 3) === 1) {
            $this->__error_msg = '返还积分超出锁定积分';
            return false;
        }
        //保存返还记录
        $save_refund_data = [
            'channel' => $data['channel'],
            'use_type' => $data['use_type'],
            'use_obj' => $data['use_obj'],
            'money' => $data['money'],
            'point' => $data['point'],
            'company_id' => $data['company_id'],
            'member_id' => $data['member_id'],
            'status' => 1,
            'refund_id' => $data['refund_id'], //增加售后流水号信息, 这个流水号是来自mis_order_refund_bill表
            'create_time' => time(),
            'update_time' => time(),
        ];
        $refund_id = PointRefund::AddPointRefundRecord($save_refund_data);
        if (!$refund_id) {
            $this->__error_msg = '返还积分记录保存失败';
            return false;
        }
        //请求第三方进行积分锁定
        $point_source_logic = new PointSource();
        $refund_data = [
            'member_bn' => $data['member_id'],
            'company_bn' => $data['company_id'],
            'out_trade_no' => $data['use_obj'],
            'money' => $data['money'],
            'point' => $data['point'],
            'refund_id' => $data['refund_id'], //添加售后流水号 这个流水号是来自mis_order_refund_bill表
        ];
        $refund_res = $point_source_logic->RefundMemberPoint($refund_data, $data['channel']);
        if ($refund_res['Result'] == 'true') {
            $update_data = [
                'status' => 2,
                'update_time' => time(),
                'trade_no' => $refund_res["Data"]['trade_no']
            ];
            PointRefund::UpdatePointRefundRecord($update_data, ['id' => $refund_id, 'status' => 1]);
            return $refund_res;
        } else {
            $update_data = [
                'status' => 3,
                'update_time' => time(),
            ];
            PointRefund::UpdatePointRefundRecord($update_data, ['id' => $refund_id, 'status' => 1]);
            return $refund_res;
        }
    }

    //获取积分锁定记录
    public function GetLockRecord($data)
    {
        if (empty($data['use_obj']) || empty($data['use_type'])) {
            $this->__error_msg = '参数错误';
            return false;
        }
        //查询是否被使用
        $point_where = [
            'use_type' => $data['use_type'],
            'use_obj' => $data['use_obj'],
        ];
        $record_info = PointRecord::GetPointRecord($point_where);
        if (empty($record_info)) {
            $this->__error_msg = '积分锁定记录不存在';
            return false;
        }
        return $record_info;
    }

    public function GetPointRate($data)
    {
        if (empty($data['channel'])) {
            $this->__error_msg = '参数错误';
            return false;
        }
        //查询积分兑换比例
        $channel_data = [
            'member_bn' => $data['member_id'],
        ];
        //请求第三方进行积分锁定
        $point_source_logic = new PointSource();
        $point_data = $point_source_logic->GetPointRate($channel_data, $data['channel']);
        if (empty($point_data)) {
            $this->__error_msg = '积分锁定记录不存在';
            return false;
        }
        return $point_data;
    }

    /** 获取积分名称
     *
     * @param $data ['channnel' => '渠道名称','member_id' => '','company_id' => '']
     * @return bool | string 返回false或者积分名称
     * @author liuming
     */
    public function GetPointName($data)
    {
        if (empty($data['channel'])) {
            $this->__error_msg = '请求参数不完整';
            return false;
        }
        $data['member_id'] = intval($data['member_id']);
        $data['company_id'] = intval($data['company_id']);

        //todo 检查渠道是否符合条件, 如果不符合直接返回数据库结果, 否则返回store积分名称
        if (!$this->_CheckChannel($data['channel']) || empty($data['company_id'])) {
            return PointModel::GetChannelInfo($data['channel'])->point_name;
        }

        //todo 请求获取积分名称
        $channel_data = [
            'channel' => $data['channel'],
            'company_bn' => $data['company_id'],
        ];

        //member_bn为非必填, 如果有member_bn则传入member_bn
        if (isset($data['member_bn'])) {
            $channel_data['member_bn'] = $data['member_id'];
        }

        $point_source_logic = new PointSource();
        $res = $point_source_logic->GetPointName($channel_data);

        //todo 判断是否有返回结果, 有返回结果返回积分名称. 若获取积分名称失败: 1,有失败信息返回失败信息,2,没有失败信息返回默认错误
        if ($res['Result'] != "true") {
            if (isset($res['ErrorMsg'])) {
                $this->__error_msg = $res['ErrorMsg'];
                return false;
            }
        } elseif (empty($res)) {
            $this->__error_msg = '积分名称获取失败';
            return false;
        }
        return $res['Data']['coin_name'];
    }

    /*
     * 获取用户积分记录
     */
    public function GetMemberRecord($data)
    {
        if (empty($data['member_id']) || empty($data['channel'])) {
            $this->__error_msg = '参数错误';
            return false;
        }
        $point_source_logic = new PointSource();
        //获取用户积分
        $get_data = [
            'member_bn' => $data['member_id'],
            'company_bn' => $data['company_id'],
            'page' => $data['page'],
            'rowNum' => $data['rowNum'],
        ];
        $point_data = $point_source_logic->GetMemberRecord($get_data, $data['channel']);
        return $point_data;
    }

    /** 判断渠道是否允许的
     *
     * @param string $channel
     * @return bool
     * @author liuming
     */
    private function _CheckChannel($channel = '')
    {
        //此处写数组主要是为了将来扩展方便
        $allowGetPointNameArr = [
            self::POINT_NAME_CHANNEL_NEIGOU,
        ];

        if (in_array($channel, $allowGetPointNameArr)) {//允许通过service获取积分名称
            return true;
        }
        return false;
    }

    public function WithRule($data)
    {
        if (!$data['filter_data']['product']) {
            $this->__error_msg = '参数错误';
            return false;
        }
        $goodsList = array();
        foreach ($data['filter_data']['product'] as $val) {
            if (!isset($val['goods_bn'])) {
                $this->__error_msg = '参数错误';
                return false;
            }
            $goodsList[$val['goods_bn']] = $val;
        }
        return array(
            'Result' => true,
            'ErrorMsg' => '',
            'Data' => array(
                'product' => array(
                    self::COMMON_SCENE => array(
                        'rule_id' => self::COMMON_RULE,
                        'scene_id' => self::COMMON_SCENE,
                        'scene_name' => '通用积分',
                        'goods_list' => $goodsList
                    )
                )
            )
        );
    }


    public function GetErrorMsg()
    {
        return $this->__error_msg;
    }
}
