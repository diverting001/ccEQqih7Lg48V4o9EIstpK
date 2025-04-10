<?php
/**
 * neigou_service-stock
 * @package     api
 * @author      xupeng
 * @since       Version
 * @filesource
 */

namespace App\Api\V1\Controllers;

use App\Api\Common\Controllers\BaseController;
use App\Api\V1\Service\Order\Customization as OrderCustomizationLogic;
use Illuminate\Http\Request;

/**
 * 订单定制 Controller
 *
 * @package     api
 * @category    Controller
 * @author      xupeng
 */
class OrderCustomizationController extends BaseController
{
    /**
     * 获取订单定制信息
     *
     */
    public function getOrderCustomizationInfo(Request $request)
    {
        $params = $this->getContentArray($request);

        // 验证请求参数
        if (empty($params['order_id'])) {
            $this->setErrorMsg('请求参数错误');
            return $this->outputFormat(null, 400);
        }

        $orderCustomizationLogic = new OrderCustomizationLogic();
        $errMsg = '';
        // 获取订单定制信息
        $customizationInfo = $orderCustomizationLogic->getOrderCustomizationInfo($params['order_id'], $errMsg);
        if ( ! $customizationInfo) {
            $this->setErrorMsg($errMsg);
            return $this->outputFormat(null, 401);
        }
        $this->setErrorMsg('请求成功');
        return $this->outputFormat($customizationInfo);
    }

    // --------------------------------------------------------------------

    /**
     * 获取订单定制结果
     *
     */
    public function getOrderCustomizationResult(Request $request)
    {
        $params = $this->getContentArray($request);
        // 验证请求参数
        if (empty($params['order_id'])) {
            $this->setErrorMsg('请求参数错误');
            return $this->outputFormat(null, 400);
        }

        $orderCustomizationLogic = new OrderCustomizationLogic();

        // 获取订单定制信息
        $errMsg = '';
        $customizationInfo = $orderCustomizationLogic->getOrderCustomizationResult($params['order_id'], $params['order_customization'], $errMsg);
        if ( ! $customizationInfo) {
            $this->setErrorMsg($errMsg);
            return $this->outputFormat(null, 401);
        }
        $this->setErrorMsg('请求成功');
        return $this->outputFormat($customizationInfo);
    }

}
