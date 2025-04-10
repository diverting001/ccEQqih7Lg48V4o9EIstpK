<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-12-02
 * Time: 17:06
 */

namespace App\Api\V3\Service\ScenePoint;


class PointExchangeRate
{
    private $rate = 100;

    public function point2money($point)
    {
        return sprintf('%.2f', $point / $this->rate);
    }

    public function money2point($money)
    {
        return sprintf('%.2f', $money * $this->rate);
    }
}
