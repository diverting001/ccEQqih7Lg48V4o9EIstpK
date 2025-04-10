<?php
/**
 * Created by PhpStorm.
 * User: chuanbin
 * Date: 2017/10/18
 * Time: 19:53
 */

namespace App\Api\V1\Controllers;

use App\Api\Common\Controllers\BaseController;
use App\Api\Model\Point\Point as PointModel;
use App\Api\V1\Service\Point\Point as PointService;
use Illuminate\Http\Request;

class PointController extends BaseController
{
    const PASSWORD_ERROR = 511;

    /**
     * 获取公司可用积分类型
     * @param Request $request
     * @return mixed
     */
    public function GetCompanyChannel(Request $request)
    {
        $company_data = $this->getContentArray($request);
        if (empty($company_data['company_id'])) {
            $this->setErrorMsg('请指定公司');
            return $this->outputFormat([], 400);
        }
        $data = array();
        $company_point_channel_list = PointModel::GetCompanyChannel($company_data['company_id']);
        if (empty($company_point_channel_list)) {
            $this->setErrorMsg('不支持积分支付');
            return $this->outputFormat([], 0);
        } else {
            foreach ($company_point_channel_list as $point_channel) {
                //设置获取积分名称请求参数
                $paramsArr = [
                    'channel' => $point_channel->channel,
                    'company_id' => $point_channel->company_id,
                ];
                $point_service = new PointService();
                $point_name = $point_service->GetPointName($paramsArr);
                $data[] = [
                    'company_id' => $point_channel->company_id,
                    'channel' => $point_channel->channel,
                    'point_name' => $point_name ? $point_name : PointService::DEFAULT_POINT_NAME,
                    'point_type' => $point_channel->point_type,//积分类型
                    'exchange_rate' => $point_channel->exchange_rate,
                    'point_version' => isset($point_channel->point_version) ? $point_channel->point_version : 1,
                ];
            }
            $this->setErrorMsg('请求成功');
            return $this->outputFormat($data, 0);
        }
    }

    /**
     * 渠道积分信息查询
     * @param Request $request
     * @return mixed
     */
    public function GetChannelInfo(Request $request)
    {
        $channel_data = $this->getContentArray($request);
        $channel_data['member_id'] = intval($channel_data['member_id']);
        $channel_data['company_id'] = intval($channel_data['company_id']); //公司id

        //保留原始参数
        $originalParams = $channel_data;
        if (empty($channel_data['channel'])) {
            $this->setErrorMsg('请求选择积分渠道');
            return $this->outputFormat([], 400);
        }
        $point_channel_info = PointModel::GetChannelInfo($channel_data['channel']);
        if (empty($point_channel_info)) {
            $this->setErrorMsg('积分渠道不存在');
            return $this->outputFormat([], 401);
        } else {
            $point_service = new PointService();
            $exchange_rate = $point_channel_info->exchange_rate;
            //动态积分比例
            if ($point_channel_info->type == 2) {
                $channel_data = $point_service->GetPointRate([
                    'channel' => $channel_data['channel'],
                    'member_id' => $channel_data['member_id']
                ]);
                if ($channel_data['Result'] != 'true') {
                    $this->setErrorMsg('积分比例获取失败');
                    return $this->outputFormat([], 402);
                }
                $exchange_rate = $channel_data['Data']['ratio'];
            }
            if ($exchange_rate <= 0) {
                $this->setErrorMsg('无效的积分比例');
                return $this->outputFormat([], 402);
            }

            //设置请求参数
            $paramsArr = [
                'channel' => $originalParams['channel'],
                'member_id' => $originalParams['member_id'],
                'company_id' => $originalParams['company_id'],
            ];
            $point_name = $point_service->GetPointName($paramsArr);

            $data = [
                'channel' => $point_channel_info->channel,
                'point_name' => $point_name ? $point_name : PointService::DEFAULT_POINT_NAME,
                'exchange_rate' => $exchange_rate,
                'adapter_type' => $point_channel_info->adapter_type,
                'point_type' => $point_channel_info->point_type,
                'point_version' => isset($point_channel_info->point_version) ? $point_channel_info->point_version : 1,
            ];
            $this->setErrorMsg('请求成功');
            return $this->outputFormat($data, 0);
        }
    }

    /**
     * 获取所有积分渠道
     * @param Request $request
     * @return mixed
     */
    public function GetAllChannel(Request $request)
    {
        $pointMdl = new PointModel();
        $all_channels = $pointMdl->GetAllChannels();
        $this->setErrorMsg('请求成功');
        return $this->outputFormat($all_channels, 0);
    }

    /**
     * 个人积分查询接口
     * @param Request $request
     * @return mixed
     */
    public function GetMemberPoint(Request $request)
    {
        $member_data = $this->getContentArray($request);
        if (empty($member_data['member_id']) || empty($member_data['channel'])) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }
        $point_service = new PointService();
        $data = [
            'company_id' => $member_data['company_id'],
            'member_id' => $member_data['member_id'],
            'channel' => $member_data['channel']
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
     * 积分锁定
     * @param Request $request
     * @return mixed
     */
    public function LockMemberPoint(Request $request)
    {
        $lock_data = $this->getContentArray($request);
        if (empty($lock_data['member_id']) || empty($lock_data['channel']) || empty($lock_data['point'])) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }
        $point_service = new PointService();
        $data = [
            'company_id' => $lock_data['company_id'],
            'member_id' => $lock_data['member_id'],
            'use_type' => $lock_data['use_type'],
            'use_obj' => $lock_data['use_obj'],
            'money' => $lock_data['money'],
            'point' => $lock_data['point'],
            'channel' => $lock_data['channel'],
            'items' => $lock_data['items'],
            'third_point_pwd' => $lock_data['third_point_pwd'],
        ];
        $lock_res = $point_service->LockMemberPoint($data);
        if (!$lock_res) {
            $this->setErrorMsg($point_service->GetErrorMsg());
            return $this->outputFormat([], 500);
        } else {
            if ($lock_res['Result'] != 'true') {
                if ($lock_res['ErrorMsg'] == self::PASSWORD_ERROR) {
                    $this->setErrorMsg('密码错误');
                    return $this->outputFormat([], self::PASSWORD_ERROR);
                } else {
                    $this->setErrorMsg($lock_res['ErrorMsg']);
                    return $this->outputFormat([], 501);
                }
            } else {
                $this->setErrorMsg('请求成功');
                return $this->outputFormat($lock_res['Data'], 0);
            }
        }
    }

    /**
     * 取消积分锁定
     * @param Request $request
     * @return mixed
     */
    public function CancelLockMemberPoint(Request $request)
    {
        $lock_data = $this->getContentArray($request);
        if (empty($lock_data['member_id']) || empty($lock_data['channel']) || empty($lock_data['use_obj'])) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }
        $point_service = new PointService();
        $data = [
            'company_id' => $lock_data['company_id'],
            'member_id' => $lock_data['member_id'],
            'use_type' => $lock_data['use_type'],
            'use_obj' => $lock_data['use_obj'],
            'memo' => empty($lock_data['memo']) ? '' : $lock_data['memo'],
            'channel' => $lock_data['channel'],
        ];
        $lock_res = $point_service->CancelLockMemberPoint($data);
        if (!$lock_res) {
            $this->setErrorMsg($point_service->GetErrorMsg());
            return $this->outputFormat([], 500);
        } else {
            if ($lock_res['Result'] != 'true') {
                $this->setErrorMsg($lock_res['ErrorMsg']);
                return $this->outputFormat([], 501);
            } else {
                $this->setErrorMsg('请求成功');
                return $this->outputFormat($lock_res['Data'], 0);
            }
        }
    }

    /**
     * 确认积分锁定正式使用
     * @param Request $request
     * @return mixed
     */
    public function ConfirmLockMemberPoint(Request $request)
    {
        $lock_data = $this->getContentArray($request);
        if (empty($lock_data['member_id']) || empty($lock_data['channel']) || empty($lock_data['use_obj'])) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }
        $point_service = new PointService();
        $data = [
            'company_id' => $lock_data['company_id'],
            'member_id' => $lock_data['member_id'],
            'use_type' => $lock_data['use_type'],
            'use_obj' => $lock_data['use_obj'],
            'channel' => $lock_data['channel'],
        ];
        $lock_res = $point_service->ConfirmLockMemberPoint($data);
        if (!$lock_res) {
            $this->setErrorMsg($point_service->GetErrorMsg());
            return $this->outputFormat([], 500);
        } else {
            if ($lock_res['Result'] != 'true') {
                $this->setErrorMsg($lock_res['ErrorMsg']);
                return $this->outputFormat([], 501);
            } else {
                $this->setErrorMsg('请求成功');
                return $this->outputFormat($lock_res['Data'], 0);
            }
        }
    }

    /**
     * 退还积分
     * @param Request $request
     * @return mixed
     */
    public function RefundPoint(Request $request)
    {
        $refund_point_data = $this->getContentArray($request);
        if (empty($refund_point_data['member_id']) || empty($refund_point_data['channel']) || empty($refund_point_data['use_obj'])) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }
        $point_service = new PointService();
        $data = [
            'company_id' => $refund_point_data['company_id'],
            'member_id' => $refund_point_data['member_id'],
            'use_type' => $refund_point_data['use_type'],
            'use_obj' => $refund_point_data['use_obj'],
            'channel' => $refund_point_data['channel'],
            'point' => $refund_point_data['point'],
            'money' => $refund_point_data['money'],
            'refund_id' => $refund_point_data['refund_id'],//添加售后流水号
        ];
        $lock_res = $point_service->RefundMemberPoint($data);
        if (!$lock_res) {
            $this->setErrorMsg($point_service->GetErrorMsg());
            return $this->outputFormat([], 500);
        } else {
            if ($lock_res['Result'] != 'true') {
                $this->setErrorMsg($lock_res['ErrorMsg']);
                return $this->outputFormat([], 501);
            } else {
                $this->setErrorMsg('请求成功');
                return $this->outputFormat($lock_res['Data'], 0);
            }
        }
    }

    /**
     * 获取积分锁定记录
     * @param Request $request
     * @return mixed
     */
    public function GetLockRecord(Request $request)
    {
        $lock_data = $this->getContentArray($request);
        if (empty($lock_data['use_obj']) || empty($lock_data['use_type'])) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }
        $point_service = new PointService();
        $data = [
            'use_obj' => $lock_data['use_obj'],
            'use_type' => $lock_data['use_type'],
        ];
        $record_data = $point_service->GetLockRecord($data);
        if (!$record_data) {
            $this->setErrorMsg($point_service->GetErrorMsg());
            return $this->outputFormat([], 500);
        } else {
            $this->setErrorMsg('请求成功');
            return $this->outputFormat($record_data, 0);
        }
    }

    /**
     * 开通公司积分
     * @param Request $request
     * @return mixed
     */
    public function AddCompany(Request $request)
    {
        $company_data = $this->getContentArray($request);
        if (empty($company_data['company_id']) || empty($company_data['channel'])) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }
        $company_point_channel = PointModel::GetCompanyPoin($company_data['company_id'], $company_data['channel']);
        if (!empty($company_point_channel)) {
            if ($company_point_channel->status == 1) {
                $this->setErrorMsg('请求成功');
                return $this->outputFormat(array(), 0);
            } else {
                $res = PointModel::UpdateCompanyPoin(['id' => $company_point_channel->id], ['status' => 1]);
            }
        } else {
            $res = PointModel::AddCompanyPoin([
                'company_id' => $company_data['company_id'],
                'channel' => $company_data['channel'],
                'status' => 1
            ]);
        }
        if (!$res) {
            $this->setErrorMsg('请求失败');
            return $this->outputFormat(array(), 500);
        } else {
            $this->setErrorMsg('请求成功');
            return $this->outputFormat(array(), 0);
        }
    }

    /**
     * 操作一个公司对应多个channel
     * @param Request $request
     * @return mixed
     */
    public function SetCompanyMultiChannel(Request $request)
    {
        $data = $this->getContentArray($request);
        if (empty($data['company_id'])) {
            $this->setErrorMsg('company_id或channel_list不能为空');
            return $this->outputFormat(array(), 400);
        }

        //如果强制关闭开启, 那么直接关闭现有公司的所有渠道积分
        if (empty($data['channel_list'])) {
            $res = PointModel::UpdateCompanyPoin(['company_id' => $data['company_id']], ['status' => 2]);
            if (!$res) {
                $this->setErrorMsg('批量关闭失败');
                return $this->outputFormat([], 400);
            } else {
                $this->setErrorMsg('批量关闭成功');
                return $this->outputFormat([], 0);
            }
        }

        $companyDataRes = PointModel::GetCompanyPoin($data['company_id']);
        //如果没有数据,只做新增处理
        if (!empty($companyDataRes)) {
            foreach ($companyDataRes as $companyV) {
                $allChannelList[] = $companyV->channel;
                if ($companyV->status == 1) {
                    $oldChannelList['open'][] = $companyV->channel;
                } else {
                    $oldChannelList['close'][] = $companyV->channel;
                }
            }
            $newOpenChannelListAdd = array_diff($data['channel_list'], $oldChannelList['open']);
            if (!empty($oldChannelList['close'])) {
                $newOpenChannelListUp = array_intersect($data['channel_list'], $oldChannelList['close']); //要修改为开通的
                $newOpenChannelListAdd = array_diff($newOpenChannelListAdd, $newOpenChannelListUp); //要增加的
            }
            $newCloseChannelList = array_diff($oldChannelList['open'], $data['channel_list']);//要修改为关闭的
        } else {
            $newOpenChannelListAdd = $data['channel_list'];
            $newOpenChannelListUp = [];
            $newCloseChannelList = [];
        }
        try {
            app('api_db')->beginTransaction();
            //批量添加
            if ($newOpenChannelListAdd) {
                foreach ($newOpenChannelListAdd as $channelV) {
                    $inserData[] = [
                        'company_id' => $data['company_id'],
                        'channel' => $channelV,
                    ];
                }
                $res = PointModel::BatchAddCompanyPoint($inserData);
                if (!$res) {
                    throw new \Exception('批量添加channel和公司失败');
                }
            }
            //新更新为开通的公司和channel
            if ($newOpenChannelListUp) {
                foreach ($newOpenChannelListUp as $channelV) {
                    $whereArr = [
                        'company_id' => $data['company_id'],
                        'channel' => $channelV,
                    ];
                    $res = PointModel::UpdateCompanyPoin($whereArr, ['status' => 1]);

                    if (!$res) {
                        throw new \Exception('批量更新channel和公司失败');
                    }
                }
            }

            //新关闭的公司channel
            if ($newCloseChannelList) {
                foreach ($newCloseChannelList as $channelV) {
                    $whereArr = [
                        'company_id' => $data['company_id'],
                        'channel' => $channelV,
                    ];
                    $res = PointModel::UpdateCompanyPoin($whereArr, ['status' => 2]);
                    if (!$res) {
                        throw new \Exception('批量关闭channel和公司失败');
                    }
                }
            }
            app('api_db')->commit();

            $this->setErrorMsg('修改成功');
            return $this->outputFormat([], 0);
        } catch (\Exception $e) {
            app('api_db')->rollBack();

            $this->setErrorMsg($e->getMessage());
            return $this->outputFormat([], 500);
        }
    }

    /**
     * 开通公司积分
     * @param Request $request
     * @return mixed
     */
    public function DeleteCompany(Request $request)
    {
        $company_data = $this->getContentArray($request);
        if (empty($company_data['company_id']) || empty($company_data['channel'])) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }
        $company_point_channel = PointModel::GetCompanyPoin($company_data['company_id'], $company_data['channel']);
        $res = true;
        if (!empty($company_point_channel)) {
            if ($company_point_channel->status == 1) {
                $res = PointModel::UpdateCompanyPoin(['id' => $company_point_channel->id], ['status' => 2]);
            }
        }
        if (!$res) {
            $this->setErrorMsg('请求失败');
            return $this->outputFormat(array(), 500);
        } else {
            $this->setErrorMsg('请求成功');
            return $this->outputFormat(array(), 0);
        }
    }

    /**
     * 获取员工积分记录列表
     * @param Request $request
     * @return mixed
     */
    public function GetMemberRecord(Request $request)
    {
        $member_data = $this->getContentArray($request);
        //设置接收参数
        if (empty($member_data['member_id']) || empty($member_data['channel'])) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }

        //实例化service对象
        $point_service = new PointService();
        $data = [
            'company_id' => $member_data['company_id'],
            'member_id' => $member_data['member_id'],
            'channel' => $member_data['channel'],
            'rowNum' => $member_data['rowNum'],
            'page' => $member_data['page']
        ];

        //通过service获取会员积分记录
        $point_data = $point_service->GetMemberRecord($data);
        if (!$point_data) { //积分记录获取失败,返回错误消息
            $this->setErrorMsg($point_service->GetErrorMsg());
            return $this->outputFormat([], 500);
        } else {
            if ($point_data['Result'] != 'true') {
                $this->setErrorMsg($point_data['ErrorMsg']);
                return $this->outputFormat([], 501);
            } else { //获取成功, 返回需要的数据
                $this->setErrorMsg('请求成功');
                return $this->outputFormat($point_data['Data'], 0);
            }
        }
    }

    /**
     * 根据公司id获取积分渠道下的公司信息
     * @param Request $request
     * @return mixed
     */
    public function getPointChannelCompanyDetail(Request $request)
    {
        $data = $this->getContentArray($request);
        //参数检查
        if (empty($data['company_id'])) {
            $this->setErrorMsg('company_id不能为空');
            return $this->outputFormat([], 400);
        }

        $pointChannelCompanyRes = PointModel::GetCompanyPoin($data['company_id']);
        if (empty($pointChannelCompanyRes)) {
            $this->setErrorMsg('该公司积分渠道配置不存在');
            return $this->outputFormat([], 500);
        }

        $this->setErrorMsg('查询成功');
        return $this->outputFormat($pointChannelCompanyRes, 0);
    }
}
