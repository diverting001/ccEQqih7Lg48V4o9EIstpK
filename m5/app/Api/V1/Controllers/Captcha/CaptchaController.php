<?php

namespace App\Api\V1\Controllers\Captcha;

use App\Api\Common\Controllers\BaseController;
use App\Api\V1\Service\Captcha\CaptchaService;
use Illuminate\Http\Request;

class CaptchaController extends BaseController
{
    /**
     * 查验验证码结果
     * 腾讯滑块验证码 params：【channel_id 渠道id  ticket票据  rand_str随机数 client_ip客户端ip】
     *
     */
    public function checkCaptcha(Request $request)
    {
        $params = $this->getContentArray($request);
        if (empty($params['channel_id'])) {
            $this->setErrorMsg('请求参数不能为空');
            return $this->outputFormat([], 400);
        }

        $captchaService = new CaptchaService();
        $result = $captchaService->verifyCode($params['channel_id'], $params);
        $this->setErrorMsg($result['msg']);
        return $this->outputFormat([], $result['code']);
    }
}
