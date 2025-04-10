<?php

namespace App\Api\V1\Controllers;

use App\Api\Common\Controllers\BaseController;
use App\Api\Model\ProjectMall\ProjectMall;
use Illuminate\Http\Request;

class ProjectMallController extends BaseController
{

    // 绑定业务码和商城
    public function Save(Request $request)
    {
        $content_data = $this->getContentArray($request);
        $project_code = !isset($content_data['project_code']) ? '' : trim($content_data['project_code']);
        $mall_id = !isset($content_data['mall_id']) ? '' : trim($content_data['mall_id']);

        if (empty($project_code) || empty($mall_id)) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat($content_data, 400);
        }
        $project_mall_model = new ProjectMall();
        $project_mall_info = $project_mall_model->findByProject($project_code);
        $data = [
            'project_code' => $project_code,
            'mall_id' => $mall_id,
        ];
        if (!empty($project_mall_info)) {
            $res = $project_mall_model->update($project_mall_info['id'], $data);
        } else {
            $res = $project_mall_model->create($data);
        }
        if ($res === false) {
            $this->setErrorMsg('提交失败');
            return $this->outputFormat($data, 10004);
        }
        $this->setErrorMsg('success');
        return $this->outputFormat($data);
    }

    // 绑定业务码和商城
    public function Get(Request $request)
    {
        $content_data = $this->getContentArray($request);
        $project_code = !isset($content_data['project_code']) ? '' : trim($content_data['project_code']);

        if (empty($project_code)) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat($content_data, 400);
        }
        $project_mall_model = new ProjectMall();
        $project_mall_info = $project_mall_model->findByProject($project_code);
        $this->setErrorMsg('success');
        $data['info'] = $project_mall_info;
        return $this->outputFormat($data);
    }
}
