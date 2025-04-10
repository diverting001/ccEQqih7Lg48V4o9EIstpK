<?php
namespace App\Api\V1\Service\OpenPlatform\WeChat;

use App\Api\V1\Service\OpenPlatform\OpenPlatform;
use App\Api\V1\Service\ServiceTrait;
use Neigou\RedisNeigou;

class JyflWeChat implements OpenPlatform
{
    use ServiceTrait;

    const GET_ACCESS_TOKEN_PATH = '/saas/weChat/login/getAccessToken';

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
        $params = [
            'appId' => $paramData["app_id"],
            'timestamp' => time(),
        ];
        $params['sign'] = $this->getSign($params);
        $res = $this->request(self::GET_ACCESS_TOKEN_PATH, $params);

        if ($res && $res['code'] == 0 && !empty($res['data']['access_token'])) {
            $return = array(
                'access_token' => $res['data']['access_token'],
                'expires_time' => time() + $res['data']['expires_in'],
            );
            return $this->Response(true, '成功', $return);
        }

        return $this->Response(false, '获取失败');
    }

    /**
     * Notes:公众号用于调用微信JS接口的临时票据
     * User: mazhenkang
     * Date: 2024/7/31 下午3:14
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
     * Notes:签名
     * User: mazhenkang
     * Date: 2024/7/31 上午11:07
     * @param $params
     */
    private function getSign($params)
    {
        $params['salt'] = config('neigou.JYFL_API_SALT');
        ksort($params);
        $str = '';
        foreach ($params as $k => $v) {
            $str .= $k . '=' . $v . '&';
        }
        $str = rtrim($str, '&');

        return md5($str);
    }

    /**
     * Notes:请求
     * User: mazhenkang
     * Date: 2024/7/31 上午11:17
     * @param $path
     * @param $params
     * @return mixed
     */
    private function request($path, $params)
    {
        $url = 'https://'.config('neigou.JYFL_API_GATEWAY_DOMAIN').$path;

        $curl = new \Neigou\Curl();
        $curl->SetHeader('Content-Type', 'application/json;charset=UTF-8');
        $result_str = $curl->Post($url, json_encode($params));
        $result = trim($result_str, "\xEF\xBB\xBF");
        $result = json_decode($result, true);

        return $result;
    }
}
