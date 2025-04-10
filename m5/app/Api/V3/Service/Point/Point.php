<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-11-28
 * Time: 14:46
 */

namespace App\Api\V3\Service\Point;

use App\Api\Model\Point\Point as PointModel;
use App\Api\V3\Service\ServiceTrait;

/**
 * 积分服务
 * Class Point
 * @package App\Api\V3\Service\Point
 */
class Point
{
    use ServiceTrait;

    public function __call($method, $paramList)
    {
        $param   = current($paramList);
        $channel = $param['channel'] ?? '';
        if (!$channel) {
            return $this->Response(false, '参数错误【积分渠道】');
        }

        $pointChannelInfo = PointModel::GetChannelInfo($param['channel']);
        if (!$pointChannelInfo) {
            return $this->Response(false, '积分渠道不存在');
        }

        switch ($pointChannelInfo->point_version) {
            case 1:
                $service = new NeigouPoint();
                break;
            case 2:
                $service = new NeigouScenePoint();
                break;
            default:
                return $this->Response(false, '积分渠道版本异常');
        }

        return $service->$method($param);
    }
}
