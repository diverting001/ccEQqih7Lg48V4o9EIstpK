<?php
/**
 * Created by PhpStorm.
 * User: chuanbin
 * Date: 2018/11/6
 * Time: 15:15
 */

namespace App\Api\Model\Credit;

use Neigou\Logger;

class CreditLimit
{
    private $_db;
    private $_table_acc = 'server_credit_account';
    private $_table_acc_limit = 'server_credit_account_limit';
    private $_table_rec = 'server_credit_bill_record';
    private $_table_remind = 'server_credit_remind';

    public function __construct()
    {
        $this->_db = app('api_db');
    }

    /**
     * 新建商户
     * @param $data
     * @return mixed
     */
    public function insert_account($data)
    {
        $insert['account_name'] = $data['account_name'];
        $insert['account_type'] = $data['account_type'];
        $insert['account_value'] = $data['account_value'];
        $insert['credit_limit'] = $data['credit_limit'];
        $insert['statement_date'] = $data['statement_date'];
        $insert['balance'] = 0;
        $res = $this->_db->table($this->_table_acc)->insertGetId($insert);
        if ($res) {
            Logger::General('service.creditAccount.create', array('remark' => 'succ', 'id' => $res, 'data' => $data));
            return $res;
        } else {
            Logger::General('service.creditAccount.create.fail', array('remark' => 'fail', 'data' => $data));
            return 0;
        }
    }

    /**
     * 取消锁定额度
     * @param $trans_id
     * @return int
     */
    public function cancelRecord($trans_id){
        $where['trans_id'] = $trans_id;
        $bill_info = $this->_db->table($this->_table_rec)->where($where)->get()->toArray();
        if(is_array($bill_info)){
            $sum = 0;
            $data = [];
            foreach ($bill_info as $key=> $bill){
                $sum +=$bill->rmb_amount;
                $data[$key]['account_id'] = $bill->Account_id;
                $data[$key]['rmb_amount'] = 0-$bill->rmb_amount;
                $data[$key]['trans_type'] = 'reply';
                $data[$key]['trans_id'] = $bill->trans_id;
                $data[$key]['part'] = $bill->part;
            }
            if($sum<=0){
                return 0;
            } else {
                foreach ($data as $insert){
                    $res = $this->insert_record($insert);
                    if($res==0){
                        Logger::General('service.credit.cancel.err',[
                            'trans_id'=>$trans_id,
                            'data'=>$insert,
                        ]);
                        return 0;
                    }
                }
            }
        }
        return 1;
    }

    /**
     * 修改商户
     * @param $where
     * @param $data
     * @return bool
     */
    public function edit_account($where, $data)
    {
        $res = $this->_db->table($this->_table_acc)->where($where)->update($data);
        if ($res) {
            Logger::General('service.creditAccount.update',
                array('remark' => 'succ', 'where' => $where, 'data' => $data));
            return true;
        } else {
            Logger::General('service.creditAccount.update.fail',
                array('remark' => 'fail', 'where' => $where, 'data' => $data));
            return false;
        }
    }

    /**
     * 添加额度变动记录
     * @param $data
     * @return int
     */
    public function insert_record($data)
    {
        if($data['rmb_amount']==0){
            //0元不操作额度
            return 1;
        }
        //1.先查询商户是否有消费记录
        $map['account_id'] = $data['account_id'];//商户ID
        $balance = $this->getNowBalance($data['account_id']);
        $insert['now_balance'] = $balance - $data['rmb_amount'];
        $insert['description'] = $data['trans_type'] . ' ' . $data['trans_id'] . ' 变动 ' . $data['rmb_amount'];
        $insert['rmb_amount'] = $data['rmb_amount'];
        $insert['account_id'] = $data['account_id'];
        $insert['trans_date'] = time();
        $insert['trans_type'] = $data['trans_type'];
        $insert['trans_id'] = $data['trans_id'];
        $insert['settle_status'] = 0;
        $insert['part'] = $data['part'];

        $this->_db->beginTransaction();
        $res = $this->_db->table($this->_table_rec)->insertGetId($insert);
        //回写余额给账户表
        if ($data['trans_type'] != 'reply') {
            //如果是消费的话 判断余额必须满足消费金额才可以消费
            $sql = "UPDATE " . $this->_table_acc . " SET balance=(balance-" . $data['rmb_amount'] . ") where id=" . $data['account_id'] . " AND (credit_limit + balance-" . $data['rmb_amount'] . ")>=0";
            $acc_res = $this->_db->update($sql);
        } else {
            $acc_res = $this->_db->table($this->_table_acc)->where('id',
                $data['account_id'])->update(array('balance' => $insert['now_balance']));
        }
        if ($res == true && $acc_res == true) {
            $this->_db->commit();
            Logger::General('service.creditRecord.create', array('remark' => 'succ', 'id' => $res, 'data' => $data));
            return $res;
        } else {
            $this->_db->rollback();
            Logger::General('service.creditRecord.create.fail', array('remark' => 'fail', 'data' => $data));
            return 0;
        }
    }

    public function getAccountLimitPart($account_id){
        $res = $this->_db->table($this->_table_acc_limit)->where(array('account_id'=>$account_id))->get()->toArray();
        if(!empty($res)){
            return array_column($res,'limit_part');
        } else {
            return [];
        }
    }

    public function setAccountLimitPart($account_id,$limit_part){
        $this->_db->beginTransaction();
        foreach ($limit_part as $part){
            $data['account_id'] = $account_id;
            $data['limit_part'] = $part;
            $id = $this->_db->table($this->_table_acc_limit)->insertGetId($data);
            if(!$id){
                $this->_db->rollback();
                return false;
            }
        }
        $this->_db->commit();
        return true;
    }

    /**
     * 获取当前可用余额
     * @param $account_id
     * @return mixed
     */
    public function getNowBalance($account_id)
    {
        $account_info = $this->getAccount(array('id' => $account_id));
        return $account_info->balance;
    }

    /**
     * 获取账户信息
     * @param $where
     * @return mixed
     */
    public function getAccount($where)
    {
        return $this->_db->table($this->_table_acc)->where($where)->first();
    }

    /**
     * 获取最近一条记录
     * @param $account_id
     * @return mixed
     */
    public function lastRecord($account_id)
    {
        $map['account_id'] = $account_id;//商户ID
        return $this->_db->table($this->_table_rec)->where($map)->orderBy('id', 'desc')->first();
    }

    /**
     * 商户列表
     * @param $where
     * @param int $page
     * @param int $limit
     * @return array
     */
    public function getSearchPageList($where, $page = 1, $limit = 20)
    {
        if (empty($where)) {
            $listCount = $this->_db->table($this->_table_acc)->count();
        } else {
            $listCount = $this->_db->table($this->_table_acc)->where($where)->count();
        }
        $offset = ($page - 1) * $limit;
        $totalPage = ceil($listCount / $limit);
        if (empty($where)) {
            $return = $this->_db->table($this->_table_acc)->offset($offset)->limit($limit)->orderBy('id',
                'desc')->get()->toArray();
        } else {
            $return = $this->_db->table($this->_table_acc)->where($where)->offset($offset)->limit($limit)->orderBy('id',
                'desc')->get()->toArray();
        }
        return array('page' => $page, 'totalCount' => $listCount, 'totalPage' => $totalPage, 'data' => $return);
    }

    /**
     * @param $where
     *
     * @return mixed
     */
    public function getRemind($where)
    {
        $res = $this->_db->table($this->_table_remind)->where($where)->get();

        return $res;
    }

    /**
     * @param $where
     * @param $data
     *
     * @return mixed
     */
    public function editRemind($where, $data)
    {
        if (!$where || !$data) {
            return false;
        }
        $res = $this->_db->table($this->_table_remind)->where($where)->update($data);

        return $res;
    }

    /**
     * @param $id
     *
     * @return bool
     */
    public function addRemindTimes($id)
    {
        if (!$id) {
            return false;
        }

        $res = $this->_db->table($this->_table_remind)->where(['id' => $id])->increment('remind_times', 1,
            ['update_time' => time()]);

        return $res;
    }
}
