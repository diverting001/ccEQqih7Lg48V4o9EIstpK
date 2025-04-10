<?php

namespace App\Api\V1\Controllers;

use App\Api\Common\Controllers\BaseController;
use App\Api\V1\Service\Delivery\Delivery as DeliveryLogic;
use Illuminate\Http\Request;

class DeliveryController extends BaseController
{
    /**
     * 增加店铺运费规则
     * @param Request $request
     * @return array
     */
    public function create(Request $request)
    {
        $param = $this->getContentArray($request);
        $_logic = new DeliveryLogic();
        $result = $_logic->createDelivery($param);
        $this->setErrorMsg($result['error_msg']);
        return $this->outputFormat($result['data'], $result['error_code']);
    }

    /**
     * 删除店铺运费规则
     * @param Request $request
     * @return array
     */
    public function del(Request $request)
    {
        $param = $this->getContentArray($request);
        $id = $param['id'];
        $_logic = new DeliveryLogic();
        $result = $_logic->deleteDeliveryById($id);
        $this->setErrorMsg($result['error_msg']);
        return $this->outputFormat($result['data'], $result['error_code']);
    }

    /**
     * 修改店铺运费规则
     * @param Request $request
     * @return array
     */
    public function save(Request $request)
    {
        $param = $this->getContentArray($request);
        $id = $param['id'];
        unset($param['id']);
        $_logic = new DeliveryLogic();
        $result = $_logic->saveDeliveryById($id, $param);
        $this->setErrorMsg($result['error_msg']);
        return $this->outputFormat($result['data'], $result['error_code']);
    }

    /**
     * 获取规则信息
     * @param Request $request
     * @return array
     */
    public function info(Request $request)
    {
        $param = $this->getContentArray($request);
        $id = $param['id'];
        $_logic = new DeliveryLogic();
        $result = $_logic->getDeliveryById($id);
        $this->setErrorMsg($result['error_msg']);
        return $this->outputFormat($result['data'], $result['error_code']);
    }

    /**
     * 获取规则信息
     * @param Request $request
     * @return array
     */
    public function lists(Request $request)
    {
        $param = $this->getContentArray($request);
        $shop_id = $param['shop_id'];
        $_logic = new DeliveryLogic();
        $result = $_logic->getDeliveryListByShopId($shop_id);
        $this->setErrorMsg($result['error_msg']);
        return $this->outputFormat($result['data'], $result['error_code']);
    }

    /**
     * 运费计算
     * @param Request $request
     * @return array
     */
    public function freight(Request $request)
    {
        $param = $this->getContentArray($request);
        $_logic = new DeliveryLogic();
        $result = $_logic->getDeliveryFreight($param);
        $this->setErrorMsg($result['error_msg']);
        return $this->outputFormat($result['data'], $result['error_code']);
    }

    /**
     * 运费说明
     * @author liuming
     */
    public function freightDetail(Request $request)
    {
        $param = $this->getContentArray($request);
        $_logic = new DeliveryLogic();
        $result = $_logic->getFreightDetail($param);
        $this->setErrorMsg($result['error_msg']);
        return $this->outputFormat($result['data'], $result['error_code']);
    }
}
