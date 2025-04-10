<?php
/**
 * neigou_service-stock
 * @package     api
 * @author      xupeng
 * @since       Version
 * @filesource
 */

namespace App\Api\Logic\Invoice\Perform;

/**
 * salyut perform
 *
 * @package     api
 * @category    Logic
 * @author        xupeng
 */
class SalyutPerform
{
    /**
     * 发票申请
     *
     * @param   $applyInfo   array   数据
     * @param   $errMsg     string   错误信息
     * @return  array|bool
     */
    public function apply($applyInfo, & $errMsg = '')
    {
        if (empty($applyInfo)) {
            return false;
        }

        // 发票申请
        $result = $this->_request('ThirdInvoice', 'apply', $applyInfo);

        if ($result['Result'] != 'true') {
            $errMsg = $result['ErrorMsg'] ? $result['ErrorMsg'] : '请求失败';
            return false;
        }

        return true;
    }

    // --------------------------------------------------------------------

    /**
     * 发票作废申请
     *
     * @param   $applyInfo   array   数据
     * @param   $errMsg     string   错误信息
     * @return  array|bool
     */
    public function cancel($applyInfo, & $errMsg = '')
    {
        if (empty($applyInfo)) {
            return false;
        }

        // 发票申请
        $result = $this->_request('ThirdInvoice', 'cancel', $applyInfo);

        if ($result['Result'] != 'true') {
            $errMsg = $result['ErrorMsg'] ? $result['ErrorMsg'] : '请求失败';
            return false;
        }

        return true;
    }

    // --------------------------------------------------------------------

    /**
     * 请求方法
     *
     * @param   $class          string  类
     * @param   $method         string  方法
     * @param   $requestData    array   请求参数
     * @return boolean
     */
    private function _request($class, $method,  $requestData)
    {
        if (empty($class) OR empty($method) OR empty($requestData)) {
            return false;
        }

        $curl = new \Neigou\Curl();
        $curl->time_out = 20;
        $request_params = array(
            'class_obj' => $class,
            'method'    => $method,
            'data' => json_encode($requestData),
        );

        $request_params['token'] =  self::getJDToken($request_params);

        $result = $curl->Post(config('neigou.SALYUT_DOMIN'). '/OpenApi/apirun', $request_params);

        \Neigou\Logger::General('salyut_service_order_create', array('sender' => $request_params, 'remark' => $result));
        if ($curl->GetHttpCode() != 200) {
            $result = false;
        } else {
            $result = json_decode($result, true);
        }

        return $result;
    }

    // 获取 token
    private static function getJDToken($arr)
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
        $sign_ori_string .= ("&key=" . config('neigou.SALYUT_SIGN'));
        return strtoupper(md5($sign_ori_string));
    }

}
