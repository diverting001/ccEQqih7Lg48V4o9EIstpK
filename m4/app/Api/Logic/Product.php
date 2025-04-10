<?php

namespace App\Api\Logic;

use App\Api\Logic\Goods as GoodsLogic;

class Product
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
//        LEFT JOIN sdb_image_image image ON goods.image_default_id=image.image_id
        $where = '';
        $sql = 'select products.product_id,products.bn as product_bn,products.goods_id,goods.bn as goods_bn,products.name,products.weight,products.unit,products.goods_type,products.spec_info,products.spec_desc,products.is_default,products.intro,products.marketable,products.pop_shop_id as shop_id,products.invoice_type,products.invoice_tax,products.invoice_tax_code,goods.spec_desc as goods_spec_desc,goods.image_default_id,goods.source from sdb_b2c_products products LEFT JOIN sdb_b2c_goods goods on products.goods_id=goods.goods_id';
        if (isset($pars['filter']['goods_bn'])) {
            $bn_str = '\'' . implode('\',\'', $pars['filter']['goods_bn']) . '\'';
            $sql_goods = 'select goods_id from sdb_b2c_goods where bn in(' . $bn_str . ')';
            $res = $this->_db->select($sql_goods);
            $good_list = json_decode(json_encode($res), true);
            foreach ($good_list as $item) {
                $pars['filter']['goods_id'][] = $item['goods_id'];
            }
        }
        if (isset($pars['filter']['goods_id'])) {
            $goods_ids_str = implode(',', $pars['filter']['goods_id']);
            $where .= 'products.goods_id IN(' . $goods_ids_str . ')';
        }
        if (isset($pars['filter']['product_id'])) {
            $product_ids_str = implode(',', $pars['filter']['product_id']);
            $where .= 'products.product_id IN(' . $product_ids_str . ')';
        }
        if (isset($pars['filter']['product_bn'])) {
            $bn_str = '\'' . implode('\',\'', $pars['filter']['product_bn']) . '\'';
            $where .= 'products.bn IN(' . $bn_str . ')';
        }
        if (isset($pars['filter']['marketable'])) {
            if (!empty($where)) {
                $where .= ' AND ';
            }
            $where .= 'products.marketable=\'' . $pars['filter']['marketable'] . '\'';
        }
        if (empty($where)) {
            return false;
        }
        $sql .= ' where ' . $where;
        if (isset($pars['start']) && isset($pars['limit'])) {
            $sql .= ' limit ' . $pars['start'] . ',' . $pars['limit'];
        }

        $res = $this->_db->select($sql);
        $product_list = json_decode(json_encode($res), true);
        $goods_logic = new GoodsLogic;
        $goods_ids_str = '';
        foreach ($product_list as &$product) {
            $goods_logic->fixImg($product);
            $goods_ids_str .= $product['goods_id'] . ',';
        }
        $goods_ids_str = trim($goods_ids_str, ',');
        $this->initSpec($product_list);
        $this->initImg($product_list, $goods_ids_str);


        $return_data['list'] = $product_list;
        $return_data['count'] = $this->GetCount($where);
        return $return_data;
    }

    /*
     * 查询商品数量
     */
    public function GetCount($where)
    {
        $sql = 'select count(*) as count from sdb_b2c_products products where ' . $where;
        $res = $this->_db->select($sql);
        $res = json_decode(json_encode($res), true);
        return $res[0]['count'];
    }

    /*
     * 查询商品
     */
    public function Get($pars)
    {
        $where = '';
        if (isset($pars['filter']['product_id'])) {
            if (!empty($where)) {
                $where .= ' AND ';
            }
            $where .= 'products.product_id=' . $pars['filter']['product_id'];
        } elseif (isset($pars['filter']['product_bn'])) {
            if (!empty($where)) {
                $where .= ' AND ';
            }
            $where .= 'products.bn=\'' . $pars['filter']['product_bn'] . '\'';
        }
        if ($where == '') {
            return [];
        }
        $sql = 'select products.product_id,products.bn as product_bn,products.goods_id,goods.bn as goods_bn,products.name,products.weight,products.unit,products.goods_type,products.is_default,products.marketable as product_marketable,goods.marketable as goods_marketable,products.disabled as product_disabled,products.pop_shop_id as shop_id,products.invoice_type,products.invoice_tax,products.invoice_tax_code,products.taxfees,products.cost,goods.image_default_id,goods.intro,products.spec_desc,goods.source,goods.`spec_desc` AS goods_spec_desc from sdb_b2c_products products LEFT JOIN sdb_b2c_goods goods on products.goods_id=goods.goods_id where ' . $where . ' limit 1';
        $res = $this->_db->select($sql);
        $return_data = json_decode(json_encode($res), true);
        if (empty($return_data)) {
            return [];
        }
        $goods_logic = new GoodsLogic;
        $return_data = $return_data[0];
        $return_data['specs'] = [];
        if (!empty($return_data['spec_desc'])) {
            $return_data['spec_desc'] = unserialize($return_data['spec_desc']);
            $return_data['specs'] = $goods_logic->GetSpecList(array_values($return_data['spec_desc']['spec_value_id']));
        }

        $return_data['images'] = $goods_logic->GetGoodsImgs($return_data['goods_id'], $return_data['image_default_id']);
        unset($return_data['image_default_id']);

        //货品规格
        $return_data['product_images'] = [];
        if ($return_data['goods_spec_desc'] && $return_data['spec_desc']['spec_private_value_id']) {

            $goods_spec_desc = $this->getGoodsSpecDesc(unserialize($return_data['goods_spec_desc']));

            //货品图片image_id
            $productSpecImages = array();
            $default_product_spec = current($return_data['spec_desc']['spec_private_value_id']);

            if ($goods_spec_desc && isset($goods_spec_desc[$default_product_spec]) && isset($goods_spec_desc[$default_product_spec]['spec_goods_images']) && $goods_spec_desc[$default_product_spec]['spec_goods_images']) {
                $productSpecImages = array_filter(explode(',', $goods_spec_desc[$default_product_spec]['spec_goods_images']));
            }

            //获取图片
            if ($productSpecImages) {
                $return_data['product_images'] = $this->getImagesList($goods_logic, $productSpecImages);
            }
        }
        unset($return_data['spec_desc']);

        // 货品上下架设置
        if ($return_data['product_marketable'] == 'true' && $return_data['goods_marketable'] == 'true' && $return_data['product_disabled'] == 'false') {
            $return_data['marketable'] = 'true';
        } else {
            $return_data['marketable'] = 'false';
        }
        unset($return_data['product_marketable']);
        unset($return_data['goods_marketable']);
        unset($return_data['product_disabled']);

        return $return_data;
    }

    public function getGoodsSpecDesc($goods_spec_desc_data)
    {

        $goods_spec_desc = array();

        foreach ($goods_spec_desc_data as $goods_spec) {
            foreach ($goods_spec as $spec_key => $spec_item) {
                if ($spec_item['spec_goods_images']) {
                    $goods_spec_desc[$spec_key] = $spec_item;
                }
            }
        }

        return $goods_spec_desc;
    }

    public function getImagesList(&$goods_logic, $image_ids = '')
    {
        $return = [];
        if (!$image_ids) {
            return $return;
        }

        $images_data_obj = $this->_db->table('sdb_image_image')->select('l_url', 'm_url', 's_url')->whereIn('image_id', $image_ids)->get()->toArray();
        foreach ($images_data_obj as $key => $obj) {
            $image_arr = get_object_vars($obj);
            $goods_logic->fixImg($image_arr);
            $return[] = $image_arr;
        }

        return $return;
    }

    public function initSpec(&$product_list)
    {
        $goods_logic = new GoodsLogic;
        $spec_value_ids = [];
        foreach ($product_list as &$product) {
            if (!empty($product['spec_desc'])) {
                $product['spec_desc'] = unserialize($product['spec_desc']);
                $spec_value_ids = array_merge($spec_value_ids, array_values($product['spec_desc']['spec_value_id']));
            }
        }
        $specs = $goods_logic->GetSpecList($spec_value_ids);
        foreach ($product_list as &$product) {
            $product_spec_value_ids = array_values($product['spec_desc']['spec_value_id']);
            foreach ($specs as $spec) {
                if (in_array($spec['spec_value_id'], $product_spec_value_ids)) {
                    $product['specs'][] = $spec;
                }
            }
            unset($product['spec_desc']);
        }
    }

    private function initImg(&$product_list, $goods_ids_str)
    {
        $goods_logic = new GoodsLogic;
        $image_list = $goods_logic->getMultiGoodsImgList($goods_ids_str);
        $goods_id_2_image_list = [];
        foreach ($image_list as $image) {
            $image['is_default'] = 1;
            $goods_id = $image['goods_id'];
            $image_id = $image['image_id'];
            unset($image['goods_id']);
            unset($image['image_id']);
            $goods_id_2_image_list[$goods_id][$image_id] = $image;
        }

        foreach ($product_list as &$product) {
            if (!empty($product['specs']) && !empty($product['goods_spec_desc'])) {
                $spec_value_id_2_spec_goods_image = $this->get_spec_img_mapping($product['goods_spec_desc']);
                foreach ($product['specs'] as $spec) {
                    if (!empty($spec_value_id_2_spec_goods_image[$spec['spec_value_id']])) {
                        $product['image_default_id'] = $spec_value_id_2_spec_goods_image[$spec['spec_value_id']];
                        break;
                    }
                }
            }
            if (!empty($product['image_default_id'])) {
                $product['images'][] = $goods_id_2_image_list[$product['goods_id']][$product['image_default_id']];
            }
            unset($product['goods_spec_desc']);
            unset($product['image_default_id']);
        }
    }

    private function get_spec_img_mapping($goods_spec_desc)
    {
        $goods_spec_desc = unserialize($goods_spec_desc);
        $spec_value_id_2_spec_goods_image = [];
        foreach ($goods_spec_desc as $spec_value_list) {
            foreach ($spec_value_list as $spec_value_item) {
                if (!empty($spec_value_item['spec_goods_images'])) {
                    $spec_value_id_2_spec_goods_image[$spec_value_item['spec_value_id']] = current(explode(',',
                        $spec_value_item['spec_goods_images']));
                }
            }
        }
        return $spec_value_id_2_spec_goods_image;
    }
}
