<?php

namespace App\Api\V2\Controllers\Promotion;

use App\Api\Common\Controllers\BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Api\V2\Service\Promotion\PromotionWhiteListService;


class WhiteListController extends BaseController
{

    public function Create(Request $request) :array
    {
        $data      = $this->getContentArray($request);
        $validator = Validator::make($data, [
            'rule_id'                 => 'required|integer|gt:0',
            'white_list'              => "array",
            'white_list.*.company_id' => 'integer|gt:0',
            'white_list.*.member_id'  => 'integer|gt:0',
        ]);

        if ($validator->fails()) {
            $this->setErrorMsg($validator->errors()
                                         ->getMessages());

            return $this->outputFormat([], 400);
        }

        $ruleId              = $data['rule_id'];
        $whiteMemberDataList = $data['white_list'];

        $promotionWhiteListService = new PromotionWhiteListService();
        $res = $promotionWhiteListService->createPromotionWhiteList($ruleId, $whiteMemberDataList);
        if ($res['status']) {
            return $this->outputFormat($res['data'], 0);
        } else {
            return $this->outputFormat([], 400);
        }
    }

    public function Delete(Request $request) :array
    {
        $data      = $this->getContentArray($request);
        $validator = Validator::make($data, [
            'rule_id'       => 'required|integer|gt:0',
            'white_id'      => "required|array",
            'white_id.*'    => 'required|integer|gt:0',
        ]);

        if ($validator->fails()) {
            $this->setErrorMsg($validator->errors()
                                         ->getMessages());

            return $this->outputFormat([], 400);
        }

        $ruleId      = $data['rule_id'];
        $whiteIdList = $data['white_id'];

        $promotionWhiteListService = new PromotionWhiteListService();
        $res  = $promotionWhiteListService->deletePromotionWhiteList($ruleId, $whiteIdList);
        if ($res['status']) {
            return $this->outputFormat($res['data'], 0);
        } else {
            return $this->outputFormat([], 400);
        }
    }


    public function GetList(Request $request) :array
    {
        $data      = $this->getContentArray($request);
        $validator = Validator::make($data, [
            'filter'         => 'present|array',
            'filter.rule_id' => 'required|integer|gt:0',
        ]);

        if ($validator->fails()) {
            $this->setErrorMsg($validator->errors()
                                         ->getMessages());

            return $this->outputFormat([], 400);
        }

        $promotionWhiteListService = new PromotionWhiteListService();
        $res                       = $promotionWhiteListService->getList($data);
        if ($res['status']) {
            return $this->outputFormat($res['data'], 0);
        } else {
            return $this->outputFormat([], 400);
        }
    }

}
