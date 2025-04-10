<?php

namespace App\Api\V1\Service\Captcha;

/**
 * 微信小程序验证码验证类
 */
class TencentminiCaptcha extends CaptchaAbstract
{

    /**
     * 腾讯云服务类型-验证码
     * @var string
     */
    private $service = "captcha";
    /**
     * 腾讯云服务域名
     * @var string
     */
    private $host = "captcha.tencentcloudapi.com";
    /**
     * 腾讯云服务地域 当前可置空
     * @var string
     */
    private $req_region = "";

    /**
     * 签名算法
     * @var string
     */
    private $algorithm = "TC3-HMAC-SHA256";

    /**
     * 请求方式
     * @var string
     */
    private $http_request_method = "POST";

    /**
     * 平台参数配置
     * @var array
     */
    private $config;

    /**
     * 初始化配置
     * @param $config
     */
    public function __construct($config)
    {
        $this->config = $config;
    }

    /**
     * 验证验证码
     * @param $params
     * @param $msg
     * @return bool
     */
    public function verifyCode($params, &$msg): bool
    {
        //type 0 图形验证码； 1 滑动验证码； 2 图形点选； 3 文字点选；4 语音验证码；5 语音验证码（滑块）
        if ($this->config['type'] != 1) {
            $msg = '暂不支持该类型验证码';
            return false;
        }

        //1. 验证当前传日的验证码是否正确
        $checkRet = $this->checkParams($params);
        if ($checkRet['code'] != 0) {
            $msg = $checkRet['msg'];
            return false;
        }
        //2. 组合微信小程序验证需要的数据
        $checkCode = false;
        switch ($this->config['type']) {
            case 1:
                $checkCode = $this->checkSlipTypeCode($params);
                break;
        }
        if (!$checkCode) {
            $msg = '验证失败';
            return false;
        }
        return true;
    }

    /**
     * 验证入参
     * @param $params
     * @return array
     */
    public function checkParams($params): array
    {
        if (empty($params['ticket']) || empty($params['client_ip'])) {
            return $this->returnData(-1, '参数为空');
        }
        return $this->returnData(0, '');
    }

    /**
     * 获取签名
     * @param $payload
     * @param $timestamp
     * @return string
     */
    public function getAuthorization($payload, $timestamp): string
    {
        $date = gmdate("Y-m-d", $timestamp);
        // ************* 步骤 1：拼接规范请求串 *************
        $canonical_uri = "/";
        $canonical_querystring = "";
        $ct = "application/json; charset=utf-8";
        $canonical_headers = "content-type:" . $ct . "\nhost:" . $this->host . "\nx-tc-action:" . strtolower($this->config['action']) . "\n";
        $signed_headers = "content-type;host;x-tc-action";
        $hashed_request_payload = hash("sha256", json_encode($payload));
        $canonical_request = "$this->http_request_method\n$canonical_uri\n$canonical_querystring\n$canonical_headers\n$signed_headers\n$hashed_request_payload";
//        \Neigou\Logger::General('TencentCaptcha.DescribeCaptchaMiniResult', array('$canonical_request' => $canonical_request,));

        // ************* 步骤 2：拼接待签名字符串 *************
        $credential_scope = "$date/$this->service/tc3_request";
        $hashed_canonical_request = hash("sha256", $canonical_request);
        $string_to_sign = "$this->algorithm\n$timestamp\n$credential_scope\n$hashed_canonical_request";
//        \Neigou\Logger::General('TencentCaptcha.DescribeCaptchaMiniResult', array('$string_to_sign' => $string_to_sign,));

        // ************* 步骤 3：计算签名 *************
        $secret_date = $this->sign("TC3" . $this->config['secretKey'], $date);
        $secret_service = $this->sign($secret_date, $this->service);
        $secret_signing = $this->sign($secret_service, "tc3_request");
        $signature = hash_hmac("sha256", $string_to_sign, $secret_signing);
//        \Neigou\Logger::General('TencentCaptcha.DescribeCaptchaMiniResult', array('$signature' => $signature,));

        // ************* 步骤 4：拼接 Authorization *************
        return "{$this->algorithm} Credential={$this->config['secretId']}/{$credential_scope}, SignedHeaders={$signed_headers}, Signature={$signature}";

    }

    /**
     * 签名
     * @param $key
     * @param $msg
     * @return string
     */
    public function sign($key, $msg): string
    {
        return hash_hmac("sha256", $msg, $key, true);
    }

    /**
     * 滑块验证码
     * @param $params
     * @return bool
     */
    private function checkSlipTypeCode($params): bool
    {
        $payload = [
            'Ticket' => $params['ticket'],// 票据
            'CaptchaType' => $this->config['captchaType'],// 固定标识
            'CaptchaAppId' => $this->config['captchaAppId'],//小程序验证app_id
            'AppSecretKey' => $this->config['appSecretKey'],//appsecretkey
            'UserIp' => $params['client_ip'],//客户端真实ip
        ];

        $endpoint = "https://" . $this->host;
        $timestamp = time();
        $authorization = $this->getAuthorization($payload, $timestamp);
//        \Neigou\Logger::General('TencentCaptcha.DescribeCaptchaMiniResult', array('$authorization' => $authorization,));

        // ************* 步骤 5：构造并发起请求 *************
        $headers = [
            "Authorization" => $authorization,
            "Content-Type" => "application/json; charset=utf-8",
            "Host" => $this->host,
            "X-TC-Action" => $this->config['action'],
            "X-TC-Timestamp" => $timestamp,
            "X-TC-Version" => $this->config['version'],
        ];
        if ($this->req_region) {
            $headers["X-TC-Region"] = $this->req_region;
        }

        //3. 请求腾讯接口，获取验证结果
        $result = $this->request($endpoint, json_encode($payload), strtolower($this->http_request_method), $headers);
        $result = json_decode($result, true);
        if (isset($result['Response']['CaptchaCode']) && $result['Response']['CaptchaCode'] == 1) {
            return true;
        }
        \Neigou\Logger::General('TencentCaptcha.DescribeCaptchaMiniResult', array('payload' => $payload, 'result' => $result, 'header' => $headers));
        return false;
    }
}
