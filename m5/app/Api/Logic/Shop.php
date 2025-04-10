<?php

namespace App\Api\Logic;

use App\Api\Model\Shop\Shop as ShopModel;

class Shop
{
    private $_db;

    public function __construct()
    {
        $this->_db = app('db')->connection('neigou_store');
    }

    /*
     * 查询shop详情
     */
    public function Get($pars)
    {
        if (!$pars['shop_id']) {
            return false;
        }

        $sql = 'select shop.pop_shop_id as shop_id,shop.name as shop_name,shop.pop_owner_id,wms.pop_wms_id,wms.pop_wms_code,shop.ico from sdb_b2c_pop_shop shop LEFT JOIN sdb_b2c_pop_owner owner on shop.pop_owner_id=owner.pop_owner_id LEFT JOIN sdb_b2c_pop_wms wms on owner.pop_wms_id=wms.pop_wms_id where shop.pop_shop_id = :pop_shop_id';
        $res = $this->_db->selectOne($sql, ['pop_shop_id' => $pars['shop_id']]);
        return json_decode(json_encode($res), true);
    }

    /*
     * 查询商品
     */
    public function GetList($pars)
    {
        $where = '';
        $sql   = 'select shop.pop_shop_id as shop_id,shop.name as shop_name,shop.sup_name,shop.pop_owner_id,wms.pop_wms_id,wms.pop_wms_code from sdb_b2c_pop_shop shop LEFT JOIN sdb_b2c_pop_owner owner on shop.pop_owner_id=owner.pop_owner_id LEFT JOIN sdb_b2c_pop_wms wms on owner.pop_wms_id=wms.pop_wms_id';
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

        if (isset($pars['filter']['sup_name'])) {
            $cat = $cat->where('sup_name', 'like', '%' . $pars['filter']['sup_name'] . '%');
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
    public function GetPopShopList(array $pars, $field = 'pop_shop_id shop_id, name, pop_owner_id owner_id, sup_name, ico')
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

        if ( ! empty($pars['filter']['ico']) && $pars['filter']['ico'] == 'not_empty') {
            $cat = $cat->where('ico', '<>', "");
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

    public function SetExt(array $pars)
    {
        $cur_ext_list = $this->_db->table('sdb_b2c_pop_shop_ext')->where('pop_shop_id', $pars['shop_id'])->get()->toArray();
        $k_v_mapping = [];
        foreach ($cur_ext_list as $cur_ext_item) {
            $k_v_mapping[$cur_ext_item->key] = $cur_ext_item->val;
        }
        $res = true;
        app('db')->beginTransaction();
        foreach ($pars['ext'] as $ext) {
            if (isset($k_v_mapping[$ext['key']])) {
                $res = $this->_db->table('sdb_b2c_pop_shop_ext')
                    ->where([
                        'pop_shop_id' => $pars['shop_id'],
                        'key' => $ext['key'],
                    ])
                    ->update([
                        'val' => $ext['val'],
                        'update_time' => time(),
                    ]);
            } else {
                $res = $this->_db->table('sdb_b2c_pop_shop_ext')
                    ->insert([
                        'pop_shop_id' => $pars['shop_id'],
                        'key' => $ext['key'],
                        'val' => $ext['val'],
                        'create_time' => time(),
                    ]);
            }
            if (!$res) {
                app('db')->rollBack();
                return $res;
            }
        }
        app('db')->commit();
        return $res;
    }

    /**
     * 设置pop店铺配置
     * 注意：key相同，pop_shop_id不同
     *
     * @param  array $params
     * @return array
     */
    public function setPopShopExt(array $params)
    {
        $cur_ext_list = $this->_db->table('sdb_b2c_pop_shop_ext')->where('key', $params['key'])->get()->toArray();
        $k_v_mapping = [];
        foreach ($cur_ext_list as $cur_ext_item) {
            $k_v_mapping[$cur_ext_item->pop_shop_id] = $cur_ext_item;
        }

        $res = true;
        app('db')->beginTransaction();
        foreach ($params['ext'] as $ext) {
            if (!empty($k_v_mapping[$ext['shop_id']])) {
                $res = $this->_db->table('sdb_b2c_pop_shop_ext')
                    ->where([
                        'pop_shop_id' => $ext['shop_id'],
                        'key' => $params['key'],
                    ])
                    ->update([
                        'val' => $ext['val'],
                        'update_time' => time(),
                    ]);
            } else {
                $res = $this->_db->table('sdb_b2c_pop_shop_ext')
                    ->insert([
                        'pop_shop_id' => $ext['shop_id'],
                        'key' => $params['key'],
                        'val' => $ext['val'],
                        'create_time' => time(),
                    ]);
            }
            if (!$res) {
                app('db')->rollBack();
                return $res;
            }
        }
        app('db')->commit();
        return $res;
    }

    /**
     * 获取pop店铺供应商列表
     * @param array $params
     * @param string $field
     *
     * @return array
     */
    public function getSupplierShopList(array $params, $field = '')
    {
        $params['filter'] = (isset($params['filter']) && is_array($params['filter'])) ? $params['filter'] : [];

        if (empty($field)) {
            $field = 'ps.pop_shop_id as shop_id, ps.name, ps.sup_name, ps.pop_owner_id, ps.sup_company_name, ps.sup_company_code, ps.business_type, pw.pop_wms_code, pw.platform_name';
        }

        $shopModel = $this->_db->table('sdb_b2c_pop_shop as ps')
            ->leftJoin('sdb_b2c_pop_owner as po', 'ps.pop_owner_id', '=', 'po.pop_owner_id')
            ->leftJoin('sdb_b2c_pop_wms as pw', 'pw.pop_wms_id', '=', 'po.pop_wms_id');

        if (!empty($params['filter']['shop_id'])) {
            $shopModel = $shopModel->where('ps.pop_shop_id', 'like', '%' . $params['filter']['shop_id'] . '%');
        }

        if (!empty($params['filter']['sup_name'])) {
            $shopModel = $shopModel->where('ps.sup_name', 'like', '%' . $params['filter']['sup_name'] . '%');
        }

        if (!empty($params['filter']['search'])) {
            $shopModel = $shopModel->where('ps.sup_name', 'like', '%' . $params['filter']['search'] . '%');
            $shopModel = $shopModel->orWhere('ps.pop_shop_id', 'like', '%' . $params['filter']['search'] . '%');
        }

        $listData = [];

        if (!empty($params['page']) && $params['page'] > 0 && !empty($params['limit']) && $params['limit'] > 0) {
            $listCount = $shopModel->count();

            $offset = ($params['page'] - 1) * $params['limit'];

            $totalPage = ceil($listCount / $params['limit']);

            $shopModel = $shopModel->offset($offset)->limit($params['limit']);
            $listData = ['page' => $params['page'], 'totalCount' => $listCount, 'totalPage' => $totalPage];
        }

        $data = $shopModel->select($this->_db->raw($field))->orderBy('ps.pop_shop_id', 'desc')->get()->toArray();

        $listData['list'] = $data;

        return $listData;
    }

    /**
     * 设置pop店铺信息
     * @param array $params
     * @return bool
     */
    public function setPopShopInfo(array $params){
        $shopModel = new ShopModel();
        $result = $shopModel->updatePopShopInfo($params['pop_shop_id'], $params['data']);
        if($result === false){
            return false;
        }
        return true;
    }
}
