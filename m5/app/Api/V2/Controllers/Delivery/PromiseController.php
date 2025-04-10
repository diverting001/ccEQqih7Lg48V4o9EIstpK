<?php

namespace App\Api\V2\Controllers\Delivery;

use App\Api\Common\Controllers\BaseController;
use App\Api\V2\Service\Delivery\Promise;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * 货品预计送达时间
 */
class PromiseController extends BaseController
{
    //获取货品预计配送时间
    public function GetProductPromiseInfo(Request $request)
    {
        //验证参数
        $content_data = $this->getContentArray($request);

        $rules = [
            'province' => 'required',
            'city' => 'required',
            'county' => 'required',
            'product.product_bn' => 'required',
            'product.num' => 'required',
        ];
        $validator = Validator::make($content_data, $rules);
        if ($validator->fails()) {
            $this->setErrorMsg($validator->errors()->getMessages());
            return $this->outputFormat([], 400);
        }

        //组装请求数据
        $product = [
            'product_bn'=>$content_data['product']['product_bn'],
            'num'=>$content_data['product']['num'],
        ];

        $area = [
            'province' => $content_data['province'],
            'city' => $content_data['city'],
            'county' => $content_data['county'],
            'town' => $content_data['town'],
            'addr' => $content_data['addr'],
        ];
        $extendData = !isset($content_data['extend_data']) ? array() : $content_data['extend_data'];

        $promise = new Promise();
        $result = $promise->getProductPromiseInfo($product, $area, $extendData);

        $this->setErrorMsg('请求成功');
        return $this->outputFormat($result);
    }
}
