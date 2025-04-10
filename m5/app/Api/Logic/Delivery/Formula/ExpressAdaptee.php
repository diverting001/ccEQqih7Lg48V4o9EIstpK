<?php

namespace App\Api\Logic\Delivery\Formula;

use App\Api\Logic\Delivery\FormulaTarget;

use App\Api\Model\Delivery\RuleFormula as RuleFormulaModel;

/**
 * 表达式计算运费
 */
class ExpressAdaptee extends FormulaTarget
{
    private $type;

    public function __construct($type)
    {
        $this->type = $type;
    }

    private function checkExp($checkExp)
    {
        if (!isset($checkExp) || !is_string($checkExp)) {
            return $this->Response(false, '运费校验规则失败【1】');
        }
        // 注意变量前面需要有转移符号 \
        // __weight $weight_kg $weight_g $subtotal floor ceil bcadd bcmul bcsub bcdiv bccomp == === <= >= ? : ( ) 数字 . ,
        $allowExp = array(
            '__weight', '\$weight_kg', '\$weight_g', '\$subtotal', 'floor', 'ceil', 'bcadd', 'bcmul', 'bcsub', 'bcdiv', 'bccomp', '==', '===', '<=', '>=', '\?', ':', '\(', '\)', '\d', '\.', ',',
        );
        $reg = "/^(" . implode('|', $allowExp) . ")+$/";

        if (!preg_match($reg, $checkExp)) {
            return $this->Response(false, '运费校验规则失败【2】');
        }
        return $this->Response(true, '成功');
    }

    public function createRuleFormula($ruleId, $formula)
    {
        $checkExp = $this->checkExp($formula['exp']);
        if (!$checkExp['status']) {
            return $checkExp;
        }
        $formulaId = RuleFormulaModel::Create([
            'rule_id' => $ruleId,
            'type' => $this->type,
            'value' => json_encode(array(
                'exp' => $formula['exp'],
                'show' => $formula['show'] ?: '',
            ))
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
        $rule = json_decode($ruleInfo->value, true);
        if (!$rule || !isset($rule['exp'])) {
            return $this->Response(false, '运费规则错误');
        }

        $money = $this->runExpGetMoney($rule['exp'], $elementData);
        \Neigou\Logger::Debug('service.ExpressAdaptee.runFormula', ['action' => 'getfreight', 'data' => $elementData, 'rule' => $rule, 'money' => $money]);
        return $this->Response(true, '成功', [
            'freight' => number_format($money, 2, ".", ""),
            'show' => $elementData['show'],
        ]);
    }

    private function runExpGetMoney($exp, $elementData)
    {
        // 计算运费
        $subtotal = $elementData['subtotal'];
        $weight_kg = $elementData['weight'];
        $weight_g = bcmul($elementData['weight'], 1000, 3);
        $exp = str_replace('$subtotal', $subtotal, $exp);
        $exp = str_replace('$weight_g', $weight_g, $exp);
        $exp = str_replace('$weight_kg', $weight_kg, $exp);
        $exp = str_replace('__weight', 'WeightAdaptee::calcWeightFreight', $exp);

        $money = 0;
        eval("$money=$exp;");
        return bccomp($money, '0', 2) >= 0 ? $money : 0;
    }
}
