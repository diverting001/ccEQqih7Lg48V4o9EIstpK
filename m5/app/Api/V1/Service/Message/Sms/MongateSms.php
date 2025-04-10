<?php

namespace App\Api\V1\Service\Message\Sms;

use App\Api\V1\Service\Message\MessageHandler;
use Illuminate\Support\Str;

class MongateSms extends MessageHandler
{
    use SmsTrait;

    const PLATFORM_NAME = "梦网短信";

    /**
     * 平台对应内部统一状态码
     * @return mixed|\string[][]
     */
    protected function errorMap()
    {
        return [
            -100001 => ['鉴权不通过,请检查账号,密码,时间戳,固定串,以及MD5算法是否按照文档要求进行设置', self::CODE_SIGN_ERROR],
            -100002 => ['用户多次鉴权不通过,请检查账号,密码,时间戳,固定串,以及MD5算法是否按照文档要求进行设置', self::CODE_SIGN_ERROR],
            -100003 => ['用户欠费', self::CODE_BALANCE_ERROR],
            -100004 => ['custid或者exdata字段填写不合法', self::CODE_FIELD_FAIL],
            -100011 => ['短信内容超长', self::CODE_FIELD_FAIL],
            -100012 => ['手机号码不合法', self::CODE_FIELD_FAIL],
            -100014 => ['手机号码超过最大支持数量（1000）', self::CODE_FIELD_FAIL],
            -100029 => ['端口绑定失败', self::CODE_ERROR],
            -100056 => ['用户账号登录的连接数超限', self::CODE_ERROR],
            -100057 => ['用户账号登录的IP错误', self::CODE_ERROR],
            -100126 => ['短信有效存活时间无效', self::CODE_ERROR],
            -100252 => ['业务类型不合法(超长或包含非字母数字字符)', self::CODE_FIELD_FAIL],
            -100253 => ['自定义参数超长', self::CODE_FIELD_FAIL],
            -100999 => ['平台数据库内部错误', self::CODE_ERROR],
        ];
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

    protected function send($mobile, $messageTemplate, $item)
    {
        return $this->publicSend($mobile, $messageTemplate, $item);
    }

    /**
     * 相同内容群发
     * $data:请求数据集合
     * @param $mobiles array
     * @param $messageTemplate
     * @param $item
     * @return array
     */
    public function batchSend($mobiles, $messageTemplate, $item)
    {
        return $this->publicSend(implode(',', $mobiles), $messageTemplate, $item, 'batch_send');
    }

    private function publicSend($mobiles, $messageTemplate, $item, $method = 'single_send')
    {
        // 解析模板数据
        $templateData = $this->templateMatch($messageTemplate, $item['template_param']);

        $data = array();
        // 设置手机号码 每个手机号之间用,隔开(必填)
        $data['mobile'] = $mobiles;//'13243757111,13243757112';
        $data['userid'] = strtoupper($this->config['user']);//用户名转化为大写
        $encrypt = $this->encryptPwd($this->config['user'], $this->config['password']);//密码进行MD5加密
        $data['pwd'] = $encrypt['pwd'];//获取MD5加密后的密码
        $data['timestamp'] = $encrypt['time'];//获取加密时间戳
        $data['content'] = $this->encryptContent($templateData);//短信内容进行urlencode加密
        $post_data = json_encode($data);//将数组转化为JSON格式
        $result = $this->httpClient($this->config['api_url'].$method, $post_data, 'post', [
            'Accept'=>'text/plain;charset=utf-8',
            'Content-Type'=>'application/json',
            'charset=utf-8',
            'Expect'=>'',
            'Connection'=>'Close'
        ]);//根据请求类型进行请求
        return $this->formatResult($result);
    }

    /**
     * 密码加密
     * $userid：用户账号
     * $pwd：用户密码
     * @param $userId
     * @param $pwd
     * @return array
     */
    private function encryptPwd($userId, $pwd): array
    {
        $char = '00000000';//固定字符串
        $time = date('mdHis', time());//时间戳
        $pwd = md5($userId.$char.$pwd.$time);//拼接字符串进行加密
        return ['pwd' => $pwd, 'time' => $time];
    }

    /**
     * 短信内容加密
     * $content：短信内容
     * @param $content
     * @return string
     */
    public function encryptContent($content)
    {
        //短信内容转化为GBK格式再进行urlencode格式加密
        return urlencode(iconv('UTF-8', 'GBK', $content));
    }

    public function formatResult($result)
    {
        $result = preg_replace('/\"msgid":(\d{1,})./', '"msgid":"\\1",', $result);//正则表达式匹配所有msgid转化为字符串
        $result = json_decode($result, true);//将返回结果集json格式解析转化为数组格式

        if (isset($result['result']) && $result['result'] != 0) {//域名问题请求失败或不存在返回结果
            return $this->response(self::CODE_ERROR, $result);
        } else {
            return $this->response(self::CODE_SUCCESS, $result);
        }
    }

}
