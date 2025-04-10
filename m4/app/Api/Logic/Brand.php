<?php

namespace App\Api\Logic;

class Brand
{
    private $_db;
    private $_table;

    public function __construct()
    {
        $this->_db    = app('db')->connection('neigou_store');
        $this->_table = 'sdb_b2c_brand';
    }

    /**
     * @param array $pars
     * @param string $field
     *
     * @return array
     */
    public function searchList(array $pars, $field = 'brand_id, brand_name, disabled, last_modify')
    {
        $_model = $this->_db->table($this->_table);

        if ( ! empty($pars['filter']['brand_name'])) {

            $_model = $_model->where('brand_name', 'like', '%'.$pars['filter']['brand_name'].'%');
        }

        if (isset($pars['filter']['brand_id'])) {
            if (is_numeric($pars['filter']['brand_id'])) {
                $_model = $_model->where('brand_id', $pars['filter']['brand_id']);
            } elseif (is_array($pars['filter']['brand_id'])) {
                $_model = $_model->whereIn('brand_id', $pars['filter']['brand_id']);
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
