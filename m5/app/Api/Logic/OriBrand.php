<?php

namespace App\Api\Logic;

class OriBrand
{
    private $_db;
    private $_table;

    public function __construct()
    {
        $this->_db    = app('db')->connection('neigou_store');
        $this->_table = 'sdb_b2c_ori_brand';
    }

    /**
     * @param array $pars
     * @param string $field
     *
     * @return array
     */
    public function searchList(array $pars, string $field = 'ori_brand_id, ori_brand_name')
    {
        $_model = $this->_db->table($this->_table);

        if (!empty($pars['filter']['ori_brand_name'])) {
            if (is_array($pars['filter']['ori_brand_name'])) {
                $_model = $_model->whereIn('ori_brand_name', $pars['filter']['ori_brand_name']);
            } else {
                if (is_numeric($pars['filter']['ori_brand_name'])) {
                    $_model = $_model->where(function ($query) use ($pars) {
                        $query->where('ori_brand_name', 'like', '%' . $pars['filter']['ori_brand_name'] . '%')
                            ->orWhere('ori_brand_id', $pars['filter']['ori_brand_name']);
                    });
                } else {
                    $_model = $_model->where('ori_brand_name', 'like', '%' . $pars['filter']['ori_brand_name'] . '%');
                }
            }
        }

        if (isset($pars['filter']['ori_brand_id'])) {
            if (is_numeric($pars['filter']['ori_brand_id'])) {
                $_model = $_model->where('ori_brand_id', $pars['filter']['ori_brand_id']);
            } elseif (is_array($pars['filter']['ori_brand_id'])) {
                $_model = $_model->whereIn('ori_brand_id', $pars['filter']['ori_brand_id']);
            }
        }

        $listData = [];

        if (isset($pars['page']) && $pars['page'] > 0 && isset($pars['limit']) && $pars['limit'] > 0) {
            $listCount = $_model->count();

            $offset = ($pars['page'] - 1) * $pars['limit'];

            $totalPage = ceil($listCount / $pars['limit']);

            $_model      = $_model->offset($offset)->limit($pars['limit']);
            $listData = ['page' => $pars['page'], 'totalCount' => $listCount, 'totalPage' => $totalPage];
        }

        $data = $_model->select($this->_db->raw($field))->get()->toArray();

        $listData['list'] = $data;

        return $listData;
    }
}
