<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-03-27
 * Time: 23:08
 */

namespace App\Api\Model\PointScene;

use Illuminate\Database\Query\Builder;

class CompanyBusinessRecord
{
    /**
     * 创建业务流水
     */
    public static function Create($data)
    {
        $saveData = array(
            'system_code'        => $data['system_code'],
            'business_type'      => $data['business_type'],
            'business_bn'        => $data['business_bn'],
            'company_account_id' => $data['company_account_id'],
            'record_type'        => $data['record_type'],
            'before_point'       => $data['before_point'] ? $data['before_point'] : 0,
            'point'              => $data['point'],
            'after_point'        => $data['after_point'] ? $data['after_point'] : 0,
            'memo'               => isset($data['memo']) && $data['memo'] ? $data['memo'] : "",
            'memo_operator'      => isset($data['memo_operator']) && $data['memo_operator'] ? $data['memo_operator'] : "",
            'created_at'         => time()
        );
        try {
            $status = app('api_db')->table('server_new_point_company_business_record')->insert($saveData);
        } catch (\Exception $e) {
            $status = false;
        }
        return $status;
    }

    public static function QueryCount($companyIds, $sceneIds, $queryWhere)
    {
        $selectSql = ' count(1) as count ';
        $sql = 'select ' . $selectSql . ' from server_new_point_company_scene_rel rel ';
        $sql .= ' left join server_new_point_company_business_record record on rel.id = record.company_account_id and rel.company_id in (' . implode(',', $companyIds) . ') and rel.scene_id in (' . implode(',', $sceneIds) . ') ';
        $sql .= ' where rel.company_id in (' . implode(',', $companyIds) . ') and rel.scene_id in (' . implode(',', $sceneIds) . ') ';

        $sql .= ' and record.system_code = "' . $queryWhere['system_code'] .'"';
        $selectData = array();
        if ($queryWhere['begin_time']) {
            //$sql .= ' and record.created_at >= ' . $queryWhere['begin_time'];
            $sql .= ' and record.created_at >= :begin_time';
            $selectData['begin_time'] = $queryWhere['begin_time'];
        }

        if ($queryWhere['end_time']) {
            //$sql .= ' and record.created_at < ' . $queryWhere['end_time'];
            $sql .= ' and record.created_at < :end_time';
            $selectData['end_time'] = $queryWhere['end_time'];
        }

        if ($queryWhere['record_type'] && $queryWhere['record_type'] != 'all') {
            //$sql .= ' and record.record_type  = "' . $queryWhere['record_type'].'"';
            $sql .= ' and record.record_type  = :record_type';
            $selectData['record_type'] = $queryWhere['record_type'];
        }

        if ($queryWhere['search_key']) {
            //$sql .= ' and record.memo like "' . $queryWhere['search_key'] . '" ';
            $sql .= ' and record.memo like :search_key';
            $selectData['search_key'] = $queryWhere['search_key'];
        }

        $sqlObj = app('api_db')->selectOne($sql,$selectData);
        return isset($sqlObj->count) ? $sqlObj->count : 0;
    }

    public static function Query($companyIds, $sceneIds, $queryWhere, $page = 1, $pageSize = 10)
    {
        $pageSize = (int)$pageSize;
        $page = (int)$page;

        $selectSql = ' record.id,record.system_code,record.business_type,record.business_bn,rel.company_id,rel.scene_id,record.record_type,record.point,record.memo,record.memo_operator,record.created_at,scene.name as scene_name';
        $sql = 'select ' . $selectSql . ' from server_new_point_company_scene_rel rel ';
        $sql .= ' left join server_new_point_company_business_record record on rel.id = record.company_account_id and rel.company_id in (' . implode(',', $companyIds) . ') and rel.scene_id in (' . implode(',', $sceneIds) . ') ';
        $sql .= ' LEFT JOIN server_new_point_scene scene ON rel.scene_id=scene.scene_id ';
        $sql .= ' where rel.company_id in (' . implode(',', $companyIds) . ') and rel.scene_id in (' . implode(',', $sceneIds) . ') ';

        $sql .= ' and record.system_code = "' . $queryWhere['system_code'] .'"';

        $selectData = array();
        if ($queryWhere['begin_time']) {
            //$sql .= ' and record.created_at >= ' . $queryWhere['begin_time'];
            $sql .= ' and record.created_at >= :begin_time';
            $selectData['begin_time'] = $queryWhere['begin_time'];
        }

        if ($queryWhere['end_time']) {
            //$sql .= ' and record.created_at < ' . $queryWhere['end_time'];
            $sql .= ' and record.created_at < :end_time';
            $selectData['end_time'] = $queryWhere['end_time'];
        }

        if ($queryWhere['record_type'] && $queryWhere['record_type'] != 'all') {
            //$sql .= ' and record.record_type  = "' . $queryWhere['record_type'].'"';
            $sql .= ' and record.record_type  = :record_type';
            $selectData['record_type'] = $queryWhere['record_type'];
        }

        if ($queryWhere['search_key']) {
           // $sql .= ' and record.memo like "' . $queryWhere['search_key'] . '" ';
            $sql .= ' and record.memo like :search_key';
            $selectData['search_key'] = $queryWhere['search_key'];
        }

        $sql .= ' order by record.created_at desc ';
        $sql .= ' limit ' . $pageSize * ($page - 1) . ' , ' . $pageSize;

        $listObj = app('api_db')->select($sql,$selectData);
        if (is_array($listObj) && count($listObj) > 0) {
            return json_decode(json_encode($listObj), true);
        }
        return array();
    }

    public static function QueryByBusiness($systemCode, $businessType, $businessBns)
    {
        if(is_string($businessBns)){
            $businessBns = [$businessBns];
        }
        $recordInfo = app('api_db')->table('server_new_point_company_business_record as record')
            ->leftJoin("server_new_point_company_scene_rel as rel", "record.company_account_id", '=', 'rel.id')
            ->leftJoin('server_new_point_scene as scene', 'rel.scene_id', '=', 'scene.scene_id')
            ->select("rel.company_id", "rel.scene_id", "scene.name as scene_name", "record.system_code", "record.business_type", "record.business_bn", "record.record_type", "record.point", "record.memo","record.memo_operator", "record.created_at")
            ->where('record.system_code', $systemCode)
            ->where('record.business_type', $businessType)
            ->whereIn('record.business_bn', $businessBns)
            ->first();
        return $recordInfo;
    }

    //流水信息查询
    public static function queryRecord($where,$page,$page_size){
        $parse_where = [];
        if($where['company_id']>0){
            array_push($parse_where,['company_id',$where['company_id']]);
        }
        if(isset($where['type'])){
            array_push($parse_where,['record_type',$where['type']]);
        }
        if($where['id']>0){
            array_push($parse_where,['br.id',$where['id']]);
        }
        if($where['start_time']>0 && $where['end_time']>0){
            array_push($parse_where,['br.created_at','>=',$where['start_time']]);
            array_push($parse_where,['br.created_at','<=',$where['end_time']]);
        }
        $data['list'] = app('api_db')->table('server_new_point_company_business_record as br')
            ->leftJoin('server_new_point_company_scene_rel as com','br.company_account_id', '=', 'com.id')
            ->when($where['company_id']>0,function (Builder $builder) use ($where){
                $builder->where('company_id',$where['company_id']);
            })
            ->when(isset($where['type']),function (Builder $builder) use ($where){
                $builder->where('record_type',$where['type']);
            })
            ->when($where['id']>0,function (Builder $builder) use ($where){
                $builder->where('br.id',$where['id']);
            })
            ->when($where['start_time']>0 && $where['end_time']>0,function (Builder $builder) use ($where){
                $builder->where([['br.created_at','>=',$where['start_time']],['br.created_at','<=',$where['end_time']]]);
            })
//            ->where($parse_where)
            ->select('br.id','br.created_at','br.business_type','br.business_bn','br.company_account_id','br.record_type','br.point','com.company_id','br.memo')
            ->orderBy('br.created_at','desc')
            ->forPage($page , $page_size)
            ->get();
        $data['count'] = app('api_db')->table('server_new_point_company_business_record as br')
            ->leftJoin('server_new_point_company_scene_rel as com','br.company_account_id', '=', 'com.id')
            ->where($parse_where)
            ->count();
        return $data;
    }

    //公司信息查询
    public static function queryCompanyAccountRecord($companyIds,$page,$page_size){
        $obj = app('api_db')->table('server_new_point_company_scene_rel as com')
            ->leftJoin('server_new_point_account as acc','com.account', '=', 'acc.account')
            ->when(count($companyIds)>0,function (Builder $builder) use ($companyIds){
                $builder->whereIn('com.company_id',$companyIds);
            })
            ->select('com.company_id','acc.account_id',app('api_db')->raw('sum(point) as point'),app('api_db')->raw('sum(used_point) as sum_used_point'),app('api_db')->raw('sum(point-used_point) as balance'))
            ->forPage($page , $page_size)
            ->groupBy('com.company_id');
        $data['list'] = $obj->get();
        if(is_object($data['list'])){
            foreach ($data['list'] as $key=>$val){
                $sum = app('api_db')->table('server_new_point_account_son')->select(app('api_db')->raw('sum(used_point) as sum_used_point'))->where('account_id',$val->account_id)->first();
                $data['list'][$key]->sum_used_point = $sum->sum_used_point;//获取所有子账户已经使用的积分总和
            }
        }
        $db = app('api_db')->table('server_new_point_company_scene_rel')
            ->when(count($companyIds)>0,function (Builder $builder) use ($companyIds){
                $builder->whereIn('company_id',$companyIds);
            })
            ->select(app('api_db')->raw('count(DISTINCT company_id) as count'));
        $tmp = $db->first();
        $data['count'] = $tmp->count;
        return $data;
    }

    //根据scene_id获取公司列表
    public static function queryCompanyListBySceneId($scene_id){
        $list = app('api_db')->table('server_new_point_company_scene_rel')
            ->where('scene_id',$scene_id)->get();
        return $list;
    }

    public static function QueryCountV2($companyIds, $queryWhere)
    {
        $db = app('api_db')->table('server_new_point_company_scene_rel as rel')
            ->leftJoin(
                'server_new_point_company_business_record as record',
                function ($join) use ($companyIds, $queryWhere) {
                    $join->on('rel.id', '=', 'record.company_account_id')
                        ->when($companyIds, function (Builder $builder) use ($companyIds) {
                            $builder->whereIn('rel.company_id', $companyIds);
                        })
                        ->when($queryWhere['scene_ids'], function (Builder $builder) use ($queryWhere) {
                            $builder->whereIn('rel.scene_id', $queryWhere['scene_ids']);
                        });
                }
            );

        if ($companyIds) {
            $db->whereIn('rel.company_id', $companyIds);
        }
        if ($queryWhere['scene_ids']) {
            $db->whereIn('rel.scene_id', $queryWhere['scene_ids']);
        }

        $where = [];
        if ($queryWhere['system_code']) {
            $where['record.system_code'] = $queryWhere['system_code'];
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

    public static function QueryV2($companyIds, $queryWhere, $direction, $page = 1, $pageSize = 10)
    {
        $db = app('api_db')->table('server_new_point_company_scene_rel as rel')
            ->select(
                'record.id',
                'record.system_code',
                'record.business_type',
                'record.business_bn',
                'rel.company_id',
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
            ->leftJoin('server_new_point_company_business_record as record',
                function ($join) use ($companyIds, $queryWhere) {
                    $join->on('rel.id', '=', 'record.company_account_id')
                        ->when($companyIds, function (Builder $builder) use ($companyIds) {
                            $builder->whereIn('rel.company_id', $companyIds);
                        })
                        ->when($queryWhere['scene_ids'], function (Builder $builder) use ($queryWhere) {
                            $builder->whereIn('rel.scene_id', $queryWhere['scene_ids']);
                        });
                }
            );

        if ($companyIds) {
            $db->whereIn('rel.company_id', $companyIds);
        }

        if ($queryWhere['scene_ids']) {
            $db->whereIn('rel.scene_id', $queryWhere['scene_ids']);
        }

        if ($queryWhere['system_code']) {
            $db->where('record.system_code', '=', $queryWhere['system_code']);
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

        $db->orderBy('record.created_at', $direction);
        $db->orderBy('record.id', $direction);
        $db->forPage($page, $pageSize);

        $listObj = $db->get()->toArray();

        if (is_array($listObj) && count($listObj) > 0) {
            return json_decode(json_encode($listObj), true);
        }
        return array();
    }
}
