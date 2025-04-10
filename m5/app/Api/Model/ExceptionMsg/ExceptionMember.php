<?php

namespace App\Api\Model\ExceptionMsg;

/**
 * 用户行为异常 model
 */
class ExceptionMember
{
    public function getList(array $where, int $page, int $limit, string $order = 'id desc'): array
    {
        $offset = (max($page - 1, 0)) * $limit;
        $db = app('db')->table('server_exception_member');
        $result = $this->addWhere($where, $db)->offset($offset)->limit($limit)->orderByRaw($order)->get()->map(function ($value) {
            $value->extend_info = json_decode($value->extend_info, true) ?: [];
            $value->error_msg = json_decode($value->error_msg, true) ?: [];
            return (array)$value;
        })->toArray();
        return $result;
    }

    public function getTotal(array $where): int
    {
        $db = app('db')->table('server_exception_member');
        return $this->addWhere($where, $db)->count();
    }

    private function addWhere($where, $db)
    {
        foreach ($where as $fieldK => $whereV) {
            $value = $whereV['value'];
            switch ($whereV['type']) {
                case 'in':
                    $db->whereIn($fieldK, $value);
                    break;
                case 'between':
                    $db->whereBetween($fieldK, [$value['egt'], $value['elt']]);
                    break;
                case 'eq':
                    $db->where($fieldK, $value);
                    break;
            }
        }
        return $db;
    }

    public function add($data)
    {
        try {
            $extendInfo = is_array($data['extend_info']) ? json_encode($data['extend_info']) : ($data['extend_info'] ?: '{}');
            $id = app('api_db')->table('server_exception_member')->insertGetId([
                'member_id' => $data['member_id'] ?? 0,
                'company_id' => $data['company_id'] ?? 0,
                'trigger_time' => $data['trigger_time'] ?? 0,
                'error_msg' => $data['error_msg'] ?? '',
                'suggest_msg' => $data['suggest_msg'] ?? '',
                'member_account' => $data['member_account'] ?? '',
                'business_no' => $data['business_no'] ?? '',
                'business_type' => $data['business_type'] ?? '',
                'business_scene' => $data['business_scene'] ?? '',
                'business_scene_detail' => $data['business_scene_detail'] ?? '',
                'extend_info' => $extendInfo,
                'batch' => $data['batch'] ?? '',
                'create_time' => time()
            ]);
        } catch (\Exception $e) {
            $id = false;
        }

        return $id;
    }
}
