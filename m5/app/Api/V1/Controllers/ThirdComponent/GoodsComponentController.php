<?php

namespace App\Api\V1\Controllers\ThirdComponent;

use App\Api\Common\Controllers\BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Api\V1\Service\ThirdComponent\Goods as GoodsComponentService;

/**
 * 商品组件
 */
class GoodsComponentController extends BaseController
{

    //获取组件
    public function GetEvaluateComponentInfo(Request $request)
    {
        //验证参数
        $content_data = $this->getContentArray($request);
        if (empty($content_data)) {
            $this->setErrorMsg('参数不能为空');
            return $this->outputFormat([], 400);
        }

        $rules = [
            'product_bn' => 'required',
            'extend_data.unselected_color' => ['sometimes', 'string', 'regex:/^#([a-fA-F0-9]{3}|[a-fA-F0-9]{6})$/'],
            'extend_data.selected_color' => ['sometimes', 'string', 'regex:/^#([a-fA-F0-9]{3}|[a-fA-F0-9]{6})$/'],
            'extend_data.show_title' => 'sometimes|in:1,0'
        ];
        $validator = Validator::make($content_data, $rules);

        if ($validator->fails()) {
            $this->setErrorMsg($validator->errors()->getMessages());
            return $this->outputFormat([], 400);
        }

        $componentService = new GoodsComponentService();
        $result = $componentService->getEvaluateComponentInfo($content_data['product_bn'], $content_data['extend_data']);

        $this->setErrorMsg('请求成功');
        return $this->outputFormat($result);
    }
}
