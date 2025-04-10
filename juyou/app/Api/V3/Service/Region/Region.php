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

        $regionIds = array_filter(explode(',', $regionInfo['region_path']));

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

}
