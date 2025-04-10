<?php
namespace App\Api\V1\Controllers;

use App\Api\Common\Controllers\BaseController;
use App\Api\V1\Service\OpenPlatform\AdapterOpenPlatform;
use App\Api\V1\Service\OpenPlatform\OpenPlatform;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Api\Logic\OpenPlatform\OpenPlatformConfig as OpenPlatformConfigLogic;

/**
 * 微信服务
 */
class OpenPlatformController extends BaseController
{
    /**
     * Notes:获取token
     * User: mazhenkang
     * Date: 2024/7/30 下午1:20
     * @param Request $request
     */
    public function GetAccessToken(Request $request)
    {
        $request = $this->getContentArray($request);

        if (empty($request['app_id'])) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }

        //平台类型 1、微信公众号 2、微信小程序
        if (empty($request['platform_type'])) {
            $this->setErrorMsg('类型不能为空');
            return $this->outputFormat([], 400);
        }

        /** @var OpenPlatform $service */
        $service = new AdapterOpenPlatform();

        $dataRes = $service->GetAccessToken($request);
        $this->setErrorMsg($dataRes['msg']);

        if(!$dataRes['status']){
            return $this->outputFormat([], 501);
        }

        return $this->outputFormat($dataRes['data'], 0);
    }


    /**
     * Notes:获取公众号用于调用微信JS接口的临时票据
     * User: mazhenkang
     * Date: 2024/7/31 下午3:38
     * @param Request $request
     * @return array
     */
    public function GetTicket(Request $request)
    {
        $request = $this->getContentArray($request);

        if (empty($request['app_id'])) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }

        //平台类型 1、微信公众号 2、微信小程序
        if (empty($request['platform_type'])) {
            $this->setErrorMsg('类型不能为空');
            return $this->outputFormat([], 400);
        }

        $type = $request['type'] ?: 'jsapi';
        if ($type == 'jsapi' && empty($request['params']['url'])) {
            $this->setErrorMsg('用于签名的网页URL不能为空');
            return $this->outputFormat([], 400);
        }

        /** @var OpenPlatform $service */
        $service = new AdapterOpenPlatform();

        $dataRes = $service->GetTicket($request);
        $this->setErrorMsg($dataRes['msg']);

        if(!$dataRes['status']){
            return $this->outputFormat([], 501);
        }

        return $this->outputFormat($dataRes['data'], 0);
    }

    /**
     * Notes:获取网页授权access_token
     * User: mazhenkang
     * Date: 2024/7/31 下午4:18
     * @param Request $request
     * @return array
     */
    public function GetOauth2AccessToken(Request $request)
    {
        $request = $this->getContentArray($request);

        if (empty($request['app_id']) || empty($request['code'])) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }

        //平台类型 1、微信公众号 2、微信小程序
        if (empty($request['platform_type'])) {
            $this->setErrorMsg('类型不能为空');
            return $this->outputFormat([], 400);
        }

        /** @var OpenPlatform $service */
        $service = new AdapterOpenPlatform();

        $dataRes = $service->GetOauth2AccessToken($request);
        $this->setErrorMsg($dataRes['msg']);

        if(!$dataRes['status']){
            return $this->outputFormat([], 501);
        }

        return $this->outputFormat($dataRes['data'], 0);
    }

    /**
     * Notes:添加配置
     * User: mazhenkang
     * Date: 2024/8/16 下午4:02
     * @param Request $request
     */
    public function saveConfig(Request $request)
    {
        $params = $this->getContentArray($request);

        $validator = Validator::make($params, [
            'platform_type' => 'required',
            'app_id' => 'required',
            'app_secret' => 'required'
        ]);

        if ($validator->fails()) {
            $this->setErrorMsg($validator->errors()->getMessages());
            return $this->outputFormat([], 400);
        }

        $data = [
            'platform_type' => $params['platform_type'],
            'app_id' => $params['app_id'],
            'app_secret' => $params['app_secret'],
            'adapter_type' => $params['adapter_type'] ?: 1,
            'auto_refresh_token' => $params['auto_refresh_token'] ?: 0,
            'memo' => $params['memo'] ?: ''
        ];
        $OpenPlatformConfigLogic = new OpenPlatformConfigLogic();
        $msg = '';
        $res = $OpenPlatformConfigLogic->saveConfig($data, $msg);
        if (!$res) {
            $this->setErrorMsg($msg);
            return $this->outputFormat([], 501);
        }

        return $this->outputFormat([], 0);
    }
}
