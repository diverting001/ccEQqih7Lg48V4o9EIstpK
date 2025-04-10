<?php
namespace App\Api\V1\Service\OpenPlatform\WeChat;

use App\Api\Model\OpenPlatform\OpenPlatformConfig as ConfigModel;
use App\Api\V1\Service\OpenPlatform\OpenPlatform;
use App\Api\V1\Service\ServiceTrait;
use Neigou\RedisNeigou;

class WeChat implements OpenPlatform
{
    use ServiceTrait;
    //获取稳定版接口调用凭据
    const STABLE_ACCESS_TOKEN_URL = 'https://api.weixin.qq.com/cgi-bin/stable_token';
    //通过code换取网页授权access_token
    const OAUTH2_ACCESS_TOKEN_URL = 'https://api.weixin.qq.com/sns/oauth2/access_token';
    //公众号用于调用微信JS接口的临时票据
    const JSAPI_TICKET_URL = 'https://api.weixin.qq.com/cgi-bin/ticket/getticket';

    private $config = array();
    private $redisClient;

    public function __construct($config)
    {
        $this->config = $config;
        $this->redisClient = new RedisNeigou();
    }

    /**
     * Notes:获取微信公众号 access_token
     * User: mazhenkang
     * Date: 2024/7/30 下午5:50
     * @param $paramData
     * @return array|false|mixed
     */
    public function GetAccessToken($paramData)
    {
        $force_refresh = $paramData['params']['force_refresh'] == true ? true : false;

        $key = 'token_' . $this->config['app_id'] . $this->config['adapter_type'];
        $res = $this->redisClient->_redis_connection->get($key);
        if(!$res || $force_refresh == true){
            $tokenRes = $this->getStableToken($this->config['app_id'], $this->config['app_secret'], $force_refresh);
            if (!isset($tokenRes['access_token']) || empty($tokenRes['access_token'])) {
                return $this->Response(false, '获取微信access_token异常' . $tokenRes['errmsg'] ?: '');
            }
            $expires_in = $tokenRes['expires_in'];
            $redisData = [
                'access_token' => $tokenRes['access_token'],
                'expires_time' => time() + ($expires_in),
                'expires_in'   => $expires_in
            ];
            $this->redisClient->_redis_connection->set($key, json_encode($redisData),
                $expires_in);
            $res = $redisData;
            if ($this->config['auto_refresh_token'] == 1) { //刷新临期token记录
                $configModel = new ConfigModel();
                $configModel->setExpiresTimeByAppId($paramData['app_id'], $res['expires_time']);
            }
        } else {
            $res = json_decode($res, true);
        }
        unset($res['expires_in']);

        return $this->Response(true, '成功', $res);
    }

    /**
     * Notes:公众号用于调用微信JS接口的临时票据
     * User: mazhenkang
     * Date: 2024/7/31 下午3:15
     * @param $paramData
     */
    public function GetTicket($paramData)
    {
        $app_id = $paramData['app_id'];
        $type = $paramData['type'] ?: 'jsapi';
        $params = $paramData['params'] ?: array();

        $key = 'ticket_' . $this->config['app_id'] . $this->config['adapter_type'];
        $ticketRes = $this->redisClient->_redis_connection->get($key);
        if (!$ticketRes) {
            $accessTokenRes = $this->GetAccessToken(['app_id'=>$app_id]);
            if (!$accessTokenRes['status']) {
                return $this->Response(false, $accessTokenRes['msg']);
            }

            $accessToken = $accessTokenRes['data']['access_token'];
            $ticketParams = [
                'access_token' => $accessToken,
                'type' => $type
            ];
            $requestRes = $this->request(self::JSAPI_TICKET_URL, $ticketParams, 'GET');
            if (empty($requestRes) || $requestRes['errcode'] !== 0 || empty($requestRes['ticket'])) {
                return $this->Response(false, '获取ticket异常' . $requestRes['errmsg']);
            }
            $expires_in = $requestRes['expires_in'];
            $ticketRes = [
                'ticket' => $requestRes['ticket'],
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
            $this->getJsapiTicketBySign($app_id, $ticketRes, $params);
        }

        return $this->Response(true, '成功', $ticketRes);
    }

    /**
     * Notes:通过code换取网页授权access_token
     * User: mazhenkang
     * Date: 2024/7/31 下午4:20
     * @param $paramsData
     */
    public function GetOauth2AccessToken($paramsData)
    {
        $app_id = $paramsData['app_id'];
        $code = $paramsData['code'];

        $params = [
            'appid'      => $app_id,
            'secret'     => $this->config['app_secret'],
            'code'       => $code,
            'grant_type' => 'authorization_code',
        ];
        $requestRes = $this->request(self::OAUTH2_ACCESS_TOKEN_URL, $params, 'GET');
        if (empty($requestRes) || empty($requestRes['access_token'])) {
            return $this->Response(false, '获取oauth2-access_token异常-' . $requestRes['errmsg']);
        }
        $expires_in = $requestRes['expires_in'];
        $oauth2Res = [
            'access_token'    => $requestRes['access_token'],
            'refresh_token'   => $requestRes['refresh_token'],
            'openid'          => $requestRes['openid'],
            'scope'           => $requestRes['scope'],
            'is_snapshotuser' => $requestRes['is_snapshotuser'] ?: '',
            'unionid'         => $requestRes['unionid'] ?: '',
            'expires_time'    => time() + ($expires_in),
            'expires_in'      => $expires_in
        ];

        unset($oauth2Res['expires_in']);

        return $this->Response(true, '成功', $oauth2Res);
    }

    /**
     * Notes:获取微信access_token
     * User: mazhenkang
     * Date: 2024/7/30 下午6:49
     * @param $app_id
     * @param $app_secret
     * @param $force_refresh
     * @return mixed
     */
    private function getStableToken($app_id, $app_secret, $force_refresh = false)
    {
        $send_data = array(
            'grant_type'    => 'client_credential',
            'appid'         => $app_id,
            'secret'        => $app_secret,
            'force_refresh' => $force_refresh,
        );

        $result = $this->request(self::STABLE_ACCESS_TOKEN_URL, $send_data);
        return $result;
    }

    /**
     * Notes:获取JS-SDK的页面注入配置信息
     * User: mazhenkang
     * Date: 2024/7/31 下午4:03
     * @param $app_id
     * @param $ticketRes
     * @param $params
     */
    private function getJsapiTicketBySign($app_id, &$ticketRes, $params = [])
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

        $ticketRes['app_id'] = $app_id;
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
        /**
     * Notes:请求
     * User: mazhenkang
     * Date: 2024/7/31 下午3:23
     * @param $url
     * @param $params
     * @param $method
     */
    private function request($url, $params, $method = 'POST')
    {
        $curl = new \Neigou\Curl();
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
