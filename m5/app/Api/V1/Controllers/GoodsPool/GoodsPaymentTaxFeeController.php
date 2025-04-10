<?php

namespace App\Api\V1\Controllers\GoodsPool;

use App\Api\Common\Controllers\BaseController;
use App\Api\Logic\GoodsPool\GoodsPaymentTaxFeeLogic;
use Illuminate\Http\Request;

class GoodsPaymentTaxFeeController extends BaseController
{
    /**
     * 根据给定的公司id和单bn，获取到单个货品的税率
     * @param Request $request
     * @return array|void
     */
    public function getRateSingle(Request $request)
    {
        $requestData = $this->getContentArray($request);
        if (empty($requestData['company_id']) || empty($requestData['product_bn'])) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }

        $GoodsPaymentTaxFee = new GoodsPaymentTaxFeeLogic();
        $ret = $GoodsPaymentTaxFee->getRateSingle($requestData['company_id'], $requestData['product_bn']);
        if ($ret['code'] == 0) {
            return $this->outputFormat($ret['data'], 0);
        } else {
            $this->setErrorMsg('获取失败 ' . $ret['msg']);
            return $this->outputFormat(array(), 400);
        }
    }

    /**
     * 根据给定的公司id和单bn，获取到单个货品是否要收服务费
     * @param Request $request
     * @return array|void
     */
    public function getRateSingleNotRate(Request $request)
    {
        $requestData = $this->getContentArray($request);
        if (empty($requestData['company_id']) || empty($requestData['product_bn'])) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }

        $GoodsPaymentTaxFee = new GoodsPaymentTaxFeeLogic();
        $ret = $GoodsPaymentTaxFee->getRateSingleNotRate($requestData['company_id'], $requestData['product_bn'], false);
        if ($ret['code'] == 0) {
            return $this->outputFormat($ret['data'], 0);
        } else {
            $this->setErrorMsg('获取失败 ' . $ret['msg']);
            return $this->outputFormat(array(), 400);
        }
    }

    /**
     * 根据给定的公司id和多个bn，获取到多个货品的税率
     * @param Request $request
     * @return array|void
     */
    public function getRateMulti(Request $request)
    {
        $requestData = $this->getContentArray($request);
        if (empty($requestData['company_id']) || empty($requestData['product_bns'])) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }

        $GoodsPaymentTaxFee = new GoodsPaymentTaxFeeLogic();
        $ret = $GoodsPaymentTaxFee->getRateMulti($requestData['company_id'], $requestData['product_bns']);
        if ($ret['code'] == 0) {
            return $this->outputFormat($ret['data'], 0);
        } else {
            $this->setErrorMsg('获取失败 ' . $ret['msg']);
            return $this->outputFormat(array(), 400);
        }
    }

    /**
     * 根据id获取服务费详情
     * @param Request $request
     * @return array
     */
    public function getTaxFeeInfo(Request $request)
    {
        $requestData = $this->getContentArray($request);
        if (empty($requestData['tax_fee_id'])) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }
        $GoodsPaymentTaxFee = new GoodsPaymentTaxFeeLogic();
        $ret = $GoodsPaymentTaxFee->getTaxFeeInfo($requestData['tax_fee_id']);
        return $this->outputFormat($ret['data'], 0);
    }

    /**
     * 新增服务费分组记录
     * @param Request $request
     * @return array
     */
    public function createTaxFee(Request $request)
    {
        $requestData = $this->getContentArray($request);
        list($name, $weight, $cash_rate, $point_rate) = $this->getParams($requestData);
        $GoodsPaymentTaxFee = new GoodsPaymentTaxFeeLogic();
        $ret = $GoodsPaymentTaxFee->createTaxFee($name, $weight, $cash_rate, $point_rate);
        if ($ret['code'] == 0) {
            return $this->outputFormat($ret['data'], 0);
        }
        $this->setErrorMsg($ret['msg']);
        return $this->outputFormat([], 400);
    }

    /**
     * 修改服务费分组记录
     * @param Request $request
     * @return array
     */
    public function updateTaxFee(Request $request)
    {
        $requestData = $this->getContentArray($request);
        list($name, $weight, $cash_rate, $point_rate) = $this->getParams($requestData);
        $tax_fee_id = $requestData['tax_fee_id'];
        if (empty($tax_fee_id) || !is_numeric($tax_fee_id) || $tax_fee_id < 0) {
            $this->setErrorMsg('id参数错误');
            return $this->outputFormat([], 400);
        }
        $GoodsPaymentTaxFee = new GoodsPaymentTaxFeeLogic();
        $ret = $GoodsPaymentTaxFee->updateTaxFee($tax_fee_id, $name, $weight, $cash_rate, $point_rate);
        if ($ret['code'] == 0) {
            return $this->outputFormat($ret['data'], 0);
        }
        $this->setErrorMsg($ret['msg']);
        return $this->outputFormat([], 400);
    }

    /**
     * 删除服务费分组记录
     * @param Request $request
     * @return array
     */
    public function deleteTaxFee(Request $request)
    {
        $requestData = $this->getContentArray($request);
        $tax_fee_id = $requestData['tax_fee_id'];
        if (empty($tax_fee_id) || !is_numeric($tax_fee_id) || $tax_fee_id < 0) {
            $this->setErrorMsg('id参数错误');
            return $this->outputFormat([], 400);
        }
        $GoodsPaymentTaxFee = new GoodsPaymentTaxFeeLogic();
        $ret = $GoodsPaymentTaxFee->deleteTaxFee($tax_fee_id);
        if ($ret['code'] == 0) {
            return $this->outputFormat($ret['data'], 0);
        }
        $this->setErrorMsg($ret['msg']);
        return $this->outputFormat([], 400);
    }

    /**
     * 推送服务费分组记录
     * @param Request $request
     * @return array
     */
    public function sendTaxFee(Request $request)
    {
        $requestData = $this->getContentArray($request);
        $tax_fee_id = $requestData['tax_fee_id'];
        if (empty($tax_fee_id) || !is_numeric($tax_fee_id) || $tax_fee_id < 0) {
            $this->setErrorMsg('id参数错误');
            return $this->outputFormat([], 400);
        }
        $visible = $requestData['visible'];
        if (!in_array($visible, [1, 2, 3])) {
            $this->setErrorMsg('指定作用范围参数错误');
            return $this->outputFormat([], 400);
        }
        $company_ids = $requestData['company_ids'];
        if (empty($company_ids) && $visible != 1) {
            $this->setErrorMsg('公司id参数错误');
            return $this->outputFormat([], 400);
        }
        $state = $requestData['state'];
        if (!in_array($state, [0, 1])) {
            $this->setErrorMsg('状态参数错误');
            return $this->outputFormat([], 400);
        }
        $GoodsPaymentTaxFee = new GoodsPaymentTaxFeeLogic();
        $ret = $GoodsPaymentTaxFee->sendTaxFee($tax_fee_id, $visible, $company_ids, $state);
        if ($ret['code'] == 0) {
            return $this->outputFormat($ret['data'], 0);
        }
        $this->setErrorMsg($ret['msg']);
        return $this->outputFormat([], 400);
    }

    /**
     * 新增服务费分组的货品记录【单个】
     * @param Request $request
     * @return array
     */
    public function addTaxFeeProduct(Request $request)
    {
        $requestData = $this->getContentArray($request);
        $tax_fee_id = $requestData['tax_fee_id'];
        if (empty($tax_fee_id) || !is_numeric($tax_fee_id) || $tax_fee_id < 0) {
            $this->setErrorMsg('id参数错误');
            return $this->outputFormat([], 400);
        }
        $product_bn = $requestData['product_bn'];
        if (empty($product_bn)) {
            $this->setErrorMsg('bn参数错误');
            return $this->outputFormat([], 400);
        }
        $GoodsPaymentTaxFee = new GoodsPaymentTaxFeeLogic();
        $ret = $GoodsPaymentTaxFee->addTaxFeeProduct($tax_fee_id, $product_bn);
        if ($ret['code'] == 0) {
            return $this->outputFormat($ret['data'], 0);
        }
        $this->setErrorMsg($ret['msg']);
        return $this->outputFormat([], 400);

    }

    /**
     * 删除服务费分组的货品记录【单个】
     * @param Request $request
     * @return array
     */
    public function delTaxFeeProduct(Request $request)
    {
        $requestData = $this->getContentArray($request);
        $tax_fee_id = $requestData['tax_fee_id'];
        if (empty($tax_fee_id) || !is_numeric($tax_fee_id) || $tax_fee_id < 0) {
            $this->setErrorMsg('id参数错误');
            return $this->outputFormat([], 400);
        }
        $product_bn = $requestData['product_bn'];
        if (empty($product_bn)) {
            $this->setErrorMsg('bn参数错误');
            return $this->outputFormat([], 400);
        }
        $GoodsPaymentTaxFee = new GoodsPaymentTaxFeeLogic();
        $ret = $GoodsPaymentTaxFee->delTaxFeeProduct($tax_fee_id, $product_bn);
        if ($ret['code'] == 0) {
            return $this->outputFormat($ret['data'], 0);
        }
        $this->setErrorMsg($ret['msg']);
        return $this->outputFormat([], 400);
    }

    /**
     * 获取服务费分组列表
     * @param Request $request
     * @return array
     */
    public function getTaxFeeList(Request $request)
    {
        $requestData = $this->getContentArray($request);
        $page = $requestData['page'] ?? 1;
        $page_num = $requestData['page_num'] ?? 20;
        $GoodsPaymentTaxFee = new GoodsPaymentTaxFeeLogic();
        $ret = $GoodsPaymentTaxFee->getTaxFeeList($page, $page_num);
        if ($ret['code'] == 0) {
            return $this->outputFormat($ret['data'], 0);
        }
        $this->setErrorMsg($ret['msg']);
        return $this->outputFormat([], 400);
    }

    /**
     * 获取分组服务费对应的货品列表
     * @param Request $request
     * @return array
     */
    public function getGroupProductList(Request $request)
    {
        $requestData = $this->getContentArray($request);
        $page = $requestData['page'] ?? 1;
        $page_num = $requestData['page_num'] ?? 20;

        $tax_fee_id = $requestData['tax_fee_id'];
        if (empty($tax_fee_id) || !is_numeric($tax_fee_id) || $tax_fee_id < 0) {
            $this->setErrorMsg('id参数错误');
            return $this->outputFormat([], 400);
        }
        $product_bn = $requestData['product_bn'];
        $product_name = $requestData['product_name'];

        $GoodsPaymentTaxFee = new GoodsPaymentTaxFeeLogic();
        $ret = $GoodsPaymentTaxFee->getGroupProductList($tax_fee_id, $product_bn,$product_name, $page, $page_num);
        if ($ret['code'] == 0) {
            return $this->outputFormat($ret['data'], 0);
        }
        $this->setErrorMsg($ret['msg']);
        return $this->outputFormat([], 400);
    }

    /**
     * 获取服务费分组对应的推送公司id列表
     * @param Request $request
     * @return array
     */
    public function getTaxFeesSendCompany(Request $request)
    {
        $requestData = $this->getContentArray($request);

        $tax_fee_id = $requestData['tax_fee_id'];
        if (empty($tax_fee_id) || !is_numeric($tax_fee_id) || $tax_fee_id < 0) {
            $this->setErrorMsg('id参数错误');
            return $this->outputFormat([], 400);
        }

        $GoodsPaymentTaxFee = new GoodsPaymentTaxFeeLogic();
        $ret = $GoodsPaymentTaxFee->getTaxFeesSendCompany($tax_fee_id);
        if ($ret['code'] == 0) {
            return $this->outputFormat($ret['data'], 0);
        }
        $this->setErrorMsg($ret['msg']);
        return $this->outputFormat([], 400);
    }

    /**
     * 判断小数点位数
     * @param $number
     * @return int
     */
    private function getDecimalPlaces($number)
    {
        $numberStr = (string)$number;
        $decimalPointPosition = strpos($numberStr, '.');

        if ($decimalPointPosition === false) {
            return 0; // 没有小数点，返回0
        }

        return strlen($numberStr) - $decimalPointPosition - 1;
    }

    /**
     * 新增/修改分组服务费入参验证
     * @param $requestData
     * @return array
     */
    private function getParams($requestData): array
    {
        $name = $requestData['name'];
        if (empty($name) || mb_strlen($name, 'UTF-8') > 15) {
            $this->setErrorMsg('名字参数错误');
            return $this->outputFormat([], 400);
        }
        $weight = $requestData['weight'];
        if (!is_numeric($weight) || $weight < 0) {
            $this->setErrorMsg('权重参数错误');
            return $this->outputFormat([], 400);
        }
        $cash_rate = $requestData['cash_rate'];
        if (!is_numeric($cash_rate) || $cash_rate < 0 || $cash_rate >= 10 || $this->getDecimalPlaces($cash_rate) > 4) {
            $this->setErrorMsg('现金税率参数错误');
            return $this->outputFormat([], 400);
        }
        $point_rate = $requestData['point_rate'];
        if (!is_numeric($point_rate) || $point_rate < 0 || $point_rate >= 10 || $this->getDecimalPlaces($point_rate) > 4) {
            $this->setErrorMsg('积分税率参数错误');
            return $this->outputFormat([], 400);
        }
        return array($name, $weight, $cash_rate, $point_rate);
    }
}
