<?php

namespace App\Api\V1\Service\Brand;

use App\Api\Model\Brand\BrandRule as BrandRuleModel;
use App\Api\Model\Brand\BrandRuleOutlet as BrandRuleOutletModel;
use App\Api\Model\Outlet\OutletModel;

class BrandRule
{

    /**
     * 新增品牌规则
     * @param array $params
     * @return array
     */
    public function createBrandRule(array $params)
    {
        if (empty($params)) {
            return $this->Response(-1, '新增品牌规则参数有误');
        }

        app('db')->beginTransaction();

        //添加规则
        $brandRule = [
            'rule_name' => $params['rule_name'],
            'create_time' => time()
        ];

        $brandRuleId = BrandRuleModel::createBrandRule($brandRule);

        if (!$brandRuleId) {
            app('db')->rollback();
            return $this->Response(20001, '添加规则失败');
        }

        //添加规则门店
        $res = $this->createBrandRuleOutlet($brandRuleId, $params['outlet_ids']);
        if ($res['error_code'] != 0) {
            app('db')->rollback();
            return $this->Response(20002, $res['error_msg']);
        }

        app('db')->commit();

        return $this->Response(0, '', ['brand_rule_id'=>$brandRuleId]);
    }

    /**
     * 修改品牌规则
     * @param array $params
     * @return array|void
     */
    public function updateBrandRule(array $params) {
        if (empty($params)) {
            return $this->Response(-1, '修改品牌规则参数有误');
        }

        $info = BrandRuleModel::findBrandRuleById($params['brand_rule_id']);
        if (empty($info)) {
            return $this->Response(20001, '规则不存在');
        }

        app('db')->beginTransaction();

        //修改规则
        if ($params['rule_name']) {
            $brandRule = [
                'rule_name' => $params['rule_name'],
                'update_time' => time()
            ];
            $res = BrandRuleModel::updateBrandRuleById($params['brand_rule_id'], $brandRule);
            if (!$res) {
                app('db')->rollback();
                return $this->Response(20002, '修改规则失败');
            }
        }

        //修改规则门店
        if ($params['outlet_ids']) {
            $res = $this->updateBrandRuleOutlet($params['brand_rule_id'], $params['outlet_ids']);
            if ($res['error_code'] != 0) {
                app('db')->rollback();
                return $this->Response(20003, $res['error_msg']);
            }
        }

        app('db')->commit();

        return $this->Response(0);
    }


    /**
     * 删除规则
     * @param int $brandRuleId
     * @return array
     */
    public function delBrandRule(int $brandRuleId){
        if (empty($brandRuleId)) {
            return $this->Response(-1, '删除品牌规则参数有误');
        }

        app('db')->beginTransaction();
        //删除规则
        $res = BrandRuleModel::delBrandRuleById($brandRuleId);
        if (!$res) {
            app('db')->rollback();
            return $this->Response(20001, '删除规则失败');
        }

        //删除删除规则门店
        $res = BrandRuleOutletModel::delBrandRuleOutletByBrandRuleId($brandRuleId);
        if (!$res) {
            app('db')->rollback();
            return $this->Response(20002, '删除规则门店失败');
        }

        app('db')->commit();

        return $this->Response(0);
    }

    /**
     * 获取列表
     * @param array $filter
     * @param array $options
     * @return array
     */
    public function getList(array $filter, array $options) {
        $return = array(
            'total_count' => 0,
            'total_page' => 0,
            'list' => array()
        );

        //搜索条件
        $where = array();
        if ($filter['rule_name']) {
            $where[] = ['rule_name', 'like', '%'.$filter['rule_name'].'%'];
        }

        $whereIn = array();
        if ($filter['brand_rule_ids']) {
            $whereIn['id'] = $filter['brand_rule_ids'];
        }

        //获取总数量
        $count = BrandRuleModel::getListCount($where, $whereIn);
        if ($count == 0) {
            return $this->Response(0, '', $return);
        }

        //列表
        $list = BrandRuleModel::getList($where, $whereIn, $options['page'], $options['limit']);

        $return['total_count'] = $count;
        $return['total_page'] = ceil($count / $options['limit']);
        $return['list'] = $list;

        return $this->Response(0, '', $return);
    }

    /**
     * 新增品牌规则门店
     * @param int $brandRuleId
     * @param array $outletIds
     * @return array
     */
    public function createBrandRuleOutlet(int $brandRuleId, array $outletIds) {
        //检测门店数据是否有误
//        $outletModel = new OutletModel();
//        $field = [
//            "outlet_id"
//        ];
//        $outletList = $outletModel->getDataByOutletId($outletIds, $field);
//        $diff = array_diff($outletIds, array_column($outletList, 'outlet_id'));
//        if (!empty($diff)) {
//            return $this->Response(20001, '门店数据有误');
//        }

        //组装批量写入数据
        $brandRuleOutlets = array();
        $time = time();
        foreach ($outletIds as $outletId) {
            $brandRuleOutlets[] = [
                'brand_rule_id' => $brandRuleId,
                'outlet_id' =>  $outletId,
                'create_time' => $time
            ];
        }

        $res = BrandRuleOutletModel::createBrandRuleOutlet($brandRuleOutlets);
        if (!$res) {
            return $this->Response(20002, '添加规则门店失败');
        }

        return $this->Response(0);
    }

    /**
     * 修改品牌规则门店
     * @param int $brandRuleId
     * @param array $outletIds
     * @return array
     */
    public function updateBrandRuleOutlet(int $brandRuleId, array $outletIds) {

        if (empty($brandRuleId) || empty($outletIds)) {
            return $this->Response(-1, '修改规则门店参数有误');
        }

        //获取关联数据
        $list = BrandRuleOutletModel::getListByBrandRuleId($brandRuleId, ['outlet_id']);
        $oldOutletIds = array_column($list, 'outlet_id');

        //新增
        $newOutletIds = array_diff($outletIds, $oldOutletIds);
        if (!empty($newOutletIds)) {
            $res = $this->createBrandRuleOutlet($brandRuleId, array_values($newOutletIds));
            if ($res['error_code'] != 0) {
                return $this->Response(20001, $res['error_msg']);
            }
        }

        //删除
        $delOutletIds = array_diff($oldOutletIds, $outletIds);
        if (!empty($delOutletIds)) {
            $res = BrandRuleOutletModel::delBrandRuleOutlet($brandRuleId, array_values($delOutletIds));
            if (!$res) {
                return $this->Response(20002, '删除规则门店失败');
            }
        }

        return $this->Response(0);
    }

    /**
     * @param array $params
     * @return array
     */
    public function getBrandRuleOutlet(array $params) {
        //获取规则
        $list = BrandRuleModel::getBrandRuleById($params['brand_rule_ids']);
        if (empty($list)) {
            return $this->Response(20001, '规则不存在');
        }

        //获取关联数据
        $info = BrandRuleOutletModel::getBrandRuleOutlet($params['brand_rule_ids'], $params['outlet_ids']);
        if (empty($info)) {
            return $this->Response(20001, '规则门店不存在');
        }

        return $this->Response(0, '', $info);
    }


    private function Response($error_code, $error_msg = '', $data = [])
    {
        return [
            'error_code' => $error_code,
            'error_msg' => $error_msg,
            'data' => $data,
        ];
    }
}
