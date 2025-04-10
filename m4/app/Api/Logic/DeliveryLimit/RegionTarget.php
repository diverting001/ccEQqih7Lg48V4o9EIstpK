<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-10-18
 * Time: 18:37
 */

namespace App\Api\Logic\DeliveryLimit;

abstract class RegionTarget
{
    abstract public function createRuleRegion($ruleId, $regionList);

    abstract public function deleteRuleRegion($regionId);

    abstract public function matchingRegion($regionId, $regionInfo);

    abstract public function getAvailableRegion($regionId, $regionInfo);

    public function Response($status = true, $msg = '成功', $data = [])
    {
        return [
            'status' => $status,
            'msg'    => $msg,
            'data'   => $data,
        ];
    }
}
