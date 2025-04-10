<?php

namespace App\Api\V1\Service\Captcha;

abstract class CaptchaAbstract
{
    abstract function verifyCode($params, &$msg);

    public function request($http_url, $data, $method = 'post', $header = [])
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

    public function returnData($code = 0, $msg = '', $data = []): array
    {
        return array(
            'code' => $code,
            'msg'  => $msg,
            'data' => $data
        );
    }
}
