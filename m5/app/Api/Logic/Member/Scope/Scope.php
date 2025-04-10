<?php
/**
 *Create by PhpStorm
 *User:liangtao
 *Date:2021-7-14
 */

namespace App\Api\Logic\Member\Scope;

use App\Api\Model\Member\ScopeChannel;
use App\Api\Model\Member\ScopeRule;

class Scope
{
    /**
     * 创建成员标识的规则
     * @param string $channel
     * @param array $identifyData
     * @param string $error
     * @return bool|string
     */
    public function create( $channel = '', $identifyData = array(), &$error = "" )
    {
        $scopeChannel = new ScopeChannel();
        $channels = $scopeChannel->GetAllChannelList();
        if ( !in_array( $channel, $channels ) )
        {
            $error = "当前渠道不存在";
            return false;
        }

        try
        {
            $adapter = new ScopeAdapter( $channel );
            $response = $adapter->create( $channel, $identifyData );
            if ( $response['result'] !== true )
            {
                $error = $response['msg'];
                return false;
            }

            if ( !isset( $response['data']['third_unique_code'] ) )
            {
                $error = "三方人员权限标识错误";
                return false;
            }

            $time = time();
            $ruleBn = $this->createRuleBn();
            $data = [
                'channel' => $channel,
                'rule_bn' => $ruleBn,
                'third_unique_code' => $response['data']['third_unique_code'],
                'create_time' => $time,
            ];

            $scopeRule = new ScopeRule();
            $res = $scopeRule->create( $data );
            if ( $res === true )
            {
                return $ruleBn;
            }

            return false;
        } catch ( \Exception $e )
        {
            $error = $e->getMessage();
            return false;
        }
    }

    /**
     * 更新成员标识规则
     * @param $ruleBn
     * @param array $params
     * @param string $error
     * @return bool
     * @throws \Exception
     */
    public function update($channel, $ruleBn, $params = [], &$error = '')
    {
        if (empty($ruleBn)) {
            $error = "规则参数错误";
            return false;
        }

        $scopeRule = new ScopeRule();
        $ruleData = $scopeRule->findRuleByBn($ruleBn);
        if (empty($ruleData)) {
            $error = "规则错误";
            return false;
        }
        if (($ruleData['channel'] !== $channel) && !$scopeRule->upateByRuleByBn($ruleBn, ['channel' => $channel])) {
            $error = "渠道更新失败";
            return false;
        }
        $adapter = new ScopeAdapter($channel);
        $response = $adapter->update($ruleData['third_unique_code'], $params);
        if ($response['result'] !== true) {
            $error = $response['msg'];
            return false;
        }

        return true;
    }

    /**
     * 查询规则下所有成员标识
     * @param string $ruleBn
     * @param $page
     * @param $limit
     * @param $error
     * @return array|bool
     * @throws \Exception
     */
    public function getScopeIdentifyListByRuleBn( $ruleBn = '', $page, $limit, &$error )
    {
        if ( empty( $ruleBn ) )
        {
            $error = "规则参数错误";
            return false;
        }

        $scopeRule = new ScopeRule();
        $ruleData = $scopeRule->findRuleByBn( $ruleBn );
        if ( empty( $ruleData ) )
        {
            $error = "规则错误";
            return false;
        }

        $adapter = new ScopeAdapter( $ruleData['channel'] );
        $response = $adapter->getScopeIdentifyListByRuleBn( $ruleData['third_unique_code'], $page, $limit );
        if ( $response['result'] !== true )
        {
            $error = $response['msg'];
            return false;
        }

        return [
            'page' => $page,
            'limit' => $limit,
            'total_count' => isset( $response['data']['total_count'] ) ? $response['data']['total_count'] : 0,
            'total_page' => isset( $response['data']['total_page'] ) ? $response['data']['total_page'] : 0,
            'channel'=>$ruleData['channel'],
            'identify_data' => isset( $response['data']['data'] ) ? $response['data']['data'] : []
        ];
    }

    /**
     * 获取多规则和多成员可见性
     * @param array $ruleBns
     * @param array $identifyData
     * @param string $error
     * @return array|bool
     * @throws \Exception
     */
    public function getScopeByRuleBnsAndIdentify($ruleBns = [], $identifyData = [], &$error = '')
    {
        if (empty($ruleBns) || empty($identifyData)) {
            $error = "参数错误";
            return false;
        }

        $scopeRule = new ScopeRule();
        $ruleDataList = $scopeRule->getRuleListByBn($ruleBns);
        if (empty($ruleDataList)) {
            $error = "规则错误";
            return false;
        }
        $format = array();
        foreach ($ruleDataList as $channel => $ruleData) {
            $thirdUniqueCodes = array_column($ruleData, 'third_unique_code');
            $ruleBnMapping = [];
            foreach ($ruleData as $rule) {
                $ruleBnMapping[$rule['third_unique_code']] = $rule['rule_bn'];
            }
            $adapter = new ScopeAdapter($channel);
            $response = $adapter->getScopeByRuleBnsAndIdentify($thirdUniqueCodes, $identifyData);
            if ($response['result'] !== true) {
                $error = $response['msg'];
                return false;
            }
            foreach ($response['data'] as $thirdUniqueCode => $item) {
                if (in_array($thirdUniqueCode, $thirdUniqueCodes, true)) {
                    $format[$ruleBnMapping[$thirdUniqueCode]] = $item;
                }
            }
        }
        return $format;
    }

    private function createRuleBn()
    {
        $bn = strtoupper( substr( md5( uniqid( true ) ), 16, 16 ) );
        $scopeRule = new ScopeRule();
        $ruleData = $scopeRule->findRuleByBn( $bn );
        if ( empty( $ruleData ) )
        {
            return $bn;
        }

        return $this->createRuleBn();
    }
}