<?php
/**
 * Created by PhpStorm.
 * User: chuanbin
 * Date: 2020-06-09
 * Time: 18:17
 */

namespace App\Api\Logic\Promotion\Matcher;


interface RuleMatcherInterface
{

    public function exec($times,$config=array(),$filter_data=array());
}