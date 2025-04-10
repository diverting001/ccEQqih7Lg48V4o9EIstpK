<?php

namespace App\Api\Model\Brand;

class BrandRule
{

    /**
     * 获取总数
     * @param array $where
     * @return mixed
     */
    public static function getListCount(array $where, array $whereIn = []) {
        $query = app('db')->table('server_brand_rule')->where($where);
        if (!empty($whereIn)) {
            foreach ($whereIn as $field => $value) {
                $query = $query->whereIn($field, $value);
            }
        }
        return $query->count();
    }

    /**
     * 获取列表
     * @return void
     */
    public static function getList(array $where, array $whereIn, int $page, int $limit, string $order = 'id desc') :array {

        $offset = ($page - 1) * $limit;

        $query = app('db')->table('server_brand_rule')->where($where);
        if (!empty($whereIn)) {
            foreach ($whereIn as $field => $value) {
                $query = $query->whereIn($field, $value);
            }
        }

        $return =  $query->orderByRaw($order)->limit($limit)->offset($offset)->get()->map(function ($value) {
            return (array)$value;
        })->toArray();

        return $return;
    }

    /**
     * 新增规则
     * @param $data
     * @return false
     */
    public static function createBrandRule(array $data) {

        if (empty($data)) {
            return false;
        }

        return app('db')->table('server_brand_rule')->insertGetId($data);
    }

    /**
     * 修改
     * @param int $id
     * @param array $update
     * @return false
     */
    public static function updateBrandRuleById(int $id, array $update) {

        if (empty($id) || empty($update)) {
            return false;
        }

        $where = array(
            'id' => $id
        );

        return app('db')->table('server_brand_rule')->where($where)->update($update);
    }

    /**
     * @param int $id
     * @return array
     */
    public static function findBrandRuleById(int $id) {

        if (empty($id)) {
            return array();
        }

        $where = array(
            'id' => $id
        );

        $return =  app('db')->table('server_brand_rule')->where($where)->first();

        return $return ? get_object_vars($return) : array();
    }

    /**
     * @param int $id
     * @return array
     */
    public static function getBrandRuleById(array $id, array $field = ['*']) {

        if (empty($id)) {
            return array();
        }

        $return =  app('db')->table('server_brand_rule')->select($field)->whereIn('id', $id)->get()->map(function ($value) {
            return (array)$value;
        })->toArray();

        return $return;
    }

    /**
     * 删除
     * @param int $id
     * @return false
     */
    public static function delBrandRuleById(int $id) {
        if (empty($id)) {
            return false;
        }

        $where = array(
            'id' => $id
        );

        $res =  app('db')->table('server_brand_rule')->where($where)->delete();
        return $res !== false;
    }
}
