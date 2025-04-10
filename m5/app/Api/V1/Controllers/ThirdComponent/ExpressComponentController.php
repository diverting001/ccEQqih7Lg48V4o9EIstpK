<?php

namespace App\Api\V1\Controllers\ThirdComponent;

use App\Api\Common\Controllers\BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Api\V1\Service\ThirdComponent\Express as ExpressComponentService;

/**
 * 物流组件
 */
class ExpressComponentController extends BaseController
{

    //获取组件
    public function GetExpressComponentInfo(Request $request)
    {
        //验证参数
        $content_data = $this->getContentArray($request);
        if (empty($content_data)) {
            $this->setErrorMsg('参数不能为空');
            return $this->outputFormat([], 400);
        }

        $rules = [
            'order_id' => 'required',
            'extend_data.show_title' => 'sometimes|in:1,0'
        ];
        $validator = Validator::make($content_data, $rules);

        if ($validator->fails()) {
            $this->setErrorMsg($validator->errors()->getMessages());
            return $this->outputFormat([], 400);
        }

        $componentService = new ExpressComponentService();
        $result = $componentService->getExpressComponentInfo($content_data['order_id'], $content_data['extend_data']);

        $this->setErrorMsg('请求成功');
        return $this->outputFormat($result);
    }
}
