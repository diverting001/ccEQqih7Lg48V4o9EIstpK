<?php

namespace App\Console\Commands;

use App\Api\Model\Express\CompanyMapping as CompanyMappingModel;
use App\Api\Model\Express\V2\Express as ExpressModel;
use App\Api\Model\Express\Express as OldExpressModel;
use App\Api\V4\Service\Express\Express as ExpressService;
use App\Api\V3\Service\Express\Express as OldExpressService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Neigou\RedisClient;


class ExpressSync extends Command
{
    protected $force = '';
    protected $signature = 'ExpressSync {type} {--channel=} {--id=} {--time=} {--batch=}';
    protected $description = '订单服务更新、订阅物流信息';
    protected $redisClient;

    public function handle()
    {
        $this->redisClient = new RedisClient();
        $type = $this->argument('type');
        switch ($type) {
            case 'pull':
                $this->expressPull();
                break;
            case 'subscribe':
                $this->expressSubscribe();
                break;
            case 'sync':
                $this->sync();
                break;
            case 'syncOld':
                $this->syncOld();
                break;
            case 'syncCollectTime':
                $this->syncCollectTime();
                break;
        }
    }

    /**
     * 主动拉取物流信息
     * @return void
     */
    private function expressPull()
    {
        $channel = $this->option('channel');
        $time = $this->option('time');
        $id = $this->option('id');
        //脚本批次
        $batch = $this->option('batch');
        $batch = $batch ?: 0 ;

        //上次拉取的id
        $maxIdKey = 'cron_express_pull' . $channel . '_last_id_key'.$batch;

        //指定开始的id
        if ($id) {
            $this->setMaxId($maxIdKey, $id);
        }

        //实例化
        $expressService = new ExpressService();


        $i = 0;
        while ($i < 99999) {
            //获取最新id
            $maxId = $this->getMaxId($maxIdKey);

            $where = array(
                array('channel', '=', $channel),
                array('is_pull', '=', ExpressModel::NEED_PULL),
                array('pull_time', '<', time() - ($time?:0)),
                array('fail_count', '<', 100),
                array('id', '>=', $maxId ?: 0),
            );

            //获取带同步的订单数据
            $expressList = ExpressModel::getExpressList('*', $where, 1000);
            if (empty($expressList)) {
                $this->setMaxId($maxIdKey, 0, 7200);
                $this->info('同步完成');
                return true;
            }

            //更新最新id
            $endExpress = end($expressList);
            $maxId = $endExpress['id'] + 1;
            $this->setMaxId($maxIdKey, $maxId, 7200);

            foreach ($expressList as $express) {

               //拉取
                $request = array(
                    'channel_company'=>$express['channel_company'],
                    'num'=>$express['num'],
                    'mobile'=>$express['mobile'],
                    'channel'=>$express['channel']
                );
                $result = $expressService->expressPull($request);

                //更新拉取时间
                $update = array(
                    'pull_time'=>time(),
                    'pull_count'=>DB::raw('pull_count + 1')
                );

                //拉取失败
                if (!isset($result['Result']) || $result['Result'] != 'true' || $result['Data']['express_channel_com'] != $request['channel_company']){
                    $update['fail_count'] = DB::raw('fail_count + 1');
                }

                //更新
                ExpressModel::updateExpressById($express['id'], $update);

                //如果拉取失败跳过
                if (!isset($result['Result']) || $result['Result'] != 'true'  || $result['Data']['express_channel_com'] != $request['channel_company']){
                    $this->error("id-" . $express['id'] . ":拉取数据失败");
                    continue;
                }

                //保存数据
                $errMsg = '';
                $result['Data']['express_com'] = $express['company'];
                $res = $expressService->updateExpressDetail($express, $result['Data'], $errMsg);

                $this->info("id-" . $express['id'] . ":" . ($res ? '成功' : $errMsg));
            }

            $i ++;
        }

    }


    /**
     * 物流信息订阅
     * @return void
     */
    private function expressSubscribe()
    {
        $channel = $this->option('channel');
        $time = $this->option('time');
        $id = $this->option('id');
        //脚本批次
        $batch = $this->option('batch');
        $batch = $batch ?: 0 ;

        if (empty($channel)) {
            $this->error('无效的channel');
        }

        //上次订阅的id
        $maxIdKey = 'cron_express_subscribe' . $channel . '_last_id_key'.$batch;

        //指定开始的id
        if ($id) {
            $this->setMaxId($maxIdKey, $id);
        }

        $expressService = new ExpressService();

        $i = 0;
        while ($i < 99999) {

            //获取最新id
            $maxId = $this->getMaxId($maxIdKey);

            $where = array(
                array('channel', '=', $channel),
                array('is_subscribe', '=', ExpressModel::NEED_SUBSCRIBE),
                array('subscribe_time', '<', time() - ($time?:0)),
                array('fail_count', '<', 100),
                array('id', '>=', $maxId ?: 0),
            );

            //获取带同步的订单数据
            $expressList = ExpressModel::getExpressList('*', $where, 1000);

            if (empty($expressList)) {
                $this->setMaxId($maxIdKey, 0, 7200);
                $this->info('订阅完成');
                return true;
            }

            //更新最新id
            $endExpress = end($expressList);
            $maxId = $endExpress['id'] + 1;
            $this->setMaxId($maxIdKey, $maxId, 7200);

            foreach ($expressList as $express) {

                //订阅
                $request = array(
                    'channel_company'=>$express['channel_company'],
                    'num'=>$express['num'],
                    'mobile'=>$express['mobile'],
                    'channel'=>$express['channel']
                );
                $result = $expressService->expressSubscribe($request);

                //更新订阅时间
                $update = array(
                    'subscribe_time'=>time(),
                    'subscribe_count'=>DB::raw('subscribe_count + 1')
                );

                //订阅成功或者失败
                if (isset($result['Result']) && $result['Result'] == 'true') {
                    $update['is_subscribe'] = 0;
                } else {
                    $update['fail_count'] = DB::raw('fail_count + 1');
                }

                //更新
                ExpressModel::updateExpressById($express['id'], $update);

                $this->info("id-" . $express['id'] . ":" . ((isset($result['Result']) && $result['Result'] == 'true') ? '成功' : '失败'));
            }

            $i ++;
        }

    }

    /**
     *  club 同步 service
     */
    private function sync(){
        //指定id
        $id = $this->option('id');
        //脚本批次
        $batch = $this->option('batch');
        $batch = $batch ?: 0 ;

        //上次同步的id
        $maxIdKey = 'cron_express_sync_last_id_key'.$batch;

        if ($id) {
            $this->setMaxId($maxIdKey, $id);
        }

        //实例化
        $oldExpressModel = new OldExpressModel();
        $expressService = new ExpressService();

        $i = 0;

        while ($i < 999999) {
            //获取最新id
            $maxId = $this->getMaxId($maxIdKey);

            $where = array(
                array('id', '>=', $maxId ?: 0),
            );

            //获取需要同步的旧数据
            $expressList = $oldExpressModel->getExpressList('*',  $where, 1000);
            if (empty($expressList)) {
                $this->info('同步完成');
                return true;
            }

            //更新最新id
            $endExpress = end($expressList);
            $maxId = $endExpress['id'] + 1;
            $this->setMaxId($maxIdKey, $maxId);

            foreach ($expressList as $express) {

                $mappings = CompanyMappingModel::getCompanyMappingList($express['company'], 'kuaidi100', 'channel');
                //格式化数据,旧数据按照快递100去格式化
                $expressData = array(
                    //物流公司
                    'express_com'=>'',
                    //第三方物流公司
                    'express_channel_com'=>$express['company'],
                    //物流单号
                    'express_no'=>$express['num'],
                    //渠道
                    'express_channel'=>$express['is_kuaidi100'] ? 'kuaidi100' : 'supplier',
                    //手机号
                    'express_mobile'=>$express['mobile'],
                    //状态
                    'status'=>$this->_getKuaidi100StatusMapping($express['status']),
                    //内容
                    'express_data'=>$express['data'],
                    //揽收时间
                    'collect_time'=>$express['collect_time'],
                    //添加时间
                    'add_time'=>$express['addtime'],
                    //修改时间
                    'update_time'=>$express['updatetime'],
                    //拉取
                    'is_pull' => $express['is_kuaidi100']  ? $this->_checkKuaidi100NeedPull($express['status']) : ExpressModel::NO_NEED_PULL,
                    //订阅
                    'is_subscribe' => ExpressModel::NO_NEED_SUBSCRIBE
                );

                //保存数据
                foreach ($mappings as $mapping) {
                    $expressData['express_com'] = $mapping['corp_code'];
                    $errMsg = '';
                    $res = $expressService->saveExpressDetail($expressData, $errMsg);
                    $this->info("id-" . $express['id'] . ":" . ($res ? '成功' : $errMsg));
                }
            }

            $i ++;
        }

    }

    /**
     * service 同步 club
     */
    private function syncOld(){
        //指定id
        $id = $this->option('id');
        //脚本批次
        $batch = $this->option('batch');
        $batch = $batch ?: 0 ;

        //上次同步的id
        $maxIdKey = 'cron_express_sync_old_last_id_key'.$batch;

        if ($id) {
            $this->setMaxId($maxIdKey, $id);
        }

        //实例化
        $oldExpressService = new OldExpressService();

        $i = 0;

        while ($i < 999999) {
            //获取最新id
            $maxId = $this->getMaxId($maxIdKey);

            $where = array(
                array('id', '>=', $maxId ?: 0)
            );

            //获取需要同步的旧数据
            $expressList = ExpressModel::getExpressList('*',  $where, 1000);
            if (empty($expressList)) {
                $this->info('同步完成');
                return true;
            }

            //更新最新id
            $endExpress = end($expressList);
            $maxId = $endExpress['id'] + 1;
            $this->setMaxId($maxIdKey, $maxId);

            foreach ($expressList as $express) {

                //格式化数据,旧数据按照快递100去格式化
                $expressData = array(
                    //物流公司
                    'company' => $express['channel_company'],
                    //手机号
                    'mobile' => $express['mobile'],
                    //物流单号
                    'num' => $express['num'],
                    //物流状态
                    'status' => $this->_getKuaidi100OldStatusMapping($express['status']),
                    //物流内容
                    'data' => $express['data'],
                    //是否快递100
                    'is_kuaidi100' => $express['channel'] == 'kuaidi100' ? 1 : 0,
                    //揽收时间
                    'collect_time' => $express['collect_time']

                );
                //保存数据
                $errMsg = '';
                $res = $oldExpressService->saveExpress($expressData, $errMsg);
                $this->info("id-" . $express['id'] . ":" . ($res ? '成功' : $errMsg));
            }

            $i ++;
        }

    }


    /**
     * 数据同步
     * @return bool|void
     */
    private function syncCollectTime(){
        //指定id
        $id = $this->option('id');
        $id = $id ?: 0;

        $i = 0;

        while ($i < 999999) {
            $this->info('startId:'.$id);

            //获取最新id
            $where = "id >= $id and (collect_time is null or collect_time = 0)";

            //获取需要同步的旧数据
            $expressList = ExpressModel::getExpressList('*',  $where, 1000);
            if (empty($expressList)) {
                $this->info('同步完成');
                return true;
            }

            foreach ($expressList as $express) {
                $id = $express['id'] + 1;

                //物流节点
                $data = unserialize($express['data']);
                if (empty($data['data'])) {
                    continue;
                }

                //判断是否多包裹
                $collectTime = 0;
                if ($express['company'] != ExpressService::PRESENT_LOGISTICS) {
                    $endData = end($data['data']);
                    $endData['time'] = str_replace('+', '', $endData['time']);
                    $collectTime = strtotime($endData['time']);
                } else {
                    //以时间最大的记录来判断揽收时间
                    foreach ($data['data'] as $v) {
                        //查询子单
                        $subExpress = ExpressModel::getExpressDetail($v['logi_code'], $v['logi_no']);
                        if (empty($subExpress)) {
                           continue;
                        }

                        //子单物流节点
                        $data = unserialize($subExpress['data']);
                        if (empty($data)) {
                            continue;
                        }
                        $endData = end($data['data']);
                        $endData['time'] = str_replace('+', '', $endData['time']);
                        if (strtotime($endData['time']) > $collectTime) {
                            $collectTime = strtotime($endData['time']);
                        }
                    }

                }

                //更新揽收时间
                if ($collectTime > 0) {
                    $update = array(
                        'collect_time' => $collectTime
                    );
                    $res = ExpressModel::updateExpressById($express['id'], $update);
                    $this->info("id-" . $express['id'] . ":" . ($res ? '成功' : '失败'));
                }
            }

            $i ++;
        }

    }

    /**
     * 阻塞获取最新id
     * @param $maxIdKey
     * @return void
     */
    private function getMaxId($maxIdKey) {
        $i = 0;

        //阻塞获取锁,10分钟获取不到直接强制获取
        while ($i < 600) {
            $incr = $this->redisClient->_redis_connection->incr($maxIdKey.'_incr', 1);
            if ($incr == 1) {
                break;
            }
            sleep(1);
            $i ++ ;
        }

        //设置过期时间
        $this->redisClient->_redis_connection->expire($maxIdKey.'_incr', 10);

        //获取最新id
        return $this->redisClient->_redis_connection->get($maxIdKey);
    }

    /**
     * 设置最新id
     * @param $maxIdKey
     * @param $maxId
     * @return void
     */
    private function setMaxId($maxIdKey, $maxId, $expire = 86400) {
        //设置最新的max
        $this->redisClient->_redis_connection->setex($maxIdKey, $expire, $maxId);

        //初始化锁并设置过期时间
        $this->redisClient->_redis_connection->setex($maxIdKey.'_incr', 10, 0);
    }


    private function _getKuaidi100StatusMapping($state)
    {
        $status = ExpressModel::STATUS_EMPTY;

        $expressStatusMsgMapping = array(
            0 => ExpressModel::STATUS_UNDERWAY,
            1 => ExpressModel::STATUS_COLLECT,
            2 => ExpressModel::STATUS_BLOCKED,
            3 => ExpressModel::STATUS_RECEIVED,
            4 => ExpressModel::STATUS_RETURN_RECEIVED,
            5 => ExpressModel::STATUS_DELIVERY,
            6 => ExpressModel::STATUS_RETURN_UNDERWAY,
            7 => ExpressModel::STATUS_FORWARDING,
            8 => ExpressModel::STATUS_CUSTOMS_CLEARANCE,
            14 => ExpressModel::STATUS_REFUSAL,
            200 => ExpressModel::STATUS_EMPTY,
        );

        if (!isset($expressStatusMsgMapping[$state])) {
            return $status;
        }

        return $expressStatusMsgMapping[$state];
    }
    private function _getKuaidi100OldStatusMapping($state)
    {
        $status = 200;

        $expressStatusMsgMapping = array(
           ExpressModel::STATUS_UNDERWAY => 0,
            ExpressModel::STATUS_COLLECT => 1,
            ExpressModel::STATUS_BLOCKED => 2,
            ExpressModel::STATUS_RECEIVED => 3,
            ExpressModel::STATUS_RETURN_RECEIVED => 4,
            ExpressModel::STATUS_DELIVERY => 5,
            ExpressModel::STATUS_RETURN_UNDERWAY => 6,
            ExpressModel::STATUS_FORWARDING => 7,
            ExpressModel::STATUS_CUSTOMS_CLEARANCE => 8,
            ExpressModel::STATUS_REFUSAL => 14,
            ExpressModel::STATUS_EMPTY => 200
        );

        if (!isset($expressStatusMsgMapping[$state])) {
            return $status;
        }

        return $expressStatusMsgMapping[$state];
    }

    private function _checkKuaidi100NeedPull($state) {
        if (in_array($state, array(0, 1, 5))) {
            return ExpressModel::NEED_PULL;
        } else {
            return ExpressModel::NO_NEED_PULL;
        }
    }


}
