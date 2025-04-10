<?php
/**
 * @author zhangjian 18604419839@163.com
 */
namespace App\Api\Model\DaemonTask;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class DaemonTask extends Model
{
    const STATUS_INIT                = 1; // 初试状态
    const STATUS_WAITING_JOIN_MQ     = 2; // 待入列
    const STATUS_WAITING_RUN         = 3; // 待执行
    const STATUS_RUNNING             = 4; // 执行中
    const STATUS_SUCCESS             = 5; // 成功
    const STATUS_FAILED              = 6; // 失败

    const THE_LAST_TIME_UNKNOWN     = 1;
    const THE_LAST_TIME_YES         = 2;
    const THE_LAST_TIME_NO          = 3;

    const MQ_QUEUE_NAME     = 'service.daemon.task.consume';
    const MQ_EXCHANGE_NAME  = 'service';
    const MQ_ROUTING_KEY    = 'service.daemon.task';

    const REDIS_KEY_PREFIX = 'service_daemon_task_consume_';

    protected $originalData     = null;
    protected $theLastTime      = null;
    protected $model            = null;
    protected $connection       = 'mysql';
    protected $table            = 'server_daemon_task';
    protected $primaryKey       = 'id';

    protected $id                   = null;
    protected $taskName             = '';
    protected $taskAddress          = '';
    protected $taskParameters       = array();
    protected $runTime              = 0;
    protected $runNumber            = 0;
    protected $runMaxNumber         = 0;
    protected $intervalTime         = 0;
    protected $estimateMaxTime      = 0;
    protected $estimateOverTime     = 0;
    protected $resultset            = array();
    protected $recordOutput         = array();
    protected $addTime              = 0;
    protected $updateTime           = 0;
    protected $status               = 0;

    public function __construct()
    {
        $this->model = app('api_db')->connection($this->connection)->table($this->table);
    }

    public function connection()
    {
        $this->model = app('api_db')->connection($this->connection)->table($this->table);
        return $this->model;
    }

    public function model()
    {
        return $this->connection();
    }

    /**
     * 获取所有状态值的数组
     * @return array
     */
    public static function getAllStatus()
    {
        return array(
            self::STATUS_INIT,
            self::STATUS_WAITING_JOIN_MQ,
            self::STATUS_WAITING_RUN,
            self::STATUS_RUNNING,
            self::STATUS_SUCCESS,
            self::STATUS_FAILED,
        );
    }

    /**
     * @param string $taskName                  名称    e.g. order.yc_settlement.order_refund
     * @param string $taskAddress               地址    e.g. \App\Api\V1\Controllers\OrderGatherController->ycSettlementOrderRefund
     * @param array  $taskParameters            参数
     * @param int    $runTime                   执行时间（时间戳），默认当前时间，立即执行
     * @param int    $runMaxNumber              最大执行次数，默认1次
     * @param int    $intervalTime              间隔时间（单位秒），默认10秒
     * @param int    $estimateMaxTime           预计最大执行时间（超时时间，单位秒），默认10秒
     * @param bool   $updateStatusWaitingJoinMQ 直接修改状态为待入列
     * 
     * @return int|null 成功返回自增主键，失败返回null
     */
    public function addDaemonTask(
        $taskName, $taskAddress, $taskParameters = array(), $runTime = null, $runMaxNumber = 1, $intervalTime = 10,
        $estimateMaxTime = 10, $updateStatusWaitingJoinMQ = false
    ) {
        if ($runTime === null) {
            $runTime = time();
        }
        $this->dataInit();
        $addData = $this->getData();
        $addData['task_name']           = trim($taskName);
        $addData['task_address']        = trim($taskAddress);
        $addData['task_parameters']     = (array)$taskParameters;
        $addData['run_time']            = $runTime;
        $addData['run_max_number']      = $runMaxNumber;
        $addData['interval_time']       = $intervalTime;
        $addData['estimate_max_time']   = $estimateMaxTime;
        $addData['status']              = $updateStatusWaitingJoinMQ === true ? self::STATUS_WAITING_JOIN_MQ : self::STATUS_INIT;
        $this->dataOverwrite($addData);
        $this->saveData();

        return $this->getID();
    }

    /**
     * 数据初始化
     */
    public function dataInit()
    {
        $this->id                   = null;
        $this->taskName             = '';
        $this->taskAddress          = '';
        $this->taskParameters       = array();
        $this->runTime              = 0;
        $this->runNumber            = 0;
        $this->runMaxNumber         = 0;
        $this->intervalTime         = 0;
        $this->estimateMaxTime      = 0;
        $this->estimateOverTime     = 0;
        $this->resultset            = array();
        $this->recordOutput         = array();
        $this->addTime              = 0;
        $this->updateTime           = 0;
        $this->status               = 0;

        return $this;
    }

    /**
     * 获取主键ID的值
     * @return int
     */
    public function getID()
    {
        return $this->id;
    }

    /**
     * 获取当前的状态
     * @return int
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * 获取待执行状态的值
     * @return int
     */
    public function getStatusWaitingRun()
    {
        return self::STATUS_WAITING_RUN;
    }

    /**
     * @return array
     */
    public function getData()
    {
        $data = array(
            'id'                    => $this->id,
            'task_name'             => $this->taskName,
            'task_address'          => $this->taskAddress,
            'task_parameters'       => $this->taskParameters,
            'run_time'              => $this->runTime,
            'run_number'            => $this->runNumber,
            'run_max_number'        => $this->runMaxNumber,
            'interval_time'         => $this->intervalTime,
            'estimate_max_time'     => $this->estimateMaxTime,
            'estimate_over_time'    => $this->estimateOverTime,
            'resultset'             => $this->resultset,
            'record_output'         => $this->recordOutput,
            'add_time'              => $this->addTime,
            'update_time'           => $this->updateTime,
            'status'                => $this->status,
        );
        if ($this->id === null) {
            unset($data['id']);
        }

        return $data;
    }

    /**
     * 获取原始数据
     * @return array
     */
    public function getOriginalData()
    {
        return $this->originalData;
    }

    /**
     * 是否为最后一次
     * @return string
     */
    public function isTheLastTime()
    {
        if ($this->theLastTime === true) {
            return self::THE_LAST_TIME_YES;
        }
        else if ($this->theLastTime === false) {
            return self::THE_LAST_TIME_NO;
        }
        else {
            return self::THE_LAST_TIME_UNKNOWN;
        }
    }

    /**
     * @param array $data
     */
    public function dataOverwrite(array $data)
    {
        if (!empty($data['id']) && is_numeric($data['id'])) {
            $this->id = (int)$data['id'];
        }
        $this->taskName             = !empty($data['task_name']) ? trim($data['task_name']) : '';
        $this->taskAddress          = !empty($data['task_address']) ? trim($data['task_address']) : '';
        $this->taskParameters       = !empty($data['task_parameters']) ? (array)$data['task_parameters'] : array();
        $this->runTime              = isset($data['run_time']) ? (int)$data['run_time'] : 0;
        $this->runNumber            = isset($data['run_number']) ? (int)$data['run_number'] : 0;
        $this->runMaxNumber         = isset($data['run_max_number']) ? (int)$data['run_max_number'] : 0;
        $this->intervalTime         = isset($data['interval_time']) ? (int)$data['interval_time'] : 0;
        $this->estimateMaxTime      = isset($data['estimate_max_time']) ? (int)$data['estimate_max_time'] : 0;
        $this->estimateOverTime     = isset($data['estimate_over_time']) ? (int)$data['estimate_over_time'] : 0;
        $this->resultset            = !empty($data['resultset']) ? (array)$data['resultset'] : array();
        $this->recordOutput         = !empty($data['record_output']) ? (array)$data['record_output'] : array();
        $this->addTime              = isset($data['add_time']) ? (int)$data['add_time'] : 0;
        $this->updateTime           = isset($data['update_time']) ? (int)$data['update_time'] : 0;
        $this->status               = isset($data['status']) && in_array((int)$data['status'] , self::getAllStatus()) ? (int)$data['status'] : 0;

        return $this;
    }

    /**
     * @return array $data
     */
    private function _arrayField2json(array $data)
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = json_encode($value);
            }
        }
        return $data;
    }

    /**
     * @return array $data
     */
    private function _jsonField2array(array $data)
    {
        if (!empty($data['task_parameters'])) {
            $data['task_parameters'] = json_decode($data['task_parameters'] , true);
        }
        if (!empty($data['resultset'])) {
            $data['resultset'] = json_decode($data['resultset'] , true);
        }
        if (!empty($data['record_output'])) {
            $data['record_output'] = json_decode($data['record_output'] , true);
        }
        return $data;
    }

    /**
     * @return array $data
     */
    private function _computationTime(array $data)
    {
        $data['estimate_over_time'] =
            $data['run_time'] +
            ($data['run_max_number'] - $data['run_number'] - 1) * $data['interval_time'] +
            ($data['run_max_number'] - $data['run_number']) * $data['estimate_max_time'];

        return $data;
    }

    /**
     * @param array $where 额外的条件（更新）
     * @return bool
     */
    public function saveData(array $where = array())
    {
        $data = $this->getData();
        $data = $this->_arrayField2json($data);
        $data = $this->_computationTime($data);
        if (!empty($this->id)) {
            $this->updateTime = time();
            $data['update_time'] = $this->updateTime;
            unset($data['id']);
            $where[] = array('id', '=', $this->id);
            $result = $this->connection()->where($where)->update($data);

            return $result === 1 ? true : false;
        }
        else if (empty($where)) {
            $this->addTime = $this->updateTime = time();
            $data['add_time'] = $this->addTime;
            $data['update_time'] = $this->updateTime;
            $result = $this->connection()->insertGetId($data);
            $this->id = is_numeric($result) ? (int)$result : null;

            return is_int($this->id) ? true : false;
        }
        else {
            return false;
        }
    }

    /**
     * @param array $where
     */
    public function findData(array $where)
    {
        $result = $this->connection()->where($where)->first();
        if (!empty($result) && is_object($result)) {
            $result = get_object_vars($result);
            $result = $this->_jsonField2array($result);
            $this->originalData = $result;
            $this->dataOverwrite($result);
        }

        return $this;
    }

    /**
     * @param array $where
     * @return array
     */
    public function selectData(array $where)
    {
        $result = $this->connection()->where($where)->get()->toArray();
        $result = $this->_object2Array($result);

        return $result;
    }

    private function _object2Array($data) {
        if (!empty($data)) {
            foreach ($data as $key => $value) {
                if (is_object($value)) {
                    $data[$key] = get_object_vars($value);
                }
            }
        }
        return $data;
    }

    /**
     * @return array
     */
    public function tableData2MQ()
    {
        $time = time();
        $statusWaitingJoinMq = self::STATUS_WAITING_JOIN_MQ;
        $statusWaitingRun    = self::STATUS_WAITING_RUN;
        $result = DB::select("SELECT *
                FROM `server_daemon_task`
                WHERE `run_time` <= {$time}
                AND `status` = {$statusWaitingJoinMq}
                UNION
                SELECT *
                FROM `server_daemon_task`
                WHERE `run_time` <= {$time}
                AND `status` = {$statusWaitingRun}
                AND `estimate_over_time` <= {$time}
            ");
        if (!empty($result) && is_array($result)) {
            $return = array();
            foreach ($result as $key => $value) {
                $this->dataInit();
                $temp = $this->_jsonField2array((array)$value);
                $return[] = $this->dataOverwrite($temp)->getData();
            }
            return $return;
        }
        else {
            return array();
        }
    }

    /**
     * @return bool
     */
    public function updateStatusWaitingJoinMQ()
    {
        $where   = array();
        $where[] = array('status', '=', self::STATUS_INIT);
        $where[] = array('update_time', '=', $this->updateTime);
        $this->status = self::STATUS_WAITING_JOIN_MQ;

        return $this->saveData($where);
    }

    /**
     * @return bool
     */
    public function updateStatusWaitingRun()
    {
        $where   = array();
        $where[] = array('status', '=', self::STATUS_WAITING_JOIN_MQ);
        $where[] = array('update_time', '=', $this->updateTime);
        $this->status = self::STATUS_WAITING_RUN;

        return $this->saveData($where);
    }

    /**
     * @return bool
     */
    public function updateRunTime()
    {
        $where   = array();
        $where[] = array('status', '=', self::STATUS_WAITING_RUN);
        $where[] = array('update_time', '=', $this->updateTime);
        $this->runTime = time();

        return $this->saveData($where);
    }

    /**
     * @return bool
     */
    public function updateStatusSuccess()
    {
        $redis = $this->_redisConnect();
        $redisKey = self::REDIS_KEY_PREFIX . $this->id;
        $redis->del($redisKey);

        $where   = array();
        $where[] = array('status', '=', self::STATUS_RUNNING);
        $where[] = array('update_time', '=', $this->updateTime);
        $this->status = self::STATUS_SUCCESS;

        return $this->saveData($where);
    }

    /**
     * @return bool
     */
    public function updateStatusFailed()
    {
        $redis = $this->_redisConnect();
        $redisKey = self::REDIS_KEY_PREFIX . $this->id;
        $redis->del($redisKey);

        $where   = array();
        $where[] = array('update_time', '=', $this->updateTime);
        $this->status = self::STATUS_FAILED;

        return $this->saveData($where);
    }

    /**
     * @return bool
     */
    public function updateStatusRunning()
    {
        $where   = array();
        $where[] = array('status', '=', self::STATUS_WAITING_RUN);
        $where[] = array('run_number', '=', $this->runNumber);
        $where[] = array('update_time', '=', $this->updateTime);
        $this->status = self::STATUS_RUNNING;
        $this->runNumber++;
        if ($this->runNumber === $this->runMaxNumber) {
            $this->theLastTime = true;
        }
        else {
            $this->theLastTime = false;
        }

        return $this->saveData($where);
    }

    /**
     * 连接Redis
     */
    private function _redisConnect()
    {
        $server     = PSR_REDIS_WEB_HOST;
        $port       = PSR_REDIS_WEB_PORT;
        $timeout    = 1;
        $auth       = PSR_REDIS_WEB_PWD;

        $redis = new \Redis();
        $result = $redis->connect($server, $port, $timeout);
        if (!$result) {
            $this->recordOutput('Redis连接失败', array('result' => $result));
            return (object)array();
        }
        $result = $redis->auth($auth);
        if (!$result) {
            $this->recordOutput('Redis密码错误', array('result' => $result));
            return (object)array();
        }
        $result = $redis->ping();
        if ($result !== true) {
            $this->recordOutput('检查Redis连接失败', array('result' => $result));
            return (object)array();
        }

        return $redis;
    }

    /**
     * 唯一消费者校验
     * @return bool
     */
    public function uniqueConsumerCheck()
    {
        $redis = $this->_redisConnect();
        $redisKey = self::REDIS_KEY_PREFIX . $this->id;
        $res = $redis->incr($redisKey);
        if ($res === 1) {
            $redis->expire($redisKey, $this->estimateMaxTime);
        }

        return $res === 1 ? true : false; 
    }

    /**
     * @param string $msg
     * @param array $data
     * @return bool
     */
    public function recordOutput(string $msg = null, array $data = array())
    {
        $where   = array();
        $where[] = array('update_time', '=', $this->updateTime);
        $this->recordOutput[] = array(
            'msg'  => trim($msg),
            'data' => (array)$data
        );

        return $this->saveData($where);
    }

    /**
     * 记录结果
     * @param array $data
     * @return bool
     */
    public function pushResultset(array $data = array())
    {
        $where   = array();
        $where[] = array('status', '=', self::STATUS_RUNNING);
        $where[] = array('update_time', '=', $this->updateTime);
        $this->resultset[] = (array)$data;

        return $this->saveData($where);
    }

    /**
     * 重试
     * @return bool
     */
    public function tryAgain()
    {
        $where   = array();
        $where[] = array('status', '=', self::STATUS_RUNNING);
        $where[] = array('update_time', '=', $this->updateTime);
        $this->runTime = time() + $this->intervalTime;
        $this->status  = self::STATUS_WAITING_JOIN_MQ;

        return $this->saveData($where);
    }

}
