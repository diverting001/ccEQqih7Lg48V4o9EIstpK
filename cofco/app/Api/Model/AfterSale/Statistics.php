<?php
/**
 * Created by PhpStorm.
 * User: liangtao
 * Date: 2018/12/3
 * Time: 下午4:07
 */

namespace App\Api\Model\AfterSale;

class Statistics
{
    protected $_db;

    public function __construct()
    {
        $this->_db = app('api_db');
    }

    public function create($data = array())
    {
        if (empty($data)) {
            return false;
        }
        return $this->_db->table('server_after_sales_statistics')->insert($data);
    }

    public function update($where = array(), $param = array())
    {
        if (empty($where) || empty($param)) {
            return false;
        }
        return $this->_db->table('server_after_sales_statistics')->where($where)->update($param);
    }

    public function get($where = array())
    {
        if (empty($where)) {
            return array();
        }
        return $this->_db->table('server_after_sales_statistics')->where($where)->orderBy('id', 'desc')->first();
    }

    public function getList($param = array())
    {
        if (empty($param)) {
            return array();
        }
        $data_obj = $this->_db->table('server_after_sales_statistics')
            ->whereIn('source_bn', $param['source_bn'])
            ->where('source_type', $param['source_type'])
            ->groupBy('source_bn')
            ->orderBy('id', 'desc')
            ->get();
        $return = array();
        foreach ($data_obj as $obj) {
            $arr = get_object_vars($obj);
            $return[$arr['source_bn']] = $arr;
        }
        return $return;
    }

    public function getStatus($where = array())
    {
        if (empty($where)) {
            return array();
        }
        $return = array();
        $list = $this->_db->table('server_after_sales_statistics_status')
            ->where($where)
            ->orderBy('id', 'asc')
            ->get()
            ->toArray();
        foreach ($list as $item) {
            $return[] = get_object_vars($item);
        }
        return $return;
    }

}
