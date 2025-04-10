<?php


namespace App\Api\Model\Promotion;


class PromotionWhiteListModel
{
    protected $_db;

    public function __construct()
    {
        $this->_db = app('api_db');
    }

    /**
     * @param $whiteMemberDataList
     *
     * @return int|false
     */
    public function create($whiteMemberDataList)
    {
        try {
            $status = app('api_db')
                ->table('server_promotion_v2_member_white_list')
                ->insert($whiteMemberDataList);
        } catch (\Exception $e) {
            $status = false;
        }

        return $status;
    }


    /**
     * @param $id
     *
     * @return bool
     */
    /**
     * @param $id
     * @param $pid
     *
     * @return bool
     */
    public function delete($id, $pid)
    {
        if (!$id || !$pid) {
            return false;
        }

        $info = app('api_db')
            ->table('server_promotion_v2_member_white_list')
            ->where('id', $id)
            ->where('pid', $pid)
            ->get();

        if (!$info) {
            return true;
        }

        return app('api_db')
            ->table('server_promotion_v2_member_white_list')
            ->where('id', '=', $id)
            ->where('pid', $pid)
            ->delete();
    }

    /**
     * @param $pid
     *
     * @return bool
     */
    public function deleteByPid($pid)
    {
        if (!$pid) {
            return false;
        }

        $info = app('api_db')
            ->table('server_promotion_v2_member_white_list')
            ->where('pid', $pid)
            ->get();

        if (!$info) {
            return true;
        }

        return app('api_db')
            ->table('server_promotion_v2_member_white_list')
            ->where('pid', $pid)
            ->delete();
    }

    /**
     * @param array          $condition
     * @param array          $option
     * @param array|string[] $columns
     *
     * @return mixed
     */
    public function getList(array $condition, array $option = [], array $columns = ['*'])
    {
        if (empty($condition) && empty($option)) {
            return [];
        }

        $model = app('api_db')->table('server_promotion_v2_member_white_list');

        if (isset($condition['pid'])) {
            if (is_array($condition['pid'])) {
                $model->whereIn('pid', $condition['pid']);
            } else {
                $model->where('pid', '=', $condition['pid']);
            }
        }

        if (isset($condition['company_id'])) {
            $model->where('company_id', '=', $condition['company_id']);
        }

        if (isset($condition['member_id'])) {
            $model->where('member_id', '=', $condition['member_id']);
        }

        if (isset($option['offset'])) {
            $ret = $model->limit($option['limit'])
                         ->offset($option['offset'])
                         ->select($columns)
                         ->orderBy('id', 'desc')
                         ->get()
                         ->map(function ($item) {
                             return (array)$item;
                         })
                         ->toArray();
        } else {
            $ret = $model->select($columns)->orderBy('id', 'desc')
                         ->get()->map(function ($item) {
                    return (array)$item;
                })->toArray();
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
        $model = app('api_db')->table('server_promotion_v2_member_white_list');

        if (isset($condition['pid'])) {
            if (is_array($condition['pid'])) {
                $model->whereIn('pid', $condition['pid']);
            } else {
                $model->where('pid', '=', $condition['pid']);
            }
        }

        if (isset($condition['company_id'])) {
            $model->where('company_id', '=', $condition['company_id']);
        }

        if (isset($condition['member_id'])) {
            $model->where('member_id', '=', $condition['member_id']);
        }

        return $model->count();
    }


}
