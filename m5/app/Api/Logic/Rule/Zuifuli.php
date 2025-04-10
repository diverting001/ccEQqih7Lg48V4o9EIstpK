<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-09-25
 * Time: 10:24
 */

namespace App\Api\Logic\Rule;

use App\Api\Model\PointScene\Rule as NeigouRuleModel;
use App\Api\Model\Rule\Rule as RuleModel;

class Zuifuli extends SyncRule
{

    public function run($channel, $filter = [], $page = 1, $pageSize = 20)
    {


        $ruleIdList[] = 'ZUIFULI';
        return [
            'external_bns' => $ruleIdList,
            'total' => 1
        ];
    }
}
