<?php

namespace App\Api\Logic\Delivery\Formula;

use App\Api\Logic\Delivery\FormulaTarget;

use App\Api\Model\Delivery\RuleFormula as RuleFormulaModel;

/**
 * 根据重量计算运费
 */
class WeightAdaptee extends FormulaTarget
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
            'type' => $this->type,
            'value' => json_encode(array(
                'first_weight' => $formula['first_weight'],
                'first_money' => $formula['first_money'],
                'continue_weight' => $formula['continue_weight'],
                'continue_money' => $formula['continue_money'],
                'weight_type' => $formula['weight_type'] == 1 ? 1 : 2, // 1克  2千克
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
        if (!$rule || !isset($rule['first_weight'], $rule['first_money'], $rule['continue_weight'], $rule['continue_money'])) {
            return $this->Response(false, '运费规则错误-详细');
        }

        if ($elementData['weight_type'] == 1) {
            // 克
            $weight = bcmul($elementData['weight'], 1000);
        } else {
            // 千克
            $weight = $elementData['weight'];
        }

        $sz = $rule['first_weight']; // 首重
        $sj = $rule['first_money']; // 首金
        $xz = $rule['continue_weight']; // 续重
        $xj = $rule['continue_money']; // 续金
        $money = self::calcWeightFreight($weight, $sz, $sj, $xz, $xj);
        $message = "该商品{$sz}千克内{$sj}元,每增加{$xz}千克加{$xj}元";
        \Neigou\Logger::Debug('service.WeightAdaptee.runFormula', ['action' => 'getfreight', 'data' => $elementData, 'weight' => $weight, 'show' => $message, 'money' => $money, 'rule_info' => $ruleInfo]);
        return $this->Response(true, '成功', [
            'freight' => number_format($money, 2, ".", ""),
            'message' => $message,
        ]);
    }

    /**
     * 重量算法，注意此方法可能被ExpressionsAdaptee调用
     * @param string $weight 商品重量
     * @param string $firstWeight 首重
     * @param string $firstMoney 首金
     * @param string $continueWeight 续重
     * @param string $continueMoney 续金
     * @return string
     */
    public static function calcWeightFreight($weight, $firstWeight, $firstMoney, $continueWeight, $continueMoney)
    {
        if (bccomp($firstWeight, $weight, 3) >= 0) {
            return $firstMoney;
        }
        // 续重重量
        $addWeight = bcsub($weight, $firstWeight, 3);
        // 续重份数
        $addCount = ceil(bcdiv($addWeight, $continueWeight, 3));
        // 续重金额
        $addMoney = bcmul($addCount, $continueMoney, 2);
        $money = bcadd($firstMoney, $addMoney, 2);
        return bccomp($money, '0', 2) >= 0 ? $money : 0;
    }
}
