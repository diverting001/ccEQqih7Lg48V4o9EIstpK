<?php

namespace App\Api\Logic\Neigou;

class Club
{
    /**
     * 获取配置信息
     *
     * @return array
     */
    public function getConfig($scope, $scopeValue = '', $key = '')
    {
        $return = array();
        $data = array(
            'scope'         => $scope,
            'scope_value'   => $scopeValue,
            'key'           => $key,
        );
        $result = $this->_request('Config', 'get_config', $data);

        if ( ! empty($result['Data'])) {
            $return = $result['Data'];
        }

        return $return;
    }

    // --------------------------------------------------------------------

    /**
     * 获取订单审核详情
     *
     * @return mixed
     */
    private function _request($class, $method,  $requestData)
    {
        $return = array();

        if (empty($class) OR empty($method) OR empty($requestData)) {
            return false;
        }

        $curl = new \Neigou\Curl();
        $curl->time_out = 20;
        $request_params = array(
            'class_obj' => $class,
            'method'    => $method,
        );

        $request_params = array_merge($request_params, $requestData);
        $request_params['token'] = self::_getToken($request_params);

        $result = $curl->Post(config('neigou.CLUB_DOMAIN'). '/Home/OpenApi/apirun', $request_params);

        \Neigou\Logger::debug('service_club_request', array('sender' => $request_params, 'remark' => $result));
        if ($curl->GetHttpCode() == 200) {
            $return = json_decode($result, true);
        }

        return $return;
    }

    // 获取 token
    private static function _getToken($arr)
    {
        ksort($arr);
        $sign_ori_string = "";
        foreach ($arr as $key => $value)
        {
            if ( ! empty($value) && ! is_array($value))
            {
                if ( ! empty($sign_ori_string))
                {
                    $sign_ori_string .= "&$key=$value";
                }
                else
                {
                    $sign_ori_string = "$key=$value";
                }
            }
        }
        $sign_ori_string .= ("&key=" . config('neigou.CLUB_SIGN'));

        return strtoupper(md5($sign_ori_string));
    }

}
