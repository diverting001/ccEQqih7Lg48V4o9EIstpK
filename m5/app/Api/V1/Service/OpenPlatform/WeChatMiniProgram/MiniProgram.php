<?php
namespace App\Api\V1\Service\OpenPlatform\WeChatMiniProgram;

use App\Api\Model\OpenPlatform\OpenPlatformConfig as ConfigModel;
use App\Api\V1\Service\OpenPlatform\OpenPlatform;
use App\Api\V1\Service\ServiceTrait;
use Neigou\RedisNeigou;

class MiniProgram  implements OpenPlatform
{
    use ServiceTrait;

    const ACCESS_TOKEN_UTL = 'https://api.weixin.qq.com/cgi-bin/token';

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
            $tokenRes = $this->getMiniAccessToken($this->config['app_id'],
                $this->config['app_secret']);
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
     * @return array
     */
    public function GetTicket($paramData)
    {
        return $this->Response(false, '暂不支持');
    }

    public function GetOauth2AccessToken($paramData)
    {
        return $this->Response(false, '暂不支持');

    }
    /**
     * Notes:获取小程序 access_token
     * User: mazhenkang
     * Date: 2024/7/30 下午6:49
     * @param $app_id
     * @param $app_secret
     * @return mixed
     */
    private function getMiniAccessToken($app_id, $app_secret)
    {
        $send_data = array(
            'grant_type'    => 'client_credential',
            'appid'         => $app_id,
            'secret'        => $app_secret,
        );

        $curl = new \Neigou\Curl();
        $result_str = $curl->Get(self::ACCESS_TOKEN_UTL, $send_data);
        $result = trim($result_str, "\xEF\xBB\xBF");
        $result = json_decode($result, true);

        return $result;
    }
}
