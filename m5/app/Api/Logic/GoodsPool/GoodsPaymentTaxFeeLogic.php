<?php

namespace App\Api\Logic\GoodsPool;

use App\Api\Model\Goods\GoodsPaymentTaxFeeModel;
use App\Api\Model\Goods\GoodsPaymentTaxFeeProductModel;
use App\Api\Model\Goods\GoodsPaymentTaxFeePushCompanyModel;
use App\Api\Model\Goods\GoodsPaymentTaxFeePushModel;
use App\Api\Model\Goods\GoodsPaymentTaxFeeRateModel;

class GoodsPaymentTaxFeeLogic
{
    /**
     * 根据给定的公司id和单bn，获取到单个货品的税率
     * @param $company_id
     * @param $product_bn
     * @return array
     */
    public function getRateSingle($company_id, $product_bn): array
    {
        $product_data[$product_bn] = array(
            'payment_tax_fee_rate' => [],
        );
        $taxIdRet = $this->getRateSingleTaxIds($company_id, $product_bn);
        if ($taxIdRet['code'] != 0) {
            return $this->returnData(0, '', $product_data);
        }
        $tmp_taxFeeIds = $taxIdRet['data'];
        $GoodsPaymentTaxFeeRateModel = new GoodsPaymentTaxFeeRateModel();
        $rate = $GoodsPaymentTaxFeeRateModel->getTaxRateByTaxIds([$tmp_taxFeeIds]);

        //计算比率按照手续费id分组 ['1' => ['cash_rate'=>0.87,'point_rate'=>0.45]]
        foreach ($rate as $item) {
            $rate_type = $item['rate_type'] == 1 ? 'cash_rate' : 'point_rate';
            $product_data[$product_bn]['payment_tax_fee_rate'][$rate_type] = $item['rate'];
            $product_data[$product_bn]['payment_tax_fee_rate']['tax_fee_id'] = $item['tax_fee_id'];
        }

        return $this->returnData(0, '', $product_data);
    }

    /**
     * 根据给定的公司id和单bn，获取到单个货品是否要收服务费
     * @param $company_id
     * @param $product_bn
     * @return array
     */
    public function getRateSingleNotRate($company_id, $product_bn): array
    {
        $product_data[$product_bn] = array(
            'is_tax_fee_rate' => false,
        );
        $taxIdRet = $this->getRateSingleTaxIds($company_id, $product_bn);
        if ($taxIdRet['code'] != 0) {
            return $this->returnData(0, '', $product_data);
        }
        $product_data[$product_bn] = array(
            'is_tax_fee_rate' => true,
        );
        return $this->returnData(0, '', $product_data);
    }

    /**
     * 根据给定的公司id和多个bn，获取到多个货品的税率
     * @param $company_id
     * @param array $product_bns
     * @return array
     */
    public function getRateMulti($company_id, array $product_bns = array()): array
    {
        $product_data = array();
        foreach ($product_bns as $product) {
            $product_data[$product]['payment_tax_fee_rate'] = [];
        }
        $list = $this->getFeeIdsByCompanyTaxId($company_id);
        if (!$list) {
            return $this->returnData(0, '', $product_data);
        }
        $tax_fee_ids = array();
        foreach ($list as $list_tmp) {
            $tax_fee_ids[$list_tmp['tax_fee_id']] = $list_tmp['tax_fee_id'];
        }

        //获取bn对应的分组信息
        $GoodsPaymentTaxFeeProductModel = new GoodsPaymentTaxFeeProductModel();
        $productRet = $GoodsPaymentTaxFeeProductModel->getTaxFeeIdByProductIds($product_bns, $tax_fee_ids);
        if (!$productRet) {
            return $this->returnData(0, '', $product_data);
        }
        $taxFeeIds = $productTaxFee = array();
        //获取可用的手续费id，并给bn对应的手续费id
        foreach ($productRet as $productRet_item) {
            $taxFeeIds[$productRet_item['tax_fee_id']] = $productRet_item['tax_fee_id'];
            $productTaxFee[$productRet_item['product_bn']][] = $productRet_item['tax_fee_id'];
        }

        $GoodsPaymentTaxFeeModel = new GoodsPaymentTaxFeeModel();
        $taxFeeIdsRet = $GoodsPaymentTaxFeeModel->getTaxByIds($taxFeeIds);
        if (!$taxFeeIdsRet) {
            return $this->returnData(0, '', $product_data);
        }
        $taxFeeIds = array();
        //获取可用的手续费id，并给bn对应的手续费id
        foreach ($taxFeeIdsRet as $taxFeeIdsRet_item) {
            $taxFeeIds[$taxFeeIdsRet_item['id']] = $taxFeeIdsRet_item['id'];
        }

        $GoodsPaymentTaxFeeRateModel = new GoodsPaymentTaxFeeRateModel();
        $rate = $GoodsPaymentTaxFeeRateModel->getTaxRateByTaxIds($taxFeeIds);

        //计算比率按照手续费id分组 ['1' => ['cash_rate'=>0.87,'point_rate'=>0.45]]
        $rate_arr = array();
        foreach ($rate as $item) {
            $rate_type = $item['rate_type'] == 1 ? 'cash_rate' : 'point_rate';
            $rate_arr[$item['tax_fee_id']][$rate_type] = $item['rate'];
        }

        //将手续费比率按照手续费排序，并赋给对应的手续费id，组合出一个 ['1'=>['cash_rate'=>0.87,'point_rate'=>0.45]] 的数组
        $tax_fee_arr = array();
        foreach ($taxFeeIdsRet as $value) {
            if (!isset($rate_arr[$value['id']])) {
                continue;
            }
            $tax_fee_arr[$value['id']] = $rate_arr[$value['id']];
        }

        $product_data = array();
        // 按照排序优先级，判断当前商品的手续费是否可用，并将可用的赋予当前商品
        foreach ($tax_fee_arr as $tax_id => $tax_tmp) {
            foreach ($product_bns as $product) {
                if(isset($product_data[$product]['payment_tax_fee_rate'])){
                    continue;
                }
                if (isset($productTaxFee[$product])) {
                    $bn_tax_fee_ids = $productTaxFee[$product];
                    if (in_array($tax_id, $bn_tax_fee_ids)) {
                        $product_data[$product]['payment_tax_fee_rate'] = $tax_tmp;
                    }
                }
            }
        }
        return $this->returnData(0, '', $product_data);
    }

    /**
     * 获取分组详情
     * @param $tax_fee_id
     * @return array
     */
    public function getTaxFeeInfo($tax_fee_id)
    {
        $GoodsPaymentTaxFeeModel = new GoodsPaymentTaxFeeModel();
        $where = ['id' => $tax_fee_id];
        $info = $GoodsPaymentTaxFeeModel->getTaxInfoByIds($where, ['id', 'name', 'weight', 'state']);
        return $this->returnData(0, '', $info);
    }

    /**
     * 新增分组
     * @param $name
     * @param $weight
     * @param $cash_rate
     * @param $point_rate
     * @return array
     */
    public function createTaxFee($name, $weight, $cash_rate, $point_rate)
    {
        $GoodsPaymentTaxFeeModel = new GoodsPaymentTaxFeeModel();
        $where = ['name' => $name];
        $info = $GoodsPaymentTaxFeeModel->getTaxInfoByIds($where);
        if ($info) {
            return $this->returnData(1002, '分组名字已经存在');
        }
        $data = array(
            'name' => $name, 'weight' => $weight, 'create_time' => time()
        );
        $addRet = $GoodsPaymentTaxFeeModel->addInfo($data);
        if (!$addRet) {
            return $this->returnData(1003, '新增失败');
        }
        $GoodsPaymentTaxFeeRateModel = new GoodsPaymentTaxFeeRateModel();
        $rate_data = array(
            array(
                'tax_fee_id' => $addRet,
                'rate_type' => 1,
                'rate' => $cash_rate ?: 0,
                'create_time' => time(),
            ),
            array(
                'tax_fee_id' => $addRet,
                'rate_type' => 2,
                'rate' => $point_rate ?: 0,
                'create_time' => time(),
            ),
        );
        $rateRet = $GoodsPaymentTaxFeeRateModel->addRateData($rate_data);
        if (!$rateRet) {
            return $this->returnData(1004, '新增税率失败');
        }
        return $this->returnData();
    }

    /**
     * 修改分组
     * @param $tax_fee_id
     * @param $name
     * @param $weight
     * @param $cash_rate
     * @param $point_rate
     * @return array
     */
    public function updateTaxFee($tax_fee_id, $name, $weight, $cash_rate, $point_rate)
    {
        $GoodsPaymentTaxFeeModel = new GoodsPaymentTaxFeeModel();
        $where = array('id' => $tax_fee_id);
        $infoRet = $GoodsPaymentTaxFeeModel->getTaxInfoByIds($where, ['id', 'name', 'weight']);
        if (!$infoRet) {
            return $this->returnData(1002, '指定的分组记录不存在');
        }
        $edit = array();
        if ($name != $infoRet['name']) {
            $name_where = array('name' => $name);
            $nameRet = $GoodsPaymentTaxFeeModel->getTaxInfoByIds($name_where);
            if ($nameRet && $nameRet['id'] != $tax_fee_id) {
                return $this->returnData(1003, '修改后的分组名已存在');
            }
            $edit['name'] = $name;
        }
        if ($weight != $infoRet['weight']) {
            $edit['weight'] = $weight;
        }

        if ($edit) {
            $edit['update_time'] = time();
            $reitRet = $GoodsPaymentTaxFeeModel->editInfo($where, $edit);
            if (!$reitRet) {
                return $this->returnData(1004, '修改分组基础信息失败');
            }
        }

        $GoodsPaymentTaxFeeRateModel = new GoodsPaymentTaxFeeRateModel();
        $rate_where = array('tax_fee_id' => $tax_fee_id);
        $rateRet = $GoodsPaymentTaxFeeRateModel->getDataByTaxFeeid($rate_where);

        foreach ($rateRet as $item) {
            $updateRet = true;
            if ($item['rate_type'] == 1 && $item['rate'] != $cash_rate) {
                $updateRet = $GoodsPaymentTaxFeeRateModel->updateRateData(array('id' => $item['id'],), array('rate' => $cash_rate));
            }
            if ($item['rate_type'] == 2 && $item['rate'] != $point_rate) {
                $updateRet = $GoodsPaymentTaxFeeRateModel->updateRateData(array('id' => $item['id'],), array('rate' => $point_rate));
            }
            if (!$updateRet) {
                return $this->returnData(1005, '修改分组百分比参数失败');
            }
        }
        return $this->returnData();
    }

    /**
     * 删除分组
     * @param $tax_fee_id
     * @return array
     */
    public function deleteTaxFee($tax_fee_id)
    {
        $GoodsPaymentTaxFeeModel = new GoodsPaymentTaxFeeModel();
        $where = array('id' => $tax_fee_id);
        $infoRet = $GoodsPaymentTaxFeeModel->getTaxInfoByIds($where);
        if (!$infoRet) {
            return $this->returnData(1002, '指定的分组记录不存在');
        }
        $edit['update_time'] = time();
        $edit['is_delete'] = 1;
        $reitRet = $GoodsPaymentTaxFeeModel->editInfo($where, $edit);
        if (!$reitRet) {
            return $this->returnData(1004, '修改分组基础信息失败');
        }
        return $this->returnData();
    }

    /**
     * 分组推送公司
     * @param $tax_fee_id
     * @param $visible
     * @param $company_ids
     * @param $state
     * @return array
     */
    public function sendTaxFee($tax_fee_id, $visible, $company_ids, $state)
    {
        $GoodsPaymentTaxFeeModel = new GoodsPaymentTaxFeeModel();
        $where = array('id' => $tax_fee_id);
        $infoRet = $GoodsPaymentTaxFeeModel->getTaxInfoByIds($where, ['id', 'state']);
        if (!$infoRet) {
            return $this->returnData(1002, '指定的分组记录不存在');
        }
        if ($state != $infoRet['state']) {
            $ret = $GoodsPaymentTaxFeeModel->editInfo($where, array('state' => $state, 'update_time' => time()));
            if (!$ret) {
                return $this->returnData(1003, '更新分组数据推送状态失败');
            }
        }
        $status = $state == 0 ? 1 : 0;
        $pushRet = $this->editPush($tax_fee_id, $visible, $status);
        if (!$pushRet) {
            return $this->returnData(1005, '更新分组数据推送数据失败');
        }
        if ($visible != 1) {
            $companyRet = $this->editPushCompany($company_ids, $tax_fee_id);
            if (!$companyRet) {
                return $this->returnData(1005, '更新分组数据推送公司数据失败');
            }
        }
        return $this->returnData();
    }

    /**
     * 新增分组货品【单个】
     * @param $tax_fee_id
     * @param $product_bn
     * @return array
     */
    public function addTaxFeeProduct($tax_fee_id, $product_bn)
    {
        $GoodsPaymentTaxFeeModel = new GoodsPaymentTaxFeeModel();
        $where = array('id' => $tax_fee_id);
        $infoRet = $GoodsPaymentTaxFeeModel->getTaxInfoByIds($where);
        if (!$infoRet) {
            return $this->returnData(1002, '指定的分组记录不存在');
        }
        $GoodsPaymentTaxFeeProductModel = new GoodsPaymentTaxFeeProductModel();
        $infoRet = $GoodsPaymentTaxFeeProductModel->getProductInfoByIds(array('tax_fee_id' => $tax_fee_id, 'product_bn' => $product_bn));
        if ($infoRet) {
            return $this->returnData(1003, '当前货品已存在');
        }
        $data = array('tax_fee_id' => $tax_fee_id, 'product_bn' => $product_bn, 'create_time' => time());
        $addRet = $GoodsPaymentTaxFeeProductModel->addData($data);
        if (!$addRet) {
            return $this->returnData(1005, '新增货品信息失败');
        }
        return $this->returnData();
    }

    /**
     * 删除分组货品【单个】
     * @param $tax_fee_id
     * @param $product_bn
     * @return array
     */
    public function delTaxFeeProduct($tax_fee_id, $product_bn)
    {
        $GoodsPaymentTaxFeeProductModel = new GoodsPaymentTaxFeeProductModel();
        $infoRet = $GoodsPaymentTaxFeeProductModel->getProductInfoByIds(array('tax_fee_id' => $tax_fee_id, 'product_bn' => $product_bn));
        if (!$infoRet) {
            return $this->returnData(1003, '当前货品不存在');
        }
        $where = array('id' => $infoRet['id']);
        $addRet = $GoodsPaymentTaxFeeProductModel->delData($where);
        if (!$addRet) {
            return $this->returnData(1005, '删除货品信息失败');
        }
        return $this->returnData();
    }

    /**
     * 获取分组列表
     * @param $page
     * @param $page_num
     * @return array
     */
    public function getTaxFeeList($page, $page_num)
    {
        $return = array(
            'count' => 0, 'list' => [],
        );
        $GoodsPaymentTaxFeeModel = new GoodsPaymentTaxFeeModel();
        $count = $GoodsPaymentTaxFeeModel->getListCount([]);
        if (!$count) {
            return $this->returnData(0, '', $return);
        }
        $list = $GoodsPaymentTaxFeeModel->getList([], ['id', 'name', 'weight', 'state'], $page, $page_num);
        $push_name = array(1 => 'all', 2 => 'in', 3 => 'not_in',);
        foreach ($list as &$v) {
            $v['tax_fee_id'] = $v['id'];
            $rate_array = [];
            foreach ($v['tax_fee_rate'] as $item) {
                $rate_type = $item['rate_type'] == 1 ? 'cash' : 'point';
                $rate_array[$item['tax_fee_id']][$rate_type] = $item['rate'];
            }
            $v['cash_rate'] = $rate_array[$v['tax_fee_id']]['cash'] ?? 0;
            $v['point_rate'] = $rate_array[$v['tax_fee_id']]['point'] ?? 0;
            $v['show_type'] = $push_name[$v['tax_fee_push']['show_type']] ?? 'not_select';
            unset($v['tax_fee_rate'], $v['tax_fee_push']);
        }
        $return['count'] = $count;
        $return['list'] = $list;
        return $this->returnData(0, '', $return);
    }

    /**
     * 获取分组货品列表
     * @param $tax_fee_id
     * @param $product_bn
     * @param $product_name
     * @param $page
     * @param $page_num
     * @return array
     */
    public function getGroupProductList($tax_fee_id, $product_bn,$product_name, $page, $page_num)
    {
        $return = array(
            'count' => 0, 'list' => [],
        );
        $where[] = array('tax_fee_id' ,'=', $tax_fee_id);
        $whereIn = array();
        if (!empty($product_bn)) {
            $whereIn = array('product_bn', $product_bn);
        }
        if (!empty($product_name)) {
            $where[] = array('p.name' ,'like',"%".$product_name."%");
        }
        $GoodsPaymentTaxFeeProductModel = new GoodsPaymentTaxFeeProductModel();
        $count = $GoodsPaymentTaxFeeProductModel->getListCount($where, $whereIn,$product_name);
        if (!$count) {
            return $this->returnData(0, '', $return);
        }
        $list = $GoodsPaymentTaxFeeProductModel->getList($where, $whereIn, ['id', 'product_bn'], $page, $page_num,$product_name);
        $return['count'] = $count;
        $return['list'] = $list;
        return $this->returnData(0, '', $return);
    }

    /**
     * 获取分组推送绑定的公司id列表
     * @param $tax_fee_id
     * @return array
     */
    public function getTaxFeesSendCompany($tax_fee_id)
    {
        $GoodsPaymentTaxFeeModel = new GoodsPaymentTaxFeeModel();
        $where = array('id' => $tax_fee_id);
        $infoRet = $GoodsPaymentTaxFeeModel->getTaxInfoByIds($where);
        if (!$infoRet) {
            return $this->returnData(1002, '指定的分组记录不存在');
        }
        $GoodsPaymentTaxFeePushCompanyModel = new GoodsPaymentTaxFeePushCompanyModel();
        $company_where = array('tax_fee_id' => $tax_fee_id);
        $companyRet = $GoodsPaymentTaxFeePushCompanyModel->getCompanyData($company_where, ['id', 'company_id']);
        $return = [];
        foreach ($companyRet as $v) {
            $return[] = $v['company_id'];
        }
        return $this->returnData(0, '', array('company_id' => $return));
    }

    public function returnData($code = 0, $msg = '', $data = array()): array
    {
        return array(
            "code" => $code,
            "msg" => $msg,
            "data" => $data,
        );
    }

    /**
     * 获取公司id对应的可用的分组id
     * @param $company_id
     * @return array
     */
    private function getFeeIdsByCompanyTaxId($company_id): array
    {
        $GoodsPaymentTaxFeePushCompanyModel = new GoodsPaymentTaxFeePushCompanyModel();
        $companyRet = $GoodsPaymentTaxFeePushCompanyModel->getTaxFeeIdsByCompanyIds($company_id);
        $company_tax_fee_ids = array();
        if ($companyRet) {
            foreach ($companyRet as $company_tmp) {
                $company_tax_fee_ids[$company_tmp['tax_fee_id']] = $company_tmp['tax_fee_id'];
            }
        }

        //获取公司绑定的推送信息
        $GoodsPaymentTaxFeePushModel = new GoodsPaymentTaxFeePushModel();
        $list = $GoodsPaymentTaxFeePushModel->getTaxFeeIdsByCompanyTaxId($company_tax_fee_ids);
        return $list;
    }

    /**
     * 根据公司id合货品bn，获取对应的有服务费的分组信息id
     * @param $company_id
     * @param $product_bn
     * @return array|mixed
     */
    private function getRateSingleTaxIds($company_id, $product_bn)
    {
        $list = $this->getFeeIdsByCompanyTaxId($company_id);
        if (!$list) {
            return $this->returnData(-1, '公司没有服务费');
        }
        $tax_fee_ids = array();
        foreach ($list as $list_tmp) {
            $tax_fee_ids[$list_tmp['tax_fee_id']] = $list_tmp['tax_fee_id'];
        }

        //获取bn对应的分组信息
        $GoodsPaymentTaxFeeProductModel = new GoodsPaymentTaxFeeProductModel();
        $productRet = $GoodsPaymentTaxFeeProductModel->getTaxFeeIdByProductIds(array($product_bn), $tax_fee_ids);
        if (!$productRet) {
            return $this->returnData(-1, '货品没有服务费');
        }
        $taxFeeIds = array();
        //获取可用的手续费id，并给bn对应的手续费id
        foreach ($productRet as $productRet_item) {
            $taxFeeIds[$productRet_item['tax_fee_id']] = $productRet_item['tax_fee_id'];
        }
        $GoodsPaymentTaxFeeModel = new GoodsPaymentTaxFeeModel();
        $taxFeeIdsRet = $GoodsPaymentTaxFeeModel->getTaxByIds($taxFeeIds);
        if (!$taxFeeIdsRet) {
            return $this->returnData(-1, '获取服务费数据异常');
        }
        return $this->returnData(0, '', $taxFeeIdsRet[0]['id']);
    }

    /**
     * 编辑推送数据
     * @param $tax_fee_id
     * @param $visible
     * @param $status
     * @return bool|mixed|string
     */
    private function editPush($tax_fee_id, $visible, $status)
    {
        $GoodsPaymentTaxFeePushModel = new GoodsPaymentTaxFeePushModel();
        $push_where = array('tax_fee_id' => $tax_fee_id);
        $pushInfo = $GoodsPaymentTaxFeePushModel->getTaxInfoByIds($push_where);
        if (!$pushInfo) {
            $data = array(
                "tax_fee_id" => $tax_fee_id,
                "push_type" => 1,
                "visible" => $visible,
                "create_time" => time(),
                "status" => $status,
            );
            $pushRet = $GoodsPaymentTaxFeePushModel->addInfo($data);
        } else {
            $data = array(
                "visible" => $visible,
                "update_time" => time(),
                "status" => $status,
            );
            $pushRet = $GoodsPaymentTaxFeePushModel->editInfo(array('id' => $pushInfo['id']), $data);
        }
        return $pushRet;
    }

    /**
     * 编辑推送的公司
     * @param array $company_ids
     * @param $tax_fee_id
     * @return bool
     */
    private function editPushCompany(array $company_ids, $tax_fee_id)
    {
        $new_company = array();
        foreach ($company_ids as $com_tmp) {
            $new_company[$com_tmp] = 1;
        }
        $GoodsPaymentTaxFeePushCompanyModel = new GoodsPaymentTaxFeePushCompanyModel();
        $company_where = array('tax_fee_id' => $tax_fee_id);
        $companyRet = $GoodsPaymentTaxFeePushCompanyModel->getCompanyData($company_where, ['id', 'company_id']);
        $del_array = array();
        foreach ($companyRet as $item) {
            if (!empty($new_company[$item['company_id']])) {
                //依然存在
                unset($new_company[$item['company_id']]);
                continue;
            }
            $del_array[] = $item['id'];
        }
        if ($del_array) {
            $del_where = array('tax_fee_id' => $tax_fee_id);
            $del_whereIn = array('id', $del_array);
            $updateRet = $GoodsPaymentTaxFeePushCompanyModel->delData($del_where, $del_whereIn);
            if (!$updateRet) {
                return false;
            }
        }
        if ($new_company) {
            $create_array = array();
            foreach ($new_company as $k => $v) {
                $create_array[] = array(
                    'company_id' => $k, 'tax_fee_id' => $tax_fee_id, 'create_time' => time(),
                );
            }
            if ($create_array) {
                $createRet = $GoodsPaymentTaxFeePushCompanyModel->addData($create_array);
                if (!$createRet) {
                    return false;
                }
            }
        }
        return true;
    }

}
