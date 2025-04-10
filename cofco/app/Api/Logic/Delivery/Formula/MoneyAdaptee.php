<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-10-18
 * Time: 18:40
 */

namespace App\Api\Logic\Delivery\Formula;


use App\Api\Logic\Delivery\FormulaTarget;

use App\Api\Model\Delivery\RuleFormula as RuleFormulaModel;

class MoneyAdaptee extends FormulaTarget
{
    private $type;

    public function __construct($type)
    {
        $this->type = $type;
    }


    public function createRuleFormula($ruleId, $formula)
    {
        $formulaId = RuleFormulaModel::Create([
            'rule_id' => $ruleId,
            'type'    => $this->type,
            'value'   => $formula['money'] ?? 0
        ]);
        if (!$formulaId) {
            return $this->Response(false, '运费创建失败【1】');
        }
        return $this->Response();
    }

    public function deleteRuleFormula($formulaId)
    {
        $status = RuleFormulaModel::Delete($formulaId);
        if (!$status) {
            return $this->Response(false, '运费删除失败【1】');
        }
        return $this->Response();
    }

    public function runFormula($formulaId, $elementData)
    {
        $ruleInfo = RuleFormulaModel::Find($formulaId);

        if (!$ruleInfo) {
            return $this->Response(false, '运费规则错误');
        }

        return $this->Response(true, '成功', [
            'freight' => number_format($ruleInfo->value, 2, ".", "")
        ]);
    }
}
