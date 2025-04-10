<?php

namespace App\Api\V1\Controllers;


use App\Api\Common\Controllers\BaseController;
use App\Api\Logic\ExceptionMsg\ExceptionMsg;
use App\Api\Logic\ExceptionMsg\ExceptionUser;
use Illuminate\Http\Request;

class ExceptionMsgController extends BaseController
{
    public function CreateMemberException(Request $request)
    {
        $requestData = $this->getContentArray($request);
        if (
            !$requestData['member_id']
            || !$requestData['company_id']
            || !$requestData['trigger_time']
            || !$requestData['error_msg']
            || !$requestData['suggest_msg']
            || !$requestData['business_type']
            || !$requestData['business_scene']
            || !$requestData['business_no']
        ) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }

        $logic = new ExceptionMsg();

        $res = $logic->addMemberException($requestData);

        if (!$res) {
            $this->setErrorMsg('添加失败');
            return $this->outputFormat(array(), 401);
        }
        return $this->outputFormat($res, 0);
    }

    public function GetMemberExceptionList(Request $request)
    {
        $whereData = $this->getContentArray($request);

        $keysInterest = ['id', 'member_id', 'company_id', 'trigger_time', 'trigger_system', 'business_no', 'business_type', 'business_scene', 'business_scene_detail', 'extend_info', 'batch', 'source'];
        $where = array_filter(array_intersect_key($whereData['filter'] ?: array(), array_flip($keysInterest)));

        $page = $whereData['page'] < 1 ? 1 : intval($whereData['page']);
        $pageSize = empty($whereData['page_size']) ? 20 : intval($whereData['page_size']);

        $search = [
            'filter' => $where,
            'page_index' => $page,
            'page_size' => $pageSize,
        ];

        $logic = new ExceptionMsg();
        $list = $logic->getMemberExceptionList($search);
        $total = $logic->getMemberExceptionTotal($search);

        return $this->outputFormat(['list' => $list, 'total' => $total, 'page' => $page, 'page_size' => $pageSize]);
    }

}
