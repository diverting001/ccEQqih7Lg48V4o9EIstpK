<?php

namespace App\Api\V1\Controllers\Outlet;

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
     * 基本参数列表
     * @var string[]
     */
    protected $params_data = [
        "outlet_name",
        "outlet_logo",
        "outlet_description",
        "outlet_address",
        "outlet_phone_number",
        "longitude",
        "latitude",
        "outlet_begin_time",
        "outlet_end_time",
        "outlet_date_horizon",
        "province_id",
        "city_id",
        "area_id",
        "brand_id",
        "is_scanning_code",
        "scanning_code_type"

    ];

    /**
     * 创建单条/多条门店数据
     * @return array
     */
    public function create(Request $request)
    {
        $data = $this->getContentArray($request);

        $validator = Validator::make($data, [
            'outlet' => "required|array",
            'outlet.*.outlet_name' => 'required',
            'outlet.*.outlet_logo' => 'required',
            'outlet.*.outlet_address' => 'required',
            'outlet.*.outlet_phone_number' => 'present',
            'outlet.*.longitude' => 'required|numeric',
            'outlet.*.latitude' => 'required|numeric',
            'outlet.*.province_id' => 'required|integer|gt:0',
            'outlet.*.city_id' => 'required|integer|gt:0',
            'outlet.*.area_id' => 'required|integer|gt:0',
        ]);
        if ($validator->fails()) {
            $this->setErrorMsg($validator->errors()->getMessages());
            return $this->outputFormat([], 400);
        }

        $array = [];
        $outlet = $data['outlet'] ?? [];
        $count = count($outlet);
        $is_batch = !($count > 1);
        if ($is_batch) {
            $array = $this->_paramsMerge($outlet[0]);
        } else {
            $outlet = $data['outlet'];
            foreach ($outlet as $outletV) {
                $array[] = $this->_paramsMerge($outletV);
            }
        }

        $outletService = new OutletService();
        $res = $outletService->create($array, $is_batch);
        if ($res['code'] != 0) {
            Logger::General('service.outlet.create.err', ['request_data' => $data, 'response' => $res]);
            $this->setErrorMsg($res['msg']);
            return $this->outputFormat([], 400);
        } else {
            $ret = [];
            if ($is_batch) {
                $ret = ['outlet_id' => $res['data']];
            }
            $this->setErrorMsg('请求成功');
            return $this->outputFormat($ret);
        }
    }

    /**
     * 更新门店信息
     * @param Request $request
     * @return array
     */
    public function update(Request $request)
    {
        $data = $this->getContentArray($request);
        $this->params_data[] = 'outlet_id';
        $data = $this->_paramsMerge($data);

        $validator = Validator::make($data, [
            'outlet_id' => 'required|integer|gt:0',
            'outlet_name' => 'required',
            'outlet_logo' => 'required',
            'outlet_address' => 'required',
            'outlet_phone_number' => 'required',
            'longitude' => 'required|numeric',
            'latitude' => 'required|numeric',
            'province_id' => 'required|integer|gt:0',
            'city_id' => 'required|integer|gt:0',
            'area_id' => 'required|integer|gt:0',
        ]);
        if ($validator->fails()) {
            $this->setErrorMsg($validator->errors()->getMessages());
            return $this->outputFormat([], 400);
        }

        $outletService = new OutletService();
        $res = $outletService->update($data);
        if ($res['code'] != 0) {
            Logger::General('service.outlet.update.err', ['request_data' => $data, 'response' => $res]);
            $this->setErrorMsg($res['msg']);
            return $this->outputFormat([], 400);
        } else {
            $this->setErrorMsg('请求成功');
            return $this->outputFormat([]);
        }
    }

    /**
     * 删除门店信息
     * @param Request $request
     * @return array
     */
    public function delete(Request $request)
    {
        $data = $this->getContentArray($request);
        $this->params_data[] = 'outlet_id';
        $data = $this->_paramsMerge($data);

        $validator = Validator::make($data, [
            'outlet_id' => 'required|integer|gt:0',
        ]);
        if ($validator->fails()) {
            $this->setErrorMsg($validator->errors()->getMessages());
            return $this->outputFormat([], 400);
        }

        $outletService = new OutletService();
        $res = $outletService->delete($data);
        if ($res['code'] != 0) {
            Logger::General('service.outlet.delete.err', ['request_data' => $data, 'response' => $res]);
            $this->setErrorMsg($res['msg']);
            return $this->outputFormat([], 400);
        } else {
            $this->setErrorMsg('请求成功');
            return $this->outputFormat([]);
        }
    }

    /**
     * 获取列表
     * @param Request $request
     * @return array
     */
    public function getList(Request $request)
    {
        $data = $this->getContentArray($request);
        $this->params_data[] = 'page';
        $this->params_data[] = 'page_size';
        $this->params_data[] = 'outlet_ids';
        $this->params_data[] = 'outlet_names';
        $data = $this->_paramsMerge($data);

        $validator = Validator::make($data, [
            'page' => "required|integer|gt:0",
            'page_size' => 'required|integer|gt:0',
            'outlet_ids' => 'present|array',
            'outlet_ids.*' => 'integer|gt:0',
            'outlet_names' => 'present|array',
            'outlet_names.*' => 'distinct',
            "province_id" => 'integer|gte:0',
            "city_id" => 'integer|gte:0',
            "area_id" => 'integer|gte:0',
        ]);
        if ($validator->fails()) {
            $this->setErrorMsg($validator->errors()->getMessages());
            return $this->outputFormat([], 400);
        }
        // 页码
        $page = isset($data['page']) ? $data['page'] : 1;

        // 页数
        $pageSize = isset($data['page_size']) ? $data['page_size'] : 20;

        //指定的门店ID
        $outletIds = $data['outlet_ids'] ?? [];

        //指定门店名字
        $outletNames = $data['outlet_names'] ?? [];

        $address = [
            "province_id" => (int)$data['province_id'],
            "city_id" => (int)$data['city_id'],
            "area_id" => (int)$data['area_id'],
        ];

        $outletService = new OutletService();
        $res = $outletService->getList($page, $pageSize, $outletIds, $outletNames,$address);
        if ($res['code'] != 0) {
            Logger::General('service.outlet.getList.err', ['request_data' => $data, 'response' => $res]);
            $this->setErrorMsg($res['msg']);
            return $this->outputFormat([], 400);
        } else {
            $this->setErrorMsg('请求成功');
            return $this->outputFormat($res['data']);
        }

    }

    /**
     * 获取指定门店信息
     * @param Request $request
     * @return array
     */
    public function getInfo(Request $request)
    {
        $data = $this->getContentArray($request);
        $this->params_data[] = 'outlet_id';
        $data = $this->_paramsMerge($data);

        $validator = Validator::make($data, [
            'outlet_id' => 'required|integer|gt:0',
        ]);
        if ($validator->fails()) {
            $this->setErrorMsg($validator->errors()->getMessages());
            return $this->outputFormat([], 400);
        }

        $outletService = new OutletService();
        $res = $outletService->getInfo($data);
        if ($res['code'] != 0) {
            Logger::General('service.outlet.getInfo.err', ['request_data' => $data, 'response' => $res]);
            $this->setErrorMsg($res['msg']);
            return $this->outputFormat([], 400);
        } else {
            $this->setErrorMsg('请求成功');
            return $this->outputFormat($res['data']);
        }
    }


    public function getOutletAgg(Request $request)
    {
        $params = $this->getContentArray($request);

        if (isset($params['filter']['brand_id']) && !is_array($params['filter']['brand_id'])) {
            $this->setErrorMsg('brand_id 不是数组');
            return $this->outputFormat([], 400);
        }

        if (isset($params['filter']['province_id']) && !is_int($params['filter']['city_id'])) {
            $this->setErrorMsg('city_id 不是整数');
            return $this->outputFormat([], 400);
        }

        if (isset($params['filter']['city_id']) && !is_int($params['filter']['city_id'])) {
            $this->setErrorMsg('city_id 不是整数');
            return $this->outputFormat([], 400);
        }

        if (isset($params['filter']['area_id']) && !is_int($params['filter']['area_id'])){
            $this->setErrorMsg('area_id 不是整数');
            return $this->outputFormat([], 400);
        }

        if (isset($params['filter']['is_scanning_code']) && !is_int($params['filter']['is_scanning_code'])){
            $this->setErrorMsg('is_scanning_code 不是整数');
            return $this->outputFormat([], 400);
        }

        if (isset($params['filter']['brand_rule_ids']) && !is_array($params['filter']['brand_rule_ids'])) {
            $this->setErrorMsg('brand_rule_ids 不是数组');
            return $this->outputFormat([], 400);
        }

        $outletService = new OutletService();
        $goods_list = $outletService->getOutletAgg($params);    //商品列表open
        $this->setErrorMsg('请求成功');
        return $this->outputFormat($goods_list);
    }

    /**
     * 只获取当前需要的参数
     * @param $data
     * @return array
     */
    protected function _paramsMerge($data): array
    {
        $new_rules = [];
        foreach ($this->params_data as $value) {
            if (array_key_exists($value, $data)) {
                $new_rules[$value] = $data[$value];
            }
        }
        return $new_rules;
    }
}
