<?php


namespace App\Api\V4\Controllers\Express;


use App\Api\Common\Controllers\BaseController;
use Illuminate\Http\Request;
use App\Api\V4\Service\Express\Pickup;
use \Illuminate\Support\Facades\Validator;

class PickupController extends BaseController
{
    /**
     * @var Pickup
     */
    private $_pickupService;

    /**
     * CompanyController constructor.
     */
    public function __construct()
    {
        $this->_pickupService = new Pickup();
    }

    /**
     * 获取上门取件详情
     * @param Request $request
     * @return array
     */
    public function GetPickupOrderInfo(Request $request)
    {
        $params = $this->getContentArray($request);

        $validator = Validator::make((array)$params, [
            'pickup_order_no' => 'sometimes|string',
            'apply_no' => 'required_without:pickup_order_no|string'
        ]);

        if ($validator->fails()) {
            $this->setErrorMsg($validator->errors());
            return $this->outputFormat(null, 401);
        }

        $result = $this->_pickupService->getPickupOrderInfo($params['pickup_order_no'], $params['apply_no']);

        $this->setErrorMsg('请求成功');
        return $this->outputFormat($result['data']);
    }

    // --------------------------------------------------------------------

    /**
     * 保存上门取件信息
     *
     * @return array
     */
    public function SavePickupOrder(Request $request)
    {
        // 验证请求参数
        $params = $this->getContentArray($request);

        $validator = Validator::make((array)$params, [
            'express_channel' => 'sometimes|string',
            'business_no' => 'required|string',
            'business_source' => 'required|string',
            'apply_no' => 'required|string',
            'sender_content' => 'required|array',
            'sender_content.name' => 'required|string',
            'sender_content.mobile' => 'required|string',
            'sender_content.address' => 'required|string',
            'receiver_content' => 'required|array',
            'receiver_content.name' => 'required|string',
            'receiver_content.mobile' => 'required|string',
            'receiver_content.address' => 'required|string',
            'cargoes' => 'required|array',
            'cargoes.*.name' => 'required|string',
            'cargoes.*.number' => 'required|integer',
            'cargoes.*.weight' => 'sometimes|numeric',
            'cargoes.*.volume' => 'sometimes|numeric',
        ]);

        if ($validator->fails()) {
            $this->setErrorMsg($validator->errors());
            return $this->outputFormat(null, 401);
        }

        //上门取件下单
        $result = $this->_pickupService->savePickup($params);

        if (!$result['status']) {
            $this->setErrorMsg($result['msg']);
            return $this->outputFormat(null, 400);
        }

        $this->setErrorMsg('上门取件下单成功');
        return $this->outputFormat($result['data']);
    }

    /**
     * 取消上门取件
     *
     * @return array
     */
    public function CancelPickupOrder(Request $request)
    {
        $params = $this->getContentArray($request);

        $validator = Validator::make((array)$params, [
            'pickup_order_no' => 'sometimes|string',
            'apply_no' => 'required_without:pickup_order_no|string'
        ]);

        if ($validator->fails()) {
            $this->setErrorMsg($validator->errors());
            return $this->outputFormat(null, 401);
        }

        $result = $this->_pickupService->cancelPickupOrder($params['pickup_order_no'], $params['apply_no']);
        if (!$result['status']) {
            $this->setErrorMsg($result['msg']);
            return $this->outputFormat(null, 400);
        }
        $this->setErrorMsg('请求成功');
        return $this->outputFormat($result['data']);
    }

}
