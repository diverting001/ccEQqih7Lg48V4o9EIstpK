<?php

namespace App\Api\V1\Service\Message\Qywx;

use App\Api\V1\Service\Message\MessageHandler;
use Illuminate\Support\Str;

class QywxText extends MessageHandler
{
    const PLATFORM_NAME = "企业微信-文本消息";

    public function checkChildParam($param)
    {
        return true;
    }

    public function batchSend($guids, $messageTemplate, $item)
    {
        foreach ($guids as $guid) {
            $result[$guid] = $this->sendLogic($guid, $messageTemplate, $item);
        }
        return $result;
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
     * @param  string  $guid
     * @param  object  $messageTemplate
     * @param $item
     * @return array
     */
    protected function send($receiver, $messageTemplate, $item)
    {
        $receiver = json_decode($receiver, true);
        return $this->sendLogic($receiver, $messageTemplate, $item);
    }

    public function sendLogic($receiver, $messageTemplate, $item)
    {
        \Neigou\Logger::Debug("qywxtest.send.params", array('receiver' => json_encode($receiver), 'messageTemplate'=>json_encode($messageTemplate), 'item'=>json_encode($item) ));
        $templateData = $this->templateMatch($messageTemplate, $item['template_param']);
        $db = app('api_db')->connection('neigou_club');
        $result = [];
        $url = empty($item['template_param']['url']) ? '' : $item['template_param']['url'];
        foreach ($receiver['guids'] as $guid) {
            $data = [
                'gcorp_id' => $receiver['gcorp_id'],
                'guid' => $guid,
                'title' => $messageTemplate->name,
                'content' => $templateData,
                'platform' => $item['template_param']['message_platform'] ? : 'neigou',
                'type' => $item['template_param']['message_type'] ? : 'voucher',
                'extra_params' => json_encode(['content' => $templateData]),
                'createtime' => time(),
                'url' => $url
            ];
            $insertId = $db->table('diandi_member_message')->insertGetId($data);
            $result[$guid] =$insertId;
        }
        return $this->response(!empty($result) ? self::CODE_SUCCESS : self::CODE_ERROR, $result);
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
}
