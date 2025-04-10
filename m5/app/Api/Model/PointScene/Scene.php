<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-01-16
 * Time: 18:11
 */

namespace App\Api\Model\PointScene;


class Scene
{
    public static function QueryCount($whereArr)
    {
        $sceneDb = app('api_db')->table('server_new_point_scene as s');
        if ($whereArr['name']) {
            $sceneDb = $sceneDb->where('name', 'like', '%' . $whereArr['name'] . '%');
        }
        if ($whereArr['id']) {
            $sceneDb = $sceneDb->where('s.scene_id', $whereArr['id']);
        }
        if ($whereArr['disabled']) {
            $sceneDb = $sceneDb->where('disabled', $whereArr['disabled']);
        }
        if ($whereArr['company_id']) {
            $sceneDb->leftJoin('server_new_point_company_scene_rel as sc', 's.scene_id', '=', 'sc.scene_id')
                ->whereIn('company_id', $whereArr['company_id']);
        }
        $count = $sceneDb->count();
        return $count ? $count : 0;
    }

    public static function QueryList($whereArr, $page = 1, $pageSize = 10)
    {
        $sceneDb = app('api_db')->table('server_new_point_scene as s')
            ->select(
                's.scene_id',
                's.name',
                's.desc',
                's.disabled',
                's.created_at',
                's.updated_at'
            );
        if ($whereArr['name']) {
            $sceneDb = $sceneDb->where('name', 'like', '%' . $whereArr['name'] . '%');
        }
        if ($whereArr['id']) {
            $sceneDb = $sceneDb->where('s.scene_id', $whereArr['id']);
        }
        if (isset($whereArr['disabled'])) {
            $sceneDb = $sceneDb->where('s.disabled', $whereArr['disabled']);
        }
        if ($whereArr['company_id']) {
            $sceneDb = $sceneDb->leftJoin('server_new_point_company_scene_rel as sc', 's.scene_id', '=', 'sc.scene_id')
                ->whereIn('sc.company_id', $whereArr['company_id']);
        }
        $sceneDb = $sceneDb->orderBy('s.scene_id', 'desc');

        $list = $sceneDb->forPage($page, $pageSize)->get();

        if ($list->count() > 0) {
            $sceneIds = [];
            foreach ($list as $item) {
                $sceneIds[] = $item->scene_id;
            }
            $relList = app('api_db')->table('server_new_point_scene_rule_rel')->whereIn('scene_id', $sceneIds)->get();
            if ($relList->count() > 0) {
                $relArr = [];
                foreach ($relList as $rel) {
                    $relArr[$rel->scene_id][] = $rel->rule_bn;
                }
                foreach ($list as $key => $scene) {
                    if (isset($relArr[$scene->scene_id]) && $relArr[$scene->scene_id]) {
                        $list[$key]->rule_bns = $relArr[$scene->scene_id];
                    } else {
                        $list[$key]->rule_bns = [];
                    }
                }
            } else {
                foreach ($list as $key => $scene) {
                    $list[$key]->rule_bns = [];
                }
            }
        }
        return $list;
    }

    /**
     * 创建积分场景
     */
    public static function Create($sceneData)
    {
        $sceneData = array(
            'name'       => $sceneData['name'],
            'desc'       => $sceneData['desc'],
            'disabled'   => 0,
            'op_id'      => $sceneData['op_id'],
            'op_name'    => $sceneData['op_name'],
            'created_at' => time(),
            'updated_at' => time(),
            'extend_data' => $sceneData['extend_data']
        );

        try {
            $lastId = app('api_db')->table('server_new_point_scene')->insertGetId($sceneData);
        } catch (\Exception $e) {
            $lastId = false;
        }

        return $lastId;
    }

    /**
     * 查询场景
     */
    public static function Find($sceneId)
    {
        $where = array(
            'scene_id' => $sceneId
        );

        return app('api_db')->table('server_new_point_scene')->where($where)->first();
    }

    public static function QueryBySceneIds($sceneIds)
    {
        $where = array(
            'scene.disabled' => 0
        );

        $sceneList = app('api_db')
            ->table('server_new_point_scene as scene')
            ->select('scene.scene_id', 'scene.name as scene_name', 'scene.desc as scene_desc','scene.extend_data')
            ->where($where)
            ->whereIn('scene.scene_id', $sceneIds)
            ->get();

        if ($sceneList->count() > 0) {
            $relList = app('api_db')->table('server_new_point_scene_rule_rel')->whereIn('scene_id', $sceneIds)->get();
            if ($relList->count() > 0) {
                $relArr = [];
                foreach ($relList as $rel) {
                    $relArr[$rel->scene_id][] = $rel->rule_bn;
                }
                foreach ($sceneList as $key => $scene) {
                    if (isset($relArr[$scene->scene_id]) && $relArr[$scene->scene_id]) {
                        $sceneList[$key]->rule_bns = $relArr[$scene->scene_id];
                    } else {
                        $sceneList[$key]->rule_bns = [];
                    }
                }
            } else {
                foreach ($sceneList as $key => $scene) {
                    $sceneList[$key]->rule_bns = [];
                }
            }
        }
        return $sceneList;
    }

    public static function Update($scene_id, $set)
    {
        try {
            $set['updated_at'] = time();

            $status = app('api_db')->table('server_new_point_scene')->where(array('scene_id' => $scene_id))->update($set);
        } catch (\Exception $e) {
            $status = false;
        }
        return $status;
    }

}
