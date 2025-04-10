<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-10-18
 * Time: 18:37
 */

namespace App\Api\Logic\Delivery;

use App\Api\Logic\Delivery\Formula\ExpressAdaptee;
use App\Api\Logic\Delivery\Formula\MoneyAdaptee;
use App\Api\Logic\Delivery\Formula\NeigouAdaptee;
use App\Api\Logic\Delivery\Formula\TieredAdaptee;
use App\Api\Logic\Delivery\Formula\WeightAdaptee;

class FormulaAdapter extends FormulaTarget
{
    protected $classObj;

    public function __construct($type)
    {
        switch ($type) {
            case 'money': // 固定金额
                $this->classObj = new MoneyAdaptee($type);
                break;
            case 'neigou': // ecstore运费
                $this->classObj = new NeigouAdaptee($type);
                break;
            case 'weight': // 重量
                $this->classObj = new WeightAdaptee($type);
                break;
            case 'express': // 表达式
                $this->classObj = new ExpressAdaptee($type);
                break;
            case 'tiered': // 阶梯运费
                $this->classObj = new TieredAdaptee($type);
                break;
            default:
                throw new \Exception('未适配的运费适配');
        }
    }


    public function createRuleFormula($ruleId, $formulaList)
    {
        return $this->classObj->createRuleFormula($ruleId, $formulaList);
    }

    public function deleteRuleFormula($formulaId)
    {
        return $this->classObj->deleteRuleFormula($formulaId);
    }

    public function runFormula($formulaId, $elementData)
    {
        return $this->classObj->runFormula($formulaId, $elementData);
    }
}
