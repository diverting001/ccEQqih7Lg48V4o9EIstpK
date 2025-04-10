<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-09-25
 * Time: 10:24
 */

namespace App\Api\Logic\Rule;

use App\Api\Common\Common;
use App\Api\Model\Rule\Rule as RuleModel;

class NeigouMvp extends SyncRule
{
    const SYNC_URI = '/openapi/scene/search';

    public function run($channel, $filter = [], $page = 1, $pageSize = 20)
    {
        $sendData = [
            'filter'       => [
                'is_preview' => 0
            ],
            'request_time' => time(),
            'page'         => $page,
            'pageSize'     => $pageSize
        ];

        if (isset($filter['searchName']) && $filter['searchName']) {
            $sendData['filter']['name'] = $filter['searchName'];
        }

        $sendData['sign'] = Common::GetMvpSign($sendData);

        $curl = new \Neigou\Curl();

        $curlRes = $curl->Get(config('neigou.MVP_DOMIN') . self::SYNC_URI, $sendData);

        $curlData = json_decode($curlRes, true);
        if (isset($curlData['data']['list']) && $curlData['data']['list']) {
            $ruleList = $curlData['data']['list'];
        } else {
            $ruleList = [];
        }

        $ruleModel = new RuleModel();

        $ruleIdList = [];

        foreach ($ruleList as $rule) {
            $ruleInfo = $ruleModel->getRuleInfo([
                'channel'     => $channel->channel,
                'external_bn' => $rule['key']
            ]);

            if ($ruleInfo) {
                if ($rule['name'] != $ruleInfo->name) {
                    $ruleModel->updateRule($ruleInfo->id, [
                        'name' => $rule['name'],
                        'desc' => '',
                    ]);
                }
            } else {
                $ruleModel->createRule([
                    'rule_bn'     => $this->getRuleBn(),
                    'name'        => $rule['name'],
                    'desc'        => '',
                    'channel'     => $channel->channel,
                    'external_bn' => $rule['key'],
                    'create_time' => time(),
                    'update_time' => time(),
                ]);
            }
            $ruleIdList[] = $rule['key'];
        }

        return [
            'external_bns' => $ruleIdList,
            'total'        => $curlData['data']['total']
        ];
    }
}
