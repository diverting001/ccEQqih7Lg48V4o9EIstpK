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

        $logic       = new ShopLogic();
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

    /**
     * @param Request $request
     *
     * @return array
     */
    public function SetExt(Request $request)
    {
        $pars = $this->getContentArray($request);
        if (empty($pars)) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat(null, 404);
        }

        $logic = new ShopLogic();
        $return_data = $logic->SetExt($pars);
        return $this->outputFormat(['status' => (bool)$return_data]);
    }

    /**
     * 设置pop店铺配置
     * @param Request $request
     *
     * @return array
     */
    public function SetPopShopExt(Request $request)
    {
        $params = $this->getContentArray($request);
        if (empty($params)) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat(null, 404);
        }

        $logic = new ShopLogic();
        $return_data = $logic->setPopShopExt($params);
        return $this->outputFormat(['status' => (bool)$return_data]);
    }

    /**
     * 获取pop店铺供应商列表
     * @param Request $request
     *
     * @return array
     */
    public function GetSupplierShopList(Request $request)
    {
        $params = $this->getContentArray($request);

        if (empty($params)) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat(null, 404);
        }

        $logic = new ShopLogic();
        $return_data = $logic->getSupplierShopList($params);
        return $this->outputFormat($return_data);
    }

    /**
     * 设置pop店铺信息
     * @param Request $request
     *
     * @return array
     */
    public function SetPopShopInfo(Request $request){
        $params = $this->getContentArray($request);
        if (empty($params['pop_shop_id']) && empty($params['data'])) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat(null, 404);
        }

        $logic = new ShopLogic();
        $return_data = $logic->setPopShopInfo($params);
        return $this->outputFormat(['status' => (bool)$return_data]);
    }
}
