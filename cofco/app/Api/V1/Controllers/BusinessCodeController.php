<?php

namespace App\Api\V1\Controllers;

use App\Api\Common\Controllers\BaseController;
use App\Api\Model\Business\Business;
use Illuminate\Http\Request;

class BusinessCodeController extends BaseController
{

    //生成业务码
    public function CreateBusinessCode(Request $request)
    {
        $content_data = $this->getContentArray($request);

        $app_name = !isset($content_data['app_name']) ? '' : trim($content_data['app_name']);
        $platform_name = !isset($content_data['platform_name']) ? '' : trim($content_data['platform_name']);
        //
        $platform_code = !isset($content_data['platform_code']) ? '' : trim($content_data['platform_code']);
        $app_code = !isset($content_data['app_code']) ? '' : trim($content_data['app_code']);
        $code_name = !isset($content_data['code_name']) ? '' : trim($content_data['code_name']);
        $code_memo = !isset($content_data['code_memo']) ? '' : trim($content_data['code_memo']);


        $businessCode = date('YmdHis', time()) . rand(100000, 999999);

        if (empty($platform_code) || empty($app_code) || empty($code_name)) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat($content_data, 400);
        }
        $businessDB = new Business();

        $appCodeRet = $businessDB->getAppRow($app_code);
        $platFormCodeRet = $businessDB->getPlatFormRow($platform_code);
        if (empty($platFormCodeRet)) {
            $this->setErrorMsg('未找到该平台');
            return $this->outputFormat($content_data, 400);
        }
        if (empty($appCodeRet)) {
            $this->setErrorMsg('未找到该应用');
            return $this->outputFormat($content_data, 400);
        }
        $app_id = $appCodeRet[0]['id'];
        $platform_id = $platFormCodeRet[0]['id'];

        $create_time = time();
        $dataIn = array(
            'app_id' => $app_id,
            'platform_id' => $platform_id,
            'code' => $businessCode,
            'name' => $code_name,
            'create_time' => $create_time,
            'memo' => $code_memo,
        );
        $codeRet = $businessDB->createCode($dataIn);
        if ($codeRet === false) {
            $this->setErrorMsg('提交失败');
            return $this->outputFormat($dataIn, 10004);
        }
        $this->setErrorMsg('success');
        return $this->outputFormat($dataIn);

    }
    
}
