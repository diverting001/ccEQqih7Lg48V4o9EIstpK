<?php
namespace App\Api\V1\Service\OpenPlatform;

use App\Api\Model\OpenPlatform\OpenPlatformConfig as ConfigModel;
use App\Api\V1\Service\OpenPlatform\DingDing\DDInternalApp;
use App\Api\V1\Service\OpenPlatform\WeChat\JyflWeChat;
use App\Api\V1\Service\OpenPlatform\WeChat\WeChat;
use App\Api\V1\Service\OpenPlatform\WeChatMiniProgram\MiniProgram;
use App\Api\V1\Service\OpenPlatform\WeChatWork\WechatWorkInternalApp;
use App\Api\V1\Service\ServiceTrait;

class AdapterOpenPlatform
{
    use ServiceTrait;
    public function __call($method, $paramList)
    {
        $params = current($paramList);
        $app_id = $params['app_id'];
        $platform_type = $params['platform_type'];
        if (empty($app_id)) {
            return $this->Response(false, '应用id不能为空');
        }
        $configModel = app(ConfigModel::class);
        $configInfo = $configModel->getConfigInfoByPlatformType($app_id,$platform_type);
        if (!$configInfo) {
            return $this->Response(false, '应用配置不存在');
        }
        $configInfo = get_object_vars($configInfo);
        //处理类型 1、微信公众号 2、小程序 3、saas提供
        switch ($configInfo['adapter_type']) {
            case 1: //微信公众号
                /** @var  $service */
                $service = new WeChat($configInfo);
                break;
            case 2: //微信小程序
                $service = new MiniProgram($configInfo);
                break;
            case 3: //saas提供（微信公众号）
                $service = new JyflWeChat($configInfo);
                break;
            case 4: // 企业微信
                $service = new WechatWorkInternalApp($configInfo);
                break;
            case 5: // 钉钉
                $service = new DDInternalApp($configInfo);
                break;
            default:
                return $this->Response(false, '处理异常');
        }

        return $service->$method($params);
    }
}
