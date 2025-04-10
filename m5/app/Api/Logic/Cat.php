<?php

namespace App\Api\Logic;

class Cat
{
    private $_db;
    private $_table;

    public function __construct()
    {
        $this->_db    = app('db')->connection('neigou_store');
        $this->_table = 'sdb_b2c_mall_goods_cat';
    }

    /**
     * @param array $pars
     * @param string $field
     *
     * @return array
     */
    public function getList(
        array $pars,
        $field = 'cat_id, parent_id, cat_path, cat_name, disabled, last_modify, min_image, mid_image, lar_image, pc_cat_image, weight, barcode_switch'
    ) {
        $pars['filter'] = (isset($pars['filter']) && is_array($pars['filter'])) ? $pars['filter'] : [];

        $cat = $this->_db->table($this->_table);


        if (isset($pars['filter']['cat_id'])) {
            if (is_numeric($pars['filter']['cat_id'])) {
                $cat = $cat->where('cat_id', $pars['filter']['cat_id']);
            } elseif (is_array($pars['filter']['cat_id'])) {
                $cat = $cat->whereIn('cat_id', $pars['filter']['cat_id']);
            }
        }

        if (isset($pars['filter']['parent_id'])) {
            if (is_numeric($pars['filter']['parent_id'])) {
                $cat = $cat->where('parent_id', $pars['filter']['parent_id']);
            } elseif (is_array($pars['filter']['parent_id'])) {
                $cat = $cat->whereIn('parent_id', $pars['filter']['parent_id']);
            }
        }

        if (isset($pars['filter']['cat_name']) && !empty($pars['filter']['cat_name'])) {
            $cat = $cat->where('cat_name', 'LIKE', '%'.$pars['filter']['cat_name'] . '%');
        }

        if (isset($pars['filter']['except_cat_id']) ) {
            if (is_numeric($pars['filter']['except_cat_id'])) {
                $cat = $cat->where('cat_id', '!=', $pars['filter']['except_cat_id']);
            } elseif (is_array($pars['filter']['except_cat_id'])) {
                $cat = $cat->whereNotIn('cat_id', $pars['filter']['except_cat_id']);
            }
        }

        $listData = [];

        if (isset($pars['page']) && $pars['page'] > 0 && isset($pars['limit']) && $pars['limit'] > 0) {
            $listCount = $cat->count();

            $offset = ($pars['page'] - 1) * $pars['limit'];

            $totalPage = ceil($listCount / $pars['limit']);

            $cat      = $cat->offset($offset)->limit($pars['limit']);
            $listData = ['page' => $pars['page'], 'totalCount' => $listCount, 'totalPage' => $totalPage];
        }

        $data = $cat->select($this->_db->raw($field))->get()->toArray();

        $listData['list'] = $data;

        return $listData;
    }

    // 获取所有的分类信息
    public function getAllCats()
    {
        $field = 'cat_id, parent_id, cat_path, cat_name, disabled, last_modify, min_image, mid_image, lar_image, pc_cat_image, weight' ;
        $cat = $this->_db->table($this->_table);
        return  $data = $cat->select($this->_db->raw($field))->get()->toArray();
    }


    public function UpdateBarcodeSwitch($catIds, $barcodeSwitch)
    {
        if(!is_array($catIds)) {
            $catIds = array($catIds);
        }
        $data = array(
            'barcode_switch' => $barcodeSwitch,
            'last_modify' => time()
        );

        return $this->_db->table($this->_table)
                         ->whereIn('cat_id', $catIds)
                         ->update($data);
    }
}
