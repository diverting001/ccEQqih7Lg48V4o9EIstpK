<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-10-18
 * Time: 18:37
 */

namespace App\Api\Logic\Delivery;

use App\Api\Logic\Delivery\Region\FourLevelBlackAdaptee;
use App\Api\Logic\Delivery\Region\FourLevelAdaptee;
use App\Api\Logic\Delivery\Region\DesignatedAreaAdaptee;

class RegionAdapter extends RegionTarget
{
    protected $classObj;

    public function __construct($type)
    {
        switch ($type) {
            case 'four_level':
                $this->classObj = new FourLevelAdaptee($type);
                break;
            case 'four_level_black':
                $this->classObj = new FourLevelBlackAdaptee($type);
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

}
