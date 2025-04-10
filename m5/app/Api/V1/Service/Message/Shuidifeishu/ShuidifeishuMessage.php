<?php
namespace App\Api\V1\Service\Message\Shuidifeishu;
use App\Api\V1\Service\Message\MessageHandler;
use Neigou\RedisNeigou;

class ShuidifeishuMessage extends MessageHandler
{
    public const PLATFORM_NAME = '水滴飞书消息';
    /**
     * @inheritDoc
     */
    protected function errorMap()
    {
        // TODO: Implement errorMap() method.
    }

    /**
     * @inheritDoc
     */
    protected function send($receiver, $templateRealData, $params)
    {
        $company_bn = '';
        $send_data = $this->sendDataAssembly($receiver, $templateRealData, $params, $company_bn);
//        \Log::error('send_message', [$send_data]);

        return $this->sendLogic($send_data);
    }

    /**
     * @inheritDoc
     */
    protected function batchSend($receivers, $templateRealData, $params)
    {
        $company_bn = '';
        \Log::error('batchSend', [$receivers, $templateRealData, $params]);
        $result = array();
        foreach ($receivers as $receiver){
            $send_data = $this->sendDataAssembly($receiver, $templateRealData, $params, $company_bn);
            $result[$receiver] = $this->sendLogic($send_data);
        }

        return $result;
    }

    /**
     * @inheritDoc
     */
    public function checkChildParam($param)
    {
        return true;
    }

    protected function sendLogic($send_data)
    {
        $header = [
            'Authorization' => 'Bearer '.$this->getAccessToken(),
        ];

        $result = $this->httpClient($this->config['api_url'], array('data' => json_encode($send_data)), 'post', $header);
        $result = json_decode($result, true);
        if(!empty($result) && $result['Result'] === 'true' && $result['ErrorId'] === 0){
            return $this->response(self::CODE_SUCCESS, $result);
        }

        return $this->response($result['ErrorId'], $result);
    }

    private function sendDataAssembly($receiver, $messageTemplate, $item, &$company_bn = '')
    {
        $receiver = json_decode($receiver, true);
        $member_ids = $receiver['receiver'];
        $company_bn = $receiver['company_id'];

        $templateId = $messageTemplate->template_id;
        $description = $messageTemplate->description; //消息内容

        $send_data = [
            'template_id'  => $templateId,
            'receiver'     => $member_ids,
            'send_channel' => $item['template_param']['send_channel'] ?: 'shuidi',
            'params'       => $item['template_param'],
            'description'  => $description
        ];
        unset($item['template_param']['send_channel']);

        return $send_data;
    }

    /**
     * 获取access_tokens
     *
     * @return string
     */
    private function getAccessToken()
    {
        $key = 'neigou-message-shuidichou_access_token';
        $redis = new RedisNeigou();

        $accessToken = $redis->_redis_connection->get($key);
        if(empty($accessToken)){
            $params = [
                'client_id'     => $this->config['client_id'],
                'client_secret' => $this->config['client_secret'],
                'grant_type'    => 'client_credentials',
                'scope'         => $this->config['scope'] ?: '',
            ];
            $result = $this->httpClient($this->config['access_url'], $params);
            $result = json_decode($result, true);

            $resultData = $result['Data'];
            $redis->_redis_connection->set($key, $resultData['access_token'], $resultData['expires_in'] - 10);
            $accessToken = $resultData['access_token'];
        }

        return $accessToken;
    }
}
