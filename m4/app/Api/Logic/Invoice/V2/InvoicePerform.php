<?php
/**
 * neigou_service-stock
 * @package     api
 * @author      xupeng
 * @since       Version
 * @filesource
 */

namespace App\Api\Logic\Invoice\V2;

use App\Api\Logic\Invoice\Perform\OpenapiPerform;

/**
 * 发票平台V2 Logic
 *
 * @package     api
 * @category    Logic
 * @author        xupeng
 */
class InvoicePerform
{
    private $_openapiPerform = array(
        'JIABAO'
    );

    /**
     * 创建发票申请
     *
     * @param   $applyInfo   array   数据
     * @param   $errMsg     string   错误信息
     * @return  bool
     */
    public function apply($applyInfo, & $errMsg = '')
    {
        if (empty($applyInfo)) {
            return false;
        }

        if (in_array($applyInfo['perform'], $this->_openapiPerform)) {
            $openapiPerformLogic = new OpenapiPerform();

            $ret = $openapiPerformLogic->apply($applyInfo, $errMsg);

            if ($ret == false) {
                return false;
            }

            return true;
        }

        $errMsg = '发票平台不支持';

        return false;
    }

    // --------------------------------------------------------------------

    /**
     * 取消发票申请
     *
     * @param   $applyInfo   array   数据
     * @param   $errMsg     string   错误信息
     * @return  bool
     */
    public function cancel($applyInfo, & $errMsg = '')
    {
        if (empty($applyInfo)) {
            return false;
        }

        if (in_array($applyInfo['perform'], $this->_openapiPerform)) {
            $openapiPerformLogic = new OpenapiPerform();

            $ret = $openapiPerformLogic->cancel($applyInfo, $errMsg);

            if ($ret == false) {
                return false;
            }

            return true;
        }

        $errMsg = '发票平台不支持';

        return false;
    }

}
