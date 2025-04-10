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

class JuyouMovie extends SyncRule
{
    const SYNC_URI = '/film/back/limitRule/list';

    public function run($channel, $filter = [], $page = 1, $pageSize = 20)
    {

        $sendData = [
            'pageNum'  => $page,
            'pageSize' => $pageSize
        ];

        if (isset($filter['searchName']) && $filter['searchName']) {
            $sendData['name'] = $filter['searchName'];
        }

        $curl     = new \Neigou\Curl();
        $curlRes  = $curl->Get(config('neigou.JUYOU_MOVIE_DOMIN') . self::SYNC_URI, $sendData);
        $curlData = json_decode($curlRes, true);
        if (isset($curlData['data']) && $curlData['data']) {
            $ruleList = $curlData['data'];
        } else {
            $ruleList = [];
        }

        $ruleModel = new RuleModel();

        $ruleIdList = [];
        foreach ($ruleList as $rule) {
            $ruleInfo = $ruleModel->getRuleInfo([
                'channel'     => $channel->channel,
                'external_bn' => $rule['id']
            ]);

            if ($ruleInfo) {
                if ($rule['name'] != $ruleInfo->name || $rule['intro'] != $ruleInfo->desc) {
                    $ruleModel->updateRule($ruleInfo->id, [
                        'name' => $rule['name'],
                        'desc' => $rule['intro'],
                    ]);
                }
            } else {
                $ruleModel->createRule([
                    'rule_bn'     => $this->getRuleBn(),
                    'name'        => $rule['name'],
                    'desc'        => $rule['intro'],
                    'channel'     => $channel->channel,
                    'external_bn' => $rule['id'],
                    'create_time' => time(),
                    'update_time' => time(),
                ]);
            }
            $ruleIdList[] = $rule['id'];
        }
        return [
            'external_bns' => $ruleIdList,
            'total' => $curlData['total']
        ];
    }
}
