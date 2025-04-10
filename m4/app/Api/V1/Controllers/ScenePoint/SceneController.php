<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-01-16
 * Time: 16:43
 */

namespace App\Api\V1\Controllers\ScenePoint;

use App\Api\Common\Controllers\BaseController;
use App\Api\Model\PointScene\Scene;
use App\Api\V1\Service\PointScene\Scene as SceneServer;
use Illuminate\Http\Request;

class SceneController extends BaseController
{
    public function QueryList(Request $request)
    {
        $sceneData = $this->getContentArray($request);
        $page     = isset($sceneData['page']) ? $sceneData['page'] : 1;
        $pageSize = isset($sceneData['page_size']) ? $sceneData['page_size'] : 10;

        $whereArr = array();
        if (isset($sceneData['name']) && $sceneData['name']) {
            $whereArr['name'] = $sceneData['name'];
        }
        if (isset($sceneData['id']) && $sceneData['id']) {
            $whereArr['id'] = $sceneData['id'];
        }
        if (isset($sceneData['company_id']) && $sceneData['company_id']) {
            $whereArr['company_id'] = $sceneData['company_id'];
        }

        if (isset($sceneData['disabled']) ) {
            $whereArr['disabled'] = $sceneData['disabled'];
        }

        $sceneServer = new SceneServer();
        $res         = $sceneServer->QueryList($whereArr, $page, $pageSize);
        $this->setErrorMsg($res['msg']);
        if ($res['status']) {
            return $this->outputFormat($res['data'], 0);
        } else {
            return $this->outputFormat(array(), 400);
        }
    }

    /**
     * 创建积分场景
     */
    public function Create(Request $request)
    {
        $sceneData = $this->getContentArray($request);
        if (empty($sceneData['name']) || !is_array($sceneData['rule_bns']) || count($sceneData['rule_bns']) <= 0) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }

        $sceneServer = new SceneServer();

        $res = $sceneServer->Create($sceneData);

        if ($res['status']) {
            $this->setErrorMsg('请求成功');
            return $this->outputFormat($res['data'], 0);
        } else {
            $this->setErrorMsg($res['msg']);
            return $this->outputFormat(array(), 400);
        }
    }

    /**
     * 场景关联公司
     */
    public function RelationCompany(Request $request)
    {
        $relationData = $this->getContentArray($request);
        if (empty($relationData['scene_id']) || empty($relationData['company_list'])) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }

        $sceneServer = new SceneServer();
        $res         = $sceneServer->RelationCompany($relationData);
        if ($res['status']) {
            $this->setErrorMsg('请求成功');
            return $this->outputFormat($res['data'], 0);
        } else {
            $this->setErrorMsg($res['msg']);
            return $this->outputFormat(array(), 400);
        }
    }

    public function GetCompanyRel(Request $request)
    {
        $relationData = $this->getContentArray($request);
        if (empty($relationData['company_id'])) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }

        $sceneServer = new SceneServer();
        $res         = $sceneServer->GetCompanyRel($relationData);
        if ($res['status']) {
            $this->setErrorMsg('请求成功');
            return $this->outputFormat($res['data'], 0);
        } else {
            $this->setErrorMsg($res['msg']);
            return $this->outputFormat(array(), 400);
        }
    }

    public function queryListById(Request $request)
    {
        $ids  = $this->getContentArray($request);
        $list = Scene::QueryBySceneIds(array_unique($ids));
        if ($list) {
            $this->setErrorMsg('请求成功');
            return $this->outputFormat($list, 0);
        } else {
            $this->setErrorMsg('请求失败');
            return $this->outputFormat(array(), 400);
        }
    }

    public function Update(Request $request)
    {
        $data = $this->getContentArray($request);
        if (
            empty($data['scene_id']) ||
            empty($data['name']) ||
            empty($data['desc']) ||
            !is_array($data['rule_bns']) ||
            count($data['rule_bns']) <= 0
        ) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }
        $scene_id = $data['scene_id'];

        $sceneServer = new SceneServer();
        $res = $sceneServer->Update($scene_id, $data);
        if ($res['status']) {
            $this->setErrorMsg('修改成功');
            return $this->outputFormat([], 0);
        } else {
            $this->setErrorMsg('修改失败');
            return $this->outputFormat(array(), 400);
        }
    }
}
