<?php

namespace App\Api\V1\Controllers;

use App\Api\Common\Controllers\BaseController;
use App\Api\V1\Service\DeliveryToB\Delivery as DeliveryLogic;
use Illuminate\Http\Request;

class DeliveryToBController extends BaseController
{
    /**
     * @todo   运费计算
     */
    public function freight(Request $request)
    {
        $param = $this->getContentArray($request);
        $_logic = new DeliveryLogic();
        $result = $_logic->getDeliveryFreight($param);
        $this->setErrorMsg($result['error_msg']);
        return $this->outputFormat($result['data'], $result['error_code']);
    }
}
