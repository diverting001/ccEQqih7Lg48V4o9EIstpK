<?php
/**
 * neigou_service-stock
 * @package     api
 * @author      xupeng
 * @since       Version
 * @filesource
 */

namespace App\Api\Logic\Invoice\Perform;

use App\Api\Logic\Openapi;

/**
 * openapi perform
 *
 * @package     api
 * @category    Logic
 * @author        xupeng
 */
class OpenapiPerform
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
        $result = $this->_request('/ChannelInterop/V2/ThirdInvoice/ThirdInvoice/apply', $applyInfo);

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
        $result = $this->_request('/ChannelInterop/V2/ThirdInvoice/ThirdInvoice/cancel', $applyInfo);

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
     * @param   $path           string  地址
     * @param   $requestData    array   请求参数
     * @return boolean
     */
    private function _request($path, $requestData)
    {
        if (empty($path) OR empty($requestData)) {
            return false;
        }

        $openapi_logic = new Openapi();
        $result = $openapi_logic->Query($path, $requestData);

        if ($result['Result'] != 'true') {
            \Neigou\Logger::Debug('invoice_jiabao_v2_fail', array(
                'path' => $path,
                'sender' => json_encode($requestData),
                'reason' => json_encode($result),
            ));
        }

        return $result;
    }

}
