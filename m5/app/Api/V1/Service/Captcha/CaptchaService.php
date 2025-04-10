<?php

namespace App\Api\V1\Service\Captcha;

use App\Api\Model\Captcha\Channel as ChannelModel;

class CaptchaService
{

    public function verifyCode($channelId, $params)
    {
        //校验渠道
        $channelModel = new ChannelModel();
        $channelInfo = $channelModel->findChannelByChannelId($channelId);
        if (empty($channelInfo)) {
            return $this->returnData(400, 'channel不存在: ' . $channelId);
        }

        //配置信息
        $config = json_decode($channelInfo->config, true);
        $config['type'] = $channelInfo->type;

        $classObj = $this->getChannelClass($channelInfo->channel, $config);
        if ($classObj === false) {
            return $this->returnData(401, '该实现类不存在');
        }
        if ( ! method_exists($classObj, 'verifyCode')) {
            return $this->returnData(401, '处理方法不存在');
        }

        $msg = '';
        $result = $classObj->verifyCode($params, $msg);
        if ( ! $result) {
            return $this->returnData(402, $msg);
        }

        return $this->returnData(0, '验证通过');
    }

    /**
     * 获取实现类
     * @param string $channel 渠道信息
     * @param array $config 配置信息
     * @return TencentCaptcha|TencentminiCaptcha|bool
     */
    public function getChannelClass($channel, $config)
    {
        $className = 'App\\Api\\V1\\Service\\Captcha\\' . ucfirst(strtolower($channel)) . 'Captcha';
        if ( ! class_exists($className)) {
            return false;
        }

        return new $className($config);
    }

    private function returnData($code = 0, $msg, $data = [])
    {
        return array(
            'code' => $code,
            'msg'  => $msg,
            'data' => $data
        );
    }
}
