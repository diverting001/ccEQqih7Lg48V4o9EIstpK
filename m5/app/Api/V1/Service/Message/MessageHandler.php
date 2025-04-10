<?php

namespace App\Api\V1\Service\Message;

use App\Api\Model\Message\Channel;
use App\Api\Model\Message\MessageBlacklist;
use App\Api\Model\Message\MessageTemplate;
use \App\Api\Model\Message\MessageLog as MessageLogModel;

/**
 * 消息通知基类
 * @author ly
 */
abstract class MessageHandler
{
    /**
     * @var string 平台名
     */
    public const PLATFORM_NAME = '';
    /**
     * 各类错误码
     */
    public const CODE_SUCCESS = 0;
    public const CODE_FIELD_FAIL = 401;
    public const CODE_ERROR = 500;
    public const CODE_TEMPLATE_ERROR = 501;
    public const CODE_SIGN_ERROR = 502;
    public const CODE_BALANCE_ERROR = 503;
    public const CODE_BLACK_ERROR = 504;
    /**
     * @var string[] 系统内标准状态码
     */
    public $status_code = [
        self::CODE_SUCCESS => '请求成功',
        self::CODE_FIELD_FAIL => '参数错误',
        self::CODE_ERROR => '请求失败',
        self::CODE_TEMPLATE_ERROR => '模板错误',
        self::CODE_SIGN_ERROR => '签名不合法',
        self::CODE_BALANCE_ERROR => '余额不足',
        self::CODE_BLACK_ERROR => '接收人黑名单'
    ];
    /**
     * @var array 平台参数配置
     */
    protected $config;
    /**
     * @var int 平台id
     */
    protected $platformId;
    /**
     * @var array 接口请求的消息数据
     */
    protected $data;
    /**
     * @var int 批次id
     */
    protected $batchId;
    /**
     * @var int id 渠道id
     */
    protected $channelId;
    /**
     * @var int retry 失败重试次数
     */
    protected $retry = 1;
    /**
     * @var boolean 是否记录log
     */
    private $log = false;
    /**
     * @var boolean 返回结果是否同步
     */
    public $syncResult = true;
    /**
     * 初始化
     * MessageHandler constructor.
     * @param $param
     */
    public function __construct($param)
    {
        $this->data = $param['data'];
        $this->setConfig($param['channel']);
    }

    public function setBatchId($batchId)
    {
        $this->batchId = $batchId;
    }
    /**
     * 平台对应错误码解析
     * @param int platform 平台状态码
     * @param string info 平台状态码描述
     * @param int code 内部状态码
     * @return array  [
     *                  'platform' => ['info', 'code'],
     *                  '2010' => ['三方错误码详情', '201']
     *                 ]
     */
    abstract protected function errorMap();

    /**
     * 发送消息
     * @param $receiver mixed 接收人
     * @param $templateRealData object  模板数据or模板id
     * @param $params
     * @return  $this->response()  返回值必须调用此方法
     */
    abstract protected function send($receiver, $templateRealData, $params);

    /**
     * 批量发送消息
     * @param $receivers array 接收人多个
     * @param $templateRealData object  模板数据or模板id
     * @param $params
     * @return  $this->response()  返回值必须调用此方法
     */
    abstract protected function batchSend($receivers, $templateRealData, $params);

    /**
     * 多平台data参数检查
     * @param $param
     * @return mixed
     */
    abstract public function checkChildParam($param);

    /**
     * 初始化配置
     * @param $channel
     */
    private function setConfig($channel)
    {
        $channelModel = new Channel();
        $platform = $channelModel->findPlatform($channel);
        $this->channelId = $platform->id;
        $this->platformId = $platform->platform_id;
        $this->config = json_decode($platform->platform_config, true);
    }
    /**
     * 拆分即将处理的消息
     */
    public function sendMessage()
    {
        MessageLog::batch($this->batchId, MessageLogModel::SEND_STATUS_ING);
        foreach ($this->data as $key => $item) {
            #模板数据
            $templateModel = new MessageTemplate();
            $messageTemplate = $templateModel->getPlatformTemplate($item['template_id'], $this->channelId);
            #模板参数映射接口参数
            $this->templateMapping($item['template_param'], $messageTemplate);
            if (count($item['receiver']) > 1 && method_exists($this, 'batchSend')) {
                $result = $this->batchRecord($item, $messageTemplate);
            } else {
                $result = $this->singleRecord($item, $messageTemplate);
            }
            if ($this->log) {
                \Neigou\Logger::General('messageSend.fail', array(
                    'batchId' => $this->batchId,
                    'result' => $result,
                    'data' => $this->data
                ));
            }
        }
        MessageLog::batch(
            $this->batchId,
            $this->syncResult ? MessageLogModel::SEND_STATUS_SUCCESS : MessageLogModel::SEND_STATUS_SYNC_RESULT_ING
        );
    }
    /**
     *  内购模板参数映射第三方接口参数
     */
    public function templateMapping(&$param, &$messageTemplate)
    {
        if (!empty($messageTemplate->param_mapping)) {
            foreach ($messageTemplate->param_mapping as $paramKey => $iParamKey) {
                if (isset($param[$paramKey])) {
                    $value = $param[$paramKey];
                    unset($param[$paramKey]);
                    $param[$iParamKey] = $value;
                }
            }
            $messageTemplate->param = array_values($messageTemplate->param_mapping);
        }
    }

    private function record($method, $receiver, $item, $messageTemplate)
    {
        #过滤黑名单人员
        $blackList = new MessageBlacklist();
        if ($blackList->blacklistExists($receiver)) {
            return ['result' => [], 'status' => MessageLogModel::SEND_STATUS_FAIL, 'error' => self::CODE_BLACK_ERROR];
        }
        //增加重试机制失败重试一次
        $result = $return = [];
        $retry = $this->retry;
        while ($retry > 0 && (empty($result) || $result['code'] !== self::CODE_SUCCESS)) {
            $result = static::$method($receiver, $messageTemplate, $item);
            $retry--;
        }
        //单条消息
        if (isset($result['sendBatch'])) {
            if (is_array($receiver)) {
                $newResult = [];
                foreach ($receiver as $re) {
                    $newResult[$re] = $result;
                }
                $result = $newResult;
            } else {
                $result = [$receiver => $result];
            }
        }
        //批量消息
        foreach ($result as $receive => $receiverResult) {
            $return[$receive] = $this->resultMapping($receiverResult);
            if ($return[$receive]['status'] === MessageLogModel::SEND_STATUS_FAIL) {
                $this->log = true;
            }
        }
        return $return;
    }

    private function resultMapping($result)
    {
        return [
            'status' => $result['code'] === self::CODE_SUCCESS ?
                MessageLogModel::SEND_STATUS_SUCCESS : MessageLogModel::SEND_STATUS_FAIL,
            'error' => $result['code'] === self::CODE_SUCCESS ?
                self::CODE_SUCCESS : $this->errorCodeMatch($result['code']),
            'sendBatch' => $result['sendBatch'],
            'result' => $result
        ];
    }
    /**
     * 批量消息调用
     * @param $item
     * @param $messageTemplate
     */
    private function batchRecord($item, $messageTemplate)
    {
        foreach ($item['receiver'] as $receiver) {
            MessageLog::info($this->batchId, $receiver, $item['template_id'], MessageLogModel::SEND_STATUS_ING);
        }
        $result = $this->record('batchSend', $item['receiver'], $item, $messageTemplate);
        //调用多次返回值
        foreach ($result as $receiver => $receiverResult) {
            if (!$this->syncResult) {
                $receiverResult['status'] = MessageLogModel::SEND_STATUS_ING;
            }
            MessageLog::info(
                $this->batchId,
                $receiver,
                $item['template_id'],
                $receiverResult['status'],
                $receiverResult['sendBatch'],
                json_encode($receiverResult['result']),
                $receiverResult['error']
            );
        }
        return $result;
    }
    /**
     * 单一消息调用
     * @param $item
     * @param $messageTemplate
     */
    private function singleRecord($item, $messageTemplate)
    {
        foreach ($item['receiver'] as $type => $receiver) {
            $receiverAll = $receiver;
            MessageLog::info($this->batchId, $receiver, $item['template_id'], MessageLogModel::SEND_STATUS_ING);
            //todo 单一的会有多个？软通
            if (is_array($receiver)) {
                $receiver = current($receiver);
            }
            $result = $this->record('send', $receiverAll, $item, $messageTemplate);
            if (!$this->syncResult) {
                $result['status'] = MessageLogModel::SEND_STATUS_ING;
            }
            MessageLog::info(
                $this->batchId,
                $receiver,
                $item['template_id'],
                $result[$receiver]['status'],
                $result[$receiver]['sendBatch'],
                json_encode($result[$receiver]['result']),
                $result[$receiver]['error']
            );
        }
        return $result;
    }

    /**
     * 检测接口参数
     * @return mixed  返回true为效验成功 必须 ===
     */
    public function checkParam()
    {
        $data = $this->data;
        if (empty($data)) {
            return '发送内容参数不全';
        }
        foreach ($data as $val) {
            if (!is_array($val['receiver']) || empty($val['receiver'])) {
                return 'receiver参数错误';
            }
            if (empty($val['template_id'])) {
                return 'template_id参数错误';
            }
            $templateModel = new MessageTemplate();
            $messageTemplate = $templateModel->getPlatformTemplate($val['template_id'], $this->channelId);
            if (empty($messageTemplate)) {
                return '当前渠道下模板id不存在';
            }
            $param = json_decode($templateModel->firstTemplate($val['template_id'])->param, true);
            if ($param[0] !== '_all_' && !empty($param) && !empty(collect($param)->diff(collect(array_keys($val['template_param'])))->all())
            ) {
                return 'template_param参数错误';
            }

            $childResult = static::checkChildParam($val);
            if ($childResult !== true) {
                return $childResult;
            }
        }
        return true;
    }

    /**
     * 外部状态码转换
     * @param $code
     * @return string
     */
    protected function errorCodeMatch($code)
    {
        return static::errorMap()[$code][1] ?? self::CODE_ERROR;
    }

    protected function response($code, $result)
    {
        return [
            'code' => $code, //解析后状态码
            'result' => $result,
            'sendBatch' => uniqid()
        ];
    }

    protected function httpClient($http_url, $data, $method = 'post', $header = [])
    {
        $curl = new \Neigou\Curl();
        if (!empty($header)) {
            $curl->SetHeader($header);
        }
        if ($method === 'post') {
            return $curl->Post($http_url, $data);
        }

        return $curl->Get($http_url, $data);
    }
}
