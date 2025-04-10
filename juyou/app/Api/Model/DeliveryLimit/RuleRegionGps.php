<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-10-18
 * Time: 15:25
 */

namespace App\Api\Model\DeliveryLimit;

class RuleRegionGps
{
    public static function Create($regionInfo)
    {
        try {
            $id = app('api_db')->table('server_delivery_limit_rule_region_gps')->insertGetId($regionInfo);
        } catch (\Exception $e) {
            $id = false;
        }
        return $id;
    }

    public static function DeleteByRegionId($regionId)
    {
        try {
            $status = app('api_db')
                ->table('server_delivery_limit_rule_region_gps')
                ->where('region_id', $regionId)
                ->delete();
        } catch (\Exception $e) {
            $status = false;
        }
        return $status;
    }

    public static function matchingRegionList($regionId, $province, $city)
    {
        $model = app('api_db')->table('server_delivery_limit_rule_region_gps')
            ->where('region_id', $regionId)
            ->when($province, function ($query1) use ($province) {
                return $query1->where('province', $province);
            })
            ->when($city, function ($query2) use ($city) {
                return $query2->where('city', $city);
            });
        return $model->get();
    }

}
