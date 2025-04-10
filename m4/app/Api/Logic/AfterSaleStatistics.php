<?php
/**
 * Created by PhpStorm.
 * User: liangtao
 * Date: 2018/12/3
 * Time: 下午3:56
 */

namespace App\Api\Logic;

use App\Api\Model\AfterSale\Statistics as Statistics;

class AfterSaleStatistics
{
    /*
     * 统计创建
     */
    public function create($param = array(), &$err = '')
    {
        if (!$param['source_bn'] || !$param['source_type'] || !$param['status'] || !$param['order_id']) {
            $err = '参数错误';
            return false;
        }

        $data = array();

        if ($param['order_id']) {
            $data['order_id'] = $param['order_id'];
        }

        if ($param['source_bn']) {
            $data['source_bn'] = $param['source_bn'];
        }

        if ($param['source_type']) {
            $data['source_type'] = $param['source_type'];
        }

        if ($param['status']) {
            $data['status'] = $param['status'];
        }

        if ($param['memo']) {
            $data['memo'] = $param['memo'];
        }

        if ($param['responsible']) {
            $data['responsible'] = $param['responsible'];
        }

        if ($param['responsible_msg']) {
            $data['responsible_msg'] = $param['responsible_msg'];
        }

        if ($param['level']) {
            $data['level'] = $param['level'];
        }

        $time = time();
        $data['create_time'] = $param['create_time'] ? $param['create_time'] : $time;
        $data['update_time'] = $param['update_time'] ? $param['update_time'] : $time;

        $StatisticsMdl = new Statistics();
        $res = $StatisticsMdl->create($data);
        if (!$res) {
            $err = '提交失败';
            return false;
        }
        return true;
    }

    public function update($param = array(), &$err = '')
    {

        if (empty($param['id']) || empty($param['source_bn'])) {
            $err = '参数错误';
            return false;
        }

        $data = array();
        if ($param['status']) {
            $data['status'] = $param['status'];
        }

        if (isset($param['memo'])) {
            $data['memo'] = $param['memo'];
        }

        if (isset($param['responsible'])) {
            $data['responsible'] = $param['responsible'];
        }

        if (isset($param['responsible_msg'])) {
            $data['responsible_msg'] = $param['responsible_msg'];
        }

        if (isset($param['level'])) {
            $data['level'] = $param['level'];
        }

        $time = time();
        $data['update_time'] = $time;

        $where = array(
            'id' => $param['id'],
            'source_bn' => $param['source_bn']
        );
        $StatisticsMdl = new Statistics();
        $res = $StatisticsMdl->update($where, $data);
        if (!$res) {
            $err = '提交失败';
            return false;
        }
        return true;
    }

    public function get($where = array(), &$err)
    {
        if (empty($where['source_bn']) || empty($where['source_type'])) {
            $err = '参数错误';
            return false;
        }
        $StatisticsMdl = new Statistics();
        $res = $StatisticsMdl->get($where);
        return $res ? $res : array();
    }

    public function getList($param = array(), &$err)
    {
        if (empty($param['source_bn']) || empty($param['source_type'])) {
            $err = '参数错误';
            return false;
        }
        $StatisticsMdl = new Statistics();
        $res = $StatisticsMdl->getList($param);
        return $res ? $res : array();
    }

    public function getStatus($where = array(), &$err)
    {
        if (empty($where['source_type'])) {
            $err = '参数错误';
            return false;
        }
        $StatisticsMdl = new Statistics();
        $res = $StatisticsMdl->getStatus($where);
        return $res ? $res : array();
    }

}
