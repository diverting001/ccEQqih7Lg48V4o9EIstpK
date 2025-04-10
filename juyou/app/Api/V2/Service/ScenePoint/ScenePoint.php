<?php

namespace App\Api\V2\Service\ScenePoint;

use App\Api\Logic\Point\ScenePoint as ScenePointSource;   //第三方积分
use App\Api\Logic\Service;
use App\Api\Model\Point\Record as PointRecord;   //积分使用记录
use App\Api\Model\Point\Refund as PointRefund;   //积分返还记录

class ScenePoint
{
    //允许获取的积分名称的渠道
    const POINT_NAME_CHANNEL_NEIGOU = 'NEIGOU';
    const DEFAULT_POINT_NAME        = '积分';

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
        $point_source_logic = new ScenePointSource();
        //获取用户积分
        $get_data   = [
            'member_bn'  => $data['member_id'],
            'company_bn' => $data['company_id'],
        ];
        $point_data = $point_source_logic->GetMemberPoint($get_data, $data['channel']);
        return $point_data;
    }

    public function GetMemberPointByOverdueTime($data)
    {
        if (empty($data['member_id']) || empty($data['channel']) || empty($data['end_time'])) {
            $this->__error_msg = '参数错误';
            return false;
        }
        $point_source_logic = new ScenePointSource();
        //获取用户积分
        $get_data   = [
            'member_bn'  => $data['member_id'],
            'company_bn' => $data['company_id'],
            'start_time' => $data['start_time'],
            'end_time'   => $data['end_time'],
        ];
        $point_data = $point_source_logic->GetMemberPointByOverdueTime($get_data, $data['channel']);
        return $point_data;
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
            'use_obj'  => $data['use_obj'],
        ];

        $record_info = PointRecord::GetPointRecord($point_where);
        if (empty($record_info)) {    //无使用记录
            //保存记录
            $save_point_record = [
                'channel'     => $data['channel'],
                'use_type'    => $data['use_type'],
                'use_obj'     => $data['use_obj'],
                'money'       => $data['money'],
                'point'       => $data['point'],
                'company_id'  => $data['company_id'],
                'member_id'   => $data['member_id'],
                'status'      => 1,
                'create_time' => time(),
                'update_time' => time(),
            ];
            $save_res          = PointRecord::AddPointRecord($save_point_record);
            if (!$save_res) {
                $this->__error_msg = '记录保存失败';
                return false;
            }
        } else {
            if ($record_info->status == 5) {  // 记录失败
                //重新修改锁定记录
                $save_point_record = [
                    'channel'     => $data['channel'],
                    'use_type'    => $data['use_type'],
                    'use_obj'     => $data['use_obj'],
                    'money'       => $data['money'],
                    'point'       => $data['point'],
                    'status'      => 1,
                    'update_time' => time(),
                ];
                $save_res          = PointRecord::UpdatePointRecord(
                    $save_point_record,
                    [
                        'id'     => $record_info->id,
                        'status' => 5
                    ]
                );
                if (!$save_res) {
                    $this->__error_msg = '记录更新失败';
                    return false;
                }
            } else {
                if ($record_info->status != 1) {

                }
            }
        }

        //请求第三方进行积分锁定
        $point_source_logic = new ScenePointSource();
        $lock_data          = [
            'system_code'     => $data['system_code'],
            'company_bn'      => $data['company_id'],
            'member_bn'       => $data['member_id'],
            'out_trade_no'    => $data['use_obj'],
            'money'           => $data['money'],
            'point'           => $data['point'],
            'third_point_pwd' => $data['third_point_pwd'],
            'overdue_time'    => $data['overdue_time'],
            'channel'         => $data['channel'],
            'memo'            => $data['memo'],
            'account_list'    => $data['account_list']
        ];

        $lock_res = $point_source_logic->LockMemberPoint($lock_data, $data['channel']);
        if ($lock_res['Result'] != 'true') {
            $update_data = [
                'status'      => 5,
                'update_time' => time(),
            ];
            PointRecord::UpdatePointRecord(
                $update_data,
                [
                    'use_type' => $data['use_type'],
                    'use_obj'  => $data['use_obj'],
                    'status'   => 1
                ]
            );
            return $lock_res;
        } else {
            $update_data = [
                'status'      => 2,
                'update_time' => time(),
                'trade_no'    => $lock_res["Data"]['trade_no']
            ];
            PointRecord::UpdatePointRecord(
                $update_data,
                [
                    'use_type' => $data['use_type'],
                    'use_obj'  => $data['use_obj'],
                    'status'   => 1
                ]
            );
            return $lock_res;
        }
    }


    /**
     * 积分锁定取消
     */
    public function CancelLockMemberPoint($data)
    {
        if (empty($data['member_id']) || empty($data['channel']) || empty($data['use_obj']) || empty($data['system_code'])) {
            $this->__error_msg = '参数错误';
            return false;
        }
        //查询是否被使用
        $point_where = [
            'use_type' => $data['use_type'],
            'use_obj'  => $data['use_obj'],
        ];
        $record_info = PointRecord::GetPointRecord($point_where);
        if (empty($record_info)) {
            $this->__error_msg = '积分锁定记录不存在';
            return false;
        }

        if ($record_info->status == 4) {
            return [
                'Result' => 'true',
                'Data'   => [

                ]
            ];
        }

        if ($record_info->status != 2) {
            $this->__error_msg = '积分锁定不可取消';
            return false;
        }

        //请求第三方进行积分锁定
        $point_source_logic = new ScenePointSource();

        $cancel_data = [
            'member_bn'    => $data['member_id'],
            'company_id'   => $data['company_id'],
            'out_trade_no' => $data['use_obj'],
            'system_code'  => $data['system_code'],
            'memo'         => empty($data['memo']) ? '' : $data['memo'],
        ];

        $cancel_res = $point_source_logic->CancelLockMemberPoint($cancel_data, $data['channel']);
        if ($cancel_res['Result'] != 'true') {
            return $cancel_res;
        } else {
            $update_data = [
                'status'      => 4,
                'update_time' => time(),
            ];
            $save_res    = PointRecord::UpdatePointRecord($update_data, ['id' => $record_info->id, 'status' => 2]);
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
            'use_obj'  => $data['use_obj'],
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
        $point_source_logic = new ScenePointSource();

        $confirm_data = [
            'system_code'  => $data['system_code'],
            'out_trade_no' => $data['use_obj'],
            'member_bn'    => $data['member_id'],
            'company_id'   => $data['company_id'],
            "point"        => $data['point'],
            "money"        => $data['money'],
            "memo"         => $data['memo'],
            "account_list" => $data['account_list'],
        ];

        $confirm_res = $point_source_logic->ConfirmLockMemberPoint($confirm_data, $data['channel']);
        if ($confirm_res['Result'] != 'true') {
            return $confirm_res;
        } else {
            $update_data = [
                'status'      => 3,
                'update_time' => time(),
            ];
            $save_res    = PointRecord::UpdatePointRecord($update_data, ['id' => $record_info->id, 'status' => 2]);
            if (!$save_res) {
                $this->__error_msg = '积分确认状态保存失败';
                return false;
            }
            return $confirm_res;
        }
    }

    /*
     * 积分退款
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
            'use_obj'  => $data['use_obj'],
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
        $total_point = 0;
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
            'channel'     => $data['channel'],
            'use_type'    => $data['use_type'],
            'use_obj'     => $data['use_obj'],
            'money'       => $data['money'],
            'point'       => $data['point'],
            'company_id'  => $data['company_id'],
            'member_id'   => $data['member_id'],
            'status'      => 1,
            'refund_id'   => $data['refund_id'], //增加售后流水号信息, 这个流水号是来自mis_order_refund_bill表
            'create_time' => time(),
            'update_time' => time(),
        ];

        $refund_id = PointRefund::AddPointRefundRecord($save_refund_data);
        if (!$refund_id) {
            $this->__error_msg = '返还积分记录保存失败';
            return false;
        }

        //请求第三方进行积分锁定
        $point_source_logic = new ScenePointSource();

        $refund_data = [
            'channel'      => $data['channel'],
            'refund_id'    => $data['refund_id'],
            'order_id'     => $data['use_obj'],
            'system_code'  => $data['system_code'],
            'member_id'    => $data['member_id'],
            'company_id'   => $data['company_id'],
            'point'        => $data['point'],
            'money'        => $data['money'],
            'account_list' => $data['account_list'],
            'memo'         => $data['memo'] ? $data['memo'] : '',
        ];

        $refund_res = $point_source_logic->RefundMemberPoint($refund_data, $data['channel']);
        if ($refund_res['Result'] == 'true') {
            $update_data = [
                'status'      => 2,
                'update_time' => time(),
                'trade_no'    => $refund_res["Data"]['trade_no']
            ];
            PointRefund::UpdatePointRefundRecord($update_data, ['id' => $refund_id, 'status' => 1]);
            return $refund_res;
        } else {
            $update_data = [
                'status'      => 3,
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
            'use_obj'  => $data['use_obj'],
        ];
        $record_info = PointRecord::GetPointRecord($point_where);
        if (empty($record_info)) {
            $this->__error_msg = '积分锁定记录不存在';
            return false;
        }
        return $record_info;
    }

    public function GetMemberRecord($data)
    {
        if (empty($data['member_id']) || empty($data['channel'])) {
            $this->__error_msg = '参数错误';
            return false;
        }
        $point_source_logic = new ScenePointSource();
        //获取用户积分
        $get_data   = [
            'system_code' => $data['system_code'],
            'channel'     => $data['channel'],
            'member_bn'   => $data['member_id'],
            'company_bn'  => $data['company_id'],
            'scene_ids'   => $data['scene_id'],
            'begin_time'  => $data['begin_time'],
            'end_time'    => $data['end_time'],
            'record_type' => $data['record_type'],
            'page'        => $data['page'],
            'page_size'   => $data['rowNum'],
        ];
        $point_data = $point_source_logic->GetMemberRecord($get_data, $data['channel']);
        return $point_data;
    }

    public function WithRule($data)
    {
        if (empty($data['member_id']) || empty($data['channel'])) {
            $this->__error_msg = '参数错误';
            return false;
        }

        $post_data = [
            'system_code' => $data['system_code'],
            'company_id'  => $data['company_id'],
            'member_id'   => $data['member_id'],
            'channel'     => $data['channel'],
            'filter_data' => $data['filter_data'],
            'time'        => time()
        ];

        $serviceLogic = new Service();

        $res_data = $serviceLogic->ServiceCall('member_scene_point_with_rule', $post_data);

        if ('SUCCESS' != $res_data['error_code']) {
            return [
                "Result"   => false,
                "ErrorMsg" => $res_data['error_msg'] ? $res_data['error_msg'] : "接口返回错误",
                "Data"     => array()
            ];
        }
        return [
            "Result"   => true,
            "ErrorMsg" => "成功",
            "Data"     => $res_data['data']
        ];
    }


    /*
     * 获取公司积分
     */
    public function GetCompanyPoint($data)
    {
        if (empty($data['channel'])) {
            $this->__error_msg = '参数错误';
            return false;
        }
        $point_source_logic = new ScenePointSource();
        //获取用户积分
        $get_data   = [
            'company_bn' => $data['company_id'],
        ];
        $point_data = $point_source_logic->GetCompanyPoint($get_data, $data['channel']);
        return $point_data;
    }

    /*
     * 获取公司积分流水
     */
    public function GetCompanyRecordList($queryData)
    {
        if (
            empty($queryData['company_ids']) ||
            empty($queryData['channel'])
        ) {
            $this->__error_msg = '参数错误';
            return false;
        }
        $point_source_logic = new ScenePointSource();
        $get_data           = [
            'channel'     => $queryData['channel'],
            'company_ids' => $queryData['company_ids'],
            'scene_ids'   => $queryData['scene_ids'],
            'begin_time'  => $queryData['begin_time'],
            'end_time'    => $queryData['end_time'],
            'search_key'  => $queryData['search_key'],
            'record_type' => $queryData['record_type'],
            'page'        => $queryData['page'],
            'page_size'   => $queryData['page_size'],
        ];
        $point_data         = $point_source_logic->GetCompanyRecordList($get_data, $queryData['channel']);
        return $point_data;
    }

    public function LockCompanyPoint($reqData)
    {
        $point_source_logic = new ScenePointSource();
        $point_data         = $point_source_logic->LockCompanyPoint($reqData, $reqData['channel']);
        return $point_data;
    }

    public function UnLockCompanyPointByAssign($reqData)
    {
        $point_source_logic = new ScenePointSource();
        $point_data         = $point_source_logic->UnLockCompanyPointByAssign($reqData, $reqData['channel']);
        return $point_data;
    }


    public function CompanyAssignToMembers($reqData)
    {
        $point_source_logic = new ScenePointSource();
        $point_data         = $point_source_logic->CompanyAssignToMembers($reqData, $reqData['channel']);
        return $point_data;
    }


    public function GetErrorMsg()
    {
        return $this->__error_msg;
    }
}
