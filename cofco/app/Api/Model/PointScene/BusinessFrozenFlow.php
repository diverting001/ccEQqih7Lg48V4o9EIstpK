<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-01-16
 * Time: 18:11
 */

namespace App\Api\Model\PointScene;


class BusinessFrozenFlow
{
    /**
     * 创建业务流水
     */
    public static function Create($businessInfo)
    {
        $businessInfo = array(
            'business_frozen_code' => $businessInfo['business_frozen_code'],
            'business_type' => $businessInfo['business_type'],
            'business_bn' => $businessInfo['business_bn'],
            'system_code' => $businessInfo['system_code'],
            'created_at' => time()
        );
        try {
            $status = app('api_db')->table('server_new_point_business_frozen')->insert($businessInfo);
        } catch (\Exception $e) {
            $status = false;
        }
        return $status;
    }

    /**
     * 根据业务流水号查询业务流水
     */
    public static function GetBusinessFrozenPoolCode($businessFrozenCode)
    {
        $where = array(
            'business_frozen_code' => strval($businessFrozenCode),
        );
        return app('api_db')
            ->table('server_new_point_business_frozen_pool_rel')
            ->where($where)
            ->get();
    }

    public static function FindByBusinessFrozenCode($businessFrozenCode)
    {
        $where = array(
            'business_frozen_code' => strval($businessFrozenCode),
        );
        return app('api_db')
            ->table('server_new_point_business_frozen')
            ->where($where)
            ->first();
    }

    public static function BindFrozenPoolCode($businessFrozenCode, $frozenPoolCode)
    {
        $businessInfo = array(
            'business_frozen_code' => strval($businessFrozenCode),
            'frozen_pool_code' => strval($frozenPoolCode),
            'created_at' => time()
        );
        try {
            $status = app('api_db')->table('server_new_point_business_frozen_pool_rel')->insert($businessInfo);
        } catch (\Exception $e) {
            $status = false;
        }
        return $status;
    }

    /**
     * 根据业务单号查询业务流水
     */
    public static function GetBusinessFrozenPoolCodeByBusinessBn($businessType, $businessBn, $systemCode)
    {
        $where = array(
            'frozen.business_type' => $businessType,
            'frozen.business_bn' => strval($businessBn),
            'frozen.system_code' => strval($systemCode)
        );
        return app('api_db')
            ->table('server_new_point_business_frozen as frozen')
            ->leftJoin('server_new_point_business_frozen_pool_rel as rel', 'frozen.business_frozen_code', '=', 'rel.business_frozen_code')
            ->select("frozen.business_frozen_code", "frozen.business_type", "frozen.business_bn", "rel.frozen_pool_code")
            ->where($where)
            ->get();
    }

}
