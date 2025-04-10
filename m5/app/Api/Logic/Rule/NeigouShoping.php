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

class NeigouShoping extends SyncRule
{
    public function run($channel, $filter = [], $page = 1, $pageSize = 20)
    {
        $ruleModel = new RuleModel();
        $whereArr  = [
            'disabled' => 0,
        ];

        if (isset($filter['searchName']) && $filter['searchName']) {
            $whereArr['name'] = $filter['searchName'];
        }

        $ruleCount = NeigouRuleModel::QueryCount($whereArr);

        $ruleList = NeigouRuleModel::QueryList(
            $whereArr,
            $page,
            $pageSize
        );

        $ruleIdList = [];
        foreach ($ruleList as $rule) {
            $ruleInfo = $ruleModel->getRuleInfo([
                'channel'     => $channel->channel,
                'external_bn' => $rule->rule_id
            ]);

            if ($ruleInfo) {
                if ($rule->name != $ruleInfo->name || $rule->desc != $ruleInfo->desc) {
                    $ruleModel->updateRule($ruleInfo->id, [
                        'name' => $rule->name,
                        'desc' => $rule->desc,
                    ]);
                }
            } else {
                $ruleModel->createRule([
                    'rule_bn'     => $this->getRuleBn(),
                    'name'        => $rule->name,
                    'desc'        => $rule->desc,
                    'channel'     => $channel->channel,
                    'external_bn' => $rule->rule_id,
                    'create_time' => time(),
                    'update_time' => time(),
                ]);
            }
            $ruleIdList[] = $rule->rule_id;
        }

        return [
            'external_bns' => $ruleIdList,
            'total' => $ruleCount
        ];
    }
}
