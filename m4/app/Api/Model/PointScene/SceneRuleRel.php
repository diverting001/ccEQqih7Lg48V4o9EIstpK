<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-09-29
 * Time: 10:13
 */

namespace App\Api\Model\PointScene;


class SceneRuleRel
{
    /**
     * 创建积分使用规则
     */
    public static function Create($sceneRule)
    {
        try {
            $status = app('api_db')->table('server_new_point_scene_rule_rel')->insert($sceneRule);
        } catch (\Exception $e) {
            $status = false;
        }
        return $status;
    }

    /**
     * 修改规则
     */
    public static function DeleteAll($sceneId)
    {
        try {
            $status = app('api_db')->table('server_new_point_scene_rule_rel')->where('scene_id', $sceneId)->delete();
        } catch (\Exception $e) {
            $status = false;
        }
        return $status;
    }
}
