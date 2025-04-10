<?php

namespace App\Api\Logic;

use App\Api\Logic\Product as ProductLogic;
use Illuminate\Database\Connection;

class Goods
{
    /**
     * @var Connection $_db
     */
    private $_db;
    private $_db_server;

    public function __construct()
    {
        $this->_db        = app('db')->connection('neigou_store');
        $this->_db_server = app('api_db');
    }

    /*
     * 查询商品
     */
    public function GetList($pars)
    {
        $where = '';
        $sql_fields_list = 'goods.goods_id,goods.bn as goods_bn,goods.name,goods.cat_id,goods.brand_id,goods.marketable,goods.cost,goods.mktprice,goods.point_price,goods.weight,goods.unit,goods.goods_type,goods.goods_bonded_type,goods.image_default_id,goods.intro,goods.intro_more,image.l_url,image.m_url,image.s_url,image.url';
        $sql = 'select %s from sdb_b2c_goods goods LEFT JOIN sdb_image_image image ON goods.image_default_id=image.image_id';
        if (isset($pars['filter']['shop_id'])) {
            if (!empty($where)) {
                $where .= ' AND ';
            }
            if (is_array($pars['filter']['shop_id'])) {
                $where .= 'products.pop_shop_id in(' . (implode(',', $pars['filter']['shop_id'])). ')';
            } else {
                $where .= 'products.pop_shop_id=' . $pars['filter']['shop_id'];
            }
            $sql .= ' LEFT JOIN sdb_b2c_products products ON products.goods_id = goods.goods_id LEFT JOIN sdb_b2c_pop_shop shop ON shop.pop_shop_id = products.pop_shop_id';
        }
        if (isset($pars['filter']['mall_id'])) {
            if (!empty($where)) {
                $where .= ' AND ';
            }
            if (is_array($pars['filter']['mall_id'])) {
                $where .= 'mall_goods.mall_id in(' . (implode(',', $pars['filter']['mall_id'])). ')';
            } else {
                $where .= 'mall_goods.mall_id=' . $pars['filter']['mall_id'];
            }
            $sql .= ' LEFT JOIN mall_module_mall_goods mall_goods ON goods.goods_id = mall_goods.goods_id';
        }
        if (isset($pars['filter']['goods_bn'])) {
            if (!empty($where)) {
                $where .= ' AND ';
            }
            $bn_str = '\'' . implode('\',\'', $pars['filter']['goods_bn']) . '\'';
            $where .= 'goods.bn IN(' . $bn_str . ')';
        }
        if (isset($pars['filter']['marketable'])) {
            if (!empty($where)) {
                $where .= ' AND ';
            }
            $where .= 'goods.marketable=\'' . $pars['filter']['marketable'] . '\'';
        }
        if (isset($pars['filter']['cat_id'])) {
            if (!empty($where)) {
                $where .= ' AND ';
            }
            $where .= 'goods.cat_id=' . $pars['filter']['cat_id'];
        }
        if (isset($pars['filter']['brand_id'])) {
            if (!empty($where)) {
                $where .= ' AND ';
            }
            $where .= 'goods.brand_id=' . $pars['filter']['brand_id'];
        }

        if (empty($where)) {
            return false;
        }
        $sql .= ' where ' . $where;
        $sql_group = ' group by goods.goods_id ';
        $sql_order = '';
        //排序
        if (isset($pars['order']) && ! empty($pars['order'])) {
            $orderByList = array();
            foreach ($pars['order'] as $field => $orderBy) {
                if (empty($orderBy['by']) OR ! in_array($orderBy['by'], array('asc', 'desc'))) {
                    continue;
                }
                if ($field == 'goods_id') {
                    $orderByList[] = 'goods.goods_id '. $orderBy['by'];
                }
                if ($field == 'last_modify') {
                    $orderByList[] = 'goods.last_modify '. $orderBy['by'];
                }
            }
            if ( ! empty($orderByList)) {
                $sql_order = ' order by '. (implode(',', $orderByList));
            }
        }
        $sql_limit = '';
        if (isset($pars['start']) && isset($pars['limit'])) {
            $sql_limit = ' limit ' . $pars['start'] . ',' . $pars['limit'];
        }


        $sql_list = sprintf($sql. $sql_group. $sql_order. $sql_limit, $sql_fields_list);
        $res = $this->_db->select($sql_list);
        $goods_list = json_decode(json_encode($res), true);

        $goods_ids = array();
        foreach ($goods_list as $goods) {
            $goods_ids[] = $goods['goods_id'];
        }
        $product_list = $this->GetProductList($goods_ids);
        $goods_product_list = array();
        foreach ($product_list as $item) {
            $goods_product_list[$item['goods_id']]['product_bn_list'][] = $item['product_bn'];
            if ($item['is_default'] === 'true') {
                $goods_product_list[$item['goods_id']]['product_bn'] = $item['product_bn'];
                $goods_product_list[$item['goods_id']]['product_id'] = $item['product_id'];
            }
        }
        foreach ($goods_list as &$goods) {
            if (!isset($goods_product_list[$goods['goods_id']])) {
                $goods_product_list[$goods['goods_id']] = array(
                    'product_id' => '',
                    'product_bn' => '',
                    'product_bn_list' => array(),
                );
            }
            $goods = array_merge($goods, $goods_product_list[$goods['goods_id']]);
            $this->fixImg($goods);
        }

        $return_data['list'] = $goods_list;
        $return_data['count'] = $this->GetCount($sql);
        return $return_data;
    }

    /*
     * 查询商品数量
     */
    private function GetCount($sql)
    {
        $sql_count = sprintf($sql, 'count(distinct(goods.goods_id)) as count');
        $res       = $this->_db->select($sql_count);
        $res       = json_decode(json_encode($res), true);

        return $res[0]['count'];
    }

    public function GetMallIdsByProjectCode($project_code)
    {
        $res = $this->_db_server->select("select mall_id from server_project_mall where project_code='$project_code'");
        $res = json_decode(json_encode($res), true);
        if (empty($res)) {
            return null;
        }

        return explode(',', $res[0]['mall_id']);
    }

    /*
     * 查询商品
     */
    public function Get($pars)
    {
        $where = ' where 1 ';
        $sql   = 'SELECT goods.goods_id,goods.bn as goods_bn,goods.name,goods.cat_id,goods.mall_goods_cat,goods.brand_id,goods.marketable,goods.cost,goods.mktprice,goods.point_price,goods.weight,goods.unit,goods.goods_type,goods.goods_bonded_type,goods.invoice_type,goods.invoice_tax,goods.invoice_tax_code,goods.image_default_id,goods.intro,goods.intro_more,goods.spec_desc,goods.ziti,goods.source,delivery.delivery_bn FROM sdb_b2c_goods goods
                    LEFT JOIN sdb_b2c_goods_delivery delivery ON goods.bn = delivery.goods_bn';
        if (isset($pars['filter']['goods_id'])) {
            $where .= ' AND goods.goods_id='.$pars['filter']['goods_id'];
        } elseif (isset($pars['filter']['goods_bn'])) {
            $where .= ' AND goods.bn=\''.$pars['filter']['goods_bn'].'\'';
        }
        if (isset($pars['filter']['mall_id'])) {
            $where .= ' AND mall_goods.mall_id in('.implode($pars['filter']['mall_id']).')';
            $sql   .= ' LEFT JOIN mall_module_mall_goods mall_goods ON goods.goods_id = mall_goods.goods_id';
        }
        $sql         .= $where.' limit 1';
        $res         = $this->_db->select($sql);
        $return_data = json_decode(json_encode($res), true);

        if ($return_data) {
            $return_data          = $return_data[0];
            $return_data['specs'] = $this->GetSpecListByDesc($return_data['spec_desc']);
            $spec_goods_images = $this->GetSpecGoodsImages($return_data['spec_desc']);
            unset($return_data['spec_desc']);
            $return_data['images'] = $this->GetGoodsImgs($return_data['goods_id'], $return_data['image_default_id']);
            unset($return_data['image_default_id']);

            $return_data['product_list'] = $this->GetProductList([$return_data['goods_id']],
                $pars['filter']['product_disabled']);

            $spec_product_list = array();
            foreach ( $return_data['product_list'] as &$product_item ) {
                if ( ! empty($product_item['specs'])){
                    foreach ( $product_item['specs'] as $spec ) {
                        $spec_product_list[$spec['spec_id']][$spec['spec_value_id']][] = $product_item['product_bn'];
                    }
                }
                $product_item['images'] = $spec_goods_images[$product_item['spec_private_value_id']];
                unset($product_item['spec_private_value_id']);
            }

            if ( ! empty($return_data['specs'])) {
                foreach ($return_data['specs'] as & $specInfo) {
                    foreach ($specInfo['spec_values'] as & $spec_value) {
                        if ( ! empty($spec_product_list[$specInfo['spec_id']]) && ! empty($spec_product_list[$specInfo['spec_id']][$spec_value['spec_value_id']])) {
                            $spec_value['product_bn_list'] = $spec_product_list[$specInfo['spec_id']][$spec_value['spec_value_id']];
                        }
                    }
                }
            }

            $product_count = count($return_data['product_list']);
            for ($i = 0; $i < $product_count; $i++) {
                if ( ! empty($return_data['specs']) && empty($return_data['product_list'][$i]['specs'])) {
                    unset($return_data['product_list'][$i]);
                    $product_count = count($return_data['product_list']);
                }
            }

//            foreach ($return_data['product_list'] as &$product) {
//                $product['spec_desc'] = unserialize($product['spec_desc']);
//                foreach ($product['spec_desc']['spec_value_id'] as $spec_id => $spec_value_id) {
//                    $product['specs'][] = [
//                        'spec_value_id' => $spec_value_id,
//                        'spec_value' => $product['spec_desc']['spec_value'][$spec_id],
//                        'spec_id' => $spec_id,
//                    ];
//                }
//                unset($product['spec_desc']);
//                unset($product['goods_id']);
//            }

        }

        return $return_data;
    }

    public function GetGoodsImgs($goods_id, $image_default_id = null)
    {
        $sql    = 'SELECT image . image_id,image . l_url,image . m_url,image . s_url,image.url from sdb_image_image_attach attach LEFT JOIN sdb_image_image image ON attach . image_id = image . image_id where attach . target_id = '.$goods_id.' and attach . target_type = \'goods\'';
        $res    = $this->_db->select($sql);
        $images = json_decode(json_encode($res), true);
        foreach ($images as &$image) {
            $image['is_default'] = $image['image_id'] === $image_default_id ? 1 : 0;
            $this->fixImg($image);
            unset($image['image_id']);
        }

        return $images;
    }

    public function GetImages($images_ids = [])
    {
        $images_ids = "'".implode("','", $images_ids)."'";
        $sql    = 'SELECT image_id,l_url,m_url,s_url,url from sdb_image_image image where image_id IN ('.$images_ids.')';
        $res    = $this->_db->select($sql);
        $images = json_decode(json_encode($res), true);

        $return_images = array();
        foreach ($images as &$image) {
            $this->fixImg($image);
            $image_id = $image['image_id'];
            unset($image['image_id']);
            $return_images[$image_id] = $image;
        }

        return $return_images;
    }

    public function getMultiGoodsImgList($goods_ids)
    {
        if (empty($goods_ids)) {
            return null;
        }
        $sql    = 'SELECT attach.target_id as goods_id,image.image_id,image.l_url,image.m_url,image.s_url,image.url from sdb_image_image_attach attach LEFT JOIN sdb_image_image image ON attach.image_id=image.image_id where attach.target_id in('.$goods_ids.') and attach.target_type=\'goods\'';
        $res    = $this->_db->select($sql);
        $images = json_decode(json_encode($res), true);
        foreach ($images as &$image) {
            $this->fixImg($image);
        }

        return $images;

    }

    public function GetProductInfo($productBn = '', $disabled = '')
    {
        if (empty($productBn)) {
            return [];
        }
        $sql           = 'SELECT goods_id,product_id,bn as product_bn,is_default,marketable,disabled,spec_desc,pop_shop_id,price,mktprice,name from sdb_b2c_products where bn="'.$productBn.'"';
        if ( ! empty($disabled)) {
            $sql .= " and disabled='{$disabled}'";
        }
        $res           = $this->_db->select($sql);
        $product_list  = json_decode(json_encode($res), true);
        return $product_list;
    }

    public function GetProductList($goods_ids, $disabled = '')
    {
        if (empty($goods_ids)) {
            return [];
        }
        $goods_ids_str = implode(',', $goods_ids);
        $sql           = 'SELECT goods_id,product_id,bn as product_bn,is_default,marketable,disabled,spec_desc,pop_shop_id,price,mktprice from sdb_b2c_products where goods_id in('.$goods_ids_str.')';
        if ( ! empty($disabled)) {
            $sql .= " and disabled='{$disabled}'";
        }
        $res           = $this->_db->select($sql);
        $product_list  = json_decode(json_encode($res), true);
        $product_logic = new ProductLogic();
        $product_logic->initSpec($product_list);
        foreach ($product_list as &$product) {
            if ($product['marketable'] === 'true' && $product['disabled'] === 'true') {
                $product['marketable'] = 'false';
            }
        }
        return $product_list;
    }

    public function GetSpecList($spec_value_ids)
    {
        $spec_value_ids = array_filter($spec_value_ids);
        if (empty($spec_value_ids)) {
            return [];
        }
        $spec_value_ids_str = implode(',', $spec_value_ids);
        $sql                = 'SELECT spec_values.spec_value_id,spec_values.spec_value,spec.spec_id,spec.spec_name,spec.spec_show_type from sdb_b2c_spec_values spec_values LEFT JOIN sdb_b2c_specification spec ON spec_values.spec_id=spec.spec_id where spec_values.spec_value_id in('.$spec_value_ids_str.')';
        $res                = $this->_db->select($sql);
        $list               = json_decode(json_encode($res), true);

        return $list;
    }

    public function GetSpecListByDesc($spec_desc)
    {
        $return = array();

        $spec_desc  = $spec_desc ? unserialize($spec_desc) : array();
        if (empty($spec_desc)) {
            return $return;
        }
        $spec_values = [];
        foreach ($spec_desc as $spec_id => $spec_value_list) {
            foreach ($spec_value_list as $spec_value_item) {
                $spec_values[] = $spec_value_item['spec_value_id'];
            }
        }
        $result = $this->GetSpecList($spec_values);
        if (empty($result)) {
            return $return;
        }

        $spec_list = array();
        $spec_value_list = array();
        foreach ($result as $item) {
            if ( ! isset($spec_list[$item['spec_id']])) {
                $spec_list[$item['spec_id']] = array(
                    'spec_id' => $item['spec_id'],
                    'spec_name' => $item['spec_name'],
                    'spec_show_type' => $item['spec_show_type'],
                );
            }
            $spec_value_list[$item['spec_value_id']] = $item;
        }
        $count = 0;
        foreach ($spec_desc as $spec_id => $value_list)
        {
            if ( ! isset($spec_list[$spec_id])) {
                continue;
            }
            $return[$count] = array(
                'spec_id' => $spec_list[$spec_id]['spec_id'],
                'spec_name' => $spec_list[$spec_id]['spec_name'],
                'spec_show_type' => $spec_list[$spec_id]['spec_show_type'],
                'spec_values' => array(),
            );
            foreach ($value_list as $value_item) {
                if ( ! isset($spec_value_list[$value_item['spec_value_id']])) {
                    continue;
                }
                $return[$count]['spec_values'][] = array(
                    'spec_value_id' => $value_item['spec_value_id'],
                    'spec_value' => $spec_value_list[$value_item['spec_value_id']]['spec_value'],
                );
            }
            if (empty($return[$count]['spec_values'])){
                unset($return[$count]);
                continue;
            }
            $count++;
        }

        return $return;
    }

    /**
     * 获取规格图片
     * @param $spec_desc
     * @return array
     */
    public function GetSpecGoodsImages( $spec_desc )
    {
        $spec_types = unserialize( $spec_desc );
        $spec_goods_images_values = [];
        foreach ( $spec_types as $spec_type => $spec_value_list )
        {
            foreach ( $spec_value_list as $spec_value_item )
            {
                $spec_goods_images_values = array_merge( $spec_goods_images_values, explode( ',', $spec_value_item['spec_goods_images'] ) );
            }
        }

        $images_data = $this->GetImages( array_unique( $spec_goods_images_values ) );


        $return_spec_images = [];
        foreach ( $spec_types as $spec_type => &$spec_value_list )
        {
            foreach ( $spec_value_list as &$spec_value_item )
            {

                $spec_goods_images = explode( ',', $spec_value_item['spec_goods_images'] );
                $spec_images = [];
                $default = true;
                foreach ( $spec_goods_images as $image_id )
                {
                    $spec_image_data = $images_data[$image_id];
                    $spec_image_data['is_default'] = $default ? 1 : 0;
                    $default = false;
                    $spec_images[] = $spec_image_data;
                }

                $return_spec_images[$spec_value_item['private_spec_value_id']] = $spec_images;
            }
        }

        return $return_spec_images;
    }

    public function fixIntro($info)
    {
        // 商品详情处理
        $info['intro'] = str_replace('<img src="http://www.neigou.com', '<img src="http:'.config('neigou.CDN_BASE_URL_NO_SCHEME'),
            $info['intro']);
        $info['intro'] = str_replace('http://salyut.neigou.com', config('neigou.SALYUT_CDN'), $info['intro']);
    }

    public function fixImg(&$obj)
    {
        //图片处理
        if ( ! empty($obj['l_url'])) {
            $obj['l_url'] = strstr($obj['l_url'],
                'http') ? $obj['l_url'] : 'http:'.config('neigou.CDN_BASE_URL_NO_SCHEME').'/'.$obj['l_url'];
        }
        if ( ! empty($obj['m_url'])) {
            $obj['m_url'] = strstr($obj['m_url'],
                'http') ? $obj['m_url'] : 'http:'.config('neigou.CDN_BASE_URL_NO_SCHEME').'/'.$obj['m_url'];
        }
        if ( ! empty($obj['s_url'])) {
            $obj['s_url'] = strstr($obj['s_url'],
                'http') ? $obj['s_url'] : 'http:'.config('neigou.CDN_BASE_URL_NO_SCHEME').'/'.$obj['s_url'];
        }
        if ( ! empty($obj['url'])) {
            $obj['url'] = strstr($obj['url'],
                'http') ? $obj['url'] : 'http:'.config('neigou.CDN_BASE_URL_NO_SCHEME').'/'.$obj['url'];
        }
    }

    /**
     * @param int $parentId
     *
     * @return mixed
     */
    public function GetCatListByParentId($parentId = 0)
    {
        return $this->_db->table('sdb_b2c_goods_cat')->where(['parent_id' => $parentId])->get();
    }

    /**
     * @param int $parentId
     *
     * @return mixed
     */
    public function getMallCatListByParentId($parentId = 0)
    {
        return $this->_db->table('sdb_b2c_mall_goods_cat')->where(['parent_id' => $parentId])->get();
    }

    /**
     * @param $keyword
     *
     * @return mixed
     */
    public function getBrandList($keyword)
    {
        $where = [];

        if ($keyword) {
            $where[] = ['brand_name', 'like', "%{$keyword}%"];
        }
        $brandModel      = $this->_db->table('sdb_b2c_brand');
        $result['list']  = $brandModel->where($where)->get();
        $result['count'] = $brandModel->where($where)->count();

        return $result;
    }

    /**
     * @param $goodsBn
     * @param $data
     *
     * @return bool
     */
    public function updateByGoodsBn($goodsBn, $data)
    {
        if ( ! $goodsBn || ! $data || ! is_array($data)) {
            return false;
        }

        if ( ! is_array($goodsBn)) {
            $goodsBn = [$goodsBn];
        }

        return $this->_db->table('sdb_b2c_goods')->whereIn('bn', $goodsBn)->update($data);
    }

    /**
     * @param $goodsList
     *
     * @return array|bool
     */
    public function UpdateList($goodsList)
    {
        if ( ! $goodsList || ! is_array($goodsList)) {
            return false;
        }

        $data = [];

        $this->_db->beginTransaction();

        foreach ($goodsList as $item) {
            if ( ! $item['goods_bn'] || ! $item['data'] || ! is_array($item['data'])) {
                $data[$item['goods_bn']] = false;

                continue;
            }

            $upRes = $this->updateByGoodsBn($item['goods_bn'], $item['data']);

            $data[$item['goods_bn']] = $upRes === false ? false : true;
        }

        $this->_db->commit();

        return $data;
    }

    /**
     *  商品列表
     *
     * @param array $filter
     * @param int $start
     * @param int $limit
     * @param string $sql_fields_list
     *
     * @return mixed
     */
    public function GetGoodsList(array $filter, $start = 0, $limit = 20, $sql_fields_list = '')
    {
        $where = '';

        if ( ! $sql_fields_list) {
            $sql_fields_list = 'ori_brand.ori_brand_name,goods.goods_id,goods.bn as goods_bn,goods.name,goods.cat_id,goods.mall_goods_cat,goods.brand_id,goods.marketable,goods.cost,goods.price,goods.mktprice,goods.point_price,goods.weight,goods.unit,goods.goods_type,goods.goods_bonded_type,goods.image_default_id,goods.intro,goods.intro_more,goods.last_modify';
        }

        $sql = 'select %s from sdb_b2c_goods goods
    LEFT JOIN sdb_b2c_goods_ext ext_ori_brand ON ext_ori_brand.goods_bn=goods.bn AND ext_ori_brand.key=\'ori_brand_id\'
    LEFT JOIN sdb_b2c_ori_brand ori_brand ON ori_brand.ori_brand_id=ext_ori_brand.val ';
        if (isset($filter['shop_id']) || isset($filter['product_bn']) || isset($filter['ori_brand_id'])) {
            $goodsIdField = 'DISTINCT(goods.goods_id)';
        } else {
            $goodsIdField = 'goods.goods_id';
        }

        $goodsIdSql = "SELECT $goodsIdField goods_id FROM sdb_b2c_goods goods  ";
        $sqlCount   = "SELECT count($goodsIdField) goods_count FROM sdb_b2c_goods goods  ";

        if (isset($filter['shop_id'])) {
            if ( ! empty($where)) {
                $where .= ' AND ';
            }
            if (is_array($filter['shop_id'])) {
                $where .= 'products.pop_shop_id in('.implode(',', $filter['shop_id']).')';
            } else {
                $where .= 'products.pop_shop_id='.$filter['shop_id'];
            }

        }

        // 增加sdb_b2c_products连表
        if (isset($filter['shop_id']) || isset($filter['product_bn'])) {
            $goodsIdSql .= ' LEFT JOIN sdb_b2c_products products ON products.goods_id=goods.goods_id ';
            $sqlCount   .= ' LEFT JOIN sdb_b2c_products products ON products.goods_id=goods.goods_id ';
        }
        // 增加sdb_b2c_goods_ext连表
        if (isset($filter['ori_brand_id'])) {
            $goodsIdSql .= ' LEFT JOIN sdb_b2c_goods_ext ext_ori_brand ON ext_ori_brand.goods_bn=goods.bn AND ext_ori_brand.key=\'ori_brand_id\' ';
            $sqlCount .= ' LEFT JOIN sdb_b2c_goods_ext ext_ori_brand ON ext_ori_brand.goods_bn=goods.bn AND ext_ori_brand.key=\'ori_brand_id\' ';
            if ( ! empty($where)) {
                $where .= ' AND ';
            }
            if ($filter['ori_brand_id'] != 0) {
                $where .= 'ext_ori_brand.val=\'' . $filter['ori_brand_id'] . '\'';
            } else {
                $where .= '(ext_ori_brand.val is null OR ext_ori_brand.val=\'\' OR ext_ori_brand.val=0)';
            }
        }
            if (isset($filter['goods_bn'])) {
            if ( ! empty($where)) {
                $where .= ' AND ';
            }
            $bn_str = '\''.implode('\',\'', $filter['goods_bn']).'\'';
            $where  .= 'goods.bn IN('.$bn_str.')';
        }

        // 增加sdb_b2c_products根据bn搜索
        if (isset($filter['product_bn']) && !empty($filter['product_bn'])){
            if (!empty($where)){
                $where .= ' AND ';
            }
            //$productBnStr = '\''.implode('\',\'', ).'\'';
            $where  .= 'products.bn="'.$filter['product_bn'].'"';
        }
        // 无法支持goods_name查询直接返回空
        if ( ! empty($filter['goods_name'])) {
            $return_data['list']  = array();
            $return_data['count'] = 0;
            return $return_data;
            if ( ! empty($where)) {
                $where .= ' AND ';
            }
            $where .= 'goods.name like \'%%'.$filter['goods_name'].'%%\'';
        }

        if (isset($filter['marketable'])) {
            if ( ! empty($where)) {
                $where .= ' AND ';
            }
            $where .= 'goods.marketable=\''.$filter['marketable'].'\'';
        }

        if (isset($filter['cat_id'])) {
            if ( ! empty($where)) {
                $where .= ' AND ';
            }
            if (is_array($filter['cat_id'])) {
                $where .= 'goods.cat_id in('.implode(',', $filter['cat_id']).')';
            } else {
                $where .= 'goods.cat_id='.$filter['cat_id'];
            }
        }

        if (array_key_exists('mall_goods_cat', $filter)) {
            if ( ! empty($where)) {
                $where .= ' AND ';
            }

            if (is_array($filter['mall_goods_cat'])) {
                $where .= 'goods.mall_goods_cat in('.implode(',', $filter['mall_goods_cat']).')';
            } else {
                $where .= 'goods.mall_goods_cat='.$filter['mall_goods_cat'];
            }
        }

        if (isset($filter['brand_id'])) {
            if ( ! empty($where)) {
                $where .= ' AND ';
            }
            if ($filter['brand_id'] > 0) {
                $where .= 'goods.brand_id=' . $filter['brand_id'];
            } else {
                $where .= '(goods.brand_id=0 or goods.brand_id is null)';
            }
        }

        if (isset($filter['price']) && isset($filter['price_conditions'])) {
            $mod = strtolower($filter['price_conditions']);
            if ($mod == 'lt') {
                if ( ! empty($where)) {
                    $where .= ' AND ';
                }
                $where .= 'goods.price<'.$filter['price'];
            } elseif ($mod == 'lte') {
                if ( ! empty($where)) {
                    $where .= ' AND ';
                }
                $where .= 'goods.price<='.$filter['price'];
            } elseif ($mod == 'eq') {
                if ( ! empty($where)) {
                    $where .= ' AND ';
                }
                $where .= 'goods.price='.$filter['price'];
            } elseif ($mod == 'gt') {
                if ( ! empty($where)) {
                    $where .= ' AND ';
                }
                $where .= 'goods.price>'.$filter['price'];
            } elseif ($mod == 'gte') {
                if ( ! empty($where)) {
                    $where .= ' AND ';
                }
                $where .= 'goods.price>='.$filter['price'];
            } elseif ($mod == 'between' && isset($filter['price'])) {
                if ( ! empty($where)) {
                    $where .= ' AND ';
                }
                $where .= 'goods.price>='.$filter['price'].' AND goods.price<='.$filter['end_price'];
            }
        }

        if (isset($filter['last_modify'])) {
            if (is_array($filter['last_modify'])) {
                $mod = strtolower(current($filter['last_modify']));

                $lastModify = end($filter['last_modify']);

                if ($mod == 'lt') {
                    if ( ! empty($where)) {
                        $where .= ' AND ';
                    }
                    $where .= 'goods.last_modify<'.$lastModify;
                } elseif ($mod == 'lte') {
                    if ( ! empty($where)) {
                        $where .= ' AND ';
                    }
                    $where .= 'goods.last_modify<='.$lastModify;
                } elseif ($mod == 'eq') {
                    if ( ! empty($where)) {
                        $where .= ' AND ';
                    }
                    $where .= 'goods.last_modify='.$lastModify;
                } elseif ($mod == 'gt') {
                    if ( ! empty($where)) {
                        $where .= ' AND ';
                    }
                    $where .= 'goods.last_modify>'.$lastModify;
                } elseif ($mod == 'gte') {
                    if ( ! empty($where)) {
                        $where .= ' AND ';
                    }
                    $where .= 'goods.last_modify>='.$lastModify;
                }
            } else {
                $where .= 'goods.last_modify='.$filter['last_modify'];
            }
        }

        if (empty($where)) {
            $where = 1;
        }

        $sqlCount .= ' where '.$where;

        $res = $this->_db->select($sqlCount);

        $res   = json_decode(json_encode($res), true);
        $count = 0;
        if (is_array($res)) {
            $goods_count_row = current($res);

            if (isset($goods_count_row['goods_count'])) {
                $count = $goods_count_row['goods_count'];
            }
        }

        $goodsIdSql .= ' where '.$where.' limit '.$start.','.$limit;

        $goodsIdList = $this->_db->select($goodsIdSql);

        if (is_array($goodsIdList) && count($goodsIdList)>0) {
            $goodsIdArr = [];

            foreach ($goodsIdList as $item) {
                $goodsIdArr[]=$item->goods_id;
            }

            $sql .= ' where goods.goods_id in('.implode(',', $goodsIdArr).') ';

            $sql = sprintf($sql, $sql_fields_list);

            $res        = $this->_db->select($sql);
            $goods_list = json_decode(json_encode($res), true);

            $goods_ids = array();
            foreach ($goods_list as $goods) {
                $goods_ids[] = $goods['goods_id'];
            }
            if (isset($filter['product_bn']) && !empty($filter['product_bn'])){
                $product_list = $this->GetProductInfo($filter['product_bn']);
            }else{
                $product_list = $this->GetProductList($goods_ids);
            }

            $goods_product_list = array();
            foreach ($product_list as $item) {
                $goods_product_list[$item['goods_id']]['product_bn_list'][] = $item['product_bn'];
                if ($item['is_default'] === 'true') {
                    $goods_product_list[$item['goods_id']]['product_bn'] = $item['product_bn'];
                    $goods_product_list[$item['goods_id']]['product_id'] = $item['product_id'];
                    $goods_product_list[$item['goods_id']]['product_name'] = $item['name'];
                    $goods_product_list[$item['goods_id']]['shop_id']    = $item['pop_shop_id'];
                    continue;
                }
                if (!isset($goods_product_list[$item['goods_id']]['product_id'])){
                    $goods_product_list[$item['goods_id']]['product_bn'] = $item['product_bn'];
                    $goods_product_list[$item['goods_id']]['product_id'] = $item['product_id'];
                    $goods_product_list[$item['goods_id']]['product_name'] = $item['name'];
                    $goods_product_list[$item['goods_id']]['shop_id']    = $item['pop_shop_id'];
                }
            }
            foreach ($goods_list as &$goods) {
                if ( ! isset($goods_product_list[$goods['goods_id']])) {
                    $goods_product_list[$goods['goods_id']] = array(
                        'product_id'      => '',
                        'product_bn'      => '',
                        'product_bn_list' => array(),
                    );
                }
                $goods = array_merge($goods, $goods_product_list[$goods['goods_id']]);
                $this->fixImg($goods);
            }

        }else{
            $goods_list = array();
        }

        $return_data['list']  = $goods_list;
        $return_data['count'] = $count;

        return $return_data;
    }

    public function SyncSwitch($params)
    {
        if (!isset($params['filter']['goods_bn'])) {
            return [false, 'filter 中必须包含 goods_bn'];
        }
        $goodsBns = $params['filter']['goods_bn'];
        if (!is_array($goodsBns)) {
            return [false, 'goods_bn 必须是数组'];
        }
        if (!isset($params['type'])) {
            return [false, 'type必填，1=允许同步，2=停止同步'];
        }
        $type = (string) $params['type'];
        if (!in_array($type, ['1', '2'], true)) {
            return [false, 'type值不正确'];
        }

        $goodsBnsCondition = implode('","', $goodsBns);
        $exists = $this->_db->select("select id,goods_bn from sdb_b2c_goods_sync_blacklist WHERE goods_bn IN(\"{$goodsBnsCondition}\")");
        $existBns = array_column($exists, 'goods_bn');

        if ($type === '2') {
            $insertBns = array_diff($goodsBns, $existBns);
            $success = 0;
            foreach ($insertBns as $bn) {
                $r = $this->_db->table('sdb_b2c_goods_sync_blacklist')->insert(['goods_bn' => $bn]);
                if ($r) {
                    $success++;
                }
            }
            return [compact('success'), false];
        } else {
            $deleteIds = array_column($exists, 'id');;
            $success = 0;
            foreach ($deleteIds as $id) {
                $r = $this->_db->table('sdb_b2c_goods_sync_blacklist')->delete($id);
                if ($r) {
                    $success++;
                }
            }
            return [compact('success'), false];
        }
    }

    public function IsExists($params)
    {
        if (!isset($params['filter']['goods_bn'])) {
            return [false, 'filter 中必须包含 goods_bn'];
        }
        $goodsBns = $params['filter']['goods_bn'];
        if (!is_array($goodsBns)) {
            return [false, 'goods_bn 必须是数组'];
        }
        $goodsBnsCondition = implode('","', $goodsBns);
        $exists = $this->_db->select("select bn from sdb_b2c_goods WHERE bn IN(\"{$goodsBnsCondition}\")");
        $bns = array_column($exists, 'bn');
        return [$bns, false];
    }

    public function getGoodsByProductBn($productBn)
    {
        $productCondition = implode('","', $productBn);

        $sql = "SELECT goods.bn AS goods_bn,product.bn AS product_bn,product.pop_shop_id AS shop_id FROM sdb_b2c_goods goods,sdb_b2c_products product WHERE goods.goods_id = product.goods_id AND product.bn IN(\"{$productCondition}\")";
        $res = $this->_db->select($sql);

        return json_decode(json_encode($res), true);
    }
}
