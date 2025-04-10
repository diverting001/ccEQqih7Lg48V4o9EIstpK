<?php
namespace App\Api\V1\Service\Message\Sms;

use App\Api\V1\Service\Message\MessageHandler;

class AliyunSms extends MessageHandler
{
    use SmsTrait;
    const PLATFORM_NAME = "阿里云短信";

    /**
     * 平台对应内部统一状态码
     * @return mixed|\string[][]
     */
    protected function errorMap()
    {
        //url https://help.aliyun.com/knowledge_detail/57717.html?spm=a2c4g.11186623.2.19.297351cdTGiCqE
        return [
            'isp.RAM_PERMISSION_DENY' => ['RAM权限DENY', MessageHandler::CODE_ERROR],
            'isv.OUT_OF_SERVICE' => ['业务停机', MessageHandler::CODE_ERROR],
            'isv.SMS_TEMPLATE_ILLEGAL' => ['短信模版不合法', MessageHandler::CODE_TEMPLATE_ERROR],
            'isv.TEMPLATE_PARAMS_ILLEGAL' => ['模版变量里包含非法关键字	', MessageHandler::CODE_TEMPLATE_ERROR],
            'isv.TEMPLATE_MISSING_PARAMETERS' => ['模版缺少变量', MessageHandler::CODE_TEMPLATE_ERROR],
            'isv.SMS_SIGNATURE_ILLEGAL' => ['短信签名不合法', MessageHandler::CODE_SIGN_ERROR],
            'isv.INVALID_PARAMETERS' => ['参数异常', MessageHandler::CODE_FIELD_FAIL],
            'isp.SYSTEM_ERROR' => ['isp.SYSTEM_ERROR', MessageHandler::CODE_FIELD_FAIL],
            'isv.MOBILE_NUMBER_ILLEGAL' => ['非法手机号', MessageHandler::CODE_FIELD_FAIL],
            'isv.MOBILE_COUNT_OVER_LIMIT' => ['手机号码数量超过限制', MessageHandler::CODE_FIELD_FAIL],
            'isv.BUSINESS_LIMIT_CONTROL' => ['业务限流', MessageHandler::CODE_ERROR],
            'isv.INVALID_JSON_PARAM' => ['JSON参数不合法，只接受字符串值', MessageHandler::CODE_FIELD_FAIL],
            'isv.BLACK_KEY_CONTROL_LIMIT' => ['黑名单管控', MessageHandler::CODE_ERROR],
            'isv.PARAM_LENGTH_LIMIT' => ['参数超出长度限制	', MessageHandler::CODE_FIELD_FAIL],
            'isv.PARAM_NOT_SUPPORT_URL' => ['不支持URL', MessageHandler::CODE_FIELD_FAIL],
            'isv.AMOUNT_NOT_ENOUGH' => ['账户余额不足', MessageHandler::CODE_BALANCE_ERROR],

        ];
    }

    /**
     * 发送消息接口
     * @param  string  $receiver
     * @param  object  $messageTemplate
     * @param $item
     * @return AliyunSms|array
     */
    protected function send($receiver, $messageTemplate, $item)
    {
        return $this->sendLogic($receiver, $messageTemplate, $item);
    }

    public function sendLogic($receiver, $messageTemplate, $item, $action = 'SendSms')
    {
        if ($action === 'SendSms') {
            $send_data = $this->getSign($receiver, $messageTemplate->template_data, $item['template_param'], $action);
        } else {
            $send_data = $this->getSignBath(
                $receiver,
                $messageTemplate->template_data,
                $item['template_param'],
                $action
            );
        }
        $result = $this->httpClient($this->config['api_url'], $send_data, 'get');
        $result = json_decode($result, true);

        if (isset($result['Code'], $result['Message']) && $result['Code'] === 'OK' && $result['Message'] === 'OK') {
            return $this->response(self::CODE_SUCCESS, $result);
        }

        return $this->response($result['Code'], $result);
    }


    public function batchSend($mobiles, $messageTemplate, $item)
    {
        return $this->sendLogic($mobiles, $messageTemplate, $item, 'SendBatchSms');
    }
    /**
     * 签名
     * @param $mobile
     * @param $template_id
     * @param $send_params
     * @return array
     */
    private function getSign($mobile, $template_id, $send_params, $action)
    {
        $data = array(
            'AccessKeyId' => $this->config['access_key_id'],
            'Action' => $action,
            'PhoneNumbers' => $mobile,
            'SignName' => $this->config['sign_name'],
            'SignatureMethod' => 'HMAC-SHA1',
            'SignatureNonce' => md5(strtotime('-8 hours') + rand(100000, 999999)),
            'SignatureVersion' => '1.0',
            'TemplateCode' => $template_id,
            'TemplateParam' => empty($send_params) ? '{}' : json_encode($send_params),
            'Timestamp' => date('Y-m-d\TH:i:s\Z', strtotime('-8 hours')),
            'Version' => '2017-05-25',
            'Format' => 'json'
        );

        $data['Signature'] = self::sign($data, $this->config['access_key_secret']);

        return $data;
    }

    private function getSignBath($mobile, $template_id, $send_params, $action)
    {
        $num = count($mobile);
        $send_params = array_fill(0, $num, $send_params);
        $sign_name = array_fill(0, $num, $this->config['sign_name']);
        $data = array(
            'AccessKeyId' => $this->config['access_key_id'],
            'Action' => $action,
            'PhoneNumberJson' => json_encode($mobile),
            'SignNameJson' => json_encode($sign_name),
            'SignatureMethod' => 'HMAC-SHA1',
            'SignatureNonce' => md5(strtotime('-8 hours') + rand(100000, 999999)),
            'SignatureVersion' => '1.0',
            'TemplateCode' => $template_id,
            'templateParamJson' => empty($send_params) ? '{}' : json_encode($send_params),
            'Timestamp' => date('Y-m-d\TH:i:s\Z', strtotime('-8 hours')),
            'Version' => '2017-05-25',
            'Format' => 'json'
        );

        $data['Signature'] = self::sign($data, $this->config['access_key_secret']);

        return $data;
    }

    private static function sign($parameters, $accessKeySecret)
    {
        ksort($parameters);

        $canonicalizedQueryString = '';

        foreach ($parameters as $key => $value) {
            $canonicalizedQueryString .= '&' . self::percentEncode($key) . '=' . self::percentEncode($value);
        }
        $stringToSign = 'GET&%2F&' . self::percentencode(substr($canonicalizedQueryString, 1));
        return base64_encode(hash_hmac('sha1', $stringToSign, $accessKeySecret . '&', true));
    }

    private static function percentEncode($str)
    {
        $res = urlencode($str);
        $res = preg_replace('/\+/', '%20', $res);
        $res = preg_replace('/\*/', '%2A', $res);
        $res = preg_replace('/%7E/', '~', $res);
        return $res;
    }
}
