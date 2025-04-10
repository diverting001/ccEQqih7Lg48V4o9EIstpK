<?php

namespace App\Api\Model\BasicBusinessLimit;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class BasicBusinessLimit
{

    /**
     * @param $data
     *
     * @return int|false
     */
    public static function create($data)
    {
        $basicBusinessLimitData = [
            'name'        => $data['name'],
            'scope'       => $data['scope'],
            'type'        => $data['type'],
            'limit'       => $data['limit'],
            'tips'        => $data['tips'],
            'op_id'       => $data['op_id'],
            'create_time' => time(),
            'update_time' => time(),
        ];

        try {
            $lastId = app('api_db')
                ->table('server_basic_business_limit')
                ->insertGetId($basicBusinessLimitData);
        } catch (\Exception $e) {
            $lastId = false;
        }

        return $lastId;
    }

    /**
     * @param $id
     * @param $basicBusinessLimitData
     *
     * @return int|false
     */
    public static function update($id, $basicBusinessLimitData)
    {
        if (!$id) {
            return false;
        }

        return app('api_db')
            ->table('server_basic_business_limit')
            ->where(['id' => $id])
            ->update($basicBusinessLimitData);
    }

    /**
     * @param $id
     * @param $state
     *
     * @return false
     */
    public static function updateState($id, $state)
    {
        if (!$id) {
            return false;
        }
        if (!in_array($state, [0, 1])) {
            return false;
        }

        return app('api_db')
            ->table('server_basic_business_limit')
            ->where(['id' => $id])
            ->update(['state' => $state, 'create_time' => time()]);
    }

    /**
     * @param array          $condition
     * @param array          $option
     * @param array|string[] $columns
     */
    public static function getList(array $condition, array $option = [], array $columns = ['*'])
    {
        if (empty($condition) && empty($option)) {
            return [];
        }

        $model = app('api_db')->table('server_basic_business_limit');
        if (isset($condition['id'])) {
            if (is_array($condition['id'])) {
                $model->whereIn('id', $condition['id']);
            } else {
                $model->where('id', '=', $condition['id']);
            }
        }

        if (isset($condition['name'])) {
            $model->where('name', 'like', '%' . $condition['name'] . '%');
        }

        $res = [];
        if (isset($option['offset'])) {
            $ret = $model->offset($option['offset'])
                         ->limit($option['limit'])
                         ->select($columns)
                         ->orderBy('id', 'desc')
                         ->get();
        } else {
            $ret = $model->orderBy('id', 'desc')
                         ->get();
        }

        if ($res) {
            $ret->map(function ($item) {
                return (array)$item;
            });
            $ret = $res->toArray();
        }

        return $ret;
    }


    /**
     * @param array $condition
     *
     * @return mixed
     */
    public static function getCount(array $condition)
    {
        $model = app('api_db')->table('server_basic_business_limit');
        if (isset($condition['id'])) {
            if (is_array($condition['id'])) {
                $model->whereIn('id', $condition['id']);
            } else {
                $model->where('id', '=', $condition['id']);
            }
        }

        if ($condition['name']) {
            $model->where('name', 'like', '%' . $condition['name'] . '%');
        }

        return $model->count();
    }

    /**
     * @param $id
     *
     * @return mixed
     */
    public static function getInfo($id)
    {
        if (!$id) {
            return [];
        }

        return app('api_db')
            ->table('server_basic_business_limit')
            ->where('id', $id)
            ->get()
            ->toArray();
    }

    /**
     * @param array          $condition
     * @param array|string[] $columns
     *
     * @return array
     */
    public static function getBasicBusinessLimitList(array $condition) :array
    {
        if (empty($condition) || !isset($condition['related_id'])) {
            return [];
        }

        if (!is_array($condition['related_id'])) {

            $condition['related_id'] = array($condition['related_id']);
        }

        // 获取全部店铺的基础风控
        $scopeBasicBusinessLimitList = app('api_db')->table('server_basic_business_limit AS l')->select("l.*")
            ->where("l.scope", 0)
            ->where("l.state", 1)
            ->get();


        $scopeBasicBusinessLimitList = $scopeBasicBusinessLimitList->map(function ($item) {
            return (array)$item;
        });

        // 获取按店铺配置基础风控
        $shopBasicBusinessLimitList = app('api_db')->table('server_basic_business_limit')->leftJoin('server_basic_business_limit_relation', 'server_basic_business_limit.id', '=', 'server_basic_business_limit_relation.limit_id')->select(['server_basic_business_limit.*','related_id'])
              ->whereIn("related_id", $condition['related_id'])
              ->where("scope", 1)
              ->where("state", 1)
              ->where("limit_type", 1)
              ->get();

        $shopBasicBusinessLimitList = $shopBasicBusinessLimitList->map(function ($item) {
            return (array)$item;
        });


        $basicBusinessLimitList  = [];
        foreach ($condition['related_id'] as $related_id) {
            foreach ($scopeBasicBusinessLimitList as $scopeBasicBusinessLimit) {
                $scopeBasicBusinessLimit['related_id'] = $related_id;
                if ($scopeBasicBusinessLimit['type'] == 1) {
                    $basicBusinessLimitList[$related_id]['num_limit'][] = $scopeBasicBusinessLimit;
                } else if ($scopeBasicBusinessLimit['type'] == 2) {
                    $basicBusinessLimitList[$related_id]['amount_limit'][] = $scopeBasicBusinessLimit;
                }
            }
        }


        foreach ($shopBasicBusinessLimitList as $shopBasicBusinessLimit) {
            if ($shopBasicBusinessLimit['type'] == 1) {
                $basicBusinessLimitList[$shopBasicBusinessLimit['related_id']]['num_limit'][] = $shopBasicBusinessLimit;
            } else if ($shopBasicBusinessLimit['type'] == 2) {
                $basicBusinessLimitList[$shopBasicBusinessLimit['related_id']]['amount_limit'][] = $shopBasicBusinessLimit;
            }
        }

        return $basicBusinessLimitList;
    }

}
