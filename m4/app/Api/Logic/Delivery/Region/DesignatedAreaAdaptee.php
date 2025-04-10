<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-10-22
 * Time: 11:02
 */

namespace App\Api\Logic\Delivery\Region;

use App\Api\Common\Common;
use App\Api\Logic\Delivery\RegionTarget;
use App\Api\Model\Delivery\RuleRegion as RuleRegionModel;
use App\Api\Model\Delivery\RuleRegionGps as GpsModel;

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

        //如果传了gps则用gps过滤
        if (isset($regionInfo['gps_info']) && $regionInfo['gps_info']) {
            foreach ($list as $key => $item) {
                $gpsInfo     = unserialize($item->gps_info);
                $isInPolygon = Common::pointIsInPolygon($regionInfo['gps_info'], $gpsInfo);
                if ($isInPolygon) {
                    return 1;
                }
            }
        }

        return 0;
    }
}

