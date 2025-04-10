<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-10-18
 * Time: 18:37
 */

namespace App\Api\Logic\Delivery;

use App\Api\Logic\Delivery\Formula\MoneyAdaptee;
use App\Api\Logic\Delivery\Formula\NeigouAdaptee;

class FormulaAdapter extends FormulaTarget
{
    protected $classObj;

    public function __construct($type)
    {
        switch ($type) {
            case 'money':
                $this->classObj = new MoneyAdaptee($type);
                break;
            case 'neigou':
                $this->classObj = new NeigouAdaptee($type);
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
