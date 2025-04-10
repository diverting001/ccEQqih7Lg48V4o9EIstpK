<?php


namespace App\Api\V1\Controllers;

use App\Api\Common\Controllers\BaseController;
use Illuminate\Http\Request;
use App\Api\V1\Service\Order\OrderChangeLog;

class OrderChangeLogController extends BaseController
{

    /**
     * Notes:获取订单变更日志
     * User: mazhenkang
     * Date: 2022/9/13 10:57
     * @param Request $request
     */
    public function getOrderChangeLog(Request $request)
    {
        $requestData = $this->getContentArray($request);
        $return = (new OrderChangeLog())->getLogs($requestData);

        $this->setErrorMsg('请求成功');
        return $this->outputFormat($return);
    }
}
