<?php
/**
 * Created by PhpStorm.
 * User: chuanbin
 * Date: 2018/4/2
 * Time: 14:35
 */

namespace App\Api\V3\Controllers;

use App\Api\Common\Controllers\BaseController;
use App\Api\Logic\Shop as ShopLogic;
use Illuminate\Http\Request;

class ShopController extends BaseController
{
    /*
     * 查询店铺列表
     */
    public function GetList(Request $request)
    {
        $pars = $this->getContentArray($request);
        if (empty($pars)) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat(null, 404);
        }

        $logic = new ShopLogic();
        $return_data = $logic->GetList($pars);
        return $this->outputFormat($return_data);
    }

    /*
     * 店铺详情
     */
    public function Get(Request $request)
    {
        $pars = $this->getContentArray($request);
        if (empty($pars)) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat(null, 404);
        }

        $logic = new ShopLogic();
        $return_data = $logic->Get($pars);
        return $this->outputFormat($return_data);
    }

    /**
     * @param Request $request
     *
     * @return array
     */
    public function GetAccountList(Request $request)
    {
        $pars = $this->getContentArray($request);
        if (empty($pars)) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat(null, 404);
        }

        $logic = new ShopLogic();
        $return_data = $logic->GetAccountList($pars);
        return $this->outputFormat($return_data);
    }

    /**
     * @param Request $request
     *
     * @return array
     */
    public function GetPopShopList(Request $request)
    {
        $pars = $this->getContentArray($request);
        if (empty($pars)) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat(null, 404);
        }

        $logic = new ShopLogic();
        $return_data = $logic->GetPopShopList($pars);
        return $this->outputFormat($return_data);
    }
}
