<?php
/**
 * 公用商品信息
 * @version 0.1
 * @package ectools.lib.api
 */

namespace App\Api\V1\Service\Search;


class Keyword
{
    const TYPE_CATGORY = 1;
    const TYPE_BRAND = 2;
    const TYPE_SPECIAL_WORD = 4;

    private $_db_store;
    private $_table = 'sdb_b2c_search_keywords';
    private $_search_hot_words_talbe = 'sdb_b2c_search_hot_words';
    private $_cat_table = 'mall_module_mall_goods_cat';
    private $_brand_talbe = 'sdb_b2c_brand';

    public function __construct()
    {
        $this->_db_store = app('api_db')->connection('neigou_store');
    }

    public function setHotWord($hot_word)
    {
        return $this->_db_store->table($this->_search_hot_words_talbe)->insert([
            'hot_word' => addslashes($hot_word),
            'create_at' => time()
        ]);
    }

    public function translate($keyword)
    {
        $data = array(
            'catgory' => array(),
            'brand' => array(),
        );
        //查找关键字
        $result = $this->getSpecialWord($keyword);
        if (is_array($result)) {
            $catgory = explode(',', $result['cat_name']);
            $brand = explode(',', $result['brand_name']);
            $data = array(
                'catgory' => $catgory,
                'brand' => $brand,
            );
            return $data;
        }

        //关键字指定分类或品牌
        $results = $this->getSynonym($keyword, false);
        if (!empty($results)) {
            if (is_array($results)) {
                $catgory = array();
                $brand = array();
                foreach ($results as $row) {
                    if ($row['type'] == self::TYPE_CATGORY) {
                        $catgory[] = $row['keyword'];
                    } elseif ($row['type'] == self::TYPE_BRAND) {
                        $brand[] = $row['keyword'];
                    }
                }
                $data = array(
                    'catgory' => $catgory,
                    'brand' => $brand,
                );
            }
            return $data;
        }
        //尝获取对应信息
        $catgoryInfo = $this->getCatgoryInfo($keyword);
        //print_r($catgoryInfo);die;
        $catgory = array();
        if (!empty($catgoryInfo)) {
            $catgory[] = $keyword;
        }
        $brand = array();
        $brandInfo = $this->getBrandInfo($keyword);
        if (!empty($brandInfo)) {
            $brand[] = $keyword;
        }

        $data = array(
            'catgory' => $catgory,
            'brand' => $brand,
        );
        if (empty($catgory) && empty($brand)) {
            return false;
        }
        return $data;
    }

    public function getSpecialWord($keyword)
    {
        $res = $this->_db_store->table($this->_table)->where([
            'keyword' => addslashes($keyword),
            'type' => self::TYPE_SPECIAL_WORD
        ])->first();
        return (array)$res;
    }

    public function getSynonym($synonym, $need_bool = true, $keyword = '')
    {
        $result = $this->_db_store->select('select * from `".$this->table."` where (`type`=? and FIND_IN_SET(?,`cat_name`)) or (`type`=? and FIND_IN_SET(?,`brand_name`))',
            [
                self::TYPE_CATGORY,
                $synonym,
                self::TYPE_BRAND,
                $synonym
            ]);
        if ($need_bool) {
            //排除自身所在行
            if (empty($result)) {
                return false;
            }

            //$keyword = addslashes($keyword);
            $flag = false;
            foreach ($result as $row) {
                if ($row['keyword'] != $keyword) {
                    $flag = true;
                    break;
                }
            }
            return $flag;
        }
        return $result;
    }

    public function getCatgoryInfo($keyword)
    {
        $result = $this->_db_store->select('select distinct(cat_id),cat_name,cat_path from `' . $this->_cat_table . '` where `cat_name`=?',
            [
                addslashes($keyword)
            ]);

        return $result;
    }

    public function getBrandInfo($keyword)
    {
        $result = $this->_db_store->select('select distinct(brand_id),brand_name from `' . $this->brand_talbe . '` where `brand_name`=\'%s\'',
            [
                addslashes($keyword)
            ]);
        return $result;
    }
}
