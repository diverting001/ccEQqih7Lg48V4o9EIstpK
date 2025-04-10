<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-08-19
 * Time: 16:03
 */

namespace App\Api\V3\Service\Pricing;


class Lnode
{
    public $mElem;
    public $mNext;

    public function __construct()
    {
        $this->mElem = null;
        $this->mNext = null;
    }
}
