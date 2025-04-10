<?php

namespace App\Api\V1\Service\Message\Kuaishou;

use App\Api\V1\Service\Message\MessageHandler;
use Illuminate\Support\Str;
use Neigou\RedisNeigou;

class KuaishouMessage extends MessageHandler
{
    public const PLATFORM_NAME = "快手消息";

    /**
     * 发送消息接口
     * @param    $receiver
     * @param object $messageTemplate
     * @param $item
     * @return array
     */
    protected function batchSend($receiverList, $messageTemplate, $item)
    {
        $send_data = [];
        foreach ($receiverList as $receiver) {
            $send_data[] = $this->sendDataAssembly($receiver, $messageTemplate, $item);
        }
        return $this->sendLogic($send_data);
    }

    protected function send($receiver, $messageTemplate, $item)
    {
//        echo 'a';
////        print_r($receiver);
//        print_r($messageTemplate);
//        print_r($item);
        // 黑名单
        $deny_receiver = [
            'linlin'
        ];
        // 白名单
//        $allow_receiver = [
//            'xiangyulin', 'chengbowen03', 'xinmengran03', 'jingziwei', 'konglingming', 'liubo', 'wb_wangxiao05', 'wb_huoyujia'
//        ];
        $receiver = is_array($receiver) ? $receiver : [$receiver];
//        $receiver = array_intersect($allow_receiver, $receiver);
        // 黑名单
        $receiver = array_diff($receiver, $deny_receiver);
        if (empty($receiver)) {
            return $this->response(self::CODE_SUCCESS, '非白名单，跳过');
        }
        $msg_code_mapping = array(
            'order' => [
                'order_pay_cancel' => [
                    'messageTypeCode' => 'ORDER_CANCEL',
                    'placeholderValues' => [
                        $item['template_param']['weixin_data']['OrderSn']
                    ],
                    'url' => $item['template_param']['weixin_data']['url'],
                    'mediaUrl' => config('neigou.CDN_WEB_NEIGOU_WWW') . '/app/b2c/neigou_statics/images/message/kuaishou/order_pay_cancel.jpg',
                ],
                'order_complete' => [
                    'messageTypeCode' => 'ORDER_FINISH',
                    'placeholderValues' => [
                        $item['template_param']['weixin_data']['OrderSn']
                    ],
                    'url' => $item['template_param']['weixin_data']['url'],
                    'mediaUrl' => config('neigou.CDN_WEB_NEIGOU_WWW') . '/app/b2c/neigou_statics/images/message/kuaishou/order_complete.jpg',
                ],
                'order_pay_finish' => [
                    'messageTypeCode' => 'ORDER_PAY',
                    'placeholderValues' => [
                        $item['template_param']['weixin_data']['OrderSn']
                    ],
                    'url' => $item['template_param']['weixin_data']['url'],
                    'mediaUrl' => config('neigou.CDN_WEB_NEIGOU_WWW') . '/app/b2c/neigou_statics/images/message/kuaishou/order_pay_finish.jpg',
                ],
            ],
            'jifen' => [
                'scene_point_send_to_member' => [
                    'messageTypeCode' => 'SCORE_ALLOCATE',
                    'placeholderValues' => [
                        $item['template_param']['weixin_data']['number'],
                        $item['template_param']['weixin_data']['keyword1'],
                    ],
                    'url' => $item['template_param']['weixin_data']['url'],
                    'mediaUrl' => config('neigou.CDN_WEB_NEIGOU_WWW') . '/app/b2c/neigou_statics/images/message/kuaishou/scene_point_send_to_member.jpg',
                ],
                'scene_point_refund_to_member' => [
                    'messageTypeCode' => 'SCORE_REFUND',
                    'placeholderValues' => [
                        $item['template_param']['base_data']['order_id'],
                        $item['template_param']['weixin_data']['number']
                    ],
                    'url' => $item['template_param']['weixin_data']['url'],
                    'mediaUrl' => config('neigou.CDN_WEB_NEIGOU_WWW') . '/app/b2c/neigou_statics/images/message/kuaishou/scene_point_refund_to_member.jpg',
                ],
            ],
            'announce' => [
                'announce_expire' => [
                    'messageTypeCode' => 'ANNOUNCEMENT_EXPIRE',
                    'placeholderValues' => [
                        $item['template_param']['weixin_data']['title']
                    ],
                    'url' => $item['template_param']['weixin_data']['url'],
                    'mediaUrl' => $item['template_param']['weixin_data']['pic_url'],
                ],
                'announce_publish' => [
                    'messageTypeCode' => 'ANNOUNCEMENT_PUBLISH',
                    'placeholderValues' => [
                        $item['template_param']['weixin_data']['title'],
                        $item['template_param']['weixin_data']['sub_title'],
                    ],
                    'url' => $item['template_param']['weixin_data']['url'],
                    'mediaUrl' => $item['template_param']['weixin_data']['pic_url'],
                ],
            ],
            'join' => [
                'ENTRY_JOIN_COMMUNITY' => [ // 员工入职进入社区
                    'messageTypeCode' => 'ENTRY_JOIN_COMMUNITY',
                    'placeholderValues' => [],
                    'url' => $item['template_param']['url'],
                    'mediaUrl' => $item['template_param']['pic_url'],
                ],
                'ENTRY_SELECT_HEALTH_INSURE' => [ // 员工入职选商保
                    'messageTypeCode' => 'ENTRY_SELECT_HEALTH_INSURE',
                    'placeholderValues' => $item['template_param']['pars'],
                    'url' => $item['template_param']['url'],
                    'mediaUrl' => $item['template_param']['pic_url'],
                ],
                'ENTRY_SELECT_HEALTH_INSURE_CLOSE' => [ // 商保自选关闭
                    'messageTypeCode' => 'ENTRY_SELECT_HEALTH_INSURE_CLOSE',
                    'placeholderValues' => [],
                    'url' => $item['template_param']['url'],
                    'mediaUrl' => $item['template_param']['pic_url'],
                ],
                'BASE' => [
                    'messageTypeCode' => $item['template_param']['msg_code'],
                    'placeholderValues' => $item['template_param']['pars'] ?: [],
                    'url' => $item['template_param']['url'],
                    'mediaUrl' => $item['template_param']['pic_url'],
                ],
            ],
        );
        $msg_info = $msg_code_mapping[$item['template_param']['msg_type']][$item['template_param']['msg_code']];
        if (empty($msg_info)) {
            $msg_info = $msg_code_mapping[$item['template_param']['msg_type']]['BASE'];
        }
        if (empty($msg_info)) {
            \Neigou\Logger::Debug('service.kuaishou.message.send.unknown', ['item' => $item]);
            return $this->response(self::CODE_SUCCESS, '未知消息类型：跳过');
        }
        $pars_post = array(
            'mediaUrl' => $msg_info['mediaUrl'],
            'messageTypeCode' => $msg_info['messageTypeCode'],
            'placeholderValues' => $msg_info['placeholderValues'],
            'url' => config('neigou.OPENAPI_DOMIN') . '/ChannelInterop/v1/KuaiShou/Web/ssoLogin?redirect=' . urlencode($msg_info['url']),
            'usernames' => is_array($receiver) ? array_values($receiver) : [$receiver],
        );
        \Neigou\Logger::Debug('service.kuaishou.message.send', ['item' => $item, 'pars' => $pars_post]);
        return $this->api($pars_post);
    }

    protected function errorMap()
    {
        // TODO: Implement errorMap() method.
    }

    public function checkChildParam($param)
    {
        return true;
    }

    private function api($pars_post)
    {
        $url = $this->config['base_url'] . $this->config['api_message_send'];
        $pars_get = array(
            'appId' => $this->config['appId'],
            'timestamp' => $this->msectime(),
        );
        $pars_get['sign'] = $this->sign($pars_get);
        $url = $url . '?' . http_build_query($pars_get);
        $apiStr = $this->httpClient($url, json_encode($pars_post), 'post', ['Content-Type' => 'application/json']);
        $result = json_decode($apiStr, true);
        $log_arr = array(
            $url,
            $pars_get,
            $pars_post,
            $apiStr
        );
        \Neigou\Logger::Debug('service.kuaishou.message.api', $log_arr);
//        print_r($log_arr);
        if ($result['code'] === 0) {
            return $this->response(self::CODE_SUCCESS, $result);
        }
        return $this->response($result['code'], $result);
    }

    private function msectime()
    {
        list($msec, $sec) = explode(' ', microtime());
        $msectime = (float)sprintf('%.0f', (floatval($msec) + floatval($sec)) * 1000);
        return $msectime;
    }

    // 获取签名
    private function sign($pars_get)
    {
        krsort($pars_get);
        $str = '';
        foreach ($pars_get as $key => $val) {
            $str .= '&' . $key . '=' . $val;
        }
        $str = trim($str, '&');
        $md5_str = md5($str . $this->config['secretKey']);
        $log_arr = array(
            '参数拼接字符串' => $str . $this->config['secretKey'],
            'md5结果' => $md5_str,
        );
//        print_r($log_arr);
//        \Neigou\Logger::General('salyut.sntb.logic.sign', $log_arr);
        return $md5_str;
    }
}
