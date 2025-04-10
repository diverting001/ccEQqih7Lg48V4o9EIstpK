<?php
namespace App\Api\V1\Service\Message\Sms;

use App\Api\V1\Service\Message\MessageHandler;

class ShlianluSms extends MessageHandler
{
    use SmsTrait;
    const PLATFORM_NAME = "联麓短信";

    /**
     * 平台对应内部统一状态码
     * @return mixed|\string[][]
     */
    protected function errorMap()
    {
        //url https://shlianlu.com/console/document/errorCode
        return [
        ];
    }

    /**
     * 单个发送短信
     * @param $receiver
     * @param $messageTemplate
     * @param $item
     * @return array
     */
    protected function send($receiver, $messageTemplate, $item)
    {
        return $this->sendLogic($receiver, $messageTemplate, $item);
    }

    /**
     * 批量发送短信
     * @param $mobiles
     * @param $messageTemplate
     * @param $item
     * @return array
     */
    public function batchSend($mobiles, $messageTemplate, $item)
    {
        return $this->sendLogic($mobiles, $messageTemplate, $item);
    }


    /**
     * 短信发送调用
     * @param $receiver
     * @param $messageTemplate
     * @param $item
     * @param $action
     * @return array
     */
    public function sendLogic($receiver, $messageTemplate, $item, $action = 'trade/template/send')
    {
        //模板数据
        $templateParams = array_values($item['template_param']);

        if (!empty($messageTemplate->param)) {
            $templateParams = array();
            foreach ($messageTemplate->param as $v) {
                $templateParams[] = $item['template_param'][$v];
            }
        }

        //组装请求数据
        $sendData = $this->getSendData([$receiver], $messageTemplate->template_data, $templateParams);
        //发送短信
        $result = $this->httpClient($this->config['api_url'] . $action,  $sendData, 'post', [
            'Accept'=>'application/json',
            'Content-Type'=>'application/json;charset=utf-8'
        ]);
        $result = json_decode($result, true);
        if (isset($result['status'], $result['message']) && $result['status'] === '00' && $result['message'] === 'success') {
            return $this->response(self::CODE_SUCCESS, $result);
        }

        return $this->response(self::CODE_ERROR, $result);
    }

    /**
     * 获取请求数据
     * @param array $mobile
     * @param string $template_id
     * @param array $send_params
     * @return string
     */
    private function getSendData( $mobile, string $template_id, array $send_params) :string
    {
        $data = array(
            'MchId' => $this->config['mch_id'],
            'AppId' => $this->config['app_id'],
            'Version' => '1.1.0',
            'Type' => '3',
            'PhoneNumberSet' => $mobile,
            'TemplateId' => $template_id,
            'TemplateParamSet' => empty($send_params) ? [] : $send_params,
            'Timestamp' => time(),
            'SignType' => 'MD5'
        );

        $data['Signature'] = $this->getSign($data);

        return json_encode($data, JSON_UNESCAPED_UNICODE);
    }


    /**
     * 生成签名
     * @param $config
     * @param $appKey
     * @return string
     */
    private function getSign($params) :string
    {
        //签名步骤一：按字典序排序参数
        ksort($params);
        $string = $this->parseParams($params);

        //签名步骤二：在string后加入KEY
        $string = $string . "&key=".$this->config['key'];

        //签名步骤三：MD5加密或者HMAC-SHA256
        $string = md5($string);

        //签名步骤四：所有字符转为大写
        return strtoupper($string);
    }

    /**
     * 组装签名参数
     * @param $params
     * @return string
     */
    private function parseParams($params) :string
    {
        $buff = "";
        $noSignKey = array("Signature", "ContextParamSet", "TemplateParamSet", "SessionContextSet", "PhoneNumberSet", "SessionContext", "PhoneList", "phoneSet");
        foreach ($params as $k => $v)
        {
            if(!in_array($k, $noSignKey) && $v != "" && !is_array($v)){
                $buff .= $k . "=" . $v . "&";
            }
        }
        return trim($buff, "&");
    }


}
