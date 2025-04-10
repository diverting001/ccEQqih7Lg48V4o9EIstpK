<?php
/**
 * neigou_service-stock
 * @package     api
 * @author      xupeng
 * @since       Version
 * @filesource
 */

namespace App\Api\V3\Service\Region;

use App\Api\Model\Region\Region as RegionModel;
use App\Api\Logic\Salyut\Stock as SalyutStock;
use App\Api\Model\Region\RegionBaiduMapModel;
use App\Api\Model\Region\RegionBaiduModel;

/**
 * 地址
 *
 * @package     api
 * @category    Service
 * @author        xupeng
 */
class Region
{
    /**
     * 获取子级地址
     *
     * @param   $regionId     int  地址ID
     * @return  array
     */
    public function getChildRegion($regionId = 0)
    {
        $return = array();

        $regionModel = new RegionModel();

        if ($regionId == 0) {
            $regionId = null;
        }
        // 获取子级地址
        $regionList = $regionModel->getChildRegion($regionId);

        if (!empty($regionList)) {
            foreach ($regionList as $region) {
                $return[] = array(
                    'region_id' => $region['region_id'],
                    'local_name' => $region['local_name'],
                    'p_region_id' => intval($region['p_region_id']),
                    'region_grade' => $region['region_grade'],
                    'region_path' => $region['region_path'],
                );
            }
        }

        return $return;
    }

    // --------------------------------------------------------------------

    /**
     * 获取父节点地址
     *
     * @param   $regionId     int  地址ID
     * @return  array
     */
    public function getParentRegion($regionId)
    {
        $return = array();

        $regionModel = new RegionModel();

        // 获取子级地址
        $regionInfo = $regionModel->getRegionInfo($regionId);

        if (empty($regionInfo)) {
            return $return;
        }
        if (is_array($regionId)) {
            $region_path = array_column($regionInfo, 'region_path');
            $region_path = explode(',', implode(',', $region_path));
        } else {
            $region_path = explode(',', $regionInfo['region_path']);
        }
        $regionIds = array_filter($region_path);

        $regionList = $regionModel->getRegionInfo($regionIds);

        if (!empty($regionList)) {
            foreach ($regionList as $region) {
                $return[] = array(
                    'region_id' => $region['region_id'],
                    'local_name' => $region['local_name'],
                    'p_region_id' => intval($region['p_region_id']),
                    'region_grade' => $region['region_grade'],
                    'region_path' => $region['region_path'],
                );
            }
        }

        return $return;
    }

    // --------------------------------------------------------------------

    /**
     * 获取所有父节点
     *
     * @param   $regionId     int  地址ID
     * @return  array
     */
    public function getParentRegionAll($regionId)
    {
        $return = array();

        $regionModel = new RegionModel();

        // 获取子级地址
        $regionInfo = $regionModel->getRegionInfo($regionId);

        if (empty($regionInfo)) {
            return $return;
        }

        $regionIds = array_filter(explode(',', $regionInfo['region_path']));

        $regionList = array();
        for ($i = 0; $i < count($regionIds); $i++) {
            $childRegion = $this->getChildRegion($regionIds[$i]);

            $regionList = array_merge($regionList, $childRegion);
        }


        if (!empty($regionList)) {
            foreach ($regionList as $region) {
                $return[] = array(
                    'region_id' => $region['region_id'],
                    'local_name' => $region['local_name'],
                    'p_region_id' => intval($region['p_region_id']),
                    'region_grade' => $region['region_grade'],
                    'region_path' => $region['region_path'],
                );
            }
        }

        return $return;
    }

    /**
     * 根据ids获取地址列表
     *
     * @param   $region_ids     array  地址ids
     * @return  array
     */
    public function getListByIds($region_ids)
    {
        $region_model = new RegionModel();
        // 获取子级地址
        return $region_model->getListByIds($region_ids);
    }

    /**
     * 获取父节点地址
     *
     * @param   $region_id     int  地址ID
     * @return  array
     */
    public function getTreeList($region_id)
    {
        $region_model = new RegionModel();
        // 获取子级地址
        return $region_model->getTreeList($region_id);
    }

    /** 获取地址库某一行数据 , 传入参数为where 查询的数组
     *
     * @param array $where
     * @return array
     * @author liuming
     */
    public function getRegionsRow($where = array())
    {
        if (empty($where)){
            return array();
        }

        $region_model = new RegionModel();
        // 获取子级地址
        return $region_model->getRegionsRow($where);
    }

    /** 根据名称获取地址库列表
     *
     * @param string $addr
     * @return array
     * @author guke
     */
    public function GetRegionByAddr($addr)
    {
        $return = [];
        if (empty($addr)) {
            return $return;
        }
        $curl = new \Neigou\Curl();
        $curl->time_out = 10;
        $request_params = array(
            'class_obj' => 'JD',
            'method' => 'getJDAddressFromAddress',
            'data' => [
                'addr' => $addr
            ]
        );
        $request_params['token'] = SalyutStock::getJDToken($request_params);
        $url = config('neigou.SALYUT_DOMIN') . '/OpenApi/apirun';

        $response_data = $curl->Post($url, $request_params);
        $response_data = json_decode($response_data, true);
        if ($response_data['Result'] === 'true') {
            $region_model = new RegionModel();
            // 获取子级地址
            $region_list = $region_model->getListByNames([
                $response_data['Data']['province_name'],
                $response_data['Data']['city_name'],
                $response_data['Data']['county_name'],
                $response_data['Data']['town_name'],
            ]);
            $return = [];
            if ($region_list[0]['region_grade'] == 1) {
                $return['province_id'] = $region_list[0]['region_id'];
                $return['province_name'] = $region_list[0]['local_name'];
                $count = count($region_list);
                for ($i = 1; $i < $count; $i++) {
                    if ($region_list[$i]['region_grade'] == 2 && $region_list[$i]['p_region_id'] === $return['province_id']) {
                        $return['city_id'] = $region_list[$i]['region_id'];
                        $return['city_name'] = $region_list[$i]['local_name'];
                    } elseif ($region_list[$i]['region_grade'] == 3 && $region_list[$i]['p_region_id'] === $return['city_id'] && $region_list[$i]['local_name'] === $response_data['Data']['county_name']) {
                        $return['county_id'] = $region_list[$i]['region_id'];
                        $return['county_name'] = $region_list[$i]['local_name'];
                    } elseif ($region_list[$i]['region_grade'] == 4 && $region_list[$i]['p_region_id'] === $return['county_id']) {
                        $return['town_id'] = $region_list[$i]['region_id'];
                        $return['town_name'] = $region_list[$i]['local_name'];
                    }
                }
            }
        }
        return $return;
    }

    /**
     *
     * @param array $where
     * @return array
     * @author liuming
     */
    public function getRegionList($where = array())
    {
        if (empty($where)){
            return array();
        }

        $region_model = new RegionModel();
        // 获取子级地址
        return $region_model->getRegionsList($where);
    }

    public function getBaiduMap($address_component): ?array
    {
        if (empty($address_component)){
            return array();
        }
        $region_model = new RegionBaiduMapModel();

        // 获取子级地址
        return $region_model->getMapIdByRegionName($address_component);
    }

    /**
     * 根据adcode获取百度地图数据
     * @param $adcode
     * @return array|false|\Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model|object|null
     */
    public function getBaiduRegionByAdcode($adcode)
    {
        if (empty($adcode)) {
            return array();
        }
        if (!is_array($adcode)) {
            $adcode = explode(',', $adcode);
        }
        $region_model = new RegionBaiduModel();

        // 获取子级地址
        return $region_model->getBaiduRegionByAdcode($adcode);
    }
}
