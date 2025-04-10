<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-10-18
 * Time: 15:25
 */

namespace App\Api\Model\Delivery;

class RuleRegionFourLevel
{
    public static function Create($regionInfo)
    {
        try {
            $id = app('api_db')->table('server_delivery_rule_region_four_level')->insertGetId($regionInfo);
        } catch (\Exception $e) {
            $id = false;
        }
        return $id;
    }

    public static function DeleteByRegionId($regionId)
    {
        try {
            $status = app('api_db')
                ->table('server_delivery_rule_region_four_level')
                ->where('region_id', $regionId)
                ->delete();
        } catch (\Exception $e) {
            $status = false;
        }
        return $status;
    }

    public static function matchingRegionList($regionId, $province, $city, $county, $town)
    {
        $model = app('api_db')->table('server_delivery_rule_region_four_level')->where('region_id', $regionId);
        $model = $model->where(function ($query) use ($province, $city, $county, $town) {
            $query->orWhere('province', 'all')
                ->when($province, function ($query0) use ($province, $city, $county, $town) {
                    return $query0->orWhere(function ($query00) use ($province, $city, $county, $town) {
                        $query00->where('province', $province)
                            ->when($city, function ($query000) use ($city) {
                                return $query000->whereIn('city', ['all', $city]);
                            })
                            ->when($county, function ($query001) use ($county) {
                                return $query001->whereIn('county', ['all', $county]);
                            })
                            ->when($town, function ($query002) use ($town) {
                                return $query002->whereIn('town', ['all', $town]);
                            });
                    });
                })
                ->when($province && $city, function ($query1) use ($province, $city, $county, $town) {
                    return $query1->orWhere(function ($query11) use ($province, $city, $county, $town) {
                        $query11->where('province', $province)
                            ->where('city', $city)
                            ->when($county, function ($query111) use ($county) {
                                return $query111->whereIn('county', ['all', $county]);
                            })
                            ->when($town, function ($query112) use ($town) {
                                return $query112->whereIn('town', ['all', $town]);
                            });
                    });
                })
                ->when($province & $city & $county, function ($query2) use ($province, $city, $county, $town) {
                    return $query2->orWhere(function ($query21) use ($province, $city, $county, $town) {
                        $query21->where('province', $province)
                            ->where('city', $city)
                            ->where('county', $county)
                            ->when($town, function ($query211) use ($town) {
                                return $query211->whereIn('town', ['all', $town]);
                            });
                    });
                });

        });

        $list = $model->get();

        return $list;
    }
}
