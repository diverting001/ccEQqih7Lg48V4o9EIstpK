<?php

namespace App\Api\V1\Service\Message\Sms;

use App\Api\V1\Service\Message\MessageHandler;

class ChuangLanSms extends MessageHandler
{
    use SmsTrait;

    const PLATFORM_NAME = "创蓝短信";

    protected function errorMap()
    {
        return [
            '0'	  => ['提交成功', MessageHandler::CODE_SUCCESS],
            '101' => ['无此用户', MessageHandler::CODE_ERROR],
            '102' => ['密码错', MessageHandler::CODE_ERROR],
            '103' => ['提交过快',	MessageHandler::CODE_ERROR],
            '104' => ['系统忙', MessageHandler::CODE_ERROR],
            '105' => ['敏感短信', MessageHandler::CODE_FIELD_FAIL],
            '106' => ['消息长度错', MessageHandler::CODE_FIELD_FAIL],
            '107' => ['包含错误的手机号码', MessageHandler::CODE_FIELD_FAIL],
            '108' => ['手机号码个数错', MessageHandler::CODE_FIELD_FAIL],
            '109' => ['无发送额度', MessageHandler::CODE_BALANCE_ERROR],
            '110' => ['不在发送时间内', MessageHandler::CODE_ERROR],
            '113' => ['扩展码格式错', MessageHandler::CODE_FIELD_FAIL],
            '114' => ['可用参数组个数错误', MessageHandler::CODE_FIELD_FAIL],
            '116' => ['签名不合法或未带签名', MessageHandler::CODE_SIGN_ERROR],
            '117' => ['IP地址认证错,请求调用的IP地址不是系统登记的IP地址', MessageHandler::CODE_FIELD_FAIL],
            '118' => ['用户没有相应的发送权限', MessageHandler::CODE_ERROR],
            '119' => ['用户已过期', MessageHandler::CODE_ERROR],
            '120' => ['违反防盗用策略', MessageHandler::CODE_ERROR],
            '123' => ['发送类型错误', MessageHandler::CODE_ERROR],
            '124' => ['白模板匹配错误', MessageHandler::CODE_TEMPLATE_ERROR],
            '125' => ['匹配驳回模板', MessageHandler::CODE_TEMPLATE_ERROR],
            '127' => ['定时发送时间格式错误', MessageHandler::CODE_FIELD_FAIL],
            '128' => ['内容编码失败', MessageHandler::CODE_FIELD_FAIL],
            '129' => ['JSON格式错误', MessageHandler::CODE_FIELD_FAIL],
            '130' => ['请求参数错误', MessageHandler::CODE_FIELD_FAIL],
            '133' => ['单一手机号错误', MessageHandler::CODE_FIELD_FAIL],
            '134' => ['违反防盗策略, 超过月发送限制', MessageHandler::CODE_ERROR],
            '135' => ['超过同一手机号相同内容发送限制', MessageHandler::CODE_ERROR],
            '136' => ['不可批量提交"验证码"短信', MessageHandler::CODE_FIELD_FAIL],
        ];
    }

    /**
     * 批量
     *
     * @param $mobiles
     * @param $messageTemplate
     * @param $item
     * @return array
     */
    public function batchSend($mobiles, $messageTemplate, $item)
    {
        $result = array();
        foreach ($mobiles as $mobile) {
            $result[$mobile] = $this->send($mobile, $messageTemplate, $item);
        }
        return $result;
    }

    /**
     * 单个
     *
     * @param  mixed  $receiver
     * @param  object $templateRealData
     * @param  array  $params
     * @return array
     */
    protected function send($receiver, $templateRealData, $params)
    {
        $data = [
            // 账号和密码
            'account' => $this->config['account'],
            'password' => $this->config['password'],
            // 发送的手机号
            'phone' => $receiver,
        ];

        // 短信模版
        $template_str = $templateRealData->template_data;

        // 结合自己的模板中的变量进行设置，如果没有变量，可以删除此参数
        if ($params) {
            $data['msg'] = (function ($params) use ($template_str) {
                $str = '';
                foreach ($params['template_param'] as $key => $param) {
                    $search = '${'.$key.'}';
                    $replace = $param;

                    $str = str_replace($search, $replace, $template_str);
                }

                return $str;
            })($params);
        }

        $response = $this->httpClient($this->config['api_url'], json_encode($data));

        $result = json_decode($response, true);

        if (!$result) {
            \Neigou\Logger::Debug('service.ChuangLanSms', [
                'action' => 'send_result', 'params' => [$receiver, $templateRealData, $params], 'return' => $response,
            ]);

            return $this->response(MessageHandler::CODE_ERROR, $result);
        }

        $errorCode = $result['code'];
        if ($errorCode === '0') {
            return $this->response(self::CODE_SUCCESS, $result);
        } else {
            \Neigou\Logger::Debug('service.ChuangLanSms', [
                'action' => 'send_fail', 'params' => [$receiver, $templateRealData, $params, $data], 'return' => $response,
            ]);
            return $this->response($result['code'], $result);
        }
    }
}
