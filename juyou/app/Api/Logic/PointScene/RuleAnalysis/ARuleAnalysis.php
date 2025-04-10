<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-01-31
 * Time: 15:27
 */

namespace App\Api\Logic\PointScene\RuleAnalysis;


abstract class ARuleAnalysis
{
    public abstract function WithRule($ruleIdList, $sceneIdList, $filterData);

    protected function Response($status = true, $msg = '成功', $data = [])
    {
        return [
            'status' => $status,
            'msg' => $msg,
            'data' => $data,
        ];
    }
}
