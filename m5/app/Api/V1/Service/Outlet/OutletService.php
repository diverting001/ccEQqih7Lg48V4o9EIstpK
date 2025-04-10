<?php

namespace App\Api\V1\Service\Outlet;

use App\Api\Model\Brand\BrandRule;
use App\Api\Model\Outlet\OutletModel;

class OutletService
{

    /**
     * 门店信息-新增（批量/单条）
     * @param array $params
     * @param bool $is_batch 是否批量,true是，false否
     * @return array
     */
    public function create(array $params = [], bool $is_batch = true)
    {
        $outletModel = new OutletModel();

        $ret = $outletModel->insert($params, $is_batch);
        if (!$ret) {
            return ['code' => -1, 'data' => [], 'msg' => '创建失败'];
        }
        return ['code' => 0, 'data' => $ret, 'msg' => 'success'];
    }

    /**
     * 跟新指定门店信息
     * @param array $params
     * @return array
     */
    public function update(array $params = [])
    {
        $outletModel = new OutletModel();
        $outletId = $params['outlet_id'];
        //判断对应的门店是否存在
        $res = $outletModel->getInfoByOutletId($outletId);
        if (!$res) {
            return ['code' => -1, 'data' => [], 'msg' => '指定目标门店不存在'];
        }
        //更新数据内容
        unset($params['outlet_id']);
        $params['updated_time'] = time();
        $ret = $outletModel->update($outletId, $params);
        if (!$ret) {
            return ['code' => -1, 'data' => [], 'msg' => '更新失败'];
        }
        return ['code' => 0, 'data' => [], 'msg' => 'success'];
    }

    /**
     * 删除指定门店（软删除）
     * @param array $params
     * @return array
     */
    public function delete(array $params = [])
    {
        $outletModel = new OutletModel();
        $outletId = $params['outlet_id'];
        //判断对应的门店是否存在
        $res = $outletModel->getInfoByOutletId($outletId);
        if (!$res) {
            return ['code' => -1, 'data' => [], 'msg' => '指定目标门店不存在'];
        }
        //更新数据内容
        unset($params['outlet_id']);
        $data['deleted_time'] = time();
        $ret = $outletModel->update($outletId, $data);
        if (!$ret) {
            return ['code' => -1, 'data' => [], 'msg' => '更新失败'];
        }
        return ['code' => 0, 'data' => [], 'msg' => 'success'];

    }

    /**
     * 获取列表
     * @param int $page
     * @param int $pageSize
     * @param array $outletIds
     * @param array $outletNames
     * @param array $address
     * @return array
     */
    public function getList(int   $page = 1,
                            int   $pageSize = 20,
                            array $outletIds = [],
                            array $outletNames = [],
                            array $address = [])
    {
        $outletModel = new OutletModel();

        //获取总数量
        $count = $outletModel->getListCount($outletIds, $outletNames, $address);

        $totalPage = ceil($count / $pageSize);

        $return = array(
            'page' => $page,
            'page_size' => $pageSize,
            'total_count' => $count,
            'total_page' => $totalPage,
            'data' => array(),
        );
        if (!$count) {
            return ['code' => 0, 'data' => $return, 'msg' => 'success'];
        }
        $offset = ($page - 1) * $pageSize;
        //按照分页信息获取对应列表数据
        $return['data'] = $outletModel->getList($offset, $pageSize, $outletIds, $outletNames, $address);

        return ['code' => 0, 'data' => $return, 'msg' => 'success'];
    }

    /**
     * 获取指定门店信息
     * @param array $params
     * @return array
     */
    public function getInfo(array $params = [])
    {
        $outletModel = new OutletModel();
        $outletId = $params['outlet_id'];
        //判断对应的门店是否存在
        $field = [
            "outlet_id",
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
        $res = $outletModel->getInfoByOutletId($outletId, $field);
        if (!$res) {
            return ['code' => -1, 'data' => [], 'msg' => '指定目标门店不存在'];
        }
        return ['code' => 0, 'data' => $res, 'msg' => 'success'];
    }

    /**
     *  给es更新的 goods返回数据 写入门店业务数据
     * @param array $goods_list
     * @return array
     */
    public function putOutletData(array $goods_list = [])
    {
        //获取全部的outlet_id
        $outlet_ids = array_column($goods_list, 'outlet_id_list');
        if ($outlet_ids) {
            $ids = [];
            foreach ($outlet_ids as $outlet_ids_v) {
                $ids = array_merge($ids, $outlet_ids_v);
            }
            $ids = array_unique($ids);
            //获取门店数据
            $outletModel = new OutletModel();
            //获取总数量
            $outlet_list = $outletModel->getList(0, count($ids), $ids);
            $outlet_list = array_column($outlet_list, NULL, 'outlet_id');
        }

        foreach ($goods_list as $k => &$v) {
            $v['outlet_list'] = [];
            foreach ($v['outlet_id_list'] as $item) {
                if (isset($outlet_list[$item])) {
                    $v['outlet_list'][] = [
                        "outlet_id" => $outlet_list[$item]->outlet_id, //门店ID
                        "coordinate" => [//经纬度
                            "lon" => $outlet_list[$item]->longitude,
                            "lat" => $outlet_list[$item]->latitude,
                        ],
                        "outlet_name" => $outlet_list[$item]->outlet_name,
                        "outlet_address" => $outlet_list[$item]->outlet_address,
                        "outlet_logo" => $outlet_list[$item]->outlet_logo,
                        "province_id" => $outlet_list[$item]->province_id,
                        "city_id" => $outlet_list[$item]->city_id,
                        "area_id" => $outlet_list[$item]->area_id,
                    ];
                }
            }
            unset($v['outlet_id_list']);
        }
        return $goods_list;
    }

    /**
     * 批量获取门店信息
     * @param array $outletIds
     * @return array
     */
    public function getDataByOutletId(array $outletIds): array
    {
        if (!$outletIds) {
            return ['code' => -1, 'data' => [], 'msg' => '传入有效的门店ID组'];
        }
        $outletModel = new OutletModel();
        $field = [
            "outlet_id",
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
        ];
        $res = $outletModel->getDataByOutletId($outletIds, $field);
        return ['code' => 0, 'data' => $res, 'msg' => 'success'];
    }


    /**
     * 获取geohash后获取距离的门店列表
     * @param $params
     *
     * @return array
     */
    public function getListWithCoordinate($params) :array
    {
        $outletModel = new OutletModel();

        $regions = array();
        if (isset($params['province_id']) && $params['province_id'] > 0) {
            $regions['province_id'] = $params['province_id'];
        }

        if (isset($params['city_id']) && $params['city_id'] >0) {
            $regions['city_id'] = $params['city_id'];
        }

        if (isset($params['area_id']) && $params['area_id'] > 0) {
            $regions['area_id'] = $params['area_id'];
        }

        if (isset($params['is_scanning_code'])) {
            $isScanningCode = $params['is_scanning_code'];
        } else {
            $isScanningCode = 0;
        }

        $location = array();
        $location['lat'] = $params['order']['coordinate']['params']['lat'];
        $location['lon'] = $params['order']['coordinate']['params']['lon'];

        $brandIds = array();
        if (isset($params['brand_ids']) && !empty($params['brand_ids'])) {
            $brandIds = $params['brand_ids'];
        }

        $outletIds = array();
        if (isset($params['outlet_ids']) && !empty($params['outlet_ids'])) {
            $outletIds = $params['brand_id'];
        }

        $outletNames = array();
        if (isset($params['outlet_names']) && !empty($params['outlet_names'])) {
            $outletNames = $params['outlet_names'];
        }

        $brandRuleIds = array();
        if (isset($params['brand_rule_ids']) && !empty($params['brand_rule_ids'])) {
            $brandRuleList = BrandRule::getBrandRuleById($params['brand_rule_ids'], ['id']);
            $brandRuleIds = array_column($brandRuleList, 'id');
        }

        $order = "";
        if (isset($params['order']['coordinate']['by'])) {
            $order = " distance ".$params['order']['coordinate']['by'];
        }

        $offset = ($params['page'] - 1) * $params['page_size'];
        $limit = $params['page_size'];

        if (!empty($brandRuleIds)) {
            $outletCount = $outletModel->getListCountV2($outletIds,  $outletNames , $regions, $brandIds, $isScanningCode, $brandRuleIds);
            $outletList = $outletModel->getListWithCoordinateV2($regions, $location, $brandIds, $outletIds, $outletNames, $order, $isScanningCode, $brandRuleIds, $offset, $limit);
        } else {
            $outletCount = $outletModel->getListCount($outletIds,  $outletNames , $regions, $brandIds, $isScanningCode);
            $outletList = $outletModel->getListWithCoordinate($regions, $location, $brandIds, $outletIds, $outletNames, $order, $isScanningCode, $offset, $limit);
        }
        return ['code' => 0, 'data' => ["data" => $outletList, "total_count" => $outletCount], 'msg' => 'success'];
    }


    /**
     * 获取品牌门店列表
     * @param $params
     *
     * @return array
     */
    public function getOutletAgg($params) :array
    {
        $outletModel = new OutletModel();

        $conditions = [];
        if (isset($params['filter']['brand_id']) && !empty($params['filter']['brand_id'])) {
            $conditions['brand_id'] = $params['filter']['brand_id'];
        }

        if (isset($params['filter']['province_id']) && !empty($params['filter']['province_id'])) {
            $conditions['province_id'] = $params['filter']['province_id'];
        }

        if (isset($params['filter']['city_id']) && !empty($params['filter']['city_id'])) {
            $conditions['city_id'] = $params['filter']['city_id'];
        }

        if (isset($params['filter']['area_id']) && !empty($params['filter']['area_id'])) {
            $conditions['area_id'] = $params['filter']['area_id'];
        }

        if (isset($params['filter']['is_scanning_code'])) {
            if ($params['filter']['is_scanning_code'] == 1) {
                $conditions['is_scanning_code'] = 0;
            } else if ($params['filter']['is_scanning_code'] == 2) {
                $conditions['is_scanning_code'] = 1;
            }
        }

        $brandRuleIds = array();
        if (isset($params['filter']['brand_rule_ids']) && !empty($params['filter']['brand_rule_ids'])) {
            $brandRuleList = BrandRule::getBrandRuleById($params['filter']['brand_rule_ids'], ['id']);
            $brandRuleIds = array_column($brandRuleList, 'id');
        }

        $options = ["group_by_field" => "brand_id"];

        if (!empty($brandRuleIds)) {
            $conditions['brand_rule_ids'] = $brandRuleIds;
            $outletBrandList = $outletModel->getOutletAggV2($conditions, $options);

        } else {
            $outletBrandList = $outletModel->getOutletAgg($conditions, $options);
        }

        $brandIds = array();
        foreach ($outletBrandList as $brandOutlet) {
            if ($brandOutlet->brand_id) {
                $brandIds[] = $brandOutlet->brand_id;
            }
        }
        $brandIds = array_unique($brandIds);

        return ['code' => 0, 'data' => [ "brand_id" => $brandIds], 'msg' => 'success'];
    }

}
