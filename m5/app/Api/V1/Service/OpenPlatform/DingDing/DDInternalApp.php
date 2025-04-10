<?php
namespace App\Api\V1\Service\OpenPlatform\DingDing;

use App\Api\Model\OpenPlatform\OpenPlatformConfig as ConfigModel;
use App\Api\V1\Service\OpenPlatform\OpenPlatform;
use App\Api\V1\Service\ServiceTrait;
use Neigou\RedisNeigou;

class DDInternalApp implements OpenPlatform
{
    use ServiceTrait;
    //获取接口调用凭据
    const ACCESS_TOKEN_URL = 'https://api.dingtalk.com/v1.0/oauth2/accessToken';
    //用于调用微信JS接口的临时票据
    const JSAPI_TICKET_URL = 'https://api.dingtalk.com/v1.0/oauth2/jsapiTickets';

    private $config = array();
    private $redisClient;

    public function __construct($config)
    {
        $config['extend_config'] = json_decode($config['extend_config'], true);
        $this->config = $config;
        $this->redisClient = new RedisNeigou();
    }

    public function GetAccessToken($paramData = [])
    {
        $force_refresh = $paramData['params']['force_refresh'] == true ? true : false;

        $key = 'token_' . $this->config['app_id'];
        $res = $this->redisClient->_redis_connection->get($key);
        if(!$res || $force_refresh == true){
            $tokenRes = $this->getToken();
            if (!isset($tokenRes['accessToken']) || empty($tokenRes['accessToken'])) {
                return $this->Response(false, '获取钉钉access_token异常' . $tokenRes['errmsg'] ?: '');
            }
            $expires_in = $tokenRes['expireIn'] - 60;
            $redisData = [
                'access_token' => $tokenRes['accessToken'],
                'expires_time' => time() + ($expires_in),
                'expires_in'   => $expires_in
            ];
            $this->redisClient->_redis_connection->set($key, json_encode($redisData),
                $expires_in);
            $res = $redisData;
            if ($this->config['auto_refresh_token'] == 1) { //刷新临期token记录
                $configModel = new ConfigModel();
                $configModel->setExpiresTimeByAppId($this->config['app_id'], $res['expires_time']);
            }
        } else {
            $res = json_decode($res, true);
        }
        unset($res['expires_in']);

        return $this->Response(true, '成功', $res);
    }

    public function GetTicket($paramData)
    {
        $type = $paramData['type'] ?: 'jsapi';
        $params = $paramData['params'] ?: array();

        $key = 'ticket_' . $this->config['app_id'];
        $ticketRes = $this->redisClient->_redis_connection->get($key);
        if (!$ticketRes) {
            $accessTokenRes = $this->GetAccessToken();
            if (!$accessTokenRes['status']) {
                return $this->Response(false, $accessTokenRes['msg']);
            }

            $accessToken = $accessTokenRes['data']['access_token'];
            $ticketParams = [
                'x-acs-dingtalk-access-token' => $accessToken,
            ];

            $requestRes = $this->request(self::JSAPI_TICKET_URL, [], $ticketParams);
            if (empty($requestRes) || empty($requestRes['jsapiTicket'])) {
                return $this->Response(false, '获取ticket异常' . $requestRes['errmsg']);
            }
            $expires_in = $requestRes['expireIn'] - 60;
            $ticketRes = [
                'ticket' => $requestRes['jsapiTicket'],
                'expires_time' => time() + ($expires_in),
                'expires_in'   => $expires_in
            ];
            $this->redisClient->_redis_connection->set($key, json_encode($ticketRes),
                $expires_in);
        } else {
            $ticketRes = json_decode($ticketRes, true);
        }
        unset($ticketRes['expires_in']);

        if (!empty($params) && $type == 'jsapi') {
            $this->getJsapiTicketBySign( $ticketRes, $params);
        }

        return $this->Response(true, '成功', $ticketRes);
    }


    public function GetOauth2AccessToken($paramsData)
    {
        return $this->Response(false, '暂不支持');
    }


    private function getToken()
    {
        $send_data = array(
            'appKey'         => $this->config['app_id'],
            'appSecret'        => $this->config['app_secret'],
        );

        $result = $this->request(self::ACCESS_TOKEN_URL, $send_data);
        return $result;
    }

    private function getJsapiTicketBySign(&$ticketRes, $params = [])
    {
        if(empty($params) || empty($params['url'])){
            return false;
        }

        $url = $params['url'];
        $timestamp = time();
        $nonceStr = $this->createNonceStr();
        $jsapiTicket = $ticketRes['ticket'];
        $string = "jsapi_ticket=$jsapiTicket&noncestr=$nonceStr&timestamp=$timestamp&url=$url";
        $signature = sha1($string);

        $ticketRes['corp_id'] = $this->config['extend_config']['corp_id'];
        $ticketRes['agent_id'] = $this->config['extend_config']['agent_id'];
        $ticketRes['nonce_str'] = $nonceStr;
        $ticketRes['timestamp'] = $timestamp;
        $ticketRes['signature'] = $signature;

        return true;
    }

    private function createNonceStr($length = 16)
    {
        $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
        $str   = "";
        for ($i = 0; $i < $length; $i++) {
            $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
        }
        return $str;
    }

    private function request($url, $params, $header = array(), $method = 'POST')
    {
        $curl = new \Neigou\Curl();
        if ($header) {
            $curl->SetHeader($header);
        }
        if ($method == 'POST') {
            $curl->SetHeader(array('Content-Type'=>'application/json; charset=utf-8'));
            $params = json_encode($params);
            $result_str = $curl->Post($url, $params);
        }
        if ($method == 'GET') {
            $result_str = $curl->Get($url, $params);
        }

        $result = trim($result_str, "\xEF\xBB\xBF");
        $result = json_decode($result, true);

        return $result;
    }
}
