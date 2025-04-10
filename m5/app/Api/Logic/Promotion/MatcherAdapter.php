<?php
/**
 * Created by PhpStorm.
 * User: chuanbin
 * Date: 2020-06-09
 * Time: 18:21
 */

namespace App\Api\Logic\Promotion;


use App\Api\Logic\Promotion\Matcher\RuleMatcherInterface;

class MatcherAdapter implements RuleMatcherInterface
{
    private $_creator;

    public function __construct($operator_class)
    {
        $className = 'App\\Api\\Logic\\Promotion\\Matcher\\' . ucfirst(camel_case($operator_class));
        $this->_creator = new $className();
    }

    public function exec($times, $config = array(), $filter_data = array())
    {
        return $this->_creator->exec($times,$config,$filter_data);
    }

    public function isBatchLimit($config)
    {
        return $this->_creator->isBatchLimit($config);
    }
}
