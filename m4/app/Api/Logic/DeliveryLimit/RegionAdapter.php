<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-10-18
 * Time: 18:37
 */

namespace App\Api\Logic\DeliveryLimit;

use App\Api\Logic\DeliveryLimit\Region\DesignatedAreaAdaptee;
use App\Api\Logic\DeliveryLimit\Region\FourLevelAdaptee;

class RegionAdapter extends RegionTarget
{
    protected $classObj;

    public function __construct($type)
    {
        switch ($type) {
            case 'four_level':
                $this->classObj = new FourLevelAdaptee($type);
                break;
            case 'designated_area':
                $this->classObj = new DesignatedAreaAdaptee($type);
                break;
            default:
                throw new \Exception('未适配的地址适配');
        }
    }

    public function createRuleRegion($ruleId, $regionList)
    {
        return $this->classObj->createRuleRegion($ruleId, $regionList);
    }

    public function deleteRuleRegion($regionId)
    {
        return $this->classObj->deleteRuleRegion($regionId);
    }

    public function matchingRegion($regionId, $regionInfo)
    {
        return $this->classObj->matchingRegion($regionId, $regionInfo);
    }

    public function getAvailableRegion($regionId, $regionInfo)
    {
        return $this->classObj->getAvailableRegion($regionId, $regionInfo);
    }
}
