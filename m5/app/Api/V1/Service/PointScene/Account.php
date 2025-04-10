<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-02-13
 * Time: 11:09
 */

namespace App\Api\V1\Service\PointScene;

use App\Api\Logic\PointServer\NeigouPoint;

class Account
{
    protected $neigouPointClass = null;

    protected function point2money($point)
    {
        if (!$this->neigouPointClass) {
            $this->neigouPointClass = new NeigouPoint();
        }
        return $this->neigouPointClass->point2money($point);
    }

    protected function money2point($money)
    {
        if (!$this->neigouPointClass) {
            $this->neigouPointClass = new NeigouPoint();
        }
        return $this->neigouPointClass->money2point($money);
    }

    protected function Response($status = true, $msg = '成功', $data = [])
    {
        return [
            'status' => $status,
            'msg' => $msg,
            'data' => $data,
        ];
    }

}
