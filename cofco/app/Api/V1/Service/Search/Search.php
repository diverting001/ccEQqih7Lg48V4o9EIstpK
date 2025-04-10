<?php
/**
 * 公用商品信息
 * @version 0.1
 * @package ectools.lib.api
 */

namespace App\Api\Logic\Search;


class Search
{
    private $_db_store;
    private $_search_hot_words_talbe = 'sdb_b2c_search_hot_words';

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

        $sql = sprintf("select * from `" . $this->table . "` where `keyword`='%s' and `type`=%d order by id desc limit 1 ",
            addslashes($keyword), self::TYPE_SPECIAL_WORD);
        $result = $this->db->select($sql);
        return $result[0];
    }
}
