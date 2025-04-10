<?php

namespace App\Api\V1\Service\Captcha;

class TencentCaptcha extends CaptchaAbstract
{
    private $host = 'captcha.tencentcloudapi.com';
    private $service = 'captcha';
    private $algorithm = 'TC3-HMAC-SHA256';
    private $region = 'ap-beijing';

    /**
     * 平台参数配置
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
     * 查验结果
     * @param $params
     * @return bool
     */
    public function verifyCode($params, &$msg)
    {
        //type 0图形验证码 1滑动验证码 2图形点选 3文字点选
        if ($this->config['type'] != 1) {
            $msg = '暂不支持该类型验证码';
            return false;
        }

        //检查参数
        $checkParams = $this->checkParams($params);
        if ( ! $checkParams['flag']) {
            $msg = $checkParams['msg'];
            return false;
        }

        //滑动验证码
        if ($this->config['type'] == 1) {
            $checkCode = $this->cheackSlipTypeCode($params);
            if ( ! $checkCode) {
                $msg = '验证失败';
                return false;
            }
            return true;
        }

        return false;
    }

    /**
     * 检查参数
     * @param $params
     * @return bool
     */
    private function checkParams($params){
        $data['flag'] = 0;
        if (empty($params['ticket']) || empty($params['rand_str']) || empty($params['client_ip'])) {
            $data['msg'] = '参数为空';
            return $data;
        }

        $data['flag'] = 1;
        return $data;
    }

    //验证滑动类型结果
    private function cheackSlipTypeCode($params)
    {
        $timestamp = time();
        $payload = array(
            'CaptchaType' => $this->config['captchaType'],
            'Ticket' => $params['ticket'],
            'UserIp' => $params['client_ip'],
            'Randstr' => $params['rand_str'],
            'CaptchaAppId' => $this->config['captchaAppId'],
            'AppSecretKey' => $this->config['appSecretKey'],
        );

        $authorization = $this->getAuthorization($payload, $timestamp);
        $header = array(
            "Authorization"=>$authorization,
            "Content-Type"=>"application/json; charset=utf-8",
            "Host"=>$this->host,
            "X-TC-Action"=>$this->config['action'],
            "X-TC-Timestamp"=>$timestamp,
            "X-TC-Version"=>$this->config['version'],
            "X-TC-Region"=>$this->region,
        );

        $result = $this->request('https://' . $this->host, json_encode($payload), 'post', $header);
        \Neigou\Logger::General('TencentCaptcha.cheackSlipTypeCode',array('payload'=>$payload, 'result'=>$result, 'header'=>$header));
        $result = json_decode($result, true);
        if (isset($result['Response']['CaptchaCode']) && $result['Response']['CaptchaCode'] == 1) {
            return true;
        }
        return false;
    }

    //获取令牌
    private function getAuthorization($params, $timestamp, $httpMethod = 'POST', $canonicalUri = '/', $canonicalQueryString = '')
    {
        $header = [
            "content-type:application/json; charset=utf-8",
            "host:" . $this->host,
            "x-tc-action:" . strtolower($this->config['action'])
        ];
        $canonicalHeaders = implode("\n", $header) . "\n";

        $signed = ["content-type", "host", "x-tc-action"];
        $signedHeaders = implode(";", $signed);

        $canonicalRequestArr = [$httpMethod, $canonicalUri, $canonicalQueryString, $canonicalHeaders, $signedHeaders, hash("SHA256", json_encode($params)) ];
        $canonicalRequest = implode("\n", $canonicalRequestArr);

        // step 2: build string to sign
        $timestamp = time();
        $date = gmdate("Y-m-d", $timestamp);
        $credentialScope = $date . "/" . $this->service . "/tc3_request";
        $stringToSignArr = [$this->algorithm, $timestamp, $credentialScope, hash("SHA256", $canonicalRequest)];
        $stringToSign = implode("\n", $stringToSignArr);

        // step 3: sign string
        $secretDate = hash_hmac("SHA256", $date, "TC3".$this->config['secretKey'], true);
        $secretService = hash_hmac("SHA256", $this->service, $secretDate, true);
        $secretSigning = hash_hmac("SHA256", "tc3_request", $secretService, true);
        $signature = hash_hmac("SHA256", $stringToSign, $secretSigning);

        // step 4: build authorization
        return $this->algorithm . " Credential=".$this->config['secretId'] . "/" . $credentialScope . ", SignedHeaders=".$signedHeaders.", Signature=".$signature;
    }

}
