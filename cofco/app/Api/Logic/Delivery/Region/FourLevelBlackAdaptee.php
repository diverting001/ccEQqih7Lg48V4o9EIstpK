<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-10-18
 * Time: 18:40
 */

namespace App\Api\Logic\Delivery\Region;


use App\Api\Logic\Delivery\RegionTarget;
use App\Api\Model\Delivery\RuleRegion as RuleRegionModel;
use App\Api\Model\Delivery\RuleRegionFourLevel as FourLevelModel;

class FourLevelBlackAdaptee extends RegionTarget
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
            $status = FourLevelModel::Create([
                'region_id' => $regionId,
                'province'  => $regionInfo['province'],
                'city'      => $regionInfo['city'],
                'county'    => $regionInfo['county'],
                'town'      => $regionInfo['town'],
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
        $fourLevelStatus = FourLevelModel::DeleteByRegionId($regionId);
        if (!$fourLevelStatus) {
            app('db')->rollBack();
            return $this->Response(false, '地址删除失败【2】');
        }
        app('db')->commit();
        return $this->Response();
    }

    public function matchingRegion($regionId, $regionInfo)
    {
        $list = FourLevelModel::matchingRegionList(
            $regionId,
            $regionInfo['province'],
            $regionInfo['city'],
            $regionInfo['county'],
            $regionInfo['town']
        );

        return $list->count() > 0 ? 0 : 1;
    }
}
