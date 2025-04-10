<?php

namespace App\Api\Model\Brand;
use Illuminate\Support\Facades\DB;

class BrandRuleOutlet
{

    /**
     * 新增
     * @param $data
     * @return false
     */
    public static function createBrandRuleOutlet(array $data) {

        if (empty($data)) {
            return false;
        }
        $sql = self::batchInsert($data);
        return DB::insert($sql);
    }

    public static function batchInsert($insert_data){
        if ( !$insert_data ) return false;

        $sql = "INSERT INTO `server_brand_rule_outlet` (brand_rule_id,outlet_id,create_time) VALUES ";

        foreach ( $insert_data as $data )
        {

            if (!isset($data['brand_rule_id']) || !isset($data['outlet_id']) || !isset($data['create_time']))
            {
                continue;
            }

            $sql .= sprintf( "('%s','%s','%s'),", $data['brand_rule_id'], $data['outlet_id'], $data['create_time']);
        }

        $sql = trim( $sql, ',' );

        return $sql;
    }


    /**
     * 获取列表
     * @param int $brandRuleId
     * @return array
     */
    public static function getListByBrandRuleId(int $brandRuleId, array $field = ['*']) {
        if (empty($brandRuleId)) {
            return [];
        }

        $where = array(
            'brand_rule_id' => $brandRuleId
        );

        $return =  app('db')->table('server_brand_rule_outlet')->select($field)->where($where)->get()->map(function ($value) {
            return (array)$value;
        })->toArray();

        return $return;
    }


    /**
     * 删除
     * @param int $brandRuleId
     * @param array $outletIds
     * @return false
     */
    public static function delBrandRuleOutlet(int $brandRuleId, array  $outletIds) {
        if (empty($brandRuleId) || empty($outletIds)) {
            return false;
        }

        $where = array(
            'brand_rule_id' => $brandRuleId
        );
        $res =  app('db')->table('server_brand_rule_outlet')->where($where)->whereIn('outlet_id', $outletIds)->delete();
        return $res !== false;
    }

    /**
     * 删除全部
     * @param int $brandRuleId
     * @return false
     */
    public static function delBrandRuleOutletByBrandRuleId(int $brandRuleId) {
        if (empty($brandRuleId)) {
            return false;
        }

        $where = [
            'brand_rule_id' => $brandRuleId
        ];

        $res =  app('db')->table('server_brand_rule_outlet')->where($where)->delete();
        return $res !== false;
    }

    /**
     * 获取门店品牌规则关联
     * @param int $brandRuleId
     * @return array
     */
    public static function getBrandRuleOutlet(array $brandRuleId, array $outletId) {
        if (empty($brandRuleId) || empty($outletId)) {
            return [];
        }

        $return =  app('db')->table('server_brand_rule_outlet')
            ->whereIn('brand_rule_id', $brandRuleId)
            ->whereIn('outlet_id', $outletId)
            ->get()->map(function ($value) {
                return (array)$value;
            })->toArray();

        return $return;
    }
}
