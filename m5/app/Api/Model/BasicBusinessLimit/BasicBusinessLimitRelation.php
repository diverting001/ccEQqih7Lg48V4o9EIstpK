<?php

namespace App\Api\Model\BasicBusinessLimit;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BasicBusinessLimitRelation
{

    /**
     * @param $data
     *
     * @return false
     */
    public static function create(array $businessLimitRelationDataList)
    {
        try {
            $status = app('api_db')
                ->table('server_basic_business_limit_relation')
                ->insert($businessLimitRelationDataList);
        } catch (\Exception $e) {
            $status = false;
        }

        return $status;
    }

    /**
     * @param $condition
     *
     * @return false
     */
    public static function delete($condition)
    {
        if (empty($condition)) {
            return false;
        }

        if (!isset($condition['limit_id'])) {
            return false;
        }

        return app('api_db')->table('server_basic_business_limit_relation')->where('limit_id', '=', $condition['limit_id'])->delete();

    }

    /**
     * @param array          $condition
     * @param array          $option
     * @param array|string[] $columns
     *
     * @return array
     */
    public static function getList(array $condition, array $option = [], array $columns = ['*'])
    {
        if (empty($condition) && empty($option)) {
            return [];
        }

        $model = app('api_db')->table('server_basic_business_limit_relation');

        if (isset($condition['id'])) {
            if (is_array($condition['id'])) {
                $model->whereIn('id', $condition['id']);
            } else {
                $model->where('id', '=', $condition['id']);
            }
        }

        if (isset($condition['limit_id'])) {
            if (is_array($condition['limit_id'])) {
                $model->whereIn('limit_id', $condition['limit_id']);
            } else {
                $model->where('limit_id', '=', $condition['limit_id']);
            }
        }


        $res = [];
        if ($condition['name']) {
            $model->where('name', 'like', '%' . $condition['name'] . '%');
        }

        if (isset($option['offset'])) {
            $ret = $model->limit($option['limit'])
                         ->offset($option['offset'])
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
        $model = app('api_db')->table('server_basic_business_limit_relation');

        if (isset($condition['id'])) {
            if (is_array($condition['id'])) {
                $model->whereIn('id', $condition['id']);
            } else {
                $model->where('id', '=', $condition['id']);
            }
        }

        if (isset($condition['limit_id'])) {
            if (is_array($condition['limit_id'])) {
                $model->whereIn('limit_id', $condition['limit_id']);
            } else {
                $model->where('limit_id', '=', $condition['limit_id']);
            }
        }

        return $model->count();
    }
}
