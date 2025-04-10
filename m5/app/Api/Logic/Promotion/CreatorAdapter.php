<?php
/**
 * Created by PhpStorm.
 * User: chuanbin
 * Date: 2020-06-09
 * Time: 18:08
 */

namespace App\Api\Logic\Promotion;


use App\Api\Logic\Promotion\Creator\RuleCreatorInterface;

class CreatorAdapter implements RuleCreatorInterface
{
    private $_creator;

    public function __construct($operator_class)
    {
        $className = 'App\\Api\\Logic\\Promotion\\Creator\\' . ucfirst(camel_case($operator_class));
        $this->_creator = new $className();
    }

    public function generate($data)
    {
        return $this->_creator->generate($data);
    }

}