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

class Daishu extends SyncRule
{

    public function run($channel, $filter = [], $page = 1, $pageSize = 20)
    {
        //查询表里面记录
        $ruleModel = new RuleModel();
        $ruleList  = $ruleModel->getRuleList($channel->channel, [], $page, $pageSize);
        $ruleIdList = [];
        $total  = 0;
        if(!empty($ruleList)){
            foreach ($ruleList as $v){
                $ruleIdList[]   = $v->channel_rule_bn;
            }
            $total  = $ruleModel->getRuleCount($channel->channel,[]);
        }
        return [
            'external_bns' => $ruleIdList,
            'total' => $total
        ];
    }
}
