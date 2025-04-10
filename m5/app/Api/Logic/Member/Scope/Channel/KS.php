<?php

namespace App\Api\Logic\Member\Scope\Channel;

use App\Api\Logic\Member\Scope\ScopeAdapterInterface;

class KS implements ScopeAdapterInterface
{
    public function create($channel = '', $identifyData = array())
    {
        $response = $this->_request('MemberScope', 'addRuleAndItems', $identifyData);
        if ($response['Result'] == 'true' && $response['Data']['rule_key']) {
            $data = [
                'third_unique_code' => $response['Data']['rule_key'],
            ];

            return $this->formatReturnMessage(true, $data);
        }

        return $this->formatReturnMessage(false, [], $response['ErrorMsg']);
    }

    /**
     * CLUB请求
     * @param $class
     * @param $method
     * @param $requestData
     * @return array|bool|mixed
     */
    private function _request($class, $method, $requestData)
    {
        $return = array();

        if (empty($class) or empty($method) or empty($requestData)) {
            return false;
        }

        $curl = new \Neigou\Curl();
        $curl->time_out = 20;
        $post = array(
            'class_obj' => $class,
            'method' => $method,
        );

        $post = array_merge($post, $requestData);
        $post['token'] = self::_getToken($post);
        $result = $curl->Post(config('neigou.CLUB_DOMAIN') . '/Home/OpenApi/apirun', $post);

        \Neigou\Logger::debug('service_club_request', array( 'report_name' => 'scope.ng', 'sender' => $post, 'remark' => $result ));

        if ($curl->GetHttpCode() == 200) {
            $return = json_decode($result, true);
        }

        return $return;
    }

    private static function _getToken($arr)
    {
        ksort($arr);
        $sign_ori_string = "";
        foreach ($arr as $key => $value) {
            if (!empty($value) && !is_array($value)) {
                if (!empty($sign_ori_string)) {
                    $sign_ori_string .= "&$key=$value";
                } else {
                    $sign_ori_string = "$key=$value";
                }
            }
        }
        $sign_ori_string .= ("&key=" . config('neigou.CLUB_SIGN'));

        return strtoupper(md5($sign_ori_string));
    }

    private function formatReturnMessage($result = true, $data = [], $msg = '成功')
    {
        return [
            'result' => $result ? true : false,
            'data' => $data ? $data : [],
            'msg' => $msg
        ];
    }

    public function update($thirdUniqueCode, $params)
    {
        $post = [
            'rule_key' => $thirdUniqueCode,
            'scope' => $params['scope'],
            'member_source' => 3,
            'increase_data' => $params['increase_data'] ? : [],
            'delete_data' => $params['delete_data'] ? : [],
            'ext_data' => !empty($params['ext_data']) ? $params['ext_data'] : []
        ];
        $response = $this->_request('MemberScope', 'editRuleTagItems', $post);
        if ($response['Result'] == 'true' && $response['Data']['result']) {
            return $this->formatReturnMessage();
        }

        return $this->formatReturnMessage(false, [], $response['ErrorMsg']);
    }

    public function getScopeIdentifyListByRuleBn($thirdUniqueCode, $page, $limit)
    {
        $post = [
            'rule_key' => $thirdUniqueCode,
            'member_source' => 3,
            'page' => $page,
            'page_size' => $limit
        ];

        $response = $this->_request('MemberScope', 'getRuleItems', $post);
        if ($response['Result'] == 'true' && $data = $response['Data']) {
            $format = [
                'total_count' => isset($data['count']) ? $data['count'] : 0,
                'total_page' => isset($data['total_page']) ? $data['total_page'] : 0,
                'data' => [
                    'scope' => $data['scope'],
                    'list' => $data['list'] ? : []
                ]
            ];

            return $this->formatReturnMessage(true, $format);
        }

        return $this->formatReturnMessage(false, [], $response['ErrorMsg']);
    }

    // 获取 token

    public function getScopeByRuleBnsAndIdentify($thirdUniqueCodes, $identifyData)
    {
        $post = [
            'rule_keys' => $thirdUniqueCodes,
            'tags' => $identifyData,
        ];

        $response = $this->_request('MemberScope', 'identifyRuleTagItems', $post);
        if ($response['Result'] == 'true') {
            return $this->formatReturnMessage(true, $response['Data']);
        }

        return $this->formatReturnMessage(false, [], $response['ErrorMsg']);
    }
}
