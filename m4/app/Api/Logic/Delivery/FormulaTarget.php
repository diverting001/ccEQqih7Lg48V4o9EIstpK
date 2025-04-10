<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-10-18
 * Time: 18:37
 */

namespace App\Api\Logic\Delivery;

abstract class FormulaTarget
{
    abstract public function createRuleFormula($ruleId, $formulaList);

    abstract public function deleteRuleFormula($formulaId);

    abstract public function runFormula($formulaId, $elementData);

    public function Response($status = true, $msg = '成功', $data = [])
    {
        return [
            'status' => $status,
            'msg'    => $msg,
            'data'   => $data,
        ];
    }
}
