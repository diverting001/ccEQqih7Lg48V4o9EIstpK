<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-01-16
 * Time: 18:11
 */

namespace App\Api\Model\PointScene;

class Rule
{
    public static function QueryCount($whereArr)
    {
        $ruleDb = app('api_db')->table('server_new_point_rule');
        if ($whereArr['name']) {
            $ruleDb = $ruleDb->where('name', 'like', '%' . $whereArr['name'] . '%');
        }
        if ($whereArr['rule_ids']) {
            $ruleDb = $ruleDb->whereIn('rule_id', $whereArr['rule_ids']);
        }
        if ($whereArr['disabled']) {
            $ruleDb = $ruleDb->where('disabled', $whereArr['disabled']);
        }
        if (!empty($whereArr['keyword'])) {
            $ruleDb = $ruleDb->where(function ($query) use ($whereArr) {
                $query->where('name', 'like', '%' . $whereArr['keyword'] . '%');
                if (is_numeric($whereArr['keyword'])) {
                    $query->orWhere(function ($query) use ($whereArr) {
                        $query->whereIn('rule_id', [$whereArr['keyword']]);
                    });
                }
            });
        }
        $count = $ruleDb->count();
        return $count ? $count : 0;
    }

    public static function QueryList($whereArr, $page = 1, $pageSize = 10)
    {
        $ruleDb = app('api_db')->table('server_new_point_rule');
        if ($whereArr['name']) {
            $ruleDb = $ruleDb->where('name', 'like', '%' . $whereArr['name'] . '%');
        }
        if ($whereArr['rule_ids']) {
            $ruleDb = $ruleDb->whereIn('rule_id', $whereArr['rule_ids']);
        }
        if ($whereArr['updated_at']) {
            $ruleDb = $ruleDb->where('updated_at', '>', $whereArr['updated_at']);
        }
        if ($whereArr['disabled']) {
            $ruleDb = $ruleDb->where('disabled', $whereArr['disabled']);
        }
        if (!empty($whereArr['keyword'])) {
            $ruleDb = $ruleDb->where(function ($query) use ($whereArr) {
                $query->where('name', 'like', '%' . $whereArr['keyword'] . '%');
                if (is_numeric($whereArr['keyword'])) {
                    $query->orWhere(function ($query) use ($whereArr) {
                        $query->whereIn('rule_id', [$whereArr['keyword']]);
                    });
                }
            });
        }
        $ruleDb = $ruleDb->orderBy('rule_id', 'desc');
        $list   = $ruleDb->forPage($page, $pageSize)->get();
        $sql = "select rule_id,act,filter_key,filter_val from server_new_point_rule_info where rule_id in(" . implode(',', array_column($list->toArray(), 'rule_id')) . ") group by rule_id";
        $rule_info_list = app('api_db')->select($sql);
        $rule_info_mapping = array();
        foreach ($rule_info_list as $rule_info_info) {
            $rule_info_mapping[$rule_info_info->rule_id]['act'] = $rule_info_info->act;
            $rule_info_mapping[$rule_info_info->rule_id]['filter_key'] = $rule_info_info->filter_key;
            $rule_info_mapping[$rule_info_info->rule_id]['filter_val'] = $rule_info_info->filter_val;
        }
        foreach ($list as &$item) {
            $item->act = $rule_info_mapping[$item->rule_id]['act'];
            $item->filter_key = $rule_info_mapping[$item->rule_id]['filter_key'];
            $item->filter_val = $rule_info_mapping[$item->rule_id]['filter_val'];
        }

        return $list;
    }

    /**
     * 创建积分使用规则
     */
    public static function Create($ruleInfo)
    {
        $ruleInfo = array(
            'name'       => $ruleInfo['name'],
            'desc'       => $ruleInfo['desc'],
            'disabled'   => 0,
            'op_id'      => $ruleInfo['op_id'],
            'op_name'    => $ruleInfo['op_name'],
            'created_at' => time(),
            'updated_at' => time()
        );
        try {
            $lastId = app('api_db')->table('server_new_point_rule')->insertGetId($ruleInfo);
        } catch (\Exception $e) {
            $lastId = false;
        }
        return $lastId;
    }

    /**
     * 修改规则
     */
    public static function Update($ruleId, $ruleInfoData)
    {
        try {
            $ruleInfoData['updated_at'] = time();
            $status                     = app('api_db')->table('server_new_point_rule')->where(array('rule_id' => $ruleId))->update($ruleInfoData);
        } catch (\Exception $e) {
            $status = false;
        }
        return $status;
    }

    /**
     * 查询积分规则
     */
    public static function Find($ruleId)
    {
        $where = array(
            'rule_id' => $ruleId
        );
        return app('api_db')->table('server_new_point_rule')->where($where)->first();
    }

}
