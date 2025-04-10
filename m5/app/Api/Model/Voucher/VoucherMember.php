<?php
/**
 * Created by PhpStorm.
 * User: chuanbin
 * Date: 2017/10/19
 * Time: 15:30
 */

namespace App\Api\Model\Voucher;


use App\Api\Logic\MessageCenter;
use App\Api\Logic\UserCenterMsg;

class VoucherMember
{

    const max_create_money = 5000;
    private $vouchermgr;
    private $store_sqllink;
    private $table_voucher = 'promotion_voucher';
    private $table_voucher_member = 'promotion_voucher_member';
    private $table_voucher_company = 'promotion_voucher_company';
    private $promotion_voucher_member_bind_log = 'promotion_voucher_member_bind_log';
    private $promotion_voucher_member_sourcetype = 'promotion_voucher_member_sourcetype';
    private $table_voucher_money_pool = 'promotion_voucher_money_pool';
    private $table_voucher_money_pool_log = 'promotion_voucher_money_pool_log';
    private $_db;

    public function __construct($db = '')
    {
        $this->_db = $db ? $db : app('api_db')->connection('neigou_store');
        $this->vouchermgr = new Voucher();
    }

    /**
     * 查询用户优惠券
     * @param $member_id
     * @param int $status
     * @return mixed
     */
    public function queryMemberBindedVoucher($member_id, $status = 0)
    {
        $where = [
            ['pvm.member_id', $member_id],
            ['pv.valid_time', '>', time()]
        ];
        if (is_string($status)) {
            $where[] = array('pv.status', $status);
        }
        $voucher_date_result = $this->_db->table($this->table_voucher_member . ' as pvm')
            ->join('promotion_voucher as pv', 'pvm.voucher_id', '=', 'pv.voucher_id')
            ->join('promotion_voucher_company as pvc', 'pv.voucher_id', '=', 'pvc.voucher_id')
            ->join('promotion_voucher_rules as pvr', 'pv.rule_id', '=', 'pvr.rule_id')
            ->select('pv.*', 'pvc.company_id', 'pvr.rule_condition', 'pvr.extend_data', 'pvr.display_intro')
            ->where($where)->get()->all();

        return $voucher_date_result;
    }

    /**
     * 查询用户优惠券
     * @param $member_id
     * @param $company_id
     * @param string $status
     * @return mixed
     */
    public function queryMemberBindedVoucherV2($member_id, $company_id, $status = 0)
    {
        $where = [
            ['pvm.member_id', $member_id],
            ['pv.valid_time', '>', time()]
        ];
        if($company_id){
           $where[] = ['pvm.company_id', $company_id];
        }
        if (is_string($status) && in_array($status,array('normal','lock','finish','disabled'))) {
            $where[] = array('pv.status', $status);
        }
        $voucher_date_result = $this->_db->table($this->table_voucher_member . ' as pvm')
            ->join('promotion_voucher as pv', 'pvm.voucher_id', '=', 'pv.voucher_id')
//            ->join('promotion_voucher_company as pvc', 'pv.voucher_id', '=', 'pvc.voucher_id')
            ->join('promotion_voucher_rules as pvr', 'pv.rule_id', '=', 'pvr.rule_id')
            ->select('pv.*', 'pvm.company_id', 'pvr.rule_condition', 'pvr.extend_data', 'pvr.display_intro')
            ->where($where)->get()->all();

        return $voucher_date_result;
    }

    //给用户发券
    public function createMemVoucher($member_id, $json_data)
    {
        \Neigou\Logger::General('action.voucher', array(
            'action' => 'createMemVoucher',
            'member_id' => $member_id
        ));
        $create_data_array = json_decode($json_data, true);
        $create_data_array['voucher_nature'] = intval($create_data_array['voucher_nature']);

        $this->_db->beginTransaction();
        $message = "";
        $create_result_id = $this->createVoucherForMember($member_id, $create_data_array, $message);

        if ($create_result_id) {
            $this->_db->commit();

//            $sql = "select type as voucher_type,discount,voucher_id,money,valid_time,rule_name from {$this->table_voucher} where create_id='{$create_result_id}'";
//            $voucher_data_list = $this->store_sqllink->findAll($sql);
            $voucher_data_list = $this->_db->table($this->table_voucher)->where('create_id',
                $create_result_id)->select('type as voucher_type', 'number', 'discount', 'voucher_id', 'money',
                'valid_time', 'rule_name')->get()->all();

            $member_money_list = array();

            foreach ($voucher_data_list as $voucher_item) {
                $member_money_list[] = array(
                    "member_id" => $member_id,
                    'voucher_type' => $voucher_item->voucher_type,
                    'discount' => $voucher_item->discount,
                    "money" => $voucher_item->money,
                    "company_id" => $create_data_array['company_id'],
                    "valid_time" => $voucher_item->valid_time,
                    "rule_name" => $voucher_item->rule_name,
                    'message_channel'=>$create_data_array['message_channel']
                );
            }
            $message_center_mgr = new MessageCenter();
            $message_center_mgr->sendMessage($member_money_list);


            $user_center_msg = new UserCenterMsg();
            $user_center_msg->sendUserCenterMsg($member_money_list);
            \Neigou\Logger::General('action.voucher', array(
                'action' => 'createMemVoucher',
                'member_id' => $member_id,
                'success' => 1,
                'create_data' => $json_data
            ));
            return array_column($voucher_data_list, 'number');
        } else {
            $this->_db->rollback();
            \Neigou\Logger::General('action.voucher', array(
                'action' => 'createMemVoucher',
                'member_id' => $member_id,
                'success' => 0,
                'create_data' => $json_data,
                'reason' => 'insert_failed'
            ));
            return false;//err 1
        }
    }

    // 创建用户内购券，必须在事务之中
    public function createVoucherForMember($member_id, $create_data_array, &$message)
    {
        if ($create_data_array['money'] * $create_data_array['count'] > self::max_create_money) {
            \Neigou\Logger::General('action.voucher', array(
                'action' => 'createMulMemberVoucher',
                'member_id' => $member_id,
                'state' => 'fail',
                'reason' => 'exceed_max_money'
            ));
            $message = "超出最大创建金额";
            return false;
        }

        if (!isset($create_data_array['source_type'])) {
            \Neigou\Logger::General('action.voucher', array(
                'action' => 'createMulMemberVoucher',
                'member_id' => $member_id,
                'state' => 'fail',
                'reason' => 'no_source_type'
            ));
            $message = "内购券类型错误";
            return false;
        }

        $source_type = $create_data_array['source_type'];
        $source_data = $this->_db->table($this->promotion_voucher_member_sourcetype)->where('source_type',
            $source_type)->first();
//        $source_data = $this->store_sqllink->findOne("select * from {$this->promotion_voucher_member_sourcetype} where source_type='{$source_type}'");
        if (empty($source_data)) {
            \Neigou\Logger::General('action.voucher', array(
                'action' => 'createMulMemberVoucher',
                'member_id' => $member_id,
                'state' => 'fail',
                'reason' => 'invalid_source_type'
            ));
            $message = "内购券类型错误";
            return false;
        }

        if ($source_data->is_single == 1) {
            // 注册送券加入限制，先做处理，之所以没有放到事务中，是因为创建券也在事务中。
//            $sql = "select * from {$this->table_voucher_member} where source_type='{$source_type}' and member_id={$member_id}";
//            $register_mem_data = $this->store_sqllink->findOne($sql);
            $register_mem_data = $this->_db->table($this->table_voucher_member)->where([
                ['souce_type', $source_type],
                ['member_id', $member_id]
            ])->first();
            if ($register_mem_data) {
                \Neigou\Logger::General('action.voucher',
                    array('action' => 'createMulMemberVoucher', 'state' => 'fail', 'reason' => 'already_acquired'));
                $message = "此类型内购券已经获取";
                return false;
            }
        }
        // 资金池限制
        if (!empty($create_data_array['money_pool_id'])) {
            $create_data_array['member_id'] = $member_id;
            if (!$this->_checkVoucherMoneyPool($create_data_array, $message)) {
                \Neigou\Logger::General('action.voucher', array(
                    'action' => 'createVoucherForMember',
                    'state' => 'fail',
                    'reason' => 'pass max money',
                    'data' => $create_data_array,
                    'message' => $message
                ));
                $message OR $message = "超出资金池最大金额";
                return false;
            }
        }

        $create_result_id = $this->vouchermgr->createVoucher($create_data_array, $message);
        if (!empty($create_result_id)) {
            if (!empty($create_data_array['money_pool_id'])) {
                if (!$this->_addVoucherMoneyPoolLog($create_result_id, $create_data_array)) {
                    \Neigou\Logger::General('action.voucher', array(
                        'action' => 'createVoucherForMember',
                        'state' => 'fail',
                        'reason' => 'add money pool log failed',
                        'data' => $create_data_array
                    ));
                    $message = "添加资金池记录失败";
                    return false;
                }
                $result = $this->_db->table($this->table_voucher_money_pool)->where('pool_id',
                    $create_data_array['money_pool_id'])->increment('used_money',
                    $create_data_array['money'] * $create_data_array['count']);
                if ($result == 0) {
                    return false;
                }
            }

            $create_time = time();
            $create_id = $create_result_id;
//            $sql = "select voucher_id from {$this->table_voucher} where create_id='{$create_id}'";
//            $voucher_id_list = $this->store_sqllink->findAll($sql);

            $voucher_id_list = $this->_db->table($this->table_voucher)->where('create_id', $create_id)->get()->all();

            $b_create_suc = true;

            // 注册送券加入限制，这个做第二次处理是因为需要进行事务处理，防止并发调用。
            if ($source_data->is_single == 1) {
//                $sql = "select * from {$this->table_voucher_member} where source_type='{$source_type}' and member_id={$member_id} for update";
//                $register_mem_data = $this->store_sqllink->findOne($sql);

                $register_mem_data = $this->_db->table($this->table_voucher_member)->where([
                    ['source_type', $source_type],
                    ['member_id', $member_id]
                ])->sharedLock()->first();
                if ($register_mem_data) {
                    $message = "此类型内购券超出限额";
                    \Neigou\Logger::General('action.voucher', array(
                        'action' => 'createMulMemberVoucher',
                        'state' => 'fail',
                        'reason' => 'already_register_for_update'
                    ));
                    return false;
                }
            }
            foreach ($voucher_id_list as $id_item) {
                $member_voucher_data = array(
                    'member_id' => $member_id,
                    'company_id' => $create_data_array['company_id'],
                    'voucher_id' => $id_item->voucher_id,
                    'source_type' => $source_type,
                    'create_time' => $create_time
                );
//                $result = $this->store_sqllink->insert($member_voucher_data, $this->table_voucher_member);
                $result = $this->_db->table($this->table_voucher_member)->insert($member_voucher_data);
                if (!$result) {
                    $b_create_suc = false;
                    break;
                }
            }
            if ($b_create_suc) {
                \Neigou\Logger::General('action.voucher', array(
                    'action' => 'createMulMemberVoucher',
                    'member_id' => $member_id,
                    'success' => 1,
                    'source_type' => $source_type
                ));
                return $create_result_id;
            } else {
                $message = "用户内购券创建失败";
                \Neigou\Logger::General('action.voucher', array(
                    'action' => 'createMulMemberVoucher',
                    'member_id' => $member_id,
                    'success' => 0,
                    'reason' => 'insert_failed'
                ));
                return false;
            }
        } else {
            \Neigou\Logger::General('action.voucher', array(
                'action' => 'createMulMemberVoucher',
                'member_id' => $member_id,
                'success' => 0,
                'reason' => 'create_failed'
            ));
            return false;
        }
    }
//todo check night
    // 用户绑定已有代金券
    public function bindMemVoucher($member_id, $voucher_number, $source_type)
    {
        \Neigou\Logger::General('action.voucher', array(
            'action' => 'bindMemVoucher',
            'member_id' => $member_id,
            'voucher_number' => $voucher_number
        ));
        if (empty($member_id) || empty($voucher_number)) {
            \Neigou\Logger::General('action.voucher', array(
                'action' => 'bindMemVoucher',
                'member_id' => $member_id,
                'voucher_number' => $voucher_number,
                'success' => 0,
                'reason' => 'params_invalid'
            ));
            return false;//err 1
        }
        $source_data = $this->_db->table($this->promotion_voucher_member_sourcetype)->where('source_type',
            $source_type)->first();
//        $source_data = $this->store_sqllink->findOne("select * from {$this->promotion_voucher_member_sourcetype} where source_type='{$source_type}'");
        if (empty($source_data)) {
            \Neigou\Logger::General('action.voucher', array(
                'action' => 'bindMemVoucher',
                'member_id' => $member_id,
                'voucher_number' => $voucher_number,
                'success' => 0,
                'reason' => 'invalid_source_type'
            ));
            return false;//err1
        }
        $voucher_info = $this->vouchermgr->queryVoucher($voucher_number);
//        $return_data = json_decode($return_data, true);
//        $voucher_info = json_decode($return_data['data'], true);
//        var_dump($return_data);die;
        if (isset($voucher_info->voucher_id)) {

            $member_voucher_data = array(
                'member_id' => $member_id,
                'voucher_id' => $voucher_info->voucher_id,
                'source_type' => $source_type,
                'create_time' => time()
            );

            //确认下在表中没有这条优惠券记录
            $count = $this->_db->table($this->table_voucher_member)->where('voucher_id',
                $voucher_info->voucher_id)->count();

            if ($count > 0) {
                $insert_result = false;
            } else {
                $insert_result = $this->_db->table($this->table_voucher_member)->insert($member_voucher_data);
            }
            if ($insert_result) {
                // 创建完成后通知用户 TODO
                $message_center_mgr = new MessageCenter();
                $message_center_mgr->sendMessage(array(
                    array(
                        "member_id" => $member_id,
                        'voucher_type' => $voucher_info->type,
                        'discount' => $voucher_info->discount,
                        "money" => $voucher_info->money,
                        "company_id" => $voucher_info->company_id,
                        "valid_time" => $voucher_info->valid_time,
                        "rule_name" => $voucher_info->rule_name
                    )
                ));
                $user_center_msg = new UserCenterMsg();
                $user_center_msg->sendUserCenterMsg(array(
                    array(
                        "member_id" => $member_id,
                        "money" => $voucher_info->money,
                        "company_id" => $voucher_info->company_id
                    )
                ));
                return true;
            } else {
                \Neigou\Logger::General('action.voucher', array(
                    'action' => 'bindMemVoucher',
                    'member_id' => $member_id,
                    'voucher_number' => $voucher_number,
                    'success' => 0,
                    'reason' => 'insert_failed'
                ));
                return false;//err1
            }
        } else {
            \Neigou\Logger::General('action.voucher', array(
                'action' => 'bindMemVoucher',
                'member_id' => $member_id,
                'voucher_number' => $voucher_number,
                'success' => 0,
                'reason' => 'invalid_voucher'
            ));
            return false;//err1
        }
    }

    // 批量绑定用户及已有代金券
    public function largeBindMemVoucher($bind_data)
    {
        \Neigou\Logger::General('action.voucher',
            array('action' => 'largeBindMemVoucher', 'data' => $bind_data));

        if (empty($bind_data) ||
            !isset($bind_data['mem_voucher_list']) ||
            !isset($bind_data['verify_code']) ||
            !isset($bind_data['source_type'])) {
            \Neigou\Logger::General('action.voucher',
                array('action' => 'checkParams', 'states' => 'fail', 'reason' => 'invalid_params'));
            return false;//err 1
        }
        if (md5($bind_data['mem_voucher_list']) != $bind_data['verify_code']) {
            \Neigou\Logger::General('action.voucher',
                array(
                    'action' => 'mem_voucher_list.err', 'states' => 'fail', 'reason' => 'invalid_verify_code'
                ));
//            return false; //err 1
        }
        $mem_voucher_list = json_decode($bind_data['mem_voucher_list'], true);
        if (count($mem_voucher_list) <= 0) {
            \Neigou\Logger::General('action.voucher',
                array('action' => 'mem_voucher_list.count', 'states' => 'fail', 'reason' => 'invalid_count'));
            return false;
        }
        $source_type = $bind_data['source_type'];
        $source_data = $this->_db->table($this->promotion_voucher_member_sourcetype)->where('source_type',
            $source_type)->first();
//        $source_data = $this->store_sqllink->findOne("select * from {$this->promotion_voucher_member_sourcetype} where source_type='{$source_type}'");
        if (empty($source_data)) {
            \Neigou\Logger::General('action.voucher',
                array('action' => 'voucherUsed', 'success' => 0, 'reason' => 'invalid_source_type', 'bind_data'=>$bind_data));
            return false;
        }

        $b_bind_suc = true;
        $create_time = time();
        $this->_db->beginTransaction();
        $bind_log_data = array(
            'op_name' => $bind_data['op_name'],
            'log_text' => $bind_data['memo'],
            'create_time' => $create_time,
            'quantity' => count($mem_voucher_list),
            'source_type' => $bind_data['source_type'],
        );
        $bind_result_id = $this->_db->table($this->promotion_voucher_member_bind_log)->insertGetId($bind_log_data);
        $member_money_list = array();
        $memberToVoucher = [];
        foreach ($mem_voucher_list as $mem_voucher_item) {
            if (empty($mem_voucher_item['member_id']) || empty($mem_voucher_item['voucher_number'])) {
                \Neigou\Logger::General('action.voucher', array(
                    'action' => 'largeBindMemVoucher',
                    'member_id' => $mem_voucher_item['member_id'],
                    'voucher_number' => $mem_voucher_item['voucher_number'],
                    'success' => 0,
                    'reason' => 'voucher_or_num_invalid'
                ));
                $b_bind_suc = false;
                break;
            }

            $memberToVoucher[$mem_voucher_item['member_id']] = $mem_voucher_item['voucher_number'];
        }

        if (!$b_bind_suc) {
            $this->_db->rollback();
            return false;
        }
        if (count($memberToVoucher) < 1) {
            $this->_db->rollback();
            \Neigou\Logger::General('action.voucher', array(
                'action' => 'nodata',
                'memberToVoucher' => $memberToVoucher
            ));
            return false;
        }

        $voucher_info = $this->vouchermgr->queryVoucherList(array_values($memberToVoucher));

        if ($voucher_info->count() != count($memberToVoucher)) {
            $this->_db->rollback();
            \Neigou\Logger::General('action.voucher', array(
                'action' => 'queryVoucherList',
                'member_id' => $memberToVoucher,
                'quantity' => count($mem_voucher_list),
            ));
            return false;
        }

        $voucherList = $voucherIdArr = [];
        foreach ($voucher_info as $item) {
            $voucherList[$item->number] = $item;
            $voucherIdArr[] = $item->voucher_id;
        }


        $count = $this->_db->table($this->table_voucher_member)->whereIn('voucher_id',
            $voucherIdArr)->count();

        if ($count) {
            $this->_db->rollback();
            \Neigou\Logger::General('action.voucher', array(
                'action' => 'voucherUsed',
                'member_id' => $memberToVoucher,
                'quantity' => count($mem_voucher_list),
                'voucherIdArr' => $voucherIdArr,
                'count' => $count,
            ));
            return false;
        }

        $mem_voucher_data = [];
        foreach ($memberToVoucher as $memberId => $voucherNumber) {
            $mem_voucher_data[] = [
                'member_id' => $memberId,
                'voucher_id' => $voucherList[$voucherNumber]->voucher_id,
                'source_type' => $bind_data['source_type'],
                'source_id' => $bind_result_id,
                'create_time' => $create_time
            ];

            $member_money_list[] = array(
                "member_id" => $memberId,
                'voucher_type' => $voucherList[$voucherNumber]->type,
                'discount' => $voucherList[$voucherNumber]->discount,
                "money" => $voucherList[$voucherNumber]->money,
                "company_id" => $voucherList[$voucherNumber]->company_id,
                "valid_time" => $voucherList[$voucherNumber]->valid_time,
                "rule_name" => $voucherList[$voucherNumber]->rule_name ?: '全场通用',
                "message_channel"=>$bind_data['message_channel']?$bind_data['message_channel']:''
            );
        }

        try {
            $insert_result = $this->_db->table($this->table_voucher_member)->insert($mem_voucher_data);
        }catch(\Exception $e){
            $this->_db->rollback();
            \Neigou\Logger::General('action.voucher', array(
                'action' => 'insertErr',
                'insert' => $mem_voucher_data,
                'quantity' => count($mem_voucher_list),
                'err' => $e->getMessage(),
            ));
            return false;
        }

        if (!$insert_result) {
            $this->_db->rollback();
            \Neigou\Logger::General('action.voucher', array(
                'action' => 'insertFail',
                'insert' => $mem_voucher_data,
                'quantity' => count($mem_voucher_list),
            ));
            return false;//err 1
        }
        $this->_db->commit();
        $message_center_mgr = new MessageCenter();
        $message_center_mgr->sendMessage($member_money_list);

        $user_center_msg = new UserCenterMsg();
        $user_center_msg->sendUserCenterMsg($member_money_list);

        return array("bind_id" => $bind_result_id);
    }

    public function createMulMemberVoucher($member_id, $voucher_list, &$message,$message_channel = '')
    {
        if (empty($member_id) || count($voucher_list) == 0) {
            $message = "参数错误";
            return false;
        }

        $member_money_list = array();
        $create_result_ids = array();
        foreach ($voucher_list as $voucher_item) {
            $create_result_id = $this->createVoucherForMember($member_id, $voucher_item, $message);
            if (empty($create_result_id)) {
                return false;
            }
            $create_result_ids[] = $create_result_id;
//            $sql = "select type as voucher_type,discount,voucher_id,money,valid_time,rule_name from {$this->table_voucher} where create_id='{$create_result_id}'";
//
//            $voucher_data_list = $this->store_sqllink->findAll($sql);
            $voucher_data_list = $this->_db->table($this->table_voucher)->select('type as voucher_type', 'discount',
                'voucher_id', 'money', 'valid_time', 'rule_name')->where('create_id', $create_result_id)->get()->all();
            $member_money_list = array();
            $voucher_data_list = json_decode(json_encode($voucher_data_list), true);

            foreach ($voucher_data_list as $voucher_data_item) {
                $member_money_list[] = array(
                    "member_id" => $member_id,
                    'voucher_type' => $voucher_data_item['voucher_type'],
                    'discount' => $voucher_data_item['discount'],
                    "money" => $voucher_data_item['money'],
                    "company_id" => $voucher_item['company_id'],
                    "valid_time" => $voucher_data_item['valid_time'],
                    "rule_name" => $voucher_data_item['rule_name'],
                    'message_channel'=>$message_channel
                );
            }
        }
        $message_center_mgr = new MessageCenter();
        $message_center_mgr->sendMessage($member_money_list);


        $user_center_msg = new UserCenterMsg();
        $user_center_msg->sendUserCenterMsg($member_money_list);
        return array("result" =>true, "create_result_ids" =>$create_result_ids);
    }

    public function transferVoucher($member_id = 0, $json_data = '')
    {
        $json_data = json_decode($json_data, true);
        if (!$member_id || !is_numeric($member_id) || !$json_data) {
            \Neigou\Logger::General('action.transferVoucher', array(
                'action' => 'transferVoucher',
                'success' => 0,
                'reason' => 'unvalid_params',
                'member_id' => $member_id,
                "params_data" => $json_data
            ));
            return false;
        }
        $member_list = array_diff(array_unique($json_data), array($member_id));
        $_time = time();
        $this->_db->beginTransaction();
        $result_num = $this->_db->table($this->table_voucher_member)->whereIn('member_id', $member_list)->get()->all();
        $set = array(
            'member_id' => $member_id,
            'last_modified' => $_time,
        );
        $result = $this->_db->table($this->table_voucher_member)->whereIn('member_id', $member_list)->update($set);

        //trans shipping coupon
        $coupon_num = $this->_db->table('promotion_freeshipping_member')->whereIn('member_id',
            $member_list)->get()->all();
        $coupon_up_num = $this->_db->table('promotion_freeshipping_member')->whereIn('member_id',
            $member_list)->update($set);
        if ($result == count($result_num) && $coupon_up_num == count($coupon_num)) {
            $this->_db->commit();
            return true;
        } else {
            $this->_db->rollback();
            return false;
        }
    }

    public function addMemberVoucherByCode($memberId = 0, $json_data = '')
    {
//        $json_data = json_decode($json_data,true);
        if (empty($memberId) || !is_numeric($memberId) || empty($json_data)) {
            \Neigou\Logger::General('voucherCode', array(
                'action' => 'voucherCode',
                'success' => 0,
                'reason' => 'unvalid_params',
                'member_id' => $memberId,
                "params_data" => $json_data
            ));
            return '参数错误';
        }
        $voucherCode = $json_data['voucherCode'];
        $sourceType = $json_data['source_type'];
        $companyId = $json_data['company_id'];
        $message_channel = $json_data['message_channel'];

        $this->_db->beginTransaction();
//        $sql = "select * from {$this->table_voucher} where number = '{$voucherCode}' for update";
//        $voucherRes = $this->store_sqllink->findOne($sql);
        $voucherRes = $this->_db->table($this->table_voucher)->where('number', $voucherCode)->sharedLock()->first();
        if (empty($voucherRes)) {
            $this->_db->rollback();
            \Neigou\Logger::General('voucherCode', array(
                'action' => 'voucherCode',
                'success' => 0,
                'member_id' => $memberId,
                "params_data" => 'res empty'
            ));
            return '内购券码错误';
        }

        if ($voucherRes->valid_time < time()) {
            $this->_db->rollback();
            \Neigou\Logger::General('voucherCode', array(
                'action' => 'voucherCode',
                'success' => 0,
                'member_id' => $memberId,
                "params_data" => 'valid_time die'
            ));
            return '内购券码失效';
        }

        if ($voucherRes->start_time > time()) {
            $this->_db->rollback();
            \Neigou\Logger::General('voucherCode', array(
                'action' => 'voucherCode',
                'success' => 0,
                'member_id' => $memberId,
                "params_data" => 'start_time die'
            ));
            return '内购券码未开始';
        }

        if ($voucherRes->status != 'normal') {
            $this->_db->rollback();
            \Neigou\Logger::General('voucherCode', array(
                'action' => 'voucherCode',
                'success' => 0,
                'member_id' => $memberId,
                "params_data" => json_encode($voucherRes)
            ));
            return '内购券码已经使用';
        }

        $voucherId = $voucherRes->voucher_id;
//        $sql = "select * from {$this->table_voucher_member} where voucher_id = $voucherId ";
//        $voucherMember = $this->store_sqllink->findOne($sql);

        $voucherMember = $this->_db->table($this->table_voucher_member)->where('voucher_id', $voucherId)->first();
        if (!empty($voucherMember)) {
            $this->_db->rollback();
            \Neigou\Logger::General('voucherCode', array(
                'action' => 'voucherCode',
                'success' => 0,
                'member_id' => $memberId,
                "params_data" => json_encode($voucherMember)
            ));
            return '内购券码已经使用';
        }

//        $sql = "select * from $this->promotion_voucher_member_sourcetype where source_type = '{$sourceType}' ";
//        $sourceTypeRes = $this->store_sqllink->findOne($sql);
        $sourceTypeRes = $this->_db->table($this->promotion_voucher_member_sourcetype)->where('source_type',
            $sourceType)->first();
        if (empty($sourceTypeRes)) {
            $this->_db->rollback();
            \Neigou\Logger::General('voucherCode', array(
                'action' => 'voucherCode',
                'success' => 0,
                'member_id' => $memberId,
                "params_data" => json_encode($sourceTypeRes)
            ));
            return '内购券类型错误';
        }

        $sourceTypeId = $sourceTypeRes->id;

        //添加内购券
        $time = time();
        $data = array();
        $data['member_id'] = $memberId;
        $data['company_id'] = $companyId;
        $data['voucher_id'] = $voucherId;
        $data['source_type'] = $sourceType;
        $data['source_id'] = $sourceTypeId;
        $data['create_time'] = $time;
        $data['last_modified'] = $time;
        $res = $this->_db->table($this->table_voucher_member)->insertGetId($data);
        if ($res) {
            $this->_db->commit();
            $member_money_list = array();
            $member_money_list[] = array(
                "member_id" => $memberId,
                'voucher_type' => $voucherRes->type,
                'discount' => $voucherRes->discount,
                "money" => $voucherRes->money,
                "company_id" => $companyId,
                "valid_time" => $voucherRes->valid_time,
                "start_time" => $voucherRes->start_time,
                "rule_name" => $voucherRes->rule_name,
                "message_channel" => $message_channel,
            );

            $message_center_mgr = new MessageCenter();
            $message_center_mgr->sendMessage($member_money_list);


            $user_center_msg = new UserCenterMsg();
            $user_center_msg->sendUserCenterMsg($member_money_list);

            \Neigou\Logger::General('voucherCode', array(
                'action' => 'voucherCode',
                'success' => 1,
                'member_id' => $memberId,
                "params_data" => json_encode($res)
            ));
            return true;
        } else {
            $this->store_sqllink->rollback();
            \Neigou\Logger::General('voucherCode', array(
                'action' => 'voucherCode',
                'success' => 0,
                'member_id' => $memberId,
                "params_data" => json_encode($res)
            ));
            return '创建失败';
        }
    }


    public function queryMemVoucherListByCreateId($createIds)
    {
        $voucher_list = $this->_db->table($this->table_voucher)->whereIn('create_id', $createIds)->get()->all();
        $voucher_id_list = array();
        foreach ($voucher_list as $voucher_item) {
            $voucher_id_list[] = $voucher_item->voucher_id;
        }

        $placeholder = implode(',',array_fill(0,count($voucher_id_list),'?'));
        $sql = "select voucher_id,source_type from {$this->table_voucher_member} where voucher_id in ({$placeholder})";
        $member_voucher_list = $this->_db->select($sql, $voucher_id_list);

        if (!empty($member_voucher_list)) {
            $voucher_id_list = array();
            $id_type_mapping = [];
            foreach ($member_voucher_list as $id_item) {
                $voucher_id_list[] = $id_item->voucher_id;
                $id_type_mapping[$id_item->voucher_id] = $id_item->source_type;
            }
            // 只取出过期时间在三个月内的数据
            $from_time = strtotime("-3 month");
            $placeholderVoucherIdList = implode(',',array_fill(0,count($voucher_id_list),'?'));
            $sql = "select pv.*, pvc.company_id from {$this->table_voucher} pv join promotion_voucher_company pvc on pv.voucher_id=pvc.voucher_id
                  where pv.valid_time>$from_time and  pv.voucher_id in (".$placeholderVoucherIdList.")";
            $voucher_date_result = $this->_db->select($sql,$voucher_id_list);
            foreach ($voucher_date_result as &$item) {
                $item->source_type = $id_type_mapping[$item->voucher_id];
            }
            if (empty($voucher_date_result)) {
                \Neigou\Logger::General('action.voucher', array(
                    'action' => 'queryMemVoucherList',
                    'success' => 0,
                    'reason' => 'voucher_empty'
                ));
                return false;
            } else {
                return $voucher_date_result;
            }
        } else {
            \Neigou\Logger::General('action.voucher', array(
                'action' => 'queryMemVoucherList',
                'success' => 0,
                'reason' => 'voucher_empty_1'
            ));
            return false;
        }
    }


    public function queryMemVoucherListByGuid($guid)
    {
        $_logic = new VoucherCommon();
        $member_list = $_logic->getMemberList($guid);
        $member_id = implode(',', $member_list);

        \Neigou\Logger::General('action.voucher', array(
            'action' => 'queryMemVoucherList',
            'member_id' => $member_id
        ));
        $placeholder = implode(',',array_fill(0,count($member_list),'?'));
        $sql = "select voucher_id,source_type from {$this->table_voucher_member} where member_id in ({$placeholder})";
        $member_voucher_list = $this->_db->select($sql,$member_list);

        return $this->getVoucherValue($member_voucher_list, $member_id);
    }

    public function queryMemVoucherListByCompany($member_id,$company_id)
    {

        \Neigou\Logger::General('action.voucher', array(
            'action' => 'queryMemVoucherList',
            'member_id' => $member_id,
            'company_id' => $company_id,
        ));
        $sql = "select voucher_id,source_type from {$this->table_voucher_member} where member_id ={$member_id}";
        if ($company_id) {
            $sql .= " and company_id=" . $company_id;
        }
        $member_voucher_list = $this->_db->select($sql);

        return $this->getVoucherValue($member_voucher_list, $member_id);
    }

    public function queryMemberBindedVoucherWithRule($member_id, $filter_data_array)
    {
        \Neigou\Logger::General('action.voucher', array(
            'action' => 'queryMemberBindedVoucherWithRule',
            'member_id' => $member_id
        ));
//        $filter_data_array = json_decode($json_filter_data, true);

        $voucher_data_list = $this->vouchermgr->queryMemberVoucherWithRuleFilter($member_id, $filter_data_array);
        if (empty($voucher_data_list)) {
            \Neigou\Logger::General('action.voucher', array(
                'action' => 'queryMemberBindedVoucherWithRule',
                'member_id' => $member_id,
                'success' => 0,
                'reason' => 'valid_voucher_empty'
            ));
            return "代金券数目为空";
        } else {
            return $voucher_data_list;
        }
    }

    /**
     * 查询用户券列表（分页、状态、排序)
     *
     * @param   $memberId   int  用户 ID
     * @param   $queryData  array  查询条件数据
     *          company_id      null or int         公司 ID
     *          order_by        null or string      排序
     *          page            int                 页数
     *          page_size       int                 每页数量
     *          voucher_status  null or string      券状态
     *          voucher_type    null ort string     券类型
     *
     * @return  string
     */
    public function queryMemberVoucherList($memberId, $queryData)
    {
        \Neigou\Logger::General('action.voucher',
            array('action' => 'queryMemberVoucherList', 'member_id' => $memberId, 'query_data' => $queryData));

        // 公司 ID
        $companyId = isset($queryData['company_id']) && $queryData['company_id'] ? $queryData['company_id'] : null;

        // 排序
        $orderByList = array('create_time');
        $orderBy = isset($queryData['order_by']) && in_array($queryData['order_by'],
            $orderByList) ? $queryData['order_by'] : current($orderByList);

        // 页数
        $page = isset($queryData['page']) && $queryData['page'] > 0 ? $queryData['page'] : 1;

        // 每页数量
        $pageSize = isset($queryData['page_size']) && $queryData['page_size'] > 0 ? $queryData['page_size'] : 20;

        // 券状态
        $voucherStatus = isset($queryData['voucher_status']) && $queryData['voucher_status'] ? $queryData['voucher_status'] : null;

        // 券类型
        $voucherType = isset($queryData['voucher_type']) && $queryData['voucher_type'] ? $queryData['voucher_type'] : null;

        // 券类型
        $validTime = isset($queryData['valid_time']) ? intval($queryData['valid_time']) : 0;

        // 现金池ID
        $moneyPoolId = isset($queryData['money_pool_id']) && $queryData['money_pool_id'] ? $queryData['money_pool_id'] : array();

        $db = $this->_db->table($this->table_voucher);

        $whereCloser = function ($query) use($memberId,$voucherStatus,$voucherType,$companyId,$validTime,$moneyPoolId){
            if ($memberId > 0) {
                $_where[$this->table_voucher_member.'.member_id'] = $memberId;
            }

            if ($voucherStatus) {
                $_where[$this->table_voucher.'.status'] = $voucherStatus;
            }

            if ($voucherType) {
                $_where[$this->table_voucher.'.type'] = $voucherType;
        }

            if ($companyId) {
                $_where[$this->table_voucher_company.'.company_id'] = $companyId;
            }

            $query->where($_where);

            if ($validTime > 0) {
                $query->where($this->table_voucher.'.valid_time',">=",$validTime);
            }

            if (!empty($moneyPoolId)) {
                if (!is_array($moneyPoolId)) {
                    $moneyPoolId = array($moneyPoolId);
                }
                $query->whereIn($this->table_voucher_money_pool_log.'.pool_id',$moneyPoolId);
            }
        };


        $totalCount = 0;

        // 查询券总数量
        if ($memberId > 0) {
            if ($companyId > 0) {
                $db->join($this->table_voucher_member,$this->table_voucher.'.voucher_id','=',$this->table_voucher_member.'.voucher_id')->join($this->table_voucher_company,$this->table_voucher.'.voucher_id','=',$this->table_voucher_company.'.voucher_id');
            } else {
                $db->join($this->table_voucher_member,$this->table_voucher.'.voucher_id','=',$this->table_voucher_member.'.voucher_id');
            }

            if (!empty($moneyPoolId)) {
                $db->join($this->table_voucher_money_pool_log,$this->table_voucher.'.create_id','=',$this->table_voucher_money_pool_log.'.create_voucher_id');
            }

            $selectDb = $db;
            $totalCount = $db->where($whereCloser)->count();
        }

        // 最大页码
        $pageNumber = ceil($totalCount / $pageSize);

        $voucherList = array();

        if ($totalCount > 0 && $page <= $pageNumber) {
            $selectDb = $selectDb->select(
                $this->table_voucher . '.type',
                $this->table_voucher . '.money',
                $this->table_voucher . '.discount',
                $this->table_voucher . '.valid_time',
                $this->table_voucher . '.status',
                $this->table_voucher . '.voucher_name',
                $this->table_voucher . '.rule_name',
                $this->table_voucher . '.rule_id',
                $this->table_voucher . '.start_time'
            );
            switch ($orderBy) {
                case "create_time":
                    $selectDb->orderBy($this->table_voucher.'.create_time','desc');
                    break;
            }
            $offset = ($page - 1) * $pageSize;
            $selectDb->offset($offset)->limit($pageSize);
            $voucherList = $selectDb->get()->toArray();
        }

        $return = array(
            'page_data' => array(
                'total_count' => $totalCount,
                'page_size' => $pageSize,
                'page' => $page,
                'page_number' => $pageNumber
            ),
            'voucher_list' => $voucherList,
        );

        return $return;
    }

    // 检查券的资金池
    private function _checkVoucherMoneyPool($voucher_data, & $message)
    {
        // 资金池 ID
        $money_pool_id = intval($voucher_data['money_pool_id']);

        // 获取资金池
        $sql = "SELECT * FROM  {$this->table_voucher_money_pool} WHERE pool_id='{$money_pool_id}' AND status= 1";
        $result = $this->_db->selectOne($sql);
        if (empty($result)) {
            $message = '资金池错误';
            return false;
        }

        foreach ($result as $key => $val) {
            $money_pool[$key] = $val;
        }
        // 现金券
        if ($voucher_data['type'] === 'money') {
            $current_total_money = $voucher_data['money'] * $voucher_data['count'];
            // 验证资金池总额度
            if ($money_pool['credit_limit'] !== null && ($money_pool['used_money'] + $current_total_money) > $money_pool['credit_limit']) {
                $message = '超出资金池总额度';
                return false;
            }

            $today = strtotime(date('Y-m-d'));
            $tomorrow = $today + 86400;
            // 验证资金池每日额度
            if ($money_pool['per_day_limit'] !== null) {
                // 查询资金池每日使用额度
                $sql = "SELECT SUM(money * quantity) money FROM {$this->table_voucher_money_pool_log} WHERE pool_id = {$money_pool_id} AND type = 'money' AND create_time >= $today AND create_time < $tomorrow";
                $total_used_money = $this->_db->selectOne($sql);
                if (intval($total_used_money->money) + $current_total_money > $money_pool['per_day_limit']) {
                    $message = '超出资金池每日额度';
                    return false;
                }
            }

            // 验证资金池每日额度
            if ($money_pool['per_day_people_limit'] !== null) {
                // 查询资金池每日使用额度
                $sql = "SELECT SUM(money * quantity) money FROM {$this->table_voucher_money_pool_log} WHERE pool_id = {$money_pool_id} AND type = 'money' AND member_id={$voucher_data['member_id']} AND create_time >= $today AND create_time < $tomorrow";
                $total_used_money = $this->_db->selectOne($sql);
                if (intval($total_used_money->money) + $current_total_money > $money_pool['per_day_people_limit']) {
                    $message = '此用户超出资金池每日额度';
                    return false;
                }
            }
        }

        return true;
    }

    // 添加券资金池记录
    private function _addVoucherMoneyPoolLog($create_voucher_id, $voucher_data)
    {
        if ($create_voucher_id <= 0 OR $voucher_data['money_pool_id'] <= 0 OR $voucher_data['member_id'] <= 0 OR $voucher_data['company_id'] <= 0) {
            return false;
        }

        $insertData = array(
            'create_voucher_id' => $create_voucher_id,
            'pool_id' => $voucher_data['money_pool_id'],
            'member_id' => $voucher_data['member_id'],
            'company_id' => $voucher_data['company_id'],
            'type' => $voucher_data['type'],
            'voucher_name' => $voucher_data['voucher_name'],
            'money' => isset($voucher_data['money']) ? $voucher_data['money'] : '0.000',
            'discount' => isset($voucher_data['discount']) ? $voucher_data['discount'] : '0.00',
            'quantity' => $voucher_data['count'],
            'source_type' => $voucher_data['source_type'],
            'create_time' => time(),
            'last_modified' => time(),
        );

        $res = $this->_db->table($this->table_voucher_money_pool_log)->insert($insertData);

        return $res ? true : false;
    }

    /**
     * @param $member_voucher_list
     * @param string $member_id
     * @return false|array
     */
    private function getVoucherValue($member_voucher_list, string $member_id)
    {
        if (!empty($member_voucher_list)) {
            $voucher_id_list = array();
            $id_type_mapping = [];
            foreach ($member_voucher_list as $id_item) {
                $voucher_id_list[] = $id_item->voucher_id;
                $id_type_mapping[$id_item->voucher_id] = $id_item->source_type;
            }
            // 只取出过期时间在三个月内的数据
            $from_time = strtotime("-3 month");
            $placeholderVoucherIdList = implode(',', array_fill(0, count($voucher_id_list), '?'));
            $sql = "select pv.*, pvc.company_id from {$this->table_voucher} pv join promotion_voucher_company pvc on pv.voucher_id=pvc.voucher_id
                  where pv.valid_time>$from_time and  pv.voucher_id in (" . $placeholderVoucherIdList . ")";
            $voucher_date_result = $this->_db->select($sql, $voucher_id_list);
            foreach ($voucher_date_result as &$item) {
                $item->source_type = $id_type_mapping[$item->voucher_id];
            }
            if (empty($voucher_date_result)) {
                \Neigou\Logger::General('action.voucher', array(
                    'action' => 'queryMemVoucherList',
                    'member_id' => $member_id,
                    'success' => 0,
                    'reason' => 'voucher_empty'
                ));
                return false;
            } else {
                return $voucher_date_result;
            }
        } else {
            \Neigou\Logger::General('action.voucher', array(
                'action' => 'queryMemVoucherList',
                'member_id' => $member_id,
                'success' => 0,
                'reason' => 'voucher_empty_1'
            ));
            return false;
        }
    }

}
