<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-09-19
 * Time: 15:47
 */

namespace App\Api\V1\Service\Rule;


use App\Api\Model\Rule\RuleChannel as RuleChannelModel;
use App\Api\Model\Rule\Rule as RuleModel;
use App\Api\V1\Service\ServiceTrait;

class Rule
{
    use ServiceTrait;

    /**
     * 获取指定消费渠道规则
     * @param $channel
     * @param $filter
     * @param int $page
     * @param int $pageSize
     * @return array
     */
    public function getRule($channel, $filter, $page = 1, $pageSize = 20)
    {
        $ruleChannelModel = new RuleChannelModel();
        $ruleChannelList  = $ruleChannelModel->getRuleChannel();
        if (!$channel || !array_key_exists($channel, $ruleChannelList)) {
            return $this->Response(false, '当前消费渠道不可用');
        }

        //查询先同步
        $syncClassName = 'App\\Api\\Logic\\Rule\\' . ucfirst(camel_case(strtolower($channel)));
        if (!class_exists($syncClassName)) {
            return $this->Response(false, '当前渠道规则尚未同步');
        }
        $syncClass      = new $syncClassName();
        $channelSyncRes = $syncClass->run($ruleChannelList[$channel], $filter, $page, $pageSize);

        //同步成功后，返回三方返回的规则ID，避免查询到已经删除的规则
        if ($channelSyncRes['external_bns']) {
            $filter['channel_rule_ids'] = $channelSyncRes['external_bns'];
        } else {
            return $this->Response(true, '成功', [
                'count' => 0,
                'list'  => []
            ]);
        }

        $ruleModel = new RuleModel();
        $ruleList  = $ruleModel->getRuleList($channel, $filter, 1, $pageSize);

        return $this->Response(true, '成功', [
            'count' => $channelSyncRes['total'],
            'list'  => $ruleList ? $ruleList : []
        ]);
    }

    /**
     * 根据渠道and规则ID查询对应的业务规则ID
     * @param $channel
     * @param $ruleIds
     * @return array
     */
    public function getChannelRuleBn($channel, $ruleBns)
    {
        if (!is_array($ruleBns) || count($ruleBns) <= 0) {
            return $this->Response(false, '规则ID错误');
        }

        $ruleChannelModel = new RuleChannelModel();
        $ruleChannelList  = $ruleChannelModel->getRuleChannel();
        if (!$channel || !array_key_exists($channel, $ruleChannelList)) {
            return $this->Response(false, '当前消费渠道不可用');
        }

        $ruleModel = new RuleModel();

        $dbRuleList  = $ruleModel->getRuleList($channel, ['rule_bns' => $ruleBns], 1, count($ruleBns));
        $channelRule = [];
        foreach ($dbRuleList as $rule) {
            $channelRule[$rule->rule_bn] = $rule;
        }

        return $this->Response(true, '成功', $channelRule);
    }

    public function queryRule($filter, $page = 1, $pageSize = 20)
    {
        $ruleModel = new RuleModel();
        $ruleList  = $ruleModel->queryRuleList($filter, $page, $pageSize);
        return $this->Response(true, '成功', $ruleList);
    }


}
