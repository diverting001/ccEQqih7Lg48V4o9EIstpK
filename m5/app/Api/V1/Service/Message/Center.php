<?php

namespace App\Api\V1\Service\Message;

use App\Api\Logic\Service;
use App\Api\V1\Service\ServiceTrait;
use Illuminate\Support\Facades\DB;
use Neigou\ApiClient;
use Swoole\Database\MysqliException;
use  \App\Api\Model\Message\Channel as ChannelModel;
use App\Api\Model\Message\Center as CenterModel;


class Center
{
    const DEFAULT_SEND_STATUS = 0;// 默认是未发送
    const STAY_SEND_STATUS = 1; //待发送
    const DEFAULT_TYPE = 1;// 默认是通知类

    use ServiceTrait;

    /**
     * Notes: 创建订单明细
     * @param array $data
     * @return \Closure
     * @throws \Exception
     * Author: liuming
     * Date: 2021/1/21
     */
    public function create($data = [])
    {
        $recvData = $data['recv_data'];
        unset($data['recv_data']);

        $sendParams = array(
            'id' => $data['channel_id']
        );
        // 获取当前发送渠道信息
        $serviceLogic = new Service();
        $res = $serviceLogic->ServiceCall('message_get_channel',$sendParams);
        if ( $res['error_code'] != 'SUCCESS') {
            return $this->outputFormat([], 'channel不存在: ' . $data['channel_id'], 400);

        }
        $CenterModel = new CenterModel();
        // 检查该公司消息中心是否存在
        $where = [
            'title' => $data['title'],
            'company_id' => $data['company_id']
        ];
        $findCenter = $CenterModel->findRow($where);
        if ($findCenter){
            return $this->outputFormat([], '消息中心title已存在', 400);
        }


        // 检查接收数据
//        foreach ($recvData as $k => $v) {
//            if (empty($v) || empty($v['recv_obj'])) {
//                unset($recvData[$k]);
//            }
//        }
//        if (empty($recvData)) {
//            return $this->outputFormat([], '信息发送对象不存在', 400);
//        }

        $createCenterData = [
            'title' => $data['title'],
            'content' => $data['content'],
            'params' => $data['params'] ?? [],
            'channel_id' => $data['channel_id'],
            'send_status' => self::DEFAULT_SEND_STATUS,
            'type' => $data['type'] ?? self::DEFAULT_TYPE,
            'member_id' => $data['member_id'] ? $data['member_id'] : 0,
            'company_id' => $data['company_id'],
            'template_id' => $data['template_id'],
            'total_num' => count($recvData)
        ];

        try {
            // 添加数据
//            DB::beginTransaction();
            $centerId = $CenterModel->createCenter($createCenterData);
            if (empty($centerId)) {
                throw new \Exception('新增消息中心失败', 5000);
            }

//            foreach ($recvData as &$v) {
//                $v['message_center_id'] = $centerId;
//            }


            // 添加明细 . 1.检查明细
//            $res = $CenterModel->createCenterItems($recvData);
//            if (empty($res)) {
//                throw new \Exception('创建消息明细失败', 5001);
//            }

//            DB::commit();
            return $this->outputFormat(['id' => $centerId]);
        } catch (MysqliException $e) {
//            DB::rollBack();
            return $this->outputFormat([], $e->getMessage(), $e->getCode());
        }

    }

    /**
     * Notes: 获取消息中心列表
     * @param array $where
     * @param int $offset
     * @param int $limit
     * Author: liuming
     * Date: 2021/1/21
     */
    public function getCenterList($where = [], $offset = 0, $limit = 20)
    {
        $CenterModel = new CenterModel();
        $count = $CenterModel->findCenterCount($where,[]);
        $list = $CenterModel->findCenterList($where,[],$offset,$limit);

        // 获取渠道信息
        $channelIds = [];
        foreach ($list as $v){
            $channelIds[] = $v->channel_id;
        }
        $channelIds = array_filter(array_unique($channelIds));
        $sendParams = [
            'ids' => $channelIds
        ];
        $serviceLogic = new Service();
        $res = $serviceLogic->ServiceCall('message_get_channel_list',$sendParams);
        if ($res['error_code'] != 'SUCCESS') {
            return $this->outputFormat([], '获取channel错误', 500);
        }

        $channelList = $res['data'];
        foreach ($list as $v){
            $v->channel = $channelList[$v->channel_id] ? $channelList[$v->channel_id]['channel'] : '';
        }

        $data = [
            'count' => $count,
            'list' => $list
        ];
        return $this->outputFormat($data);
    }

    /**
     * Notes: 获取消息中心
     * @param array $where
     * @return \Closure
     * Author: liuming
     * Date: 2021/1/21
     */
    public function getCenter($where = [])
    {
        $CenterModel = new CenterModel();
        $info = $CenterModel->findRow($where,[]);
        if (empty($info)){
            return $this->outputFormat([],'消息中心数据不存在',400);
        }

        // 获取渠道
        $sendParams = array(
            'id' => $info->channel_id
        );
        // 获取当前发送渠道信息
        $serviceLogic = new Service();
        $res = $serviceLogic->ServiceCall('message_get_channel',$sendParams);
        if ( $res['error_code'] != 'SUCCESS') {
            return $this->outputFormat([], '获取channel错误', 500);

        }
        $info->channel = $res['data']['channel'];
        return $this->outputFormat($info);
    }

    /**
     * Notes: 增加消息明细
     * @param $messageCenterId
     * @param array $recvData
     * @return \Closure
     * Author: liuming
     * Date: 2021/4/22
     */
    public function addItems($messageCenterId,$recvData = []){
        $CenterModel = new CenterModel();
        $where = [
            'id' => $messageCenterId
        ];
        $info = $CenterModel->findRow($where,[]);
        if (empty($info)){
            return $this->outputFormat([],'消息中心数据不存在:'.$messageCenterId,400);
        }
        if ($info->send_status != 0){
            return $this->outputFormat([],'消息错误状态为:'.$info->send_status,400);
        }
        $CenterModel = new CenterModel();
        $res = $CenterModel->createCenterItems($recvData);
        if (empty($res)) {
            return $this->outputFormat([],'创建消息明细失败',5001);
        }
        return $this->outputFormat([]);
    }


    public function updateSendStatus($id = 0,$status = 0){
        if (empty($id)){
            return $this->outputFormat([],'消息id不能为空',400);
        }
        $centerModel = new CenterModel();
        $where = [
            'id' => $id
        ];
        $update = [
            'send_status' => $status
        ];
        $res = $centerModel->updateCenter($where,$update);
        if (empty($res)) {
            return $this->outputFormat([],'修改状态失败',5001);
        }
        return $this->outputFormat([]);
    }

    /**
     * Notes:批量创建消息（三合一）
     * User: mazhenkang
     * Date: 2023/1/4 10:57
     * @param $params
     */
    public function batchCreate($params)
    {
        if (empty($params)) {
            return $this->outputFormat([], '参数错误', 400);
        }
        $channel_id  = $params['channel_id']; //发送渠道id
        $template_id = $params['template_id'] ?: 0; //模板id
        $company_id  = $params['company_id']; //企业id

        //校验发送渠道
        $channelParams = array(
            'id' => $channel_id
        );
        // 获取当前发送渠道信息
        $serviceLogic = new Service();
        $res = $serviceLogic->ServiceCall('message_get_channel', $channelParams);
        if (empty($res['data'])) {
            return $this->outputFormat([], 'channel不存在: ' . $channel_id, 400);
        }

        $CenterModel = new CenterModel();
        // 检查该公司消息中心是否存在
        $titles = array_column($params['data'], 'title');
        $where = [
            'company_id' => $company_id,
            [DB::Raw("title in ('".implode("','", $titles)."')"), 1],
        ];
        $findCenter = $CenterModel->findCenterList($where, [], 0, count($titles),['id','desc'], ['id','title']);
        $findCenter = $findCenter->toArray();
        $centerTitles = array_column($findCenter,'title');


        $return = [];
        try{
            DB::beginTransaction();
            foreach ($params['data'] as $k=>$param) {
                $return[$k] = [
                    'message_center_id'    => 0,
                    'message_center_title' => $param['title'] ?: '',
                    'status' => 'fail',
                ];
                if (!isset($param['title']) || empty($param['title'])) {
                    $return[$k]['msg'] = '消息中心标题不能为空';
                    continue;
                }
                if (in_array($param['title'], $centerTitles)) {
                    $return[$k]['msg'] = '消息中心标题重复-' . $param['title'];
                    continue;
                }
                if (empty($template_id) && empty($param['template_id'])) {
                    $return[$k]['msg'] = '模板id不能为空';
                    continue;
                }
                if (empty($param['params']) || !is_array($param['params'])) {
                    $return[$k]['msg'] = '模板变量数据不正确';
                    continue;
                }
                if (empty($param['send_recv_obj'])) {
                    $return[$k]['msg'] = '发送人不能为空';
                    continue;
                }
                if (empty($param['send_user_id'])) {
                    $return[$k]['msg'] = '用户id不能为空';
                    continue;
                }

                //创建消息中心消息
                $createCenterData = [
                    'title'       => $param['title'],
                    'content'     => $param['content'] ?: '',
                    'params'      => $param['params'] ?? [],
                    'channel_id'  => $channel_id,
                    'send_status' => self::STAY_SEND_STATUS,
                    'type'        => $param['type'] ?? self::DEFAULT_TYPE,
                    'member_id'   => $param['member_id'] ? $param['member_id'] : 0,
                    'company_id'  => $company_id,
                    'template_id' => $template_id,
                    'total_num'   => 0
                ];

                $centerId = $CenterModel->createCenter($createCenterData);
                if (empty($centerId)) {
                    $return[$k]['msg'] = '创建消息中心消息异常';
                    continue;
                }

                //追加内容
                $itemsList[] = [
                    'message_center_id' => $centerId,
                    'user_id' => $param['send_user_id'],
                    'recv_obj' => $param['send_recv_obj']
                ];
                $res = $CenterModel->createCenterItems($itemsList);
                if (empty($res)) {
                    $return[$k]['msg'] = '创建消息明细失败';
                    continue;
                }
                //避免内部数据title重复
                $centerTitles[] = $param['title'];

                $return[$k]['message_center_id'] = $centerId;
                $return[$k]['status'] = 'succ';
            }
            DB::commit();
        }catch (\Exception $e){
            DB::rollBack();
            return $this->outputFormat([], $e->getMessage(), 400);
        }
        return $this->outputFormat($return, 'succ', 200);
    }
}
