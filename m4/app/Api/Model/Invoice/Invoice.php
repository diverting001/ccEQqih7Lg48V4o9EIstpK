<?php
/**
 * Created by PhpStorm.
 * User: chuanbin
 * Date: 2018/9/26
 * Time: 11:35
 */

namespace App\Api\Model\Invoice;


use Neigou\Logger;

class Invoice
{
    private $invoice_table = 'server_invoice';
    private $invoice_log_table = 'server_invoice_log';
    private $invoice_detail_table = 'server_invoice_detail';
    private $_db;

    public function __construct($db = '')
    {
        $this->_db = app('api_db');
    }

    /**
     * 获取发票详情
     * @param $map
     * @return mixed
     */
    public function getDetail($map)
    {
        //base
        $list = $this->_db->table($this->invoice_table)->where($map)->get()->toArray();
        if (is_array($list)) {
            foreach ($list as $key => $val) {
                if ($map['source'] == 'order') {
                    $where['sn'] = $val->sn;
                    $bnList = $this->_db->table($this->invoice_detail_table)->where($where)->get()->toArray();
                    $logList = $this->_db->table($this->invoice_log_table)->where($where)->get()->toArray();
                    $list[$key]->item = $bnList;
                    $list[$key]->log = $logList;
                }
            }
        }
        return $list;
    }

    /**
     * 创建发票操作
     * @param $data
     * @return mixed
     */
    public function create_invoice($data)
    {
        $invoice_data = $data['invoice_data'];
        $detail_data = $data['detail_data'];
        if (empty($invoice_data) || empty($detail_data)) {
            Logger::General('service.create_invoice.err', array('remark' => 'param err', 'request_data' => $data));
            $ret['status'] = false;
            $ret['code'] = 1001;
            $ret['msg'] = 'param err';
            return $ret;
        }

        $this->_db->beginTransaction();
        //save base info
        $sn = self::create($invoice_data);
        $detail_status = true;
        if ($sn) {
            //detail
            foreach ($detail_data as $key => $value) {
                $value['sn'] = $sn;
                if ($invoice_data['source'] == 'order') {
                    $value['order_id'] = $invoice_data['source_id'];
                }
                $detail_status = self::create_detail_item($value);
                if ($detail_status == false) {
                    Logger::General('service.create_invoice.err',
                        array('remark' => 'detail add fail', 'data' => $value));
                    break;
                }
            }
        }
        if ($detail_status) {
            //详情插入成功 add log return
            self::add_log($sn, $data['member_id'], $data['member_name'] . '创建发票');
            $this->_db->commit();
            $ret['status'] = true;
            $ret['code'] = 0;
            $ret['msg'] = '保存成功';
            $ret['data'] = $sn;
        } else {
            $this->_db->rollback();
            $ret['status'] = false;
            $ret['code'] = 1003;
            $ret['msg'] = '保存失败';
        }
        return $ret;
    }


    /**
     * 创建并保存一张发票
     * @param $data
     * @return bool
     */
    private function create($data)
    {
        $data['sn'] = self::_gen_invoice_id();
        //发票基础信息
        $res = app('api_db')->table($this->invoice_table)->insert($data);
        //发票详细内容
        if ($res) {
            Logger::General('service.invoice.create', array('remark' => 'succ', 'data' => $data));
            return $data['sn'];
        } else {
            Logger::General('service.invoice.create.fail', array('remark' => 'fail', 'data' => $data));
            return false;
        }
    }

    /**
     * 创建并保存一个发票内容条目
     * @param $data
     * @return bool
     */
    private function create_detail_item($data)
    {
        $res = app('api_db')->table($this->invoice_detail_table)->insert($data);
        if ($res) {
            Logger::General('service.invoiceDetail.add_detail_item', array('remark' => 'succ', 'data' => $data));
            return true;
        } else {
            Logger::General('service.invoiceDetail.add_detail_item.fail', array('remark' => 'fail', 'data' => $data));
            return false;
        }

    }

    public function update($where, $data)
    {
        $res = app('api_db')->table($this->invoice_table)->where($where)->update($data);
        if ($res) {
            Logger::General('service.invoice.update', array('remark' => 'succ', 'where' => $where, 'data' => $data));
            return true;
        } else {
            Logger::General('service.invoice.update.fail',
                array('remark' => 'fail', 'where' => $where, 'data' => $data));
            return false;
        }
    }

    /**
     * 产生一个发票号
     * @return string
     */
    private function _gen_invoice_id()
    {
        $str = date('ymdHis') . rand(1000, 9999);
        return 'FP-' . $str;
    }

    /**
     * 操作日志保留
     * @param $sn
     * @param $member_id
     * @param $remark
     * @return bool
     */
    public function add_log($sn, $member_id, $remark)
    {
        $data['sn'] = $sn;
        $data['member_id'] = $member_id;
        $data['remark'] = $remark;
        $data['create_time'] = time();
        $res = $this->_db->table($this->invoice_log_table)->insert($data);
        if ($res) {
            Logger::General('service.invoice.log', array('remark' => 'succ', 'data' => $data));
            return true;
        } else {
            Logger::General('service.invoice.log.fail', array('remark' => 'fail', 'data' => $data));
            return false;
        }
    }

    /**
     * 发票列表
     * @param $where
     * @param int $page
     * @param int $limit
     * @return array
     */
    public function getSearchPageList($where, $page = 1, $limit = 20)
    {
        if (empty($where)) {
            $listCount = $this->_db->table($this->invoice_table)->count();
        } else {
            $listCount = $this->_db->table($this->invoice_table)->where($where)->count();
        }
        $offset = ($page - 1) * $limit;
        $totalPage = ceil($listCount / $limit);
        if (empty($where)) {
            $return = $this->_db->table($this->invoice_table)->offset($offset)->limit($limit)->orderBy('id',
                'desc')->get()->toArray();
        } else {
            $return = $this->_db->table($this->invoice_table)->where($where)->offset($offset)->limit($limit)->orderBy('id',
                'desc')->get()->toArray();
        }
        return array('page' => $page, 'totalCount' => $listCount, 'totalPage' => $totalPage, 'data' => $return);
    }

}
