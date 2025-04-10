<?php

namespace App\Api\Logic\Delivery\Formula;

use App\Api\Logic\Delivery\FormulaTarget;
use App\Api\Model\Delivery\RuleFormula as RuleFormulaModel;

/**
 * 阶梯运费
 */
class TieredAdaptee extends FormulaTarget
{
    private $type;

    public function __construct($type)
    {
        $this->type = $type;
    }


    public function createRuleFormula($ruleId, $formula)
    {
        $checkResult = $this->checkConfig($formula['config']);
        if ($checkResult !== true) {
            return $checkResult;
        }
        $formulaId = RuleFormulaModel::Create([
            'rule_id' => $ruleId,
            'type' => $this->type,
            'value' => json_encode(array(
                'config' => $formula['config']
            )),
        ]);
        if (!$formulaId) {
            return $this->Response(false, '运费创建失败【20】');
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
        if (!$rule || !isset($rule['config'])) {
            return $this->Response(false, '运费规则错误-详细');
        }

        $money = $this->calcTieredFreight($elementData['subtotal'], $rule['config']);
        $message = $this->getMessage($rule['config']);
        \Neigou\Logger::Debug('service.TieredAdaptee.runFormula', ['action' => 'getfreight', 'data' => $elementData, 'config' => $rule, 'money' => $money, 'message' => $message]);
        return $this->Response(true, '成功', [
            'freight' => number_format($money, 2, ".", ""),
            'message' => $message,
        ]);
    }

    private function calcTieredFreight($subTotal, $config)
    {
        foreach ($config as $item) {
            $compare = bccomp($subTotal, $item['total_amount'], 2);
            if ($item['contain_type'] == 1 && $compare < 0) {
                // 小于
                return $item['freight_amount'];
            } else if ($item['contain_type'] == 2 && $compare <= 0) {
                // 小于等于
                return $item['freight_amount'];
            } else if ($item['contain_type'] == 3 && $compare > 0) {
                // 大于
                return $item['freight_amount'];
            } else if ($item['contain_type'] == 4 && $compare >= 0) {
                // 大于等于
                return $item['freight_amount'];
            }
        }
        return 0;
    }

    /**
     * @param $config
     * @return array|bool
     */
    public function checkConfig($config)
    {
        if (!is_array($config) || count($config) < 2) {
            return $this->Response(false, '运费创建失败【1】');
        }
        $autoInc = 0; // 判断自增数据
        $lastTotalMoney = 0; // 上一个订单金额。  订单金额应该逐行递增
        foreach ($config as $key => $item) {
            if ($autoInc !== $key) {
                return $this->Response(false, '运费创建失败【2】');
            }
            $autoInc++;
            if (!isset($item['contain_type'], $item['total_amount'], $item['freight_amount'])) {
                return $this->Response(false, '运费创建失败【3】');
            }

            // 检查类型
            $isLast = $key == count($config) - 1;
            // 前n-1行均为小于/小于等于
            if (!$isLast && !in_array($item['contain_type'], [1, 2])) {
                return $this->Response(false, '运费创建失败【4】');
            }

            // 最后一行为大于/大于等于
            if ($isLast) {
                $needContainType = $config[$key - 1]['contain_type'] == 1 ? 4 : 3;
                if ($needContainType != $item['contain_type']) {
                    return $this->Response(false, '运费创建失败【5】');
                }
            }

            // 检查金额，小数点
            $pointCount = strrpos($item['total_amount'], '.') === false ? 0 : strlen(substr($item['total_amount'], strrpos($item['total_amount'], '.') + 1));
            if (!is_numeric($item['total_amount']) || $item['total_amount'] < 0 || $pointCount > 2) {
                // 金额请保留2位，且不能为0
                return $this->Response(false, '运费创建失败【6】');
            }
            $pointCount = strrpos($item['freight_amount'], '.') === false ? 0 : strlen(substr($item['freight_amount'], strrpos($item['freight_amount'], '.') + 1));
            if (!is_numeric($item['freight_amount']) || $item['freight_amount'] < 0 || $pointCount > 2) {
                // 运费请保留2位。允许为0
                return $this->Response(false, '运费创建失败【7】');
            }

            // 检查订单金额。需要逐级递增
            $isFirst = $key == 0;
            if ($isFirst) {
                // 首次
                if (bccomp($item['total_amount'], 0, 2) == 0) {
                    // 首位金额不能为空
                    return $this->Response(false, '运费创建失败【8】');
                }
                $lastTotalMoney = $item['total_amount'];
            } else if (!$isLast) {
                // 中间间隔
                if (bccomp($lastTotalMoney, $item['total_amount'], 2) >= 0) {
                    // 间隔金额大小异常;
                    return $this->Response(false, '运费创建失败【9】');
                }
                $lastTotalMoney = $item['total_amount'];
            }
            if ($isLast) {
                // 最后
                if (bccomp($lastTotalMoney, $item['total_amount'], 2) != 0) {
                    // 末尾金额大小异常
                    return $this->Response(false, '运费创建失败【10】');
                }
            }
        }
        return true;
    }

    private function getMessage($config): string
    {
        $result = array();
        $containRelation = array(
            1 => '小于',
            2 => '小于等于',
            3 => '大于',
            4 => '大于等于',
        );
        $reverseRelation = array(
            1 => 4,
            2 => 3,
            3 => 2,
            4 => 1,
        );
        foreach ($config as $key => $item) {
            $money = $item['total_amount'] . '元';
            $freight = bccomp($item['freight_amount'], 0, 2) == 0 ? '不收取运费' : '收取' . $item['freight_amount'] . '元运费';
            $contain = $containRelation[$item['contain_type']];
            $isFirst = $key == 0;
            $isLast = $key == count($config) - 1;

            if ($isFirst) {
                $result[] = $contain . $money . $freight;
            } else if ($isLast) {
                $preMoney = $config[$key - 1]['total_amount'] . '元';
                $result[] = $contain . $preMoney . $freight;
            } else {
                $preReverseContain = $containRelation[$reverseRelation[$config[$key - 1]['contain_type']]];
                $preMoney = $config[$key - 1]['total_amount'] . '元';
                $result[] = $preReverseContain . $preMoney . $contain . $money . $freight;
            }
        }
        return implode(',', $result);
    }
}
