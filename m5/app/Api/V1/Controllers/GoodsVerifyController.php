<?php

namespace App\Api\V1\Controllers;

use App\Api\Common\Controllers\BaseController;
use App\Api\V1\Service\GoodsVerify\GoodsVerify;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * 商品校验
 */
class GoodsVerifyController extends BaseController
{
    /**
     * 匹配商品列表
     * @param Request $request
     * @return array
     */
    public function GetMatchGoodsList(Request $request):array {
        $contentData = $this->getContentArray($request);
        if (empty($contentData)) {
            $this->setErrorMsg('参数不能为空');
            return $this->outputFormat([], 400);
        }

        $rules = [
            '*.match_id' => 'required',
            '*.match_type' => 'in:url',
            '*.match_param' => 'required',
        ];
        $validator = Validator::make($contentData, $rules);
        if ($validator->fails()) {
            $this->setErrorMsg($validator->errors()->getMessages());
            return $this->outputFormat([], 400);
        }
        $componentService = new GoodsVerify;
        $result = $componentService->GetMatchGoodsList($contentData);
        $this->setErrorMsg('请求成功');
        return $this->outputFormat($result);
    }
}
