<?php


namespace App\Api\V1\Controllers;

use App\Api\Common\Controllers\BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Api\V1\Service\BasicBusinessLimit\BasicBusinessLimit;

/**
 * 基础下单风控
 * Class BasicBusinessLimitController
 * @package App\Api\V1\Controllers
 */
class BasicBusinessLimitController extends BaseController
{

    public function Create(Request $request) :array
    {
        $data = $this->getContentArray($request);

        $validator = Validator::make($data, [
            'name'       => 'required',
            'scope'      => 'required|integer',
            'type'       => 'required|integer|gt:0',
            'tips'       => 'required',
            'related_id' => 'present|array'
        ]);

        if ($validator->fails()) {
            $this->setErrorMsg($validator->errors()
                                         ->getMessages());

            return $this->outputFormat([], 400);
        }

        if (!in_array($data['scope'], [0, 1])) {
            $this->setErrorMsg('生效范围参数异常');

            return $this->outputFormat([], 400);
        }

        if ($data['scope'] == 1 && empty($data['related_id']) || $data['scope'] == 0 && !empty($data['related_id'])) {
            $this->setErrorMsg('关联参数异常');

            return $this->outputFormat([], 400);
        }

        $basicBusinessLimitService = new BasicBusinessLimit();
        $res                       = $basicBusinessLimitService->create($data);
        if ($res['status']) {
            return $this->outputFormat($res['data'], 0);
        } else {
            return $this->outputFormat([], 400);
        }
    }


    public function Update(Request $request) :array
    {
        $data = $this->getContentArray($request);

        $validator = Validator::make($data, [
            'id'         => 'required',
            'name'       => 'required',
            'scope'      => 'required|integer',
            'type'       => 'required|integer|gt:0',
            'tips'       => 'required',
            'related_id' => 'present|array',
        ]);

        if ($validator->fails()) {
            $this->setErrorMsg($validator->errors()
                                         ->getMessages());

            return $this->outputFormat([], 400);
        }

        if (!in_array($data['scope'], [0, 1])) {
            $this->setErrorMsg('生效范围参数异常');

            return $this->outputFormat([], 400);
        }

        if ($data['scope'] == 1 && empty($data['related_id']) || $data['scope'] == 0 && !empty($data['related_id'])) {
            $this->setErrorMsg('关联参数异常');

            return $this->outputFormat([], 400);
        }

        $basicBusinessLimitService = new BasicBusinessLimit();
        $id = $data['id'];
        $res                       = $basicBusinessLimitService->update($id, $data);
        if ($res['status']) {
            return $this->outputFormat($res['data'], 0);
        } else {
            return $this->outputFormat([], 400);
        }
    }

    public function UpdateState(Request $request) :array
    {
        $data = $this->getContentArray($request);

        $validator = Validator::make($data, [
            'id'    => 'required',
            'state' => 'required',
        ]);

        if ($validator->fails()) {
            $this->setErrorMsg($validator->errors()
                                         ->getMessages());

            return $this->outputFormat([], 400);
        }

        $basicBusinessLimitService = new BasicBusinessLimit();
        $res                       = $basicBusinessLimitService->updateState($data['id'], $data['state']);
        if ($res['status']) {
            return $this->outputFormat([], 0);
        } else {
            return $this->outputFormat([], 400);
        }
    }

    /**
     * @param Request $request
     *
     * @return array
     */
    public function GetList(Request $request) :array
    {
        $data = $this->getContentArray($request);

        $basicBusinessLimitService = new BasicBusinessLimit();
        $res                       = $basicBusinessLimitService->getList($data);
        if ($res['status']) {
            return $this->outputFormat($res['data'], 0);
        } else {
            return $this->outputFormat([], 400);
        }
    }

    /**
     * @param Request $request
     *
     * @return array
     */
    public function GetInfo(Request $request) :array
    {
        $data      = $this->getContentArray($request);
        $validator = Validator::make($data, [
            'id' => 'required',
        ]);

        if ($validator->fails()) {
            $this->setErrorMsg($validator->errors()
                                         ->getMessages());

            return $this->outputFormat([], 400);
        }

        $basicBusinessLimitService = new BasicBusinessLimit();
        $res                       = $basicBusinessLimitService->getInfo($data['id']);
        if ($res['status']) {
            return $this->outputFormat($res['data'], 0);
        } else {
            return $this->outputFormat([], 400);
        }
    }

    public function Validate(Request $request) :array
    {
        $data = $this->getContentArray($request);

        $validator = Validator::make($data, [
            'order_data'              => "required|array",
            'order_data.*.goods_bn'   => 'required',
            'order_data.*.product_bn' => 'required',
            'order_data.*.quantity'   => 'required|integer|gt:0',
            'order_data.*.price'      => 'required|numeric',
            'order_data.*.pop_shop_id'    => 'required|integer|gt:0',
        ]);
        if ($validator->fails()) {
            $this->setErrorMsg($validator->errors()
                                         ->getMessages());

            return $this->outputFormat([], 400);
        }

        $firstErrorTips = "";
        $basicBusinessLimitService = new BasicBusinessLimit();
        $res  = $basicBusinessLimitService->validate($data['order_data'], $firstErrorTips);

        if ($res['status']) {
            return $this->outputFormat(['data' => $res['data'], 'first_error_tips' => $firstErrorTips ], 0);
        } else {
            return $this->outputFormat(['data' => $res['data'], 'first_error_tips' => $firstErrorTips ], 404);
        }

    }


}
