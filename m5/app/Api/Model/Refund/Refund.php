<?php
/**
 * Created by PhpStorm.
 * User: Liuming
 * Date: 2020/6/17
 * Time: 2:08 PM
 */

namespace App\Api\Model\Refund;


class Refund
{
    private $_db;

    /**
     * constructor.
     */
    public function __construct()
    {
        $this->_db = app('api_db')->connection('neigou_club');
    }

    /** 查询退款列表
     *
     * @param array $fields
     * @param array $where
     * @param int $offset
     * @param int $limit
     * @param string $order
     * @return array
     * @author liuming
     */
    public function getList($fields = array('*'), $where = [],$offset = 1,$limit = 20)
    {
        //$this->_db->enableQueryLog();
        $db = $this->_db->table('mis_order_refund_bill');
        $db = $this->addWhere($where,$db)->offset($offset)->limit($limit)->orderBy('update_time', 'desc');
        $list = $db->get($fields)->map(function ($value) {return (array)$value;})->toArray();
        //return dump($this->_db->getQueryLog());
        return $list ? $list : array();
    }

    /** 查询退款金额
     *
     * @param array $refundIdList
     * @return array
     * @author liuming
     */
    public function findObjectAssets($refundIdList = array()){
        if (empty($refundIdList)){
            return [];
        }
        $db = $this->_db->table('mis_order_refund_bill_object_assets');
        return $db->whereIn('refund_id',$refundIdList)->orderBy('id', 'desc')->get()->map(function ($value) {return (array)$value;})->toArray();
    }

    /** 查询退款数量
     * @param array $refundIdList
     * @return array
     * @author liuming
     */
    public function findObjectNums($refundIdList = array()){
        if (empty($refundIdList)){
            return [];
        }
        $db = $this->_db->table('mis_order_refund_bill_object_number');
        return $db->whereIn('refund_id',$refundIdList)->orderBy('id', 'desc')->get()->map(function ($value) {return (array)$value;})->toArray();
    }


    /** 获取订单列表总数
     *
     * @param array $where
     * @return mixed
     * @author liuming
     */
    public function getTotal($where = []){
        $db = $this->_db->table('mis_order_refund_bill');
        $db = $this->addWhere($where,$db);
        $count = $db->count();
        return $count;
    }

    /** 增加where条件
     *
     * @param array $where
     * @param $db
     * @return mixed
     * @author liuming
     */
    private function addWhere($where = array(),$db){
        //{"company_id":[20858],"page_index":1,"process_status":2,"update_time":{"start_time":"1559318400","end_time":"1559318409"}}
        foreach ($where as $fieldK => $whereV){
            $value = $whereV['value'];
            switch ($whereV['type']){
                case 'in':
                    $db->whereIn($fieldK,$value);
                    break;
                case 'between':
                    $db->whereBetween($fieldK,[$value['egt'],$value['elt']]);
                    break;
                case 'eq':
                    $db->where($fieldK,$value);
                    break;
            }
        }
        return $db;
    }

}