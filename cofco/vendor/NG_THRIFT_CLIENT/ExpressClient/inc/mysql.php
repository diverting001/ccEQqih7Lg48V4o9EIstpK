<?php

/**
 * Created by PhpStorm.
 * User: liyunlong
 * Date: 2015/7/7
 * Time: 20:49
 */
namespace ExpressServer;
class mysql
{
    private $link;
    private $res;

    public function __construct()
    {
        $this->link = @mysql_connect('192.168.66.200', 'ecstore', '20131212');
        mysql_select_db('neigou_club', $this->link);
        mysql_query('set names utf8');
    }

    private function query($sql)
    {
        return mysql_query($sql, $this->link);
    }

    public function findOne($sql)
    {
        $res = $this->query($sql);
        if ($res) {
            return mysql_fetch_assoc($res);
        }
        return false;
    }

    public function findAll($sql)
    {
        $res = $this->query($sql);
        if ($res) {
            $arr = array();
            while ($row = mysql_fetch_assoc($res)) {
                $arr[] = $row;
            }
            return $arr;
        }
        return false;
    }

    /**
     * @param $data 字符key的数组,一围数组
     * @param $table
     * @return bool|int
     */
    public function insert($data, $table)
    {
        if (is_array($data)) {
            $keys = array_keys($data);
            $keys = join(',', $keys);
            $valus = array_values($data);
            $v = '';
            foreach ($valus as $ey => $val) {
                if (is_int($val)) {
                    $v .= $val . ',';
                } else {
                    $v .= "'{$val}',";
                }
            }
            $v = trim($v, ',');
            $sql = "insert into {$table} ({$keys}) VALUES ({$v})";
        }
        $res = $this->query($sql);
        if ($res) {
            return mysql_insert_id($this->link);
        }
        return false;
    }

    /**
     * @param $data      字符key的数组,一围数组
     * @param $where
     * @return int
     */
    public function update($data, $table, $where)
    {
        $sql = '';
        if (is_array($data)) {
            foreach ($data as $key => $val) {
                if (is_int($val)) {
                    $sql .= "$key=$val,";
                } else {
                    $sql .= "$key='{$val}',";
                }
            }
            $sql = trim($sql, ',');
            $sql = "update {$table} set {$sql} WHERE {$where}";
            $this->query($sql);
            return mysql_affected_rows();
        }
    }
}
