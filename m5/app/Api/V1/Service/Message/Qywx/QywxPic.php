<?php

namespace App\Api\V1\Service\Message\Qywx;

use App\Api\Model\Message\MessageLog;
use App\Api\V1\Service\Message\MessageHandler;

class QywxPic extends MessageHandler
{
    public $syncResult = false;
    const PLATFORM_NAME = "企业微信图片消息";

    public function batchSend($mobiles, $messageTemplate, $item)
    {
        return $this->sendLogic($mobiles, $item['template_param']);
    }

    public function checkChildParam($param)
    {
        return true;
    }

    /**
     * 平台对应内部统一状态码
     * @return mixed|\string[][]
     */
    protected function errorMap()
    {
        return [];
    }

    /**
     * 发送消息接口
     * @param  string  $receiver
     * @param  object  $messageTemplate
     * @param $item
     * @return array
     */
    protected function send($receiver, $messageTemplate, $item)
    {
        return $this->sendLogic($receiver, $item['template_param']);
    }

    public function sendLogic($receiver, $item)
    {
        $post_data = array(
            'name' => 'adminSendPicture',
            'version' => 'v1',
            'gcorp_id' => $item['gcorpId'],
            'guids' => is_array($receiver) ? implode(',', $receiver) : $receiver,
            'img_url' => $item['imgUrl'],
            'channel_id' => $item['channelId'],
            'key'=> $this->batchId

        );
        $data = $this->signData($post_data);
        $result = $this->httpClient($this->config['api_url'], $data);
        $result = json_decode($result, true);
        return $this->response(isset($result['errno']) && $result['errno'] === 0 ?
            self::CODE_SUCCESS : self::CODE_ERROR, $result);
    }

    public function signData($get_data)
    {
        foreach ($get_data as $k => $v) {
            if (($v == '' || $v == null) && $v !== 0) {
                unset($get_data[$k]);
            }
        }
        $get_data['partnerId'] = $this->config['partner_id'];
        $get_data['expires'] = $get_data['expires'] ?: time() + 10;
        $get_data['nonce'] = $get_data['nonce'] ?: md5(microtime().rand(0, 999999));
        ksort($get_data);
        $sign_arr = array();
        foreach ($get_data as $key => $value) {
            $sign_arr[] = $key.'='.$value;
        }
        $sign_str = implode('&', $sign_arr);
        $signature = hash_hmac('sha256', $sign_str, $this->config['key']);
        $get_data['signature'] = $signature;
        return $get_data;
    }

    public function getResult($batchInfo)
    {
        $messageLog = new MessageLog();
        $logInfo = $messageLog->getBatchInfo($batchInfo->batch_id);
        $post_data = array(
            'name' => 'adminGetSendResult',
            'version' => 'v1',
            'gcorp_id' => unserialize($logInfo[0]->template_param)['gcorpId'],
            'key' => $batchInfo->batch_id,
        );
        $data = $this->signData($post_data);
        $res = $this->httpClient($this->config['api_url'], $data);
        $res = json_decode($res, true);
        if (isset($res['error']) && $res['error'] === 'OK') {
            if (!$res['body']['total']) {
                return ['result' => false];
            }
            return ['result' => true, 'data' => json_encode($res)];
        }

        return ['result' => false];
    }

    public function resultMapping($result)
    {
        if (isset($result['error']) && $result['error'] === 'OK') {
            return [
                'result' => true,
                'status' => MessageLog::SEND_STATUS_SUCCESS,
                'total' => (int)$result['body']['total'],
                'success' => (int)$result['body']['success'],
                //'failure' => $result['body']['total'] - $result['body']['success']
            ];
        }
        return ['result'=>false];
    }
}
