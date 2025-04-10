<?php
/**
 * Created by PhpStorm.
 * User: chuanbin
 * Date: 2018/6/22
 * Time: 11:10
 */

namespace App\Api\V2\Controllers;

use App\Api\Common\Controllers\BaseController;
use App\Api\Model\Voucher\FreeShippingCouponModel;
use Illuminate\Http\Request;

class FreeShippingController extends BaseController
{
    public function queryCouponWithRule(Request $request)
    {
        $params = $this->getContentArray($request);
        $obj = new FreeShippingCouponModel();
        $rzt = $obj->queryCouponListWithRule($params);
        $this->setErrorMsg('请求成功');
        return $this->outputFormat($rzt);
    }

}
