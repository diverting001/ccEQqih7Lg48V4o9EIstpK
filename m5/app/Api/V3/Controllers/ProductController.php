<?php
/**
 * Created by PhpStorm.
 * User: chuanbin
 * Date: 2018/4/2
 * Time: 14:35
 */

namespace App\Api\V3\Controllers;


use App\Api\Common\Controllers\BaseController;
use App\Api\Logic\Product as ProductLogic;
use Illuminate\Http\Request;

class ProductController extends BaseController
{

    /*
     * @todo 查询货品列表
     */
    public function GetList(Request $request)
    {
        $pars = $this->getContentArray($request);
        if (empty($pars)) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat(null, 404);
        }

        $products_logic = new ProductLogic();
        $return_data = $products_logic->GetList($pars);
        return $this->outputFormat($return_data);
    }

    /*
     * @todo 货品详情
     */
    public function Get(Request $request)
    {
        $pars = $this->getContentArray($request);
        if (empty($pars)) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat(null, 404);
        }

        $products_logic = new ProductLogic();
        $return_data = $products_logic->Get($pars);
        return $this->outputFormat($return_data);
    }

    /*
     * @todo 货品详情
     */
    public function GetLatestCostPrice(Request $request)
    {
        $pars = $this->getContentArray($request);
        if (empty($pars)) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat(null, 404);
        }

        $products_logic = new ProductLogic();
        $return_data = $products_logic->GetLatestCostPrice($pars);
        return $this->outputFormat($return_data);
    }

}
