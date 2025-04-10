<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-10-22
 * Time: 11:02
 */

namespace App\Api\Logic\DeliveryLimit\Region;

use App\Api\Common\Common;
use App\Api\Logic\DeliveryLimit\RegionTarget;
use App\Api\Model\DeliveryLimit\RuleRegion as RuleRegionModel;
use App\Api\Model\DeliveryLimit\RuleRegionGps as GpsModel;

class DesignatedAreaAdaptee extends RegionTarget
{
    private $type;

    public function __construct($type)
    {
        $this->type = $type;
    }

    public function createRuleRegion($ruleId, $regionList)
    {
        app('db')->beginTransaction();
        $regionId = RuleRegionModel::Create([
            'rule_id' => $ruleId,
            'type'    => $this->type,
        ]);
        if (!$regionId) {
            app('db')->rollBack();
            return $this->Response(false, '地址创建失败【1】');
        }

        foreach ($regionList as $regionInfo) {
            $status = GpsModel::Create([
                'region_id' => $regionId,
                'province'  => $regionInfo['province'],
                'city'      => $regionInfo['city'],
                'gps_info'  => serialize($regionInfo['gps_info']),
            ]);
            if (!$status) {
                app('db')->rollBack();
                return $this->Response(false, '地址创建失败【2】');
            }
        }
        app('db')->commit();
        return $this->Response();
    }

    public function deleteRuleRegion($regionId)
    {
        app('db')->beginTransaction();
        $regionStatus = RuleRegionModel::Delete($regionId);
        if (!$regionStatus) {
            app('db')->rollBack();
            return $this->Response(false, '地址删除失败【1】');
        }
        $fourLevelStatus = GpsModel::DeleteByRegionId($regionId);
        if (!$fourLevelStatus) {
            app('db')->rollBack();
            return $this->Response(false, '地址删除失败【2】');
        }
        app('db')->commit();
        return $this->Response();
    }

    public function matchingRegion($regionId, $regionInfo)
    {
        $list = GpsModel::matchingRegionList(
            $regionId,
            $regionInfo['province'],
            $regionInfo['city']
        );

        if ($list->count() <= 0) {
            return 0;
        }


        if (
            !isset($regionInfo['gps_info']['lat']) ||
            !isset($regionInfo['gps_info']['lng']) ||
            !is_numeric($regionInfo['gps_info']['lat']) ||
            !is_numeric($regionInfo['gps_info']['lng'])
        ) {
            return 1;
        }

        foreach ($list as $key => $item) {
            $gpsInfo     = unserialize($item->gps_info);
            $isInPolygon = Common::pointIsInPolygon($regionInfo['gps_info'], $gpsInfo);
            if ($isInPolygon) {
                return 1;
            }
        }

        return 0;
    }

    public function getAvailableRegion($regionId, $regionInfo)
    {
        $list = GpsModel::matchingRegionList(
            $regionId,
            $regionInfo['province'],
            $regionInfo['city']
        );
        if ($list->count() <= 0) {
            return $list;
        }

        foreach ($list as $key => $item) {
            $list[$key]->gps_info = unserialize($item->gps_info);
        }

        //如果传了gps则用gps过滤
        if (isset($regionInfo['gps_info']) && $regionInfo['gps_info']) {
            foreach ($list as $key => $item) {
                $isInPolygon          = Common::pointIsInPolygon($regionInfo['gps_info'], $item->gps_info);
                if (!$isInPolygon) {
                    unset($list[$key]);
                }
            }
        }

        return $list;
    }
}

