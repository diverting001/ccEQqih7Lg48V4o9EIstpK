<?php
namespace App\Api\V1\Service\Message;
use App\Api\Logic\Service;
use App\Api\Model\Message\ChannelPush as ChannelPushModel;
use App\Api\V1\Service\ServiceTrait;
use Illuminate\Support\Facades\DB;
use Neigou\ApiClient;
use Swoole\Database\MysqliException;
use \App\Api\Model\Message\Channel;


class ChannelPush{
    use ServiceTrait;



    public function channelPush($channelId = 0,$type = 0,$companyIds = array()){
        // 判断渠道是否存在
        $sendParams = array(
            'id' => $channelId
        );
        $serviceLogic = new Service();
        $res = $serviceLogic->ServiceCall('message_get_channel',$sendParams);
        if ( $res['error_code'] != 'SUCCESS') {
            return $this->outputFormat([], '获取channel错误', 500);
        }
        $channelInfo = $res['data'];
        if (empty($channelInfo)){
            return $this->outputFormat([],'渠道不存在:'.$channelId,401);
        }

        // 检查该公司是否推送过类似渠道
        // 1.检查是否存在推送所有公司的类型
        $where = [
            'type' => 1,// 推送类型
            'channel_type' => $channelInfo['type']
        ];
        $exWhere = [
            [
                'key' => 'channel_id',
                'express' => '<>',
                'value' => $channelId
            ],
        ];
        $channelPushModel = new ChannelPushModel();
        $channelPushTypeIsAllList = $channelPushModel->findList($where,$exWhere);
        //dd($this->objToArr($channelPushTypeIsAllList));
        if (!$channelPushTypeIsAllList->isEmpty()){
            return $this->outputFormat([],'一个公司不能绑定重复类型的渠道',401);
        }

        //2.检查是否存在非推送所有公司的类型
        $where = [
            'type' => 3,
            'channel_type' => $channelInfo['type']
        ];
        $channelPushTypeIsExcludeList = $channelPushModel->findList($where,$exWhere);
        if (!$channelPushTypeIsExcludeList->isEmpty()){

            // 同类型不可能同时存在两个排除或者两个全部
            if ($type == 1 || $type == 3) {
                return $this->outputFormat([], '一个公司不能绑定重复类型的渠道', 402);
            }

            $excludeChannelIdList = [];
            foreach ($channelPushTypeIsExcludeList as $channelPushV){
                $excludeChannelIdList[] = $channelPushV->id;
            }

            $exWhereChannelCompanyList = [
                ['key'=>'channel_push_id','express'=>'in','value' => $excludeChannelIdList],
            ];

            $excludeChannelPushCompanyList = $channelPushModel->findChannelCompanyList([],$exWhereChannelCompanyList);
            foreach ($excludeChannelPushCompanyList as $excludePushCompanyV){
                $excludeCompanyIdList[] = $excludePushCompanyV->company_id;
            }

            $excludeCompanyIdList  = array_filter($excludeCompanyIdList);
            if ($excludeCompanyIdList){
                $errCompanyIds = [];
                foreach ($companyIds as $companyIdV){
                    if (!in_array($companyIdV,$excludeCompanyIdList)){
                        $errCompanyIds[] = $companyIdV;
                    }
                }

                if ($errCompanyIds){
                    return $this->outputFormat([],'公司不能绑定重复类型的渠道: '.implode(',',$errCompanyIds),403);
                }
            }
        }
        // 3. 检查部分推送
        $where = [
            'type' => 2,
            'channel_type' => $channelInfo['type']
        ];
        $channelPushTypeIsPartList = $channelPushModel->findList($where,$exWhere);
        $partChannelPushIdList = [];
        //dd(json_decode(json_encode($channelPushTypeIsPartList),true));
        if (!$channelPushTypeIsPartList->isEmpty()){
            if ($type == 1){
                return $this->outputFormat([],'一个公司不能绑定重复类型的渠道,公司id: '.implode(',',$errCompanyIds),404);
            }
            foreach ($channelPushTypeIsPartList as $channelPushV){
                $partChannelPushIdList[] = $channelPushV->id;
            }
            $exWhereChannelCompanyList = [
                ['key'=>'channel_push_id','express'=>'in','value' => $partChannelPushIdList],
            ];

            $partChannelPushCompanyList = $channelPushModel->findChannelCompanyList([],$exWhereChannelCompanyList);
            $partCompanyIdList = [];
            foreach ($partChannelPushCompanyList as $partCompanyListV){
                $partCompanyIdList[] = $partCompanyListV->company_id;
            }

            $partCompanyIdList = array_filter($partCompanyIdList); // 已经在其他渠道设置了指定的公司id
            if ($partCompanyIdList){
                if ($type == 2) {
                    // 指定+指定 应该为不同公司，也就是交集为空
                    $errCompanyIds = array_intersect($partCompanyIdList, $companyIds);
                } else {
                    // 指定+排除 应该为相同公司，也就是差集为空。但是排除应该>=指定
                    $errCompanyIds = array_diff($partCompanyIdList, $companyIds);
                }

                if ($errCompanyIds){
                    return $this->outputFormat([],'一个公司不能绑定重复类型的渠道,公司id: '.implode(',',$errCompanyIds),401);
                }

            }
        }

        // 开始推送
        $addChannelPushData = [
            'channel_id' => $channelId,
            'type' => $type,
            'channel_type'=>$channelInfo['type']
        ];

        try {
            DB::beginTransaction();
            $where = [
                'channel_id' => $channelId
            ];
            $pushChannelInfo = $channelPushModel->findRow($where);
            //dd(json_decode(json_encode($pushChannelInfo),true));
            if (empty($pushChannelInfo)){
                $pushId = $channelPushModel->createChannelPush($addChannelPushData);
                if (empty($pushId)){
                    throw new \Exception('创建推送渠道失败',500);
                }
            }else{
                $pushId = $pushChannelInfo->id;
                $where = [
                    'id' => $pushId,
                ];
                $channelPushModel->updateChannelPush($where,$addChannelPushData);
                $where = ['channel_push_id' => $pushId];
                $channelPushModel->deletePushCompany($where);
            }

            if ($type != 1){
                $batchAddPushCompanyData = [];
                foreach ($companyIds as $companyIdV){
                    $batchAddPushCompanyData[] = [
                        'channel_push_id' => $pushId,
                        'company_id' => $companyIdV
                    ];
                }
                $batchAddRes = $channelPushModel->createPushCompany($batchAddPushCompanyData);
                if (empty($batchAddRes)){
                    throw new \Exception('创建推送渠道公司明细失败',500);
                }
            }
            DB::commit();
            return $this->outputFormat(['id' => $pushId]);
        }catch (MysqliException $e){
            DB::rollBack();
            return $this->outputFormat([],$e->getMessage(),$e->getCode());
        }
    }


    /**
     * Notes: 获取公司推送的渠道列表
     * @param int $companyId
     * @return array
     * Author: liuming
     * Date: 2021/1/21
     * @deprecated  2022年7月12日废弃，改用getCompanyChannelPushListV2
     */
    public function getCompanyChannelPushList($companyId = 0)
    {
        $channelPushModel = new ChannelPushModel();
        // 获取所有的模板
        $where = [
            'type' => 1,// 全部推送
        ];
        $allChannelPushList = $channelPushModel->findList($where);
        // 获取非的模板
        $where = [
            'type' => 3,// 全部推送
        ];
        $exChannelPushList = $channelPushModel->findList($where);
        if (!$exChannelPushList->isEmpty()) {
            $exChannelPushIdList = [];
            foreach ($exChannelPushList as $v) {
                $exChannelPushIdList[] = $v->id;
            }
            if ($exChannelPushIdList) {
                // 查询公司在这个渠道下的数据
                $exWhere = [
                    [
                        'express' => 'in',
                        'key' => 'company_id',
                        'value' => [$companyId],
                    ],
                    [
                        'express' => 'in',
                        'key' => 'channel_push_id',
                        'value' => $exChannelPushIdList,
                    ]
                ];
                $pushChannelCompanyList = $channelPushModel->findChannelCompanyList([], $exWhere);
                //dd(json_decode(json_encode($pushChannelCompanyList),true));
                $findExChannelPushIdList = [];
                if ($pushChannelCompanyList) {
                    foreach ($pushChannelCompanyList as $v) {
                        $findExChannelPushIdList[] = $v->channel_push_id;
                    }
                }

                $findExChannelPushIdList = array_filter($findExChannelPushIdList);
                $findExChannelPushIdList = array_unique($findExChannelPushIdList);
            }

            if (isset($findExChannelPushIdList) && !empty($findExChannelPushIdList)) {
                foreach ($exChannelPushList as $k => $v) {
                    if (in_array($v->id, $findExChannelPushIdList)) {
                        unset($exChannelPushList[$k]);
                    }
                }
            }
        }
        //dd(json_decode(json_encode($exChannelPushList),true));
        // 获取指定的模板
        $where = [
            'channel_push.type' => 2,
            'channel_push_company.company_id' => $companyId
        ];
        $partChannelPushCompanyList = $channelPushModel->findChannelAndCompanyList($where);
        $partChannelPushList = [];
        if (!$partChannelPushCompanyList->isEmpty()) {
            foreach ($partChannelPushCompanyList as $v) {
                $channelPushIds[] = $v->id;
            }
            $where = [
                [
                    'key' => 'id',
                    'express' => 'in',
                    'value' => $channelPushIds
                ]
            ];
            $partChannelPushList = $channelPushModel->findList([], $where);
        }

        $allChannelPushList = $this->objToArr($allChannelPushList);
        $partChannelPushList = $this->objToArr($partChannelPushList);
        $exChannelPushList = $this->objToArr($exChannelPushList);
        $list = array_merge($allChannelPushList, $partChannelPushList, $exChannelPushList);

        $channelIds = [];
        if ($list) {
            foreach ($list as $v) {
                $channelIds[] = $v['channel_id'];
            }
        }
        if ($channelIds) {
            $sendParams = [
                'ids' => $channelIds
            ];
            $serviceLogic = new Service();
            $res = $serviceLogic->ServiceCall('message_get_channel_list', $sendParams);
            if ($res['error_code'] != 'SUCCESS') {
                return $this->outputFormat([], '获取channel错误', 500);
            }

            $channelList = $res['data'];
            foreach ($list as &$v) {
                $v['channel'] = $channelList[$v['channel_id']]['channel'];
            }

        }
        return $this->outputFormat($list);
    }

    /**
     * 获取公司绑定的推送渠道列表
     * @param $companyId
     * @return \Closure
     */
    public function getCompanyChannelPushListV3($companyId = 0): \Closure
    {
        // 所有的渠道推送信息
        $channelPushModel = app(ChannelPushModel::class);
        $chanelPushAll = $channelPushModel->findBaseAll();
        if ($chanelPushAll->isEmpty()) {
            return $this->outputFormat([]);
        }

        // 与该公司相关的推送信息，包含指定与排除
        $companyChannelPushIds = $channelPushModel->getCompanyChannelPushIds($companyId)->toArray();

        // 筛选公司可用的渠道信息
        $result = $chanelPushAll->filter(function ($channelPush) use ($companyChannelPushIds) {
            switch ($channelPush->type) {
                case '1':
                    // 全部
                    return true;
                case '2':
                    // 指定
                    return in_array($channelPush->id, $companyChannelPushIds);
                case '3':
                    // 排除
                    return !in_array($channelPush->id, $companyChannelPushIds);
                default:
                    return false;
            }
        });

        if (!$result->isEmpty()) {
            // 有结果，添加渠道名
            $channelRelationList = app(Channel::class)
                ->getListByIds($result->pluck('channel_id'), ['id', 'channel'])
                ->pluck('channel', 'id');

            // 添加渠道名
            foreach ($result as $v) {
                $v->channel = $channelRelationList[$v->channel_id] ?? '';
            }
        }
        return $this->outputFormat($result->values()->toArray());
    }


    /**
     * Notes: 取消推送
     * @param $channelId
     * @return \Closure
     * @throws \Exception
     * Author: liuming
     * Date: 2021/2/3
     */
    public function cancelChannelPush($channelId){
        $channelPushModel = new ChannelPushModel();
        $channelPushDetail = $channelPushModel->findRow(['channel_id' => $channelId]);
        if (empty($channelPushDetail)){
            return $this->outputFormat([],'渠道没有绑定过任何公司',400);
        }
        try {
            DB::beginTransaction();
            $res = $channelPushModel->deleteChannelPush(['channel_id' => $channelId]);
            if (empty($res)){
                throw new \Exception('取消渠道推送失败',5000);
            }

            if ($channelPushDetail->type != 1){
                $channelPushModel->deletePushCompany(['channel_push_id' => $channelPushDetail->id]);
            }
            DB::commit();
            return $this->outputFormat(['channel_id' => $channelId],'取消成功');

        }catch (MysqliException $e){
            DB::rollBack();
            return $this->outputFormat([],$e->getMessage(),$e->getCode());
        }
    }

    /**
     * Notes: 获取渠道推送的基础信息
     * @param $chanelIds
     * @return \Closure
     * Author: liuming
     * Date: 2021/2/9
     */
    public function getChannelPushBaseList($chanelIds){
        $where = $exWhere = [];
        if ($chanelIds){
            $exWhere = [
                [
                    'key' => 'channel_id',
                    'value' => $chanelIds,
                    'express' => 'in'
                ]
            ];
        }

        $channelPushModel = new ChannelPushModel();
        $list = $channelPushModel->findBaseAll($where,$exWhere);
        return $this->outputFormat(['list' => $list],'成功',0);

    }

    /**
     * Notes: 获取渠道绑定的公司列表
     * @param $channelId
     * @return \Closure
     * Author: liuming
     * Date: 2021/1/29
     */
    public function getChannelBindCompanyList($channelId)
    {
        $channelPushModel = new \App\Api\Model\Message\ChannelPush();
        $channelDetail = $channelPushModel->findRow(['channel.id' => $channelId]);
        if (empty($channelDetail)){
            return $this->outputFormat([],'渠道没有绑定过任何公司',400);
        }
        $companyIds = [];
        if ($channelDetail->type != 1){
            $companyPushList = $channelPushModel->findChannelCompanyList(['channel_push_id' => $channelDetail->id]);
            if (!$companyPushList->isEmpty()){
                foreach ($companyPushList as $v){
                    $companyIds[] = $v->company_id;
                }
            }
        }

        $channelDetail->company_ids=$companyIds;
        return $this->outputFormat($channelDetail,'获取成功',0);
    }

    public function objToArr($obj){
        if (empty($obj)){
            return [];
        }
        return json_decode(json_encode($obj),true);
    }
}
