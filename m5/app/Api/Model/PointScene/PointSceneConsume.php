<?php
/**
 * @author xu
 * @date 2021-08-17
 */

namespace App\Api\Model\PointScene;

use Illuminate\Database\Query\Builder;

class PointSceneConsume
{
    /**
     * 过滤条件
     * @var array
     */
    protected $filters;

    public function __construct(array $filters)
    {
        $this->filters = $filters;
    }

    /**
     * 获取总条数
     *
     * @return int
     */
    public function queryCount()
    {
        $db = $this->getSelectSql('count', $this->filters);
        $count = $db->count();

        return $count;
    }

    /**
     * 获取查询语句
     * $field_type string 查询字段：all-所有；part-指定字段；count-计数
     * $where array
     * @return object $db
     */
    public function getSelectSql($field_type = 'all', $where = array())
    {
        $fields = '*';
        if ($field_type == 'part') {
            $fields = array(
                'member_business_record.business_bn',   # 业务编码
                'member_business_record.business_type', # 业务类型
                'member_business_record.point',         # 交易积分数
                'member_business_record.record_type',   # 交易类型
                'member_business_record.system_code',   # 系统编码
                'member_scene_rel.company_id',          # 公司ID
                'member_scene_rel.member_id',           # 用户ID
                'scene.name as scene_name',             # 预算名称（场景名称）
                'account_record.record_id',             # 交易编码
                'account_record.created_at',            # 交易时间
                'account_transfer.memo as memo'         # 备注
            );
        } else if ($field_type == 'count') {
            $fields = 'COUNT(*) as count';
        }

        $db = app('api_db')->table('server_new_point_account_record as account_record')
            ->select($fields)
            ->leftJoin('server_new_point_account_transfer as account_transfer', 'account_transfer.to_son_account_id', '=', 'account_record.son_account_id')
            ->leftJoin('server_new_point_business_bill_rel as business_bill_rel', 'business_bill_rel.bill_code', '=', 'account_record.bill_code')
            ->leftJoin('server_new_point_business_flow as business_flow', 'business_flow.business_flow_code', '=', 'business_bill_rel.business_flow_code')
            ->leftJoin('server_new_point_member_business_record as member_business_record', 'member_business_record.business_bn', '=', 'business_flow.business_bn')
            ->leftJoin(
                'server_new_point_member_scene_rel as member_scene_rel',
                function ($join) use ($where) {
                    $join->on('member_scene_rel.id', '=', 'member_business_record.member_account_id')
                        ->when($where['company_ids'], function (Builder $builder) use ($where) {
                            $builder->whereIn('member_scene_rel.company_id', $where['company_ids']);
                        });
                }
            )
            ->leftJoin('server_new_point_scene as scene', 'scene.scene_id', '=', 'member_scene_rel.scene_id');

        if (!empty($where['company_ids'])) {
            $db->whereIn('member_scene_rel.company_id', $where['company_ids']);
        }

        if ($where['begin_time']) {
            $db->where('account_record.created_at', '>=', $where['begin_time']);
        }

        if ($where['end_time']) {
            $db->where('account_record.created_at', '<=', $where['end_time']);
        }

        // $db->where('member_scene_rel.disabled', 0);

        $db->orderBy('account_record.record_id', 'desc');

        return $db;
    }

    /**
     * 分页获取数据
     * @param int $page
     * @param int $pageSize
     * @return array|bool|mixed
     */
    public function getRows($page = 0, $pageSize = 100)
    {
        $db = $this->getSelectSql('part', $this->filters);
        $db->forPage($page, $pageSize);

        $listObj = $db->get()->toArray();

        if (is_array($listObj) && count($listObj) > 0) {
            return json_decode(json_encode($listObj), true);
        }

        return array();
    }
}
