<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-10-18
 * Time: 18:37
 */

namespace App\Api\Logic\Delivery;

abstract class TimeTarget
{
    abstract public function createRuleTime($ruleId, $timeList);

    abstract public function deleteRuleTime($ruleId);

    abstract public function matchingTime($timeId, $timeInfo);
    
    public function Response($status = true, $msg = '成功', $data = [])
    {
        return [
            'status' => $status,
            'msg'    => $msg,
            'data'   => $data,
        ];
    }
}
