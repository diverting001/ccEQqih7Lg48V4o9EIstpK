<?php

namespace App\Api\V1\Controllers;

use App\Api\Common\Controllers\BaseController;
use App\Api\Model\OrderSplit\SplitData as SplitData;
use Illuminate\Http\Request;

class OrderSplitController extends BaseController
{
    /*
     * @todo 获取拆单结果
     */
    public function GetSplitInfo(Request $request)
    {
        $split_data = $this->getContentArray($request);
        if (empty($split_data['split_id'])) {
            $this->setErrorMsg('拆单号不存在');
            return $this->outputFormat([], 400);
        }
        $split_info = SplitData::GetInfoById($split_data['split_id']);
        if (empty($split_info)) {
            $this->setErrorMsg('拆单结果不存在');
            return $this->outputFormat([], 400);
        } else {
            if ($split_info->status != 1) {
                $this->setErrorMsg('拆单结果已失效');
                return $this->outputFormat([], 400);
            } else {
                $split_info->split_info = json_decode($split_info->split_info, true);
                $this->setErrorMsg('请求成功');
                return $this->outputFormat($split_info);
            }
        }
    }

    /*
     * @todo 保存拆单结果
     */
    public function SaveSplit(Request $request)
    {
        $split_data = $this->getContentArray($request);
        if (empty($split_data['split_info'])) {
            $this->setErrorMsg('拆单结果不能为空');
            return $this->outputFormat([], 400);
        }
        if (is_array($split_data['split_info'])) {
            $split_data['split_info'] = json_encode($split_data['split_info']);
        }
        if (!empty($split_data['order_info']) && is_array($split_data['order_info'])) {
            $split_data['order_info'] = json_encode($split_data['order_info']);
        }
        $save_data = array(
            'status' => 1,
            'order_info' => empty($split_data['order_info']) ? '' : $split_data['order_info'],
            'split_info' => empty($split_data['split_info']) ? '' : $split_data['split_info'],
            'create_time' => time()
        );
        $split_id = SplitData::Save($save_data);
        if (!$split_id) {
            $this->setErrorMsg('拆单结果保存失败');
            return $this->outputFormat([], 400);
        } else {
            $this->setErrorMsg('保存成功');
            return $this->outputFormat(['split_id' => $split_id]);
        }

    }

}
