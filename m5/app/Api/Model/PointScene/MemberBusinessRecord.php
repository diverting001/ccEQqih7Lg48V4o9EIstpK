<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-03-27
 * Time: 23:08
 */

namespace App\Api\Model\PointScene;

use Illuminate\Database\Query\Builder;

class MemberBusinessRecord
{
    /**
     * 创建业务流水
     */
    public static function Create($data)
    {
        $saveData = array(
            'system_code'       => $data['system_code'],
            'business_type'     => $data['business_type'],
            'business_bn'       => $data['business_bn'],
            'member_account_id' => $data['member_account_id'],
            'record_type'       => $data['record_type'],
            'before_point'      => $data['before_point'] ? $data['before_point'] : 0,
            'point'             => $data['point'],
            'after_point'       => $data['after_point'] ? $data['after_point'] : 0,
            'memo'              => isset($data['memo']) && $data['memo'] ? $data['memo'] : "",
            'created_at'        => time()
        );
        try {
            $status = app('api_db')->table('server_new_point_member_business_record')->insert($saveData);
        } catch (\Exception $e) {
            $status = false;
        }
        return $status;
    }

    public static function QueryCount($companyIds, $memberIds, $queryWhere)
    {
        foreach ($queryWhere['accounts'] as &$account) {
            $account = strval($account);
        }

        $db = app('api_db')->table('server_new_point_member_scene_rel as rel')
            ->leftJoin(
                'server_new_point_member_business_record as record',
                function ($join) use ($memberIds, $companyIds, $queryWhere) {
                    $join->on('rel.id', '=', 'record.member_account_id')
                        ->when($companyIds, function (Builder $builder) use ($companyIds) {
                            $builder->whereIn('rel.company_id', $companyIds);
                        })
                        ->when($memberIds, function (Builder $builder) use ($memberIds) {
                            $builder->whereIn('rel.member_id', $memberIds);
                        })
                        ->when($queryWhere['scene_ids'], function (Builder $builder) use ($queryWhere) {
                            $builder->whereIn('rel.scene_id', $queryWhere['scene_ids']);
                        })
                        ->when($queryWhere['accounts'], function (Builder $builder) use ($queryWhere) {
                            $builder->whereIn('rel.account', $queryWhere['accounts']);
                        });
                }
            );

        if ($companyIds) {
            $db->whereIn('rel.company_id', $companyIds);
        }
        if ($memberIds) {
            $db->whereIn('rel.member_id', $memberIds);
        }
        if ($queryWhere['scene_ids']) {
            $db->whereIn('rel.scene_id', $queryWhere['scene_ids']);
        }
        if ($queryWhere['accounts']) {
            $db->whereIn('rel.account', $queryWhere['accounts']);
        }

        $where = [];
        if ($queryWhere['system_code']) {
            $where['record.system_code'] = $queryWhere['system_code'];
        }

        if ($queryWhere['business_type']) {
            $where['record.business_type'] = $queryWhere['business_type'];
        }

        if ($queryWhere['begin_time']) {
            $db->where('record.created_at', '>=', $queryWhere['begin_time']);
        }

        if ($queryWhere['end_time']) {
            $db->where('record.created_at', '<', $queryWhere['end_time']);
        }

        if ($queryWhere['record_type'] && $queryWhere['record_type'] != 'all') {
            $where['record.record_type'] = $queryWhere['record_type'];
        }

        if ($queryWhere['search_key']) {
            $db->where('record.memo like', 'like', $queryWhere['search_key']);
        }

        if ($where) {
            $db->where($where);
        }
        return $db->count();
    }

    public static function Query($companyIds, $memberIds, $queryWhere, $page = 1, $pageSize = 10)
    {
        foreach ($queryWhere['accounts'] as &$account) {
            $account = strval($account);
        }

        $db = app('api_db')->table('server_new_point_member_scene_rel as rel')
            ->select(
                'record.id',
                'record.system_code',
                'record.business_type',
                'record.business_bn',
                'rel.company_id',
                'rel.member_id',
                'rel.scene_id',
                'rel.account',
                'rel.exchange_rate',
                'scene.name as scene_name',
                'record.record_type',
                'record.before_point',
                'record.point',
                'record.after_point',
                'record.memo',
                'record.created_at'
            )
            ->leftJoin('server_new_point_scene as scene', 'rel.scene_id', '=', 'scene.scene_id')
            ->leftJoin('server_new_point_member_business_record as record',
                function ($join) use ($memberIds, $companyIds, $queryWhere) {
                    $join->on('rel.id', '=', 'record.member_account_id')
                        ->when($companyIds, function (Builder $builder) use ($companyIds) {
                            $builder->whereIn('rel.company_id', $companyIds);
                        })
                        ->when($memberIds, function (Builder $builder) use ($memberIds) {
                            $builder->whereIn('rel.member_id', $memberIds);
                        })
                        ->when($queryWhere['scene_ids'], function (Builder $builder) use ($queryWhere) {
                            $builder->whereIn('rel.scene_id', $queryWhere['scene_ids']);
                        })
                        ->when($queryWhere['accounts'], function (Builder $builder) use ($queryWhere) {
                            $builder->whereIn('rel.account', $queryWhere['accounts']);
                        });
                }
            );

        if ($companyIds) {
            $db->whereIn('rel.company_id', $companyIds);
        }
        if ($memberIds) {
            $db->whereIn('rel.member_id', $memberIds);
        }
        if ($queryWhere['scene_ids']) {
            $db->whereIn('rel.scene_id', $queryWhere['scene_ids']);
        }
        if ($queryWhere['accounts']) {
            $db->whereIn('rel.account', $queryWhere['accounts']);
        }

        if ($queryWhere['system_code']) {
            $db->where('record.system_code', '=', $queryWhere['system_code']);
        }

        if ($queryWhere['business_type']) {
            $db->where('record.business_type', '=', $queryWhere['business_type']);
        }

        if ($queryWhere['begin_time']) {
            $db->where('record.created_at', '>=', $queryWhere['begin_time']);
        }

        if ($queryWhere['end_time']) {
            $db->where('record.created_at', '<', $queryWhere['end_time']);
        }

        if ($queryWhere['record_type'] && $queryWhere['record_type'] != 'all') {
            $db->where('record.record_type', '=', $queryWhere['record_type']);
        }

        if ($queryWhere['search_key']) {
            $db->where('record.memo', 'like', $queryWhere['search_key']);
        }

        $db->orderBy('record.created_at', 'desc');
        $db->orderBy('record.id', 'desc');
        $db->forPage($page, $pageSize);

        $listObj = $db->get()->toArray();

        if (is_array($listObj) && count($listObj) > 0) {
            return json_decode(json_encode($listObj), true);
        }
        return array();
    }

}
