<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-03-04
 * Time: 10:44
 */

namespace App\Api\Logic\PointServer;


class NeigouPoint
{
    private $rate = 100.00;

    public function point2money($point)
    {
        return $point / $this->rate;
    }

    public function money2point($money)
    {
        return $money * $this->rate;
    }

}
