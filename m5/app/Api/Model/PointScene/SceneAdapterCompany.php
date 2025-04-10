<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-01-16
 * Time: 18:11
 */

namespace App\Api\Model\PointScene;


class SceneAdapterCompany
{
    public static function FindAdapterInfoByCompanyId($companyId)
    {
        return app('api_db')
            ->table('server_new_point_adapter_company as a')
            ->leftJoin('server_new_point_adapter_point as b', 'a.channel', '=', 'b.channel')
            ->select('a.company_id', 'a.channel','b.exchange_rate')
            ->where('company_id', $companyId)
            ->first();
    }

    public static function FindByCompanyId($companyId)
    {
        return app('api_db')
            ->table('server_new_point_adapter_company')
            ->select('company_id', 'channel')
            ->where('company_id', $companyId)
            ->first();
    }

    public static function AddCompanyChannel($companyId, $channel)
    {
        return app('api_db')
            ->table('server_new_point_adapter_company')
            ->insertGetId([
                'company_id' => $companyId,
                'channel'    => $channel
            ]);
    }

    public static function UpdateCompanyChannel($companyId, $channel)
    {
        return app('api_db')
            ->table('server_new_point_adapter_company')
            ->where('company_id', $companyId)
            ->update([
                'channel' => $channel
            ]);
    }
}
