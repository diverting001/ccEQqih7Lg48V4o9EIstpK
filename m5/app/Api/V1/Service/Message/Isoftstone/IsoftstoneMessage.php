<?php
namespace App\Api\V1\Service\Message\Isoftstone;

use App\Api\V1\Service\Message\MessageHandler;
use Neigou\RedisNeigou;

class IsoftstoneMessage extends MessageHandler
{
    public const PLATFORM_NAME = "软通消息";

    public $whitelist = [
        '2437',
        '31106',
        '64474',
        '87160',
        '600671',
        '611571',
        '84213',
        '141025',
        '4279',
        '57089',
        '610836',
        '195359',
        '197824',
        '291511',
        '202409',
        '305988',
        '290931',
        '264379',
        '154310',
        '273182',
        '313986',
        '15958',
        '9900543'
    ];

    /**
     * 发送消息接口
     * @param    $receiver
     * @param  object  $messageTemplate
     * @param $item
     * @return array
     */
    protected function batchSend($receiverList, $messageTemplate, $item)
    {
        $send_data = [];
        foreach ($receiverList as $receiver) {
            if (!in_array($receiver, $this->whitelist)) {
                continue;
            }
            $send_data[] = $this->sendDataAssembly($receiver, $messageTemplate, $item);
        }
        return $this->sendLogic($send_data);
    }

    protected function send($receiver, $messageTemplate, $item)
    {
        $send_data = $this->sendDataAssembly($receiver, $messageTemplate, $item);
        if (!in_array($receiver, $this->whitelist)) {
            $send_data = [];
        }
        return $this->sendLogic([$send_data]);
    }

    protected function sendLogic($send_data)
    {
        $header = [
            'Authorization' => "Bearer {$this->getAccessToken()}",
            'Content-Type' => 'application/json'
        ];

        $result = $this->httpClient($this->config['api_url'], json_encode($send_data), 'post', $header);
        $result = json_decode($result, true);
        if (isset($result['code'], $result['msg']) && $result['code'] === 0 && $result['Message'] === '成功') {
            return $this->response(self::CODE_SUCCESS, $result);
        }

        return $this->response($result['code'], $result);
    }
    private function sendDataAssembly($receiver, $messageTemplate, $item)
    {
        $send_data = [
            "templateId" => $messageTemplate->template_data,
            "systemName" => "员工关怀系统"
        ];
        if ($item['template_param']) {
            $send_data['userDefinedParam'] = $item['template_param'];
            if (isset(
                $item['template_param']['url'],
                $item['template_param']['picUrl'],
                $item['template_param']['title']
            )) {
                $send_data['userDefinedParam']['customParam'] = true;
            }
        }
        if (isset($this->config['type_key'])) {
            $send_data[$this->config['type_key']] = $receiver;
        } elseif (isset($receiver['empNo'])) {
            $send_data['empNo'] = $receiver['empNo'];
        } elseif (isset($receiver['mobile'])) {
            $send_data['mobile'] = $receiver['mobile'];
        } elseif (isset($receiver['empName'])) {
            $send_data['empName'] = $receiver['empName'];
        } elseif (isset($receiver['email'])) {
            $send_data['email'] = $receiver['email'];
        }

        return $send_data;
    }

    private function getAccessToken()
    {
        $key = 'isoftstone-message-access_token';
        $redis = new RedisNeigou();
        $accessToken = $redis->_redis_connection->get($key);
        if (!$accessToken) {
            $param = [
                "client_id" => $this->config['client_id'],
                "client_secret" => $this->config['client_secret'],
                "grant_type" => "client_credentials",
                "scope" => $this->config['scope']
            ];
            $result = $this->httpClient($this->config['access_url'], $param);
            //更新access_token
            $result = json_decode($result, true);
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
