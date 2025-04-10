<?php

namespace App\Api\V1\Service\Message\Sms;

use App\Api\V1\Service\Message\MessageHandler;

class JuHeSms extends MessageHandler
{
    use SmsTrait;

    const PLATFORM_NAME = "聚合数据短信";

    protected function errorMap()
    {
        return [
            '205401' => ['错误的手机号码', MessageHandler::CODE_FIELD_FAIL],
            '205402' => ['错误的短信模板ID', MessageHandler::CODE_TEMPLATE_ERROR],
            '205403' => ['网络错误,请重试', MessageHandler::CODE_ERROR],
            '205404' => ['发送失败，具体原因请参考返回reason', MessageHandler::CODE_ERROR],
            '205405' => ['号码异常 / 同一号码发送次数过于频繁', MessageHandler::CODE_ERROR],
            '205406' => ['不被支持的模板', MessageHandler::CODE_TEMPLATE_ERROR],
            '205407' => ['批量发送库存次数不足', MessageHandler::CODE_ERROR],
            '205409' => ['系统繁忙，请稍后重试', MessageHandler::CODE_ERROR],
            '205410' => ['请求方法错误', MessageHandler::CODE_ERROR],
            '10001' => ['错误的请求KEY', MessageHandler::CODE_SIGN_ERROR],
            '10002' => ['该KEY无请求权限', MessageHandler::CODE_SIGN_ERROR],
            '10003' => ['KEY过期', MessageHandler::CODE_SIGN_ERROR],
            '10004' => ['错误的OPENID', MessageHandler::CODE_SIGN_ERROR],
            '10005' => ['应用未审核超时，请提交认证', MessageHandler::CODE_SIGN_ERROR],
            '10007' => ['未知的请求源', MessageHandler::CODE_SIGN_ERROR],
            '10008' => ['被禁止的IP', MessageHandler::CODE_SIGN_ERROR],
            '10009' => ['被禁止的KEY', MessageHandler::CODE_SIGN_ERROR],
            '10011' => ['当前IP请求超过限制', MessageHandler::CODE_ERROR],
            '10012' => ['请求超过次数限制', MessageHandler::CODE_ERROR],
            '10013' => ['测试KEY超过请求限制', MessageHandler::CODE_SIGN_ERROR],
            '10014' => ['系统内部异常', MessageHandler::CODE_ERROR],//(调用充值类业务时，请务必联系客服或通过订单查询接口检测订单，避免造成损失)
            '10020' => ['接口维护', MessageHandler::CODE_ERROR],
            '10021' => ['接口停用', MessageHandler::CODE_ERROR],
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
     * @param  string  $templateRealData
     * @param $params
     * @return array
     */
    protected function send($receiver, $templateRealData, $params)
    {
        $data = [
            // 模板id
            'tpl_id' => $templateRealData->template_data,
            // 您申请的接口调用Key
            'key' => $this->config['access_key'],
            //发送的手机号
            'mobile' => $receiver,
        ];

        //结合自己的模板中的变量进行设置，如果没有变量，可以删除此参数
        if ($params) {
            $data['tpl_value'] = (function ($params) {
                $str = '';
                foreach ($params['template_param'] as $key => $param) {
                    if (empty($str)) {
                        $str = '#'.$key.'#='.$param;
                    } else {
                        $str = $str.'&#'.$key.'#='.$param;
                    }
                }

                return urlencode($str);
            })($params);
        }

        $response = $this->httpClient($this->config['api_url'], $data);

        $result = json_decode($response, true);

        if (!$result) {
            \Neigou\Logger::Debug('service.JuHeSms', [
                'action' => 'send_result', 'params' => [$receiver, $templateRealData, $params], 'return' => $response,
            ]);

            return $this->response(MessageHandler::CODE_ERROR, $result);
        }

        $errorCode = $result['error_code'];
        if ($errorCode === 0) {
            return $this->response(self::CODE_SUCCESS, $result);
        } else {
            \Neigou\Logger::Debug('service.JuHeSms', [
                'action' => 'send_fail', 'params' => [$receiver, $templateRealData, $params, $data], 'return' => $response,
            ]);
            return $this->response($result['error_code'], $result);
        }
    }
}
