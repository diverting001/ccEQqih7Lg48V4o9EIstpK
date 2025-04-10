<?php

namespace App\Api\Logic\ExceptionMsg;

use App\Api\Model\ExceptionMsg\ExceptionMember;
use App\Api\Model\Order\Order as OrderModel;

class ExceptionMsg
{
    public static function getByDataId($data_id)
    {
        return get_object_vars(app('api_db')->table('server_exception_msg')
            ->where('data_id', $data_id)
            ->first());
    }

    public static function getInfo($where)
    {
        return get_object_vars(app('api_db')->table('server_exception_msg')
            ->where($where)
            ->first());
    }

    public static function getList($where, $offset = 0, $limit = 10, $order = 'id asc')
    {
        return app('api_db')->table('server_exception_msg')
            ->where($where)
            ->offset($offset)
            ->limit($limit)
            ->orderByRaw($order)
            ->get()->map(function ($value) {
                return (array)$value;
            })
            ->toArray();
    }

    public static function update($where, $data)
    {
        return app('api_db')->table('server_exception_msg')
            ->where($where)
            ->update($data);
    }

    /**
     * 保存异常消息
     * @param $exception_info
     * @return bool
     */
    public static function addException($exception_info)
    {
        $exception_info['create_time'] = date('Y-m-d H:i:s');
        return app('api_db')->table('server_exception_msg')->insert($exception_info);
    }

    public static function getConf($business = '')
    {
        $conf_list = app('api_db')->table('server_exception_msg_conf')
            ->whereIn('business', ['all', $business])
            ->get();
        $data = [
            'allow' => [],
            'block' => [],
        ];
        foreach ($conf_list as $conf) {
            if (empty($conf->value)) {
                continue;
            }
            if ($conf->operate === 'in') {
                $data['allow'][$conf->key] = array_merge((array)$data['allow'][$conf->key], explode(',', $conf->value));
            } elseif ($conf->operate === 'not in') {
                $data['block'][$conf->key] = array_merge((array)$data['block'][$conf->key], explode(',', $conf->value));
            }
        }
        return $data;
    }

    public function addMemberException($data)
    {
        if (empty($data)) {
            return false;
        }
        $model = new ExceptionMember();
        return $model->add($data);
    }

    public function getMemberExceptionList(array $search): array
    {
        $where = $this->getMemberExceptionWhereByFilter($search['filter']);
        $model = new ExceptionMember();
        return $model->getList($where, $search['page_index'], $search['page_size']);
    }

    public function getMemberExceptionTotal(array $search): int
    {
        $where = $this->getMemberExceptionWhereByFilter($search['filter']);
        $model = new ExceptionMember();
        return $model->getTotal($where);
    }

    private function getMemberExceptionWhereByFilter($filter)
    {
        $where = [];
        if (empty($filter)) {
            return $where;
        }
        foreach ($filter as $field => $value) {
            if (empty($value)) {
                continue;
            }
            switch ($field) {
                case 'trigger_time':
                    $where['trigger_time'] = [
                        'type' => 'between',
                        'value' => [
                            'egt' => $value['start_time'] ?? 0,
                            'elt' => $value['end_time'] ?: time()
                        ]
                    ];
                    break;
                case 'create_time':
                    $where['create_time'] = [
                        'type' => 'between',
                        'value' => [
                            'egt' => $value['start_time'],
                            'elt' => $value['end_time'] ?: time()
                        ]
                    ];
                    break;
                default:
                    if (is_array($value)) {
                        $where[$field] = [
                            'type' => 'in',
                            'value' => $value
                        ];
                    } else {
                        $where[$field] = [
                            'type' => 'eq',
                            'value' => $value
                        ];
                    }
            }
        }
        return $where;
    }
}
