<?php

namespace App\Api\V2\Controllers\Outlet;

use App\Api\Common\Controllers\BaseController;
use App\Api\V1\Service\Outlet\OutletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Neigou\Logger;

/**
 * 品牌商品的门店管理控制器
 */
class OutletController extends BaseController
{
    /**
     *
     * @param $request
     *
     * @return array
     */
    public function getList(Request $request)
    {
        $params = $this->getContentArray($request);

        $validator = Validator::make($params, [
            'page' => "required|integer|gt:0",
            'page_size' => 'required|integer|gt:0',
            'outlet_ids' => 'present|array',
            'outlet_ids.*' => 'integer|gt:0',
            'outlet_names' => 'present|array',
            'outlet_names.*' => 'distinct',
            'brand_ids' => 'present|array',
            'brand_ids.*' => 'integer|gt:0',
            'brand_rule_ids' => 'sometimes|array',
            'brand_rule_ids.*' => 'integer|gt:0',
            "province_id" => 'integer|gte:0',
            "city_id" => 'integer|gte:0',
            "area_id" => 'integer|gte:0',
            'order' => 'present|array',
            'order.*.by' => 'in:asc,desc',
            'order.*.type' => 'in:int,string,geo_point',
            'order.*.params' => 'required_if:order.*.type,geo_point',
            'order.*.params.lat' => 'required_if:order.*.type,geo_point',
            'order.*.params.lon' => 'required_if:order.*.type,geo_point',
        ]);

        if ($validator->fails()) {
            $this->setErrorMsg($validator->errors()->getMessages());
            return $this->outputFormat([], 400);
        }


        if ($validator->fails()) {
            $this->setErrorMsg($validator->errors()->getMessages());
            return $this->outputFormat([], 400);
        }

        if(isset($params['province_id']) && !is_int($params['province_id'])){
            $this->setErrorMsg('outlet.city_id 不是整数');
            return $this->outputFormat([], 400);
        }

        if(isset($params['city_id']) && !is_int($params['city_id'])){
            $this->setErrorMsg('city_id 不是整数');
            return $this->outputFormat([], 400);
        }

        if(isset($params['area_id']) && !is_int($params['area_id'])){
            $this->setErrorMsg('area_id 不是整数');
            return $this->outputFormat([], 400);
        }

        if(isset($params['is_scanning_code']) && !is_int($params['is_scanning_code'])){
            $this->setErrorMsg('is_scanning_code 不是整数');
            return $this->outputFormat([], 400);
        }

        if (isset($params['brand_ids']) && !is_array($params['brand_ids'])) {
            $this->setErrorMsg('brand_ids 不是数组');
            return $this->outputFormat([], 400);
        }


        if (isset($params['order']['coordinate']) && $params['order']['coordinate']) {
            $orderData = $params['order']['coordinate']['params'];
            if (!isset($orderData['lat']) || !is_numeric($orderData['lat'])) {
                $this->setErrorMsg('纬度参数错误');
                return $this->outputFormat([], 400);
            }
            if (!isset($orderData['lon']) || !is_numeric($orderData['lon'])) {
                $this->setErrorMsg('经度参数错误');
                return $this->outputFormat([], 400);
            }
        }

        $outletService = new OutletService();
        $outletList = $outletService->getListWithCoordinate($params);
        $this->setErrorMsg('请求成功');
        return $this->outputFormat($outletList['data']);

    }


}
