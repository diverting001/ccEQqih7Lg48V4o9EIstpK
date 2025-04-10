<?php

namespace App\Api\Logic;

class Shop
{
    private $_db;

    public function __construct()
    {
        $this->_db = app('db')->connection('neigou_store');
    }

    /*
     * 查询商品
     */
    public function GetList($pars)
    {
        $where = '';
        $sql   = 'select shop.pop_shop_id as shop_id,shop.name as shop_name,shop.pop_owner_id,wms.pop_wms_id,wms.pop_wms_code from sdb_b2c_pop_shop shop LEFT JOIN sdb_b2c_pop_owner owner on shop.pop_owner_id=owner.pop_owner_id LEFT JOIN sdb_b2c_pop_wms wms on owner.pop_wms_id=wms.pop_wms_id';
        if ( ! empty($pars['filter']['shop_id_list'])) {
            $shop_ids_str = implode(',', $pars['filter']['shop_id_list']);
            $where        .= 'shop.pop_shop_id IN('.$shop_ids_str.')';
        }
        if ( ! empty($pars['filter']['pop_owner_id_list'])) {
            $pop_owner_ids_str = implode(',', $pars['filter']['pop_owner_id_list']);
            $where             .= 'shop.pop_owner_id IN('.$pop_owner_ids_str.')';
        }

        if (empty($where)) {
            return false;
        }

        $sql  .= ' where '.$where;
        $res  = $this->_db->select($sql);
        $list = json_decode(json_encode($res), true);

        return $list;
    }

    /**
     * @param array $pars
     * @param string $field
     *
     * @return array
     */
    public function GetAccountList(array $pars, $field = 'username, owner_id, shop_id, sup_name') {
        $pars['filter'] = (isset($pars['filter']) && is_array($pars['filter'])) ? $pars['filter'] : [];

        $cat = $this->_db->table('shop_admin_members');


        if (isset($pars['filter']['shop_id'])) {
            if (is_numeric($pars['filter']['shop_id'])) {
                $cat = $cat->where('shop_id', $pars['filter']['shop_id']);
            } elseif (is_array($pars['filter']['shop_id'])) {
                $cat = $cat->whereIn('shop_id', $pars['filter']['shop_id']);
            }
        }

        if (isset($pars['filter']['owner_id'])) {
            if (is_numeric($pars['filter']['owner_id'])) {
                $cat = $cat->where('owner_id', $pars['filter']['owner_id']);
            } elseif (is_array($pars['filter']['owner_id'])) {
                $cat = $cat->whereIn('owner_id', $pars['filter']['owner_id']);
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


    /**
     * @param array $pars
     * @param string $field
     *
     * @return array
     */
    public function GetPopShopList(array $pars, $field = 'pop_shop_id shop_id, name, pop_owner_id owner_id, sup_name')
    {
        $pars['filter'] = (isset($pars['filter']) && is_array($pars['filter'])) ? $pars['filter'] : [];

        $cat = $this->_db->table('sdb_b2c_pop_shop');

        if (isset($pars['filter']['shop_id'])) {
            if (is_numeric($pars['filter']['shop_id'])) {
                $cat = $cat->where('pop_shop_id', $pars['filter']['shop_id']);
            } elseif (is_array($pars['filter']['shop_id'])) {
                $cat = $cat->whereIn('pop_shop_id', $pars['filter']['shop_id']);
            }
        }

        if (isset($pars['filter']['owner_id'])) {
            if (is_numeric($pars['filter']['owner_id'])) {
                $cat = $cat->where('pop_owner_id', $pars['filter']['owner_id']);
            } elseif (is_array($pars['filter']['owner_id'])) {
                $cat = $cat->whereIn('pop_owner_id', $pars['filter']['owner_id']);
            }
        }

        if ( ! empty($pars['filter']['name'])) {
            $cat = $cat->where('name', 'like', "%{$pars['filter']['name']}%");
        }

        if ( ! empty($pars['filter']['sup_name'])) {
            $cat = $cat->where('sup_name', 'like', "%{$pars['filter']['sup_name']}%");
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
}
