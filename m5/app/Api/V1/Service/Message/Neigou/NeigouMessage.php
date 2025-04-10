<?php
namespace App\Api\V1\Service\Message\Neigou;

use App\Api\V1\Service\Message\MessageHandler;
use Neigou\RedisNeigou;

class NeigouMessage extends MessageHandler
{
    public const PLATFORM_NAME = "内购消息";

    /**
     * 批量发送消息
     * @param  array  $receiver
     * @param  object $messageTemplate
     * @param  array  $item
     * @return array
     */
    protected function batchSend($receiverList, $messageTemplate, $item)
    {
        $send_data = [];
        $company_bn = '';
        foreach ($receiverList as $receiver) {
            $messages[] = $this->sendDataAssembly($receiver, $messageTemplate, $item, $company_bn);
        }

        $send_data['company_bn'] = $company_bn;
        $send_data['messages'] = $messages;

        return $this->sendLogic($send_data);
    }

    /**
     * 发送消息
     *
     * @param  array $receiver
     * @param  object $messageTemplate
     * @param  array $item
     * @return array
     */
    protected function send($receiver, $messageTemplate, $item)
    {
        $company_bn = '';
        $message = $this->sendDataAssembly($receiver, $messageTemplate, $item, $company_bn);
        $send_data['company_bn'] = $company_bn;
        $send_data['messages'] = array($message);

        return $this->sendLogic($send_data);
    }

    /**
     * api
     *
     * @param  array $send_data
     * @return array
     */
    protected function sendLogic($send_data)
    {
        $header = [
            'Authorization' => "Bearer {$this->getAccessToken()}",
        ];

        $result = $this->httpClient($this->config['api_url'], array('data' => json_encode($send_data)), 'post', $header);
        $result = json_decode($result, true);

        if (!empty($result) && ($result['Result'] == 'true') && ($result['ErrorId'] == 0)) {
            return $this->response(self::CODE_SUCCESS, $result);
        }

        return $this->response($result['ErrorId'], $result);
    }

    /**
     * 整理参数
     *
     * @param  string $receiver
     * @param  object $messageTemplate
     * @param  array $item
     * @return array
     */
    private function sendDataAssembly($receiver, $messageTemplate, $item, &$company_bn = '')
    {
        $receiver = json_decode($receiver, true);
        $member_ids = $receiver['receiver'];
        $template_id = $messageTemplate->template_data;
        $company_bn = $receiver['company_id'];

        $send_data = [
            'template_id' => $template_id,
            'receiver'    => $member_ids,
            'params'      => $item['template_param'],
        ];

        return $send_data;
    }

    /**
     * 获取access_tokens
     *
     * @return string
     */
    private function getAccessToken()
    {
        $key = 'neigou-message-access_token';
        $redis = new RedisNeigou();

        $accessToken = $redis->_redis_connection->get($key);
        if (!$accessToken) {
            $param = [
                "client_id"     => $this->config['client_id'],
                "client_secret" => $this->config['client_secret'],
                "grant_type"    => "client_credentials",
                "scope"         => $this->config['scope'] ?: ''
            ];

            $result = $this->httpClient($this->config['access_url'], $param);
            $result = json_decode($result, true);

            // 更新access_token
            $redis->_redis_connection->set($key, $result['access_token'], $result['expires_in'] - 10);
            $accessToken = $result['access_token'];
        }

        return $accessToken;
    }

    protected function errorMap()
    {
        // TODO: Implement errorMap() method.
    }

    public function checkChildParam($param)
    {
        return true;
    }
}
