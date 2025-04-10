<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-10-18
 * Time: 18:37
 */

namespace App\Api\Logic\DeliveryLimit;

use App\Api\Logic\DeliveryLimit\Time\SectionTimeAdaptee;

class TimeAdapter extends TimeTarget
{
    protected $classObj;

    public function __construct($type)
    {
        switch ($type) {
            case 'section_time':
                $this->classObj = new SectionTimeAdaptee($type);
                break;
            default:
                throw new \Exception('未适配的时间适配');
        }
    }

    public function createRuleTime($ruleId, $timeList)
    {
        return $this->classObj->createRuleTime($ruleId, $timeList);
    }

    public function deleteRuleTime($timeId)
    {
        return $this->classObj->deleteRuleTime($timeId);
    }

    public function matchingTime($timeId, $timeInfo)
    {
        return $this->classObj->matchingTime($timeId, $timeInfo);
    }

    public function getAvailableTime($timeId, $timeInfo)
    {
        return $this->classObj->getAvailableTime($timeId, $timeInfo);
    }
}
