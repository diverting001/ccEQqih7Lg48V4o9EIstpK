<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-01-16
 * Time: 18:11
 */

namespace App\Api\Model\PointScene;


class SceneCompanyRel
{
    /**
     * 创建公司场景关联
     */
    public static function Create($relData)
    {
        $relData = array(
            'company_id' => $relData['company_id'],
            'scene_id'   => $relData['scene_id'],
            'account'    => '',
            'created_at' => time(),
            'updated_at' => time()
        );
        try {
            $relId = app('api_db')->table('server_new_point_company_scene_rel')->insertGetId($relData);
        } catch (\Exception $e) {
            $relId = false;
        }
        return $relId;
    }

    /**
     * 公司场景关联绑定底层积分账户
     */
    public static function BindAccount($relId, $account)
    {
        $where    = array(
            'id'      => $relId,
            'account' => ''
        );
        $saveData = array(
            "account" => strval($account)
        );
        return app('api_db')->table('server_new_point_company_scene_rel')->where($where)->update($saveData);
    }

    /**
     * 公司单个场景下账户
     */
    public static function FindByCompanyAndScene($companyId, $sceneId)
    {
        $where = array(
            'company_id' => $companyId,
            'scene_id'   => $sceneId
        );
        return app('api_db')->table('server_new_point_company_scene_rel')->where($where)->first();
    }

    /**
     * 查询公司多个场景账户
     */
    public static function FindByCompanyAndSceneList($companyId, $sceneIds)
    {
        $where = array(
            'company_id' => $companyId,
            'disabled'   => 0
        );
        return app('api_db')->table('server_new_point_company_scene_rel')
            ->select("id", "company_id", 'scene_id', "account")
            ->where($where)
            ->whereIn("scene_id", $sceneIds)
            ->get();
    }

    /**
     * 获取公司关联的所有场景
     */
    public static function FindByCompany($companyId)
    {
        $where = array(
            'rel.company_id' => $companyId,
            'rel.disabled'   => 0
        );
        return app('api_db')
            ->table('server_new_point_company_scene_rel as rel')
            ->leftJoin('server_new_point_scene as scene', 'rel.scene_id', '=', 'scene.scene_id')
            ->select("rel.company_id", 'rel.scene_id', "rel.account", "scene.name as scene_name")
            ->where($where)
            ->get();

    }

    public static function FindByAccount($account)
    {
        $account = strval($account);
        return app('api_db')
            ->table('server_new_point_company_scene_rel as rel')
            ->select("rel.id", "rel.company_id", 'rel.scene_id', "rel.account")
            ->where('rel.account', $account)
            ->first();
    }

    public static function QueryByAccount($accounts)
    {
        foreach ($accounts as &$account) {
            $account = strval($account);
        }
        $list       = app('api_db')
            ->table('server_new_point_company_scene_rel as rel')
            ->select("rel.id", "rel.company_id", 'rel.scene_id', "rel.account")
            ->whereIn('rel.account', $accounts)
            ->get();
        $returnData = array();
        foreach ($list as $accountInfo) {
            $returnData[$accountInfo->account] = $accountInfo;
        }
        return $returnData;
    }

    /**
     * 公司单个场景下账户
     */
    public static function FindByCompanyListAndSceneList($companyIds, $sceneIds)
    {
        $model = app('api_db')
            ->table('server_new_point_company_scene_rel')
            ->where('disabled', 0)
            ->whereIn('company_id', $companyIds);
        if ($sceneIds) {
            $model = $model->whereIn('scene_id', $sceneIds);
        }
        $accountList = $model->get();
        return $accountList;
    }
}
