<?php
/**
 * Created by PhpStorm.
 * User: chuanbin
 * Date: 2018/4/2
 * Time: 15:33
 */

namespace App\Api\Model\Search;

class BusinessKeywordModel
{
    private $_db;
    private $table_search_business_keyword = "server_search_business_keyword";
    const MAX_LIMIT = 2;

    public function __construct()
    {
        $this->_db = app('api_db');
    }

    public function coverKeywords($business_code, $keywords, $datas)
    {
        if (empty($business_code) || empty($keywords) || empty($datas)) {
            return false;
        }
        $res = $this->delKeywords($business_code, $keywords);
        if ($res !== false) {
            return $this->_db->table($this->table_search_business_keyword)->insert($datas);
        }
        return false;
    }

    public function delKeywords($business_code, $keywords)
    {
        if (empty($business_code) || empty($keywords)) {
            return false;
        }
        $res = $this->_db->table($this->table_search_business_keyword)
            ->where([
                'business_code' => $business_code,
            ])
            ->whereIn('keyword', $keywords)
            ->delete();
        return $res !== false;
    }

    public function getKeywords($keyword)
    {
        if (empty($keyword)) {
            return false;
        }
        $list = $this->_db->table($this->table_search_business_keyword)
            ->where([
                'keyword' => $keyword,
            ])
            ->orderBy('boost', 'desc')
            ->get()->map(function ($value) {
                return (array)$value;
            })
            ->toArray();
        return $list;
    }
}