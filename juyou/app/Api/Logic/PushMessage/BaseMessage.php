<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-05-14
 * Time: 21:45
 */

namespace App\Api\Logic\PushMessage;


use App\Api\Common\Common;
use Neigou\Logger;

class BaseMessage
{
    /**
     * 订阅系统标示
     * @var string
     */
    protected $systemCode = '';

    protected $sonProcess = '';

    /**
     * 接收推送的url
     * @var string
     */
    protected $url = '';

    /**
     * 请求超时时间
     * @var int
     */
    protected $overtime = 30;

    /**
     * 请求重试次数
     * @var int
     */
    protected $retryCount = 0;

    /**
     * 重试等待
     * @var int
     */
    protected $retryWait = 10;

    /**
     * 认证类型
     * @var string
     */
    protected $sign_type = 'md5';

    /**
     * 认证配置
     * @var array
     */
    protected $sign_config = [];

    /**
     * 消息过滤配置
     * @var array
     */
    protected $message_filter = [];


    /**
     * @param string $systemCode
     */
    public function setSonProcess(string $sonProcess)
    {
        $this->sonProcess = $sonProcess;
    }

    /**
     * @param string $systemCode
     */
    public function serSystemCode(string $systemCode)
    {
        $this->systemCode = $systemCode;
    }

    /**
     * @param string $url
     */
    public function setUrl(string $url)
    {
        $this->url = $url;
    }

    /**
     * @param int $overtime
     */
    public function setOvertime(int $overtime)
    {
        $this->overtime = $overtime;
    }

    /**
     * @param int $retryCount
     */
    public function setRetryCount(int $retryCount)
    {
        $this->retryCount = $retryCount;
    }

    /**
     * @param int $retryWait
     */
    public function setRetryWait(int $retryWait)
    {
        $this->retryWait = $retryWait;
    }

    /**
     * @param string $signType
     */
    public function setSignType(string $signType)
    {
        $this->sign_type = $signType;
    }

    /**
     * @param array $sign_config
     */
    public function setSignConfig(array $sign_config)
    {
        $this->sign_config = $sign_config;
    }

    /**
     * @param array $message_filter
     */
    public function setMessageFilter(array $message_filter)
    {
        $this->message_filter = $message_filter;
    }

    /**
     *
     */
    protected function pushMessage($data)
    {
        switch (strtolower($this->sign_type)) {
            case 'md5':
                return $this->pushMd5Message($data);
            default:
                return [
                    "Result" => "false",
                    "ErrorId" => "SIGN_TYPE_ERROR",
                    "ErrorMsg" => "加密类型配置错误",
                    "Data" => ""
                ];

        }
    }

    /**
     * 已md5加密 并推送消息
     * @param $data
     */
    private function pushMd5Message($data)
    {
        $sendData = [
            'message_type' => $data['message_type'],
            'message_data' => $data['message_data'],
            'send_time' => time()
        ];
        if (!$sendData['message_type'] || !$sendData['message_data']) {
            return [
                "Result" => "false",
                "ErrorId" => "SEND_DATA_ERROR",
                "ErrorMsg" => "请求参数错误",
                "Data" => $data
            ];
        }

        $sign = isset($this->sign_config['sign']) ? $this->sign_config['sign'] : '';
        if (!$sign) {
            return [
                "Result" => "false",
                "ErrorId" => "SIGN_CONFIG_ERROR",
                "ErrorMsg" => "加密配置sign错误",
                "Data" => ""
            ];
        }

        $token = Common::GetCommonSign($sendData, $sign);
        $sendData['sign'] = $token;
        $curl = new \Neigou\Curl();
        $curl->SetHeader('Accept', 'application/json');
        $curl->SetHeader('Content-Type', 'application/json');
        $resultStr = $curl->Post($this->url, json_encode($sendData));
        $result = json_decode($resultStr, true);

        Logger::Debug('push_message_info', [
            'request_uri' => $this->url,
            'request_params' => $sendData,
            'response_result' => $result
        ]);

        if (!isset($result['Result']) || $result['Result'] != 'true') {
            Logger::General('push_message_error', [
                'request_uri' => $this->url,
                'request_params' => $sendData,
                'response_result' => $result
            ]);
        }
        return $result;
    }

    /**
     * 尝试重试
     */
    protected function attemptRetryPush($sendData)
    {
        if ($this->retryCount > 0) {
            do {
                if ($this->retryWait > 0) {
                    sleep($this->retryWait);
                }

                $result = $this->pushMessage($sendData);
                if (isset($result['Result']) && $result['Result'] == 'true') {
                    echo '重试处理成功:send_data:' . json_encode($sendData) . '\r\n';
                    return true;
                } else {
                    echo '重试处理失败:send_data:' . json_encode($sendData) . '\r\n';

                }
                $this->retryCount--;
            } while ($this->retryCount >= 1);
        }
        return false;
    }

}
