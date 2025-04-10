<?php

namespace App\Api\V1\Service\Message\Sms;

use App\Api\V1\Service\Message\MessageHandler;
use Illuminate\Support\Str;
use Neigou\RedisNeigou;

class WomaiSms extends MessageHandler
{
    use SmsTrait;

    const PLATFORM_NAME = "中粮短信";

    private $code_path = '/api/oauth/oauthcode';
    private $token_path = '/api/oauth/token';
    private $send_path = '/api/WMSMS/SendSM';

    /**
     * 平台对应内部统一状态码
     * @return mixed|\string[][]
     */
    protected function errorMap()
    {
        return [];
    }

    public function templateMatch($messageTemplate, $templateParam)
    {
        if (!empty($messageTemplate->param)) {
            foreach ($messageTemplate->param as $param) {
                $messageTemplate->template_data = Str::replaceFirst(
                    '${'.$param.'}',
                    $templateParam[$param],
                    $messageTemplate->template_data
                );
            }
        }
        return $messageTemplate->template_data;
    }

    protected function send($mobile, $messageTemplate, $item)
    {
        return $this->publicSend($mobile, $messageTemplate, $item);
    }

    public function batchSend($mobiles, $messageTemplate, $item)
    {
        $result = array();
        foreach ($mobiles as $mobile) {
            $result[$mobile] = $this->publicSend($mobile, $messageTemplate, $item);
        }
        return $result;
    }

    private function publicSend($mobile, $messageTemplate, $item, $newNewToken = false)
    {
        // 解析模板数据
        $templateData = $this->templateMatch($messageTemplate, $item['template_param']);
        $content = preg_replace('/订单号(.*?)，/u', '', $templateData);
        $token = $this->getToken($newNewToken);
        $data = array();
        // 设置手机号码 每个手机号之间用,隔开(必填)
        $data['mobileno'] = $mobile;
        $data['content'] = preg_replace('/回复TD退订([!！.。])?/u', '', $content);//短信内容
        $data['client_id'] = $this->config['user'];
        $data['access_token'] = $token;
        $data['timestamp'] = time();

        $data['sign'] = $this->sign($data);

        $result = $this->httpClient($this->config['api_url'].$this->send_path, $data);//根据请求类型进行请求
        return $this->formatResult($mobile, $messageTemplate, $item, $result);
    }

    private function sign($pars)
    {
        ksort($pars);
        $str = '';

        foreach ($pars as $k => $v) {
            if (is_array($v)) {
                foreach ($v as $i => $iValue) {
                    ksort($v[$i]);
                    foreach ($iValue as $kk => $vv) {
                        $str .= $k."[$i][$kk]".$vv;
                    }
                }
            } else {
                $str .= $k.$v;
            }
        }
        $str = $this->config['secret'].$str.$this->config['secret'];

        return strtoupper(md5($str));
    }

    public function getToken($needNewToken = false)
    {
        $redis_token_key = '_sms_send_token'.md5($this->config['user']);
        $redis_refresh_token_key = '_sms_send_refresh_token'.md5($this->config['user']);

        $redis = new RedisNeigou();
        if (!$needNewToken) {
            $token = $redis->_redis_connection->get($redis_token_key);
            if ($token) {
                return $token;
            }

            $refreshToken = $redis->_redis_connection->get($redis_refresh_token_key);

            if ($refreshToken) {
                $data = array(
                    'client_id' => $this->config['user'],
                    'grant_type' => 'refresh_token',
                    'client_secret' => $this->config['secret'],
                    'refresh_token' => $refreshToken,
                );

                $res = $this->httpClient($this->config['api_url'].$this->token_path, $data);
                $res = json_decode($res, true);
                if (!empty($res['result']['access_token']) && !empty($res['result']['refresh_token'])) {
                    $redis->_redis_connection->set(
                        $redis_token_key,
                        $res['result']['access_token'],
                        $res['result']['expires_in'] - 10
                    );
                    $redis->_redis_connection->set($redis_refresh_token_key, $res['result']['refresh_token'], 2592000);

                    return $res['result']['access_token'];
                }
            }
        }
        $code = $this->getCode();
        if (!$code) {
            return false;
        }

        $data = array(
            'client_id' => $this->config['user'],
            'code' => $code,
            'grant_type' => 'authorization_code',
            'client_secret' => $this->config['secret'],
        );

        $res = $this->httpClient($this->config['api_url'].$this->token_path, $data);
        $res = json_decode($res, true);

        if (empty($res['result']['access_token']) || empty($res['result']['refresh_token'])) {
            return false;
        }

        $redis->_redis_connection->del($redis_token_key);
        $redis->_redis_connection->del($redis_refresh_token_key);

        $redis->_redis_connection->set(
            $redis_token_key,
            $res['result']['access_token'],
            $res['result']['expires_in'] - 10
        );
        $redis->_redis_connection->set($redis_refresh_token_key, $res['result']['refresh_token'], 2592000 - 10);

        return $res['result']['access_token'];
    }

    public function getCode()
    {
        $res = $this->httpClient(
            $this->config['api_url'].$this->code_path,
            array(
                'client_id' => $this->config['user'],
                'client_password' => $this->config['password'],
                'response_type' => 'code'
            )
        );
        $res = json_decode($res, true);

        if (empty($res['result']['code'])) {
            return false;
        }

        return $res['result']['code'];
    }

    public function formatResult($mobile, $messageTemplate, $item, $result)
    {
        $result = json_decode($result, true);//将返回结果集json格式解析转化为数组格式
        if (isset($result['errmsg']) && $result['errmsg'] === 'accessToken错误') {
            return $this->publicSend($mobile, $messageTemplate, $item, true);
        }
        if (isset($result['msg']) && $result['msg'] === '成功') {
            return $this->response(self::CODE_SUCCESS, $result);
        }
        return $this->response(self::CODE_ERROR, $result);

    }
}
