<?php

namespace App\Api\V1\Controllers\Brand;

use App\Api\Common\Controllers\BaseController;
use App\Api\V1\Service\Brand\BrandRule as BrandRuleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * 品牌商品的门店管理控制器
 */
class BrandRuleController extends BaseController
{

    /**
     * 创建规则
     * @param Request $request
     * @return array
     */
    public function CreateBrandRule(Request $request)
    {
        $data = $this->getContentArray($request);

        $validator = Validator::make($data, [
            'rule_name' => 'required|string',
            'outlet_ids' => 'required|array',

        ]);
        if ($validator->fails()) {
            $this->setErrorMsg($validator->errors()->getMessages());
            return $this->outputFormat([], 400);
        }

        $brandRuleServie = new BrandRuleService();
        $res = $brandRuleServie->createBrandRule($data);
        if ($res['error_code'] != 0) {
            $this->setErrorMsg($res['error_msg']);
            return $this->outputFormat([], 400);
        } else {
            $this->setErrorMsg('请求成功');
            return $this->outputFormat($res['data'], 0);
        }
    }

    /**
     * 更新规则
     * @param Request $request
     * @return array
     */
    public function UpdateBrandRule(Request $request)
    {
        $data = $this->getContentArray($request);

        $validator = Validator::make($data, [
            'brand_rule_id' => "required|integer|gt:0",
            'rule_name' => 'sometimes|string',
            'outlet_ids' => 'sometimes|array',

        ]);
        if ($validator->fails()) {
            $this->setErrorMsg($validator->errors()->getMessages());
            return $this->outputFormat([], 400);
        }
        $brandRuleServie = new BrandRuleService();
        $res = $brandRuleServie->updateBrandRule($data);
        if ($res['error_code'] != 0) {
            $this->setErrorMsg($res['error_msg']);
            return $this->outputFormat([], 400);
        } else {
            $this->setErrorMsg('请求成功');
            return $this->outputFormat([], 0);
        }
    }

    /**
     * 删除规则
     * @param Request $request
     * @return array
     */
    public function DeleteBrandRule(Request $request)
    {
        $data = $this->getContentArray($request);

        $validator = Validator::make($data, [
            'brand_rule_id' => 'required|integer|gt:0',
        ]);
        if ($validator->fails()) {
            $this->setErrorMsg($validator->errors()->getMessages());
            return $this->outputFormat([], 400);
        }

        $brandRuleServie = new BrandRuleService();
        $res = $brandRuleServie->delBrandRule($data['brand_rule_id']);

        if ($res['error_code'] != 0) {
            $this->setErrorMsg($res['error_msg']);
            return $this->outputFormat([], 400);
        } else {
            $this->setErrorMsg('请求成功');
            return $this->outputFormat([]);
        }
    }

    /**
     * 获取规则列表
     * @param Request $request
     * @return array
     */
    public function GetBrandRuleList(Request $request)
    {
        $data = $this->getContentArray($request);

        $validator = Validator::make($data, [
            'rule_name' => 'sometimes|string',
            'brand_rule_ids' => 'sometimes|array',
            'page' => 'sometimes|int',
            'limit' => 'sometimes|int',
        ]);
        if ($validator->fails()) {
            $this->setErrorMsg($validator->errors()->getMessages());
            return $this->outputFormat([], 400);
        }

        //页码 页数
        $options = array(
            'page' => isset($data['page']) ? $data['page'] : 1,
            'limit' => isset($data['limit']) ? $data['limit'] : 20,
        );

        $filter = array(
            'rule_name' => $data['rule_name'],
            'brand_rule_ids' => $data['brand_rule_ids']
        );

        $brandRuleServie = new BrandRuleService();
        $res = $brandRuleServie->getList($filter, $options);

        if ($res['error_code'] != 0) {
            $this->setErrorMsg($res['error_msg']);
            return $this->outputFormat([], 400);
        } else {
            $this->setErrorMsg('请求成功');
            return $this->outputFormat($res['data']);
        }

    }

    /**
     * 获取规则门店关联
     * @param Request $request
     * @return array
     */
    public function GetBrandRuleOutletList(Request $request) {
        $data = $this->getContentArray($request);

        $validator = Validator::make($data, [
            'outlet_ids' => 'present|array',
            'outlet_ids.*' => 'integer|gt:0',
            'brand_rule_ids' => 'present|array',
            'brand_rule_ids.*' => 'integer|gt:0',
        ]);
        if ($validator->fails()) {
            $this->setErrorMsg($validator->errors()->getMessages());
            return $this->outputFormat([], 400);
        }


        $filter = array(
            'outlet_ids' => $data['outlet_ids'],
            'brand_rule_ids' => $data['brand_rule_ids']
        );
        $brandRuleServie = new BrandRuleService();
        $res = $brandRuleServie->getBrandRuleOutlet($filter);

        if ($res['error_code'] != 0) {
            $this->setErrorMsg($res['error_msg']);
            return $this->outputFormat([], 400);
        } else {
            $this->setErrorMsg('请求成功');
            return $this->outputFormat($res['data']);
        }
    }

}
