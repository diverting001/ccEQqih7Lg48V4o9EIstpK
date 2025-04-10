<?php
/**
 * Created by PhpStorm.
 * User: chuanbin
 * Date: 2017/10/18
 * Time: 19:57
 */

namespace App\Api\Model\Voucher;



class Voucher
{
    private $table_voucher = 'promotion_voucher';
    private $table_voucher_company = 'promotion_voucher_company';
    private $table_voucher_create_log = 'promotion_voucher_create_log';
    private $table_voucher_create_business = 'promotion_voucher_create_business';
    private $table_voucher_refund_rela = 'promotion_voucher_refund_rela';
    private $promotion_voucher_log = 'promotion_voucher_log';
    private $table_voucher_member = 'promotion_voucher_member';
    private $promotion_voucher_order_user = 'promotion_voucher_order_user';
    private $promotion_voucher_rules = 'promotion_voucher_rules';
    private $promotion_voucher_blacklist_rule = 'promotion_voucher_blacklist_rule';
    private $_db;

    public function __construct($db = '')
    {
        $this->_db = $db ? $db : app('api_db')->connection('neigou_store');
    }

    public function addVoucher($json_data)
    {
        \Neigou\Logger::General('action.voucher', array('action' => 'addVoucher'));
        $create_data_array = json_decode($json_data, true);
        if (empty($create_data_array) ||
            !isset($create_data_array['money']) ||
            !isset($create_data_array['count']) ||
            !isset($create_data_array['valid_time']) ||
            !isset($create_data_array['company_id']) ||
            !isset($create_data_array['op_id'])) {
            \Neigou\Logger::General(
                'action.voucher',
                array(
                    'action' => 'create',
                    'success' => 0,
                    'reason' => 'unvalid_params',
                    "params_data" => $json_data
                )
            );
            return false;//err 1
        }

        $this->_db->beginTransaction();
        $message = "";

        $create_result_id = $this->createVoucher($create_data_array, $message);

        if (!empty($create_result_id)) {
            $return_data['create_id'] = $create_result_id;
            $this->_db->commit();
            return $return_data;
        } else {
            $this->_db->rollback();
            return false;//err 1
        }
    }

    /**
     * @param string $voucher_number
     * @param string $memo
     * @return string
     */
    public function disableVoucher($voucher_number, $memo)
    {
        \Neigou\Logger::General('action.voucher', array('action' => 'disableVoucher', 'number' => $voucher_number));
        $this->_db->beginTransaction();
        // 用于锁住所有需要修改的代金券
        $this->_db->table($this->table_voucher)->where('number', $voucher_number)->sharedLock()->first();

        $data['status'] = 'disabled';

        $result = $this->_db->table($this->table_voucher)->where([
            ['number', $voucher_number],
            ['status', 'normal']
        ])->update($data);
        $this->_db->commit();
        if (empty($result)) {
            \Neigou\Logger::General(
                'action.voucher',
                array(
                    'action' => 'disable',
                    'success' => 0,
                    'number' => $voucher_number
                )
            );
            return false;
        } else {
            $result = $this->_db->table($this->table_voucher)->where('number', $voucher_number)->first();
            if (!empty($result)) {
                $this->addOperateLog($result->voucher_id, 0, 'system_core', 'cancel', $memo);
            }
            \Neigou\Logger::General(
                'action.voucher',
                array(
                    'action' => 'disable',
                    'success' => 1,
                    'number' => $voucher_number
                )
            );
            return true;
        }
    }

    /**
     * @param int $create_id
     * @param string $memo
     * @return string
     */
    public function disableVoucherForCreateID($create_id, $memo)
    {
        \Neigou\Logger::General(
            'action.voucher',
            array(
                'action' => 'disableVoucherForCreateID',
                'create_id' => $create_id
            )
        );
        $this->_db->beginTransaction();

        // 用于锁住所有需要修改的代金券
        $this->_db->table($this->table_voucher)->where('create_id', $create_id)->sharedLock()->first();

        $data['status'] = 'disabled';
        $result = $this->_db->table($this->table_voucher)->where([
            ['create_id', $create_id],
            ['status', 'normal']
        ])->update($data);
        $this->_db->commit();

        if (empty($result)) {
            \Neigou\Logger::General(
                'action.voucher',
                array(
                    'action' => 'disablemult',
                    'success' => 0,
                    'number' => $create_id
                )
            );
            return false;
        } else {
            $create_data['disabled'] = 1;
            $this->_db->table($this->table_voucher_create_log)->where('id', $create_id)->update($create_data);

            $result = $this->_db->table($this->table_voucher)->where([
                ['create_id', $create_id],
                ['status', 'disabled']
            ])->get()->all();
            foreach ($result as $item) {
                $this->addOperateLog($item->voucher_id, 0, 'system_core', 'cancel', $memo);
            }

            \Neigou\Logger::General(
                'action.voucher',
                array(
                    'action' => 'disablemult',
                    'success' => 1,
                    'number' => $create_id
                )
            );
            return true;
        }
    }

    // 创建内购券，供内部调用，需要保证再事务内完成
    public function createVoucher($create_params, &$message)
    {
        if (empty($create_params) ||
            !isset($create_params['money']) ||
            !isset($create_params['count']) ||
            !isset($create_params['valid_time']) ||
            !isset($create_params['company_id']) ||
            !isset($create_params['op_id'])) {
            \Neigou\Logger::General('action.voucher', array(
                'action' => 'createVoucher',
                'success' => 0,
                'reason' => 'unvalid_params',
                "params_data" => json_encode($create_params)
            ));
            $message = "参数不正确";
            return false;
        }
        $type = $create_params['type'] ? $create_params['type'] : 'money';

        // time_type券有效期类型，默认day_end；
        // 其中 param_time：传参时间；day_end：传参时间的 23:59:59；
        $time_type = !empty($create_params['time_type']) ? $create_params['time_type'] : 'day_end';
        $day_end = strtotime(date("Y-m-d", $create_params['valid_time']) . " 23:59:59");

        switch ($time_type) {
            case 'day_end':
                $create_params['valid_time'] = $day_end;
                break;
            case 'param_time':
                $create_params['valid_time'] = $create_params['valid_time'];
                break;
            default:
                $create_params['valid_time'] = $day_end;
                break;
        }

        $create_time = time();
        $b_create_suc = true;
        $op_data = array(
            'op_id' => $create_params['op_id'],
            'op_name' => isset($create_params['op_name']) ? $create_params['op_name'] : ' ',
            'log_text' => $create_params['comment'],
            'create_time' => $create_time,
            'type' => $type,
            'discount' => isset($create_params['discount']) ? $create_params['discount'] : '100.0',
            'money' => $create_params['money'],
            'quantity' => $create_params['count'],
            'company_id' => $create_params['company_id'],
            'valid_time' => $create_params['valid_time'],
            'start_time' => isset($create_params['start_time']) ? $create_params['start_time'] : 0,
            'voucher_name' => $create_params['voucher_name'],
            'external_id' => isset($create_params['external_id']) ? $create_params['external_id'] : null,
            'source_type' => isset($create_params['source_type']) ? $create_params['source_type'] : null,
        );
        // 券类型
        if (isset($create_params['source_type'])) {
            $op_data['source_type'] = $create_params['source_type'];
        }

        // 外部活动 ID
        if (isset($create_params['external_id'])) {
            $op_data['external_id'] = intval($create_params['external_id']);
        }

        $create_result_id = $this->_db->table($this->table_voucher_create_log)->insertGetId($op_data);
        if (!empty($create_result_id)) {
            if (isset($create_params['source']) && isset($create_params['money_company'])) {
                $business_data = array(
                    'create_id' => $create_result_id,
                    'source' => $create_params['source'],
                    'money_company' => $create_params['money_company'],
                    'business_1' => isset($create_params['business_1']) ? $create_params['business_1'] : 0,
                    'business_2' => isset($create_params['business_2']) ? $create_params['business_2'] : 0,
                    'business_3' => isset($create_params['business_3']) ? $create_params['business_3'] : 0,
                    'business_4' => isset($create_params['business_4']) ? $create_params['business_4'] : 0,
                    'business_5' => isset($create_params['business_5']) ? $create_params['business_5'] : 0,
                    'business_6' => isset($create_params['business_6']) ? $create_params['business_6'] : 0,
                    'business_7' => isset($create_params['business_7']) ? $create_params['business_7'] : 0,
                    'business_8' => isset($create_params['business_8']) ? $create_params['business_8'] : 0,
                    'business_9' => isset($create_params['business_9']) ? $create_params['business_9'] : 0,
                    'business_10' => isset($create_params['business_10']) ? $create_params['business_10'] : 0,
                    'create_time' => time(),
                    'update_time' => time()
                );
                $business_res = $this->_db->table($this->table_voucher_create_business)->insertGetId($business_data);
                if (empty($business_res)) {
                    \Neigou\Logger::General(
                        'action.voucher',
                        array(
                            'action' => 'createVoucher',
                            'reason' => 'create_business',
                            'sparam1' => json_encode($business_data)
                        )
                    );
                    $message = "批次财务模型字段设置失败";
                    return false;
                }
            }

            $rule_name = "";
            if (isset($create_params['rule_id'])) {
                $tmp_rule_name = $this->_db->table($this->promotion_voucher_rules)
                    ->where('rule_id', $create_params['rule_id'])
                    ->value('name');
                $rule_name = isset($create_params['rule_name']) && $create_params['rule_name'] ? $create_params['rule_name'] : $tmp_rule_name;
            }
            for ($i = 0; $i < intval($create_params['count']); $i++) {
                $key = $this->genKey(config('neigou.KEY_GEN_TOKEN', 'forge') . $i);
                $data = array(
                    'number' => $key,
                    'type' => $type,
                    'discount' => isset($create_params['discount']) ? $create_params['discount'] : '100.0',
                    'money' => $create_params['money'],
                    'start_time' => isset($create_params['start_time']) ? $create_params['start_time'] : 0,
                    'valid_time' => $create_params['valid_time'],
                    'status' => 'normal',
                    'create_time' => $create_time,
                    'last_modified' => $create_time,
                    'exclusive' => isset($create_params['exclusive']) ? $create_params['exclusive'] : 1,
                    'num_limit' => isset($create_params['num_limit']) ? $create_params['num_limit'] : 1,
                    'create_id' => $create_result_id,
                    'rule_name' => $rule_name,
                    'voucher_nature' => isset($create_params['voucher_nature']) ? $create_params['voucher_nature'] : 0,
                    'voucher_name' => $create_params['voucher_name']
                );
                if (isset($create_params['rule_id'])) {
                    $data['rule_id'] = $create_params['rule_id'];
                }
                $result_voucher_id = $this->_db->table($this->table_voucher)->insertGetId($data);
                if (!empty($result_voucher_id)) {
                    $company_data['voucher_id'] = $result_voucher_id;
                    $company_data['company_id'] = $create_params['company_id'];
                    $result_com = $this->_db->table($this->table_voucher_company)->insert($company_data);

                    if (!empty($result_com)) {
                        \Neigou\Logger::General(
                            'action.voucher',
                            array(
                                'action' => 'createVoucher',
                                'success' => 1,
                                'voucher_key' => $key
                            )
                        );
                    } else {
                        \Neigou\Logger::General(
                            'action.voucher',
                            array(
                                'action' => 'createVoucher',
                                'success' => 0,
                                'reason' => 'result_com',
                                'voucher_key' => $key
                            )
                        );
                        $message = "创建内购券失败";
                        $b_create_suc = false;
                        break;
                    }
                    $this->addOperateLog(
                        $result_voucher_id,
                        $create_params['op_id'],
                        $create_params['op_name'],
                        'create',
                        $create_params['comment']
                    );
                } else {
                    \Neigou\Logger::General(
                        'action.voucher',
                        array(
                            'action' => 'createVoucher',
                            'success' => 0,
                            'reason' => 'sqladd',
                            'voucher_key' => $key
                        )
                    );
                    $message = "创建内购券失败";
                    $b_create_suc = false;
                    break;
                }
            }
        } else {
            \Neigou\Logger::General(
                'action.voucher',
                array(
                    'action' => 'createVoucher',
                    'success' => 0,
                    'reason' => 'createsqladd'
                )
            );
            $message = "创建内购券失败";
            $b_create_suc = false;
        }
        if ($b_create_suc) {
            return $create_result_id;
        } else {
            return false;
        }
    }

    public function createRefundVoucherRela($oldVoucherId, $createId)
    {
        return $this->_db->table($this->table_voucher_refund_rela)
            ->insertGetId(array(
                "old_voucher_id" => $oldVoucherId,
                "new_create_id" => $createId,
                "create_time" => time()
            ));
    }

    public function queryRefundVoucherMoney($voucherId)
    {
        return $this->_db->table($this->table_voucher_refund_rela)
            ->leftJoin('promotion_voucher', 'promotion_voucher_refund_rela.new_create_id', '=',
                'promotion_voucher.create_id')
            ->where(['promotion_voucher_refund_rela.old_voucher_id' => $voucherId])
            ->sum("promotion_voucher.money");
    }

    public function queryVoucher($voucher_number)
    {
        $result = $this->_db->table($this->table_voucher)->where('number', $voucher_number)->first();
        if (empty($result)) {
            \Neigou\Logger::Debug(
                'action.voucher',
                array(
                    'action' => 'query',
                    'success' => 0,
                    'number' => $voucher_number
                )
            );
            return false;
        } else {
            $voucher_company = $this->_db->table($this->table_voucher_company)
                ->where('voucher_id', $result->voucher_id)
                ->first();
            if (!empty($voucher_company)) {
                $result->company_id = $voucher_company->company_id;
            }
            if (isset($result) && empty($result->rule_name)) {
                $result->rule_name = "全场通用";
            }
            return $result;
        }
    }


    /**
     *
     * 批量查询
     *
     * @param  array  $voucher_number
     * @return mixed
     */
    public function queryVoucherList(array $voucher_number)
    {
        $voucherList = $this->_db
            ->table($this->table_voucher.' as v')
            ->leftJoin($this->table_voucher_company." as vc", "v.voucher_id", '=', 'vc.voucher_id')
            ->select('v.*', 'vc.company_id')
            ->whereIn('v.number', $voucher_number)
            ->get();

        return $voucherList;
    }

    public function queryVoucherByVoucherId($voucher_id)
    {
        $result = $this->_db->table($this->table_voucher)->where('voucher_id', $voucher_id)->first();
        if (empty($result)) {
            \Neigou\Logger::Debug(
                'action.voucher',
                array(
                    'action' => 'query',
                    'success' => 0,
                    'number' => $voucher_id
                )
            );
            return false;
        } else {
            $voucher_company = $this->_db
                ->table($this->table_voucher_company)
                ->where('voucher_id', $result->voucher_id)
                ->first();
            if (!empty($voucher_company)) {
                $result->company_id = $voucher_company->company_id;
            }
            if (isset($result) && empty($result->rule_name)) {
                $result->rule_name = "全场通用";
            }
            return $result;
        }
    }

    /** 生成券码
     *
     * @param $params
     * @return string
     * @author liuming
     */
    private function genKey($params)
    {
        //循环生成卡密,直到获取到的卡密不是纯数字为止
        while (true) {
            $checkCode = strtoupper(substr(md5(uniqid(rand(), true) . $params), 8, 16));
            if (!is_numeric($checkCode)) {
                return $checkCode;
            }
        }
    }

    private function addOperateLog($voucher_id, $op_id, $op_name, $behavior, $log_text)
    {
        $data_change_log = array(
            'voucher_id' => $voucher_id,
            'op_id' => $op_id,
            'op_name' => $op_name,
            'behavior' => $behavior,
            'log_text' => $log_text,
            'create_time' => time()
        );
        $this->_db->table($this->promotion_voucher_log)->insert($data_change_log);
    }

    public function useVoucher($voucher_number, $member_id, $order_id, $use_money, $memo)
    {
        $voucher_number_list = json_decode($voucher_number, true);
        $voucher_number_list = array_unique($voucher_number_list);
        $buse_suc = true;
        $msg = "fail";
        $this->_db->beginTransaction();
        $voucher_count = count($voucher_number_list);
        $total_used_money = 0;
        foreach ($voucher_number_list as $voucher_number) {
            \Neigou\Logger::General(
                'action.voucher',
                array(
                    'action' => 'useVoucher',
                    'number' => $voucher_number,
                    'member_id' => $member_id,
                    'order_id' => $order_id,
                    'use_money' => $use_money
                )
            );

            $voucher_result = $this->_db->table($this->table_voucher)->where('number',
                $voucher_number)->sharedLock()->first();

            if (empty($voucher_result)) {
                \Neigou\Logger::General(
                    'action.voucher',
                    array(
                        'action' => 'use',
                        'success' => 0,
                        'number' => $voucher_number,
                        'reason' => 'notexist'
                    )
                );
                $buse_suc = false;
                $msg = "代金券不存在";
                break;
            } else {
                if ($voucher_result->valid_time < time()) {
                    \Neigou\Logger::General(
                        'action.voucher',
                        array(
                            'action' => 'use',
                            'success' => 0,
                            'number' => $voucher_number,
                            'reason' => "time exceed"
                        )
                    );
                    $buse_suc = false;
                    $msg = "代金券已过期";
                    break;
                }
                if ($voucher_result->start_time > time()) {
                    \Neigou\Logger::General(
                        'action.voucher',
                        array(
                            'action' => 'use',
                            'success' => 0,
                            'number' => $voucher_number,
                            'reason' => "time no start"
                        )
                    );
                    $buse_suc = false;
                    $msg = "代金券未开始";
                    break;
                }
                // 代金券使用数量超出限定数量
                if ($voucher_result->num_limit < $voucher_count) {
                    \Neigou\Logger::General(
                        'action.voucher',
                        array(
                            'action' => 'use',
                            'success' => 0,
                            'number' => $voucher_number,
                            'reason' => "quanlity exceed"
                        )
                    );
                    $buse_suc = false;
                    $msg = "代金券超出数目限额";
                    break;
                }

                if ($voucher_result->status == 'normal') {
                    $data['status'] = 'lock';
                    $ret = $this->_db->table($this->table_voucher)
                        ->where('number', $voucher_number)
                        ->update($data);
                    if (!empty($ret)) {
                        // 使用金额计算，外部传入的$use_money总金额计算出每一个代金券使用的金额。
                        $cur_used_money = min($use_money - $total_used_money, $voucher_result->money);
                        $data_use = array(
                            'voucher_id' => $voucher_result->voucher_id,
                            'order_id' => $order_id,
                            'member_id' => $member_id,
                            'use_time' => time(),
                            'use_money' => $cur_used_money
                        );
                        $total_used_money += $voucher_result->money;
                        $total_used_money = min($total_used_money, $use_money);

                        $this->_db->table($this->promotion_voucher_order_user)->insert($data_use);

                        $this->addOperateLog($voucher_result->voucher_id, 0, $member_id, 'lock', $memo);
                        \Neigou\Logger::General(
                            'action.voucher',
                            array(
                                'action' => 'use',
                                'success' => 1,
                                'number' => $voucher_number
                            )
                        );
                    } else {
                        \Neigou\Logger::General(
                            'action.voucher',
                            array(
                                'action' => 'use',
                                'success' => 0,
                                'number' => $voucher_number,
                                'reason' => 'updatesql'
                            )
                        );
                        $buse_suc = false;
                        break;
                    }
                } else {
                    \Neigou\Logger::General(
                        'action.voucher',
                        array(
                            'action' => 'use',
                            'success' => 0,
                            'number' => $voucher_number,
                            'reason' => 'already_used'
                        )
                    );
                    $msg = "代金券已使用";
                    $buse_suc = false;
                    break;
                }
            }
        }
        if ($buse_suc) {
            $this->_db->commit();
            return true;
        } else {
            $this->_db->rollback();
            return $msg;
        }
    }

    private function checkVoucher($voucher)
    {
        if (empty($voucher)) {
            return array('status' => false, 'msg' => '代金券不存在');
        }
        if ($voucher->valid_time < time()) {
            return array('status' => false, 'msg' => '代金券已过期');
        }
        if ($voucher->start_time < time()) {
            return array('status' => false, 'msg' => '代金券未开始');
        }
        return array('status' => true, 'msg' => 'succ');
    }

    public function multiUseVoucher($voucher_data)
    {
        $this->_db->beginTransaction();
        $exec_status = true;
        $msg = 'success';
        foreach ($voucher_data as $key => $item) {
            $voucher_number_list = $item['voucher_number_list'];//代金券 number
            $member_id = $item['member_id'];//代金券 number
            $order_id = $item['order_id'];//代金券 number
            $use_money = $item['use_money'];//代金券 number
            $memo = $item['memo'];//代金券 number
            $voucher_count = count($voucher_number_list);//优惠券数量 用于检测订单用券总量控制
            $total_used_money = 0;
            foreach ($voucher_number_list as $voucher_number) {
                $voucher_result = $this->_db->table($this->table_voucher)->where('number',
                    $voucher_number)->sharedLock()->first();
                if (empty($voucher_result)) {
                    $exec_status = false;
                    $msg = $voucher_number . "代金券不存在";
                    break;
                } else {
                    if ($voucher_result->valid_time < time()) {
                        $exec_status = false;
                        $msg = $voucher_number . "代金券已过期";
                        break;
                    }
                    if ($voucher_result->start_time > time()) {
                        $exec_status = false;
                        $msg = $voucher_number . "代金券未开始";
                        break;
                    }
                    // 代金券使用数量超出限定数量
                    if ($voucher_result->num_limit < $voucher_count) {
                        $exec_status = false;
                        $msg = $voucher_number . "代金券超出数目限额";
                        break;
                    }
//                    print_r($voucher_result);die;
                    if ($voucher_result->status == 'normal') {
                        $data['status'] = 'lock';
                        $ret = $this->_db->table($this->table_voucher)->where('number', $voucher_number)->update($data);
//                        var_dump($ret);die;
                        if (!empty($ret)) {
                            // 使用金额计算，外部传入的$use_money总金额计算出每一个代金券使用的金额。
                            if ($voucher_result->type == 'money') {
                                $cur_used_money = min($use_money - $total_used_money, $voucher_result->money);
                            } else {
                                //折扣券使用金额为传入金额
                                $cur_used_money = $use_money;
                            }
                            $data_use = array(
                                'voucher_id' => $voucher_result->voucher_id,
                                'order_id' => $order_id,
                                'member_id' => $member_id,
                                'use_time' => time(),
                                'use_money' => $cur_used_money
                            );
                            $total_used_money += $voucher_result->money;
                            $total_used_money = min($total_used_money, $use_money);
                            $this->_db->table($this->promotion_voucher_order_user)->insert($data_use);
                            $this->addOperateLog($voucher_result->voucher_id, 0, $member_id, 'lock', $memo);
                        } else {
                            $msg = $voucher_number . "代金券使用失败";
                            $exec_status = false;
                            break;
                        }
                    } else {
                        $msg = $voucher_number . "代金券已使用";
                        $exec_status = false;
                        break;
                    }
                }
            }
        }
        if ($exec_status) {
            $this->_db->commit();
            $out['status'] = true;
            return $out;
        } else {
            $this->_db->rollback();
            return array('status' => false, 'msg' => $msg);
        }
    }

    public function queryOrderVoucher($order_id)
    {
        \Neigou\Logger::Debug('action.voucher', array('action' => 'queryOrderVoucher', 'order_id' => $order_id));
        $order_voucher_list = $this->_db->table($this->promotion_voucher_order_user)->where('order_id',
            $order_id)->get()->all();
        if (empty($order_voucher_list)) {
            \Neigou\Logger::Debug(
                'action.voucher',
                array(
                    'action' => 'query_order',
                    'success' => 0,
                    'order_id' => $order_id
                )
            );
            return false;
        } else {
            $voucher_data_list = array();
            foreach ($order_voucher_list as $order_voucher_item) {
                $voucher_id = $order_voucher_item->voucher_id;
                $result = $this->_db->table($this->table_voucher)->where('voucher_id', $voucher_id)->first();
                if ($result) {
                    $result->member_id = $order_voucher_item->member_id;
                    $result->use_money = $order_voucher_item->use_money;
                    $voucher_data_list[] = $result;
                }
            }
            \Neigou\Logger::Debug(
                'action.voucher',
                array(
                    'action' => 'query_order',
                    'success' => 1,
                    'order_id' => $order_id
                )
            );
            return $voucher_data_list;
        }
    }

    public function queryVoucherUsedInfo($voucher_id)
    {
        //查询voucher使用过的订单
        $data['order'] = $this->_db->table($this->promotion_voucher_order_user)->where('voucher_id',
            $voucher_id)->get()->all();

        //查询voucher log
        $data['log'] = $this->_db->table($this->promotion_voucher_log)
            ->where('voucher_id', $voucher_id)
            ->get()
            ->all();

        //查询用户绑定信息
        $data['member'] = $this->_db->table($this->table_voucher_member)
            ->where('voucher_id', $voucher_id)
            ->get()
            ->all();

        return $data;
    }

    public function queryMemberVoucher($member_id,$company_id = 0)
    {
        \Neigou\Logger::Debug('action.voucher', array('action' => 'queryMemberVoucher', 'member_id' => $member_id,'company_id'=>$company_id));
        $where = [
            ['pvou.member_id', $member_id],
            ['pvou.disabled', 0],
        ];
        if (!empty($company_id)) {
            $where[] = ['pvc.company_id', $company_id];
        }
        $model = $this->_db->table($this->promotion_voucher_order_user . ' as pvou');
        //传入了公司id，根据公司id进行联查，如果没有指定公司id，则最多查询1000条进行二次筛选和展示
        if (!empty($company_id)) {
            $model->leftJoin('promotion_voucher_company as pvc', 'pvou.voucher_id', '=', 'pvc.voucher_id');
        } else {
            $model->limit(1000);
        }
        $order_voucher_list = $model->selectRaw('pvou.*')->where($where)->orderBy('pvou.id', 'desc')->get()->all();
        if (empty($order_voucher_list)) {
            \Neigou\Logger::Debug(
                'action.voucher',
                array(
                    'action' => 'query_member',
                    'success' => 0,
                    'member_id' => $member_id
                )
            );
            return false;
        } else {
            $voucher_id_list = array();
            $id_type_mapping = [];
            foreach ($order_voucher_list as $order_voucher_item) {
                $voucher_id_list[] = $order_voucher_item->voucher_id;
            }
            $vm_model = $this->_db->table($this->table_voucher_member);
            $vm_model->whereIn('voucher_id',$voucher_id_list);
            if (!empty($company_id)) {
                $vm_model->where(['company_id'=>$company_id]);
            }
            $member_voucher_list = $vm_model->select(['voucher_id','source_type'])->get()->toArray();
            if(empty($member_voucher_list)){
                return false;
            }
            foreach ($member_voucher_list as $id_item) {
                $id_type_mapping[$id_item->voucher_id] = $id_item->source_type;
            }
            // 只取出过期时间在三个月内的数据
            $from_time = strtotime("-3 month");
            $voucher_data_list = $tmp_voucher_data_list = $tmp_voucher_ids = array();
            foreach ($order_voucher_list as $order_voucher_item) {
                if (empty($id_type_mapping[$order_voucher_item->voucher_id])) {
                    continue;
                }
                $tmp_voucher_ids[] = $order_voucher_item->voucher_id;
                $tmp_voucher_data_list[$order_voucher_item->voucher_id] = get_object_vars($order_voucher_item);
            }

            $voucher_data = $this->_db->table($this->table_voucher)->whereIn('voucher_id', $tmp_voucher_ids)->get()->toArray();
            foreach ($voucher_data as $voucher_data_v){
                if ($voucher_data_v->valid_time < $from_time) {
                    continue;
                }
                if(!empty($tmp_voucher_data_list[$voucher_data_v->voucher_id])){
                    if (isset($voucher_data_v) && empty($voucher_data_v->rule_name)) {
                        $voucher_data_v->rule_name = "全场通用";
                    }
                    foreach ($tmp_voucher_data_list[$voucher_data_v->voucher_id] as $key => $value) {
                        $voucher_data_v->$key = $value;
                    }
                    $voucher_data_v->source_type = $id_type_mapping[$voucher_data_v->voucher_id];
                    $voucher_data_list[] = $voucher_data_v;
                }
            }
            \Neigou\Logger::Debug(
                'action.voucher',
                array(
                    'action' => 'query_member',
                    'success' => 1,
                    'member_id' => $member_id
                )
            );
            return $voucher_data_list;
        }
    }

    /**
     * @param string $json_data
     * @param string $json_filter_data
     * @return string
     */
    public function queryVoucherWithRule($params_data_array, $filter_data)
    {
        if (!isset($params_data_array["voucher_number"])) {
            if (isset($params_data_array['voucher_data'])) {
                //走新的支持
                foreach ($params_data_array['voucher_data'] as $key => $val) {
                    $filter_data = $val['filter_data'];
                    $voucher_number = $val['voucher_number'];
                    $filter_data['version'] = $params_data_array['version'];
                    $filter_data['newcart'] = $params_data_array['newcart'];
                    $params_data_array['voucher_data'][$key]['result'] = $this->query_voucher_with_rule($voucher_number,
                        $filter_data);
                }
                $params_data_array = json_encode($params_data_array);
                return json_decode($params_data_array);
            }
            \Neigou\Logger::Debug(
                'action.voucher',
                array(
                    'action' => 'queryVoucherWithRule',
                    'success' => 0,
                    'reason' => 'invalid_params'
                )
            );
            return "无效的内购券";
        }
        $voucher_number = $params_data_array["voucher_number"];
        \Neigou\Logger::Debug(
            'action.voucher',
            array(
                'action' => 'queryVoucherWithRule',
                'number' => $voucher_number
            )
        );
        $voucher_data = $this->_db->table($this->table_voucher)->where('number', $voucher_number)->first();
        if (empty($voucher_data)) {
            \Neigou\Logger::Debug(
                'action.voucher',
                array(
                    'action' => 'queryVoucherWithRule',
                    'success' => 0,
                    'reason' => 'rule_not_match'
                )
            );
            return "无效的内购券";
        }
        if ($voucher_data->start_time > time()) {
            return '代金券未开始';
        }

        $match_data = $this->useRuleFilter($voucher_number, $filter_data);
        $match_use_money = $match_data['match_use_money'];
        if (!$match_use_money) {
            $rule_name = $voucher_data->rule_name;
            \Neigou\Logger::Debug(
                'action.voucher',
                array(
                    'action' => 'queryVoucherWithRule',
                    'success' => 0,
                    'reason' => 'rule_not_match'
                )
            );
            return "本订单不符合此内购券使用规则({$rule_name})";
        }

        if (empty($voucher_data)) {
            \Neigou\Logger::Debug(
                'action.voucher',
                array(
                    'action' => 'query',
                    'success' => 0,
                    'number' => $voucher_number
                )
            );
            return false;
        } else {
            $voucher_company = $this->_db->table($this->table_voucher_company)->where('voucher_id',
                $voucher_data->voucher_id)->first();
            if (!empty($voucher_company)) {
                $result['company_id'] = $voucher_company->company_id;
            }
            if (isset($voucher_data) && empty($voucher_data->rule_name)) {
                $voucher_data['rule_name'] = "全场通用";
            }
            \Neigou\Logger::Debug(
                'action.voucher',
                array(
                    'action' => 'query',
                    'success' => 1,
                    'number' => $voucher_number
                )
            );
            $voucher_money = $voucher_data->money;
            if ($voucher_data->type == 'discount') {
                $match_use_money = $match_use_money * (1 - $voucher_data->discount / 100);
                $voucher_money = $match_use_money;
            }
            $voucher_data->match_use_money = min($voucher_money, $match_use_money);
            $voucher_data->product_bn_list = $match_data['product_bn_list'];
            return $voucher_data;
        }
    }

    /**
     * 统一返回
     * @param $status
     * @param $msg
     * @param $local
     * @param $bn_list
     * @return mixed
     */
    public function voucher_out_msg($status, $msg, $bn_list, $local)
    {
        $ret['status'] = $status;
        $ret['msg'] = $msg;
        $ret['need_money'] = $local['need'];
        $ret['limit_cost'] = $local['limit_cost'];
        $ret['product_bn_list'] = $bn_list;
        $ret['match_use_money'] = $local['match_money'];
        return $ret;
    }

    public function query_voucher_with_rule($no, $filter)
    {
        $voucher_data = $this->_db->table($this->table_voucher)->where('number', $no)->first();
        if ($voucher_data->start_time > time()) {
            $ret['msg'] = '代金券未开始';
            $ret['data'] = false;
            $ret['status'] = 0;
            return $ret;
        }
        $match_data = $this->useRuleFilter($no, $filter);
        $match_use_money = $match_data['match_use_money'];
        $product_amount = $match_data['match_use_money'];
        if (!$match_use_money) {
            $rule_name = $voucher_data->rule_name;
            $rule_data = $this->_db->table($this->promotion_voucher_rules)
                ->where('rule_id', $voucher_data->rule_id)
                ->first();
            $condition = unserialize($rule_data->rule_condition);
            $limit_cost = $condition[0]['filter_rule']['limit_cost'];
            ///////////////////
            $local['need'] = $limit_cost;
            $local['limit_cost'] = $limit_cost;
            $local['match_money'] = 0;
            return $this->voucher_out_msg(0, "不符合使用规则({$rule_name})", array(), $local);
        }
        if (empty($voucher_data)) {
            ///////////////////
            $local['need'] = 0;
            $local['limit_cost'] = 0;
            $local['match_money'] = 0;
            return $this->voucher_out_msg(0, "无效的内购券", array(), $local);
        } else {
            $voucher_company = $this->_db->table($this->table_voucher_company)->where('voucher_id',
                $voucher_data->voucher_id)->first();
            if (!empty($voucher_company)) {
                $result['company_id'] = $voucher_company->company_id;
            }
            if (isset($voucher_data) && empty($voucher_data->rule_name)) {
                $voucher_data->rule_name = "全场通用";
            }
            $voucher_money = $voucher_data->money;
            if ($voucher_data->type == 'discount') {
                $match_use_money = $match_use_money * (1 - $voucher_data->discount / 100);
                $voucher_money = $match_use_money;
            }
            if ($product_amount < $match_data['limit_cost']) {
                ///////////////////
                $local['need'] = $match_data['need_money'];
                $local['limit_cost'] = $match_data['limit_cost'];
                $local['match_money'] = 0;
                return $this->voucher_out_msg(0, "订单金额不满足，需凑单使用", $match_data['product_bn_list'], $local);
            } else {
                ///////////////////
                $local['need'] = 0;
                $local['limit_cost'] = $match_data['limit_cost'];
                $local['match_money'] = min($voucher_money, $match_use_money);
                return $this->voucher_out_msg(1, "当前可用", $match_data['product_bn_list'], $local);
            }
        }
    }

    // 使用规则过滤，验证此代金券能不能使用
    public function useRuleFilter($voucher_number, $filter_data)
    {
        if (empty($voucher_number)) {
            return false;
        }
        $voucher_data = $this->_db->table($this->table_voucher)->where('number', $voucher_number)->first();
        if (empty($voucher_data)) {
            return false;
        }
        $rule_id = $voucher_data->rule_id;
        if (empty($rule_id)) {
            return true;
        }

        $rule_data = $this->_db->table($this->promotion_voucher_rules)->where('rule_id', $rule_id)->first();


        if (empty($rule_data) || $rule_data->disabled == 1) {
            return false;
        }
        $ret = true;
        $condition_array = unserialize($rule_data->rule_condition);
        $condition_item = $condition_array[0];
        $str_processor = $condition_item['processor'];
        if (empty($str_processor)) {
            return false;
        }

        $str_processor = 'App\Api\Model\Voucher\Rule\\' . $str_processor;
        $processor = new $str_processor;
        $func_name = !empty($filter_data['version']) ? 'matchV' . intval($filter_data['version']) : 'match';
        if (method_exists($processor, $func_name)) {
            //
            $time = time();
            $retBlackList = $this->_db->table($this->promotion_voucher_blacklist_rule)->where([
                ['type', 1],
                ['start_time', '<=', $time],
                ['end_time', '>=', $time]
            ])->get()->all();

            $ruleBlackList = array();
            foreach ($retBlackList as $black_item) {
                if (!empty($black_item->rule)) {
                    $rule = json_decode($black_item->rule, true);
                    $ruleBlackList[$rule['bn']] = $rule['bn'];
                }
            }
            $condition_item['filter_rule']['ret_black_list'] = $ruleBlackList;
            //

            $match_data = $processor->$func_name($condition_item['filter_rule'], $filter_data);
//            print_r($filter_data);
            if (empty($match_data)) {
                $ret = false;
            } else {
                $ret = $match_data;
            }
        }
        return $ret;
    }

    public function MultiUseVoucherWithRule($voucher_data)
    {
        $this->_db->beginTransaction();
        $buse_suc = true;
        $msg = "fail";
        $used_voucher_info = array();
        foreach ($voucher_data as $key => $item) {
            $voucher_number_list = array_unique($item['voucher_number_list']);
            $filter_data = $item['filter_data'];
            $voucher_match_money_data = array();
            foreach ($voucher_number_list as $voucher_number) {
//                print_r($filter_data);die;
                $res = $this->useRuleFilter($voucher_number, $filter_data);
                if (!$res) {
                    return $voucher_number . "代金券不符合使用规则";
                } else {
                    $voucher_match_money_data[$voucher_number] = $res;
                }
            }
            if (!$voucher_match_money_data) {
                return "代金券不符合使用规则";
            }
            $member_id = $item["member_id"];
            $order_id = $item["order_id"];
            $use_money = $item["use_money"];
            $memo = $item["memo"];
            $voucher_count = count($voucher_number_list);
            $total_used_money = 0;


            foreach ($voucher_number_list as $voucher_number) {
                $voucher_result = $this->_db->table($this->table_voucher)->where('number',
                    $voucher_number)->sharedLock()->first();
                if (empty($voucher_result)) {
                    $buse_suc = false;
                    $msg = $voucher_number . "内购券券码异常";
                    break;
                }
                $voucher_member_result = $this->_db->table('promotion_voucher_member')->where('voucher_id',
                    $voucher_result->voucher_id)->first();
                if (!empty($voucher_member_result) && $voucher_member_result->member_id != $member_id) {
                    $buse_suc = false;
                    $msg = $voucher_number . "内购券已绑定其他账号";
                    break;
                }
                // 内购券可使用的金额
                $voucher_match_money = $voucher_match_money_data[$voucher_result->number]['match_use_money'];
                $voucher_use_money = min($voucher_match_money, $voucher_result->money);
                if ($voucher_result->type == 'discount') {
                    $voucher_match_money = $voucher_match_money * (1 - $voucher_result->discount / 100);
                    $voucher_use_money = $voucher_match_money;
                }
                if (!$voucher_use_money) {
                    $buse_suc = false;
                    $msg = $voucher_number . "内购券金额异常";
                    break;
                }
                if (empty($voucher_result)) {
                    $buse_suc = false;
                    $msg = $voucher_number . "代金券不存在";
                    break;
                } else {
                    if ($voucher_result->valid_time < time()) {
                        $buse_suc = false;
                        $msg = $voucher_number . "内购券已过期";
                        break;
                    }
                    if ($voucher_result->start_time > time()) {
                        $buse_suc = false;
                        $msg = $voucher_number . "代金券未开始";
                        break;
                    }
                    // 代金券使用数量超出限定数量
                    if ($voucher_result->num_limit < $voucher_count) {
                        $buse_suc = false;
                        $msg = $voucher_number . "内购券超出数目限额";
                        break;
                    }
                    if ($voucher_result->status == 'normal') {
                        $where['number'] = $voucher_number;
                        $data['status'] = 'lock';
                        $ret = $this->_db->table($this->table_voucher)->where($where)->update($data);
                        if (!empty($ret)) {
                            // 使用金额计算，外部传入的$use_money总金额计算出每一个代金券使用的金额。
                            $cur_used_money = min($use_money - $total_used_money, $voucher_use_money);
                            $data_use = array(
                                'voucher_id' => $voucher_result->voucher_id,
                                'order_id' => $order_id,
                                'member_id' => $member_id,
                                'use_time' => time(),
                                'use_money' => $cur_used_money
                            );
                            $total_used_money += $voucher_use_money;
                            $total_used_money = min($total_used_money, $use_money);
                            $this->_db->table($this->promotion_voucher_order_user)->insert($data_use);
                            $used_voucher_info[$key]['order_id'] = $order_id;
                            $used_voucher_info[$key]['match_info'][$voucher_result->number] = array(
                                "product_bn_list" => $voucher_match_money_data[$voucher_result->number]['product_bn_list'],
                                "match_use_money" => $voucher_use_money
                            );
                            $this->addOperateLog($voucher_result->voucher_id, 0, $member_id, 'lock', $memo);
                        } else {
                            $buse_suc = false;
                            break;
                        }
                    } else {
                        $msg = $voucher_number . "内购券已使用";
                        $buse_suc = false;
                        break;
                    }
                }
            }
        }
        if ($buse_suc) {
            $this->_db->commit();
            return $used_voucher_info;
        } else {
            $this->_db->rollback();
            return $msg;
        }
    }

    public function useVoucherWithRule($params_data_array, $filter_data)
    {
        \Neigou\Logger::General('action.voucher', array('action' => 'useVoucherWithRule'));
//        $params_data_array = json_decode($json_data, true);
//        $filter_data = json_decode($json_filter_data, true);
        if (!isset($params_data_array["voucher_list"]) ||
            !isset($params_data_array["member_id"]) ||
            !isset($params_data_array["order_id"]) ||
            !isset($params_data_array["use_money"]) ||
            !isset($params_data_array["memo"])) {
            \Neigou\Logger::General(
                'action.voucher',
                array(
                    'action' => 'useVoucherWithRule',
                    'success' => 0,
                    'reason' => 'invalid_params'
                )
            );
            return "代金券参数不正确";
        }
        $voucher_number_list = array_unique($params_data_array["voucher_list"]);
        $voucher_match_money_data = array();
        foreach ($voucher_number_list as $voucher_number_item) {
            $res = $this->useRuleFilter($voucher_number_item, $filter_data);
            if (!$res) {
                \Neigou\Logger::General(
                    'action.voucher',
                    array(
                        'action' => 'useVoucherWithRule',
                        'success' => 0,
                        'reason' => 'rule_not_match'
                    )
                );
                return "代金券不符合使用规则";
            }

            $voucher_match_money_data[$voucher_number_item] = $res;
        }

        if (!$voucher_match_money_data) {
            \Neigou\Logger::General(
                'action.voucher',
                array(
                    'action' => 'useVoucherWithRule',
                    'success' => 0,
                    'reason' => 'rule_not_match'
                )
            );
            return "代金券不符合使用规则";
        }

        $member_id = $params_data_array["member_id"];
        $order_id = $params_data_array["order_id"];
        $use_money = $params_data_array["use_money"];
        $memo = $params_data_array["memo"];
        $voucher_number_list = array_unique($voucher_number_list);

        $buse_suc = true;
        $msg = "fail";
        $this->_db->beginTransaction();
        $voucher_count = count($voucher_number_list);
        $total_used_money = 0;
        $used_voucher_info = array();
        foreach ($voucher_number_list as $voucher_number) {
            \Neigou\Logger::General(
                'action.voucher',
                array(
                    'action' => 'useVoucher',
                    'number' => $voucher_number,
                    'member_id' => $member_id,
                    'order_id' => $order_id,
                    'use_money' => $use_money
                )
            );

            $voucher_result = $this->_db->table($this->table_voucher)->where('number',
                $voucher_number)->sharedLock()->first();
            if (empty($voucher_result)) {
                \Neigou\Logger::General(
                    'action.voucher',
                    array(
                        'action' => 'use',
                        'success' => 0,
                        'number' => $voucher_number,
                        'reason' => 'voucher_number_error'
                    )
                );
                $buse_suc = false;
                $msg = "内购券券码异常";
                break;
            }

            $voucher_member_result = $this->_db->table('promotion_voucher_member')->where('voucher_id',
                $voucher_result->voucher_id)->first();
            if (!empty($voucher_member_result) && $voucher_member_result->member_id != $member_id) {
                \Neigou\Logger::General(
                    'action.voucher',
                    array(
                        'action' => 'use',
                        'success' => 0,
                        'number' => $voucher_number,
                        'data' => json_encode($params_data_array),
                        'reason' => 'voucher_bind_other_member'
                    )
                );
                $buse_suc = false;
                $msg = "内购券已绑定其他账号";
                break;
            }

            // 内购券可使用的金额
            $voucher_match_money = $voucher_match_money_data[$voucher_result->number]['match_use_money'];
            $voucher_use_money = min($voucher_match_money, $voucher_result->money);
            if ($voucher_result->type == 'discount') {
                $voucher_match_money = $voucher_match_money * (1 - $voucher_result->discount / 100);
                $voucher_use_money = $voucher_match_money;
            }


            if (!$voucher_use_money) {
                \Neigou\Logger::General(
                    'action.voucher',
                    array(
                        'action' => 'use',
                        'success' => 0,
                        'number' => $voucher_number,
                        'reason' => 'voucher_money_error'
                    )
                );
                $buse_suc = false;
                $msg = "内购券金额异常";
                break;
            }

            if (empty($voucher_result)) {
                \Neigou\Logger::General(
                    'action.voucher',
                    array(
                        'action' => 'use',
                        'success' => 0,
                        'number' => $voucher_number,
                        'reason' => 'notexist'
                    )
                );
                $buse_suc = false;
                $msg = "代金券不存在";
                break;
            } else {
                if ($voucher_result->valid_time < time()) {
                    \Neigou\Logger::General(
                        'action.voucher',
                        array(
                            'action' => 'use',
                            'success' => 0,
                            'number' => $voucher_number,
                            'reason' => "time exceed"
                        )
                    );
                    $buse_suc = false;
                    $msg = "内购券已过期";
                    break;
                }
                if ($voucher_result->start_time > time()) {
                    \Neigou\Logger::General(
                        'action.voucher',
                        array(
                            'action' => 'use',
                            'success' => 0,
                            'number' => $voucher_number,
                            'reason' => "time no start"
                        )
                    );
                    $buse_suc = false;
                    $msg = "代金券未开始";
                    break;
                }
                // 代金券使用数量超出限定数量
                if ($voucher_result->num_limit < $voucher_count) {
                    \Neigou\Logger::General(
                        'action.voucher',
                        array(
                            'action' => 'use',
                            'success' => 0,
                            'number' => $voucher_number,
                            'reason' => "quanlity exceed"
                        )
                    );
                    $buse_suc = false;
                    $msg = "内购券超出数目限额";
                    break;
                }

                if ($voucher_result->status == 'normal') {
                    $where['number'] = $voucher_number;
                    $data['status'] = 'lock';
                    //TODO last night
                    $ret = $this->_db->table($this->table_voucher)->where($where)->update($data);
                    if (!empty($ret)) {
                        // 使用金额计算，外部传入的$use_money总金额计算出每一个代金券使用的金额。
                        $cur_used_money = min($use_money - $total_used_money, $voucher_use_money);
                        $data_use = array(
                            'voucher_id' => $voucher_result->voucher_id,
                            'order_id' => $order_id,
                            'member_id' => $member_id,
                            'use_time' => time(),
                            'use_money' => $cur_used_money
                        );
                        $total_used_money += $voucher_use_money;
                        $total_used_money = min($total_used_money, $use_money);

                        $this->_db->table($this->promotion_voucher_order_user)->insert($data_use);

                        $used_voucher_info[$voucher_result->number] = array(
                            "product_bn_list" => $voucher_match_money_data[$voucher_result->number]['product_bn_list'],
                            "match_use_money" => $voucher_use_money
                        );
                        $this->addOperateLog($voucher_result->voucher_id, 0, $member_id, 'lock', $memo);
                        \Neigou\Logger::General(
                            'action.voucher',
                            array(
                                'action' => 'use',
                                'success' => 1,
                                'number' => $voucher_number
                            )
                        );
                    } else {
                        \Neigou\Logger::General(
                            'action.voucher',
                            array(
                                'action' => 'use',
                                'success' => 0,
                                'number' => $voucher_number,
                                'reason' => 'updatesql'
                            )
                        );
                        $buse_suc = false;
                        break;
                    }
                } else {
                    \Neigou\Logger::General(
                        'action.voucher',
                        array(
                            'action' => 'use',
                            'success' => 0,
                            'number' => $voucher_number,
                            'reason' => 'already_used'
                        )
                    );
                    $msg = "内购券已使用";
                    $buse_suc = false;
                    break;
                }
            }
        }
        if ($buse_suc) {
            $this->_db->commit();
            return $used_voucher_info;
        } else {
            $this->_db->rollback();
            return $msg;
        }
    }

    /**
     * @param string $voucher_number
     * @param string $status
     * @param string $memo
     * @return string
     */
    public function exchangeStatus($voucher_number_list, $status, $memo)
    {
        $voucher_number_list = array_unique($voucher_number_list);
        \Neigou\Logger::General(
            'action.voucher',
            array(
                'action' => 'exchangeStatus',
                'number' => $voucher_number_list,
                'voucher_status' => $status
            )
        );
        $bchange_suc = true;
        $msg = "fail";
        $this->_db->beginTransaction();
        foreach ($voucher_number_list as $voucher_number) {
            $result = $this->_db->table($this->table_voucher)->where('number', $voucher_number)->sharedLock()->first();
            if (empty($result)) {
                \Neigou\Logger::General(
                    'action.voucher',
                    array(
                        'action' => 'exchange',
                        'success' => 0,
                        'number' => $voucher_number,
                        'voucher_status' => $status,
                        'reason' => 'notexist'
                    )
                );
                $bchange_suc = false;
                $msg = "代金券不存在";
                break;
            } else {
                if ($result->status != 'finish') {
                    $data['status'] = $status;
                    $this->_db->table($this->table_voucher)->where('number', $voucher_number)->update($data);
                    // 订单取消需要将订单表中数据disable。
                    if ($status == 'normal') {
                        $order_data['disabled'] = 1;
                        $this->_db->table($this->promotion_voucher_order_user)->where('voucher_id',
                            $result->voucher_id)->update($order_data);
                    }
                    switch ($status) {
                        case "normal":
                            $behavior = 'unlock';
                            break;
                        case "lock":
                            $behavior = 'lock';
                            break;
                        case "finish":
                            $behavior = 'finish';
                            break;
                        case "disabled":
                            $behavior = 'cancel';
                            break;
                    }
                    $this->addOperateLog($result->voucher_id, 0, 'system_core', $behavior, $memo);
                    \Neigou\Logger::General(
                        'action.voucher',
                        array(
                            'action' => 'exchange',
                            'success' => 1,
                            'number' => $voucher_number,
                            'voucher_status' => $status
                        )
                    );
                } else {
                    \Neigou\Logger::General(
                        'action.voucher',
                        array(
                            'action' => 'exchange',
                            'success' => 0,
                            'number' => $voucher_number,
                            'voucher_status' => $status
                        )
                    );
                    $bchange_suc = false;
                    $msg = "代金券状态不正确";
                    break;
                }
            }
        }
        if ($bchange_suc) {
            $this->_db->commit();
            return true;
        } else {
            $this->_db->rollback();
            return $msg;//err 1
        }

    }

    //获取券规则
    public function getRule($rule_id)
    {
        $result = $this->_db->table($this->promotion_voucher_rules)->where('rule_id', $rule_id)->first();
        if (!$result) {
            return "规则ID指向规则不存在";
        }
        return $result;
    }

    /**
     * @param string $create_data_array
     * @return string
     */
    public function addBlackList($create_data_array)
    {
        \Neigou\Logger::General('action.voucher', array('action' => 'addBlackList'));
//        $create_data_array = json_decode($json_data, true);
        if (empty($create_data_array) ||
            !isset($create_data_array['type']) ||
            !isset($create_data_array['rule']) ||
            !isset($create_data_array['start_time']) ||
            !isset($create_data_array['end_time']) ||
            ($create_data_array['start_time'] >= $create_data_array['end_time'])
        ) {
            \Neigou\Logger::General(
                'action.voucher',
                array(
                    'action' => 'addBlackList',
                    'success' => 0,
                    'reason' => 'unvalid_params',
                    "params_data" => $create_data_array
                )
            );
            return '参数错误';
        }
        if (!is_numeric($create_data_array['type']) ||
            !is_numeric($create_data_array['start_time']) ||
            !is_numeric($create_data_array['end_time'])
        ) {
            \Neigou\Logger::General(
                'action.voucher',
                array(
                    'action' => 'addBlackList',
                    'success' => 0,
                    'reason' => 'unvalid_params',
                    "params_data" => $create_data_array
                )
            );
            return '参数错误';
        }
        $op_data = array(
            'type' => $create_data_array['type'],
            'rule' => $create_data_array['rule'],
            'start_time' => $create_data_array['start_time'],
            'end_time' => $create_data_array['end_time'],
            'create_time' => time(),
            'last_modify' => time()
        );

        //开始事务
        $this->_db->beginTransaction();
        $create_result_id = $this->_db->table($this->promotion_voucher_blacklist_rule)->insertGetId($op_data);
        $bn = json_decode($create_data_array['rule'], true);
        $data = array(
            'bn' => $bn['bn'],
            'rule_id' => $create_result_id,
        );
        $log_id = $this->_db->table('promotion_voucher_blacklist_products')->insertGetId($data);
        if (!empty($log_id)) {
            $this->_db->commit();
            return $create_result_id;
        } else {
            $this->_db->roolback();
//            $message = "创建失败";
            return "创建失败";
        }
    }

    /**
     * @param string $voucher_number
     * @param int $voucher_password
     * @return string
     */
    public function queryBlackList($create_data_array)
    {
        \Neigou\Logger::Debug('action.voucher', array('action' => 'queryBlackList', 'data' => $create_data_array));
//        $create_data_array = json_decode($json_data, true);
        if (empty($create_data_array) ||
            !isset($create_data_array['type']) ||
            !isset($create_data_array['rule']) ||
            !isset($create_data_array['time'])

        ) {
            \Neigou\Logger::Debug(
                'action.voucher',
                array(
                    'action' => 'queryBlackList',
                    'success' => 0,
                    'reason' => 'unvalid_params',
                    "params_data" => $create_data_array
                )
            );
            return '参数错误';
        }

        if (!is_numeric($create_data_array['type']) ||
            !is_numeric($create_data_array['time'])
        ) {
            \Neigou\Logger::Debug(
                'action.voucher',
                array(
                    'action' => 'queryBlackList',
                    'success' => 0,
                    'reason' => 'unvalid_params',
                    "params_data" => $create_data_array
                )
            );
            return '参数错误';
        }

        $where = [
            ['type', $create_data_array['type']],
            ['start_time', '<=', $create_data_array['time']],
            ['end_time', '>=', $create_data_array['time']],
        ];
        $retBlackList = $this->_db->table($this->promotion_voucher_blacklist_rule)->where($where)->get()->all();
        $retBlackList = json_decode(json_encode($retBlackList), true);
        $ruleBlackList = array();
        foreach ($retBlackList as $black_item) {
            if (!empty($black_item['rule'])) {
                $rule = json_decode($black_item['rule'], true);
                $ruleBlackList[$rule['bn']] = $rule['bn'];
            }
        }
        if (in_array($create_data_array['rule']['bn'], $ruleBlackList)) {
            $result = array('bn' => $create_data_array['rule']['bn'], 'can_use' => '0');
            return $result;
        } else {
            $result = array('bn' => $create_data_array['rule']['bn'], 'can_use' => '1');
            return $result;
        }
    }

    public function batchQueryBlackList($create_data_array)
    {
        //1.查询所有bn对应的Rule_id
        $list = $this->_db->table('promotion_voucher_blacklist_products')->select('rule_id', 'bn')->whereIn('bn',
            $create_data_array['rule']['bn'])->get()->map(function ($val) {
            return (array)$val;
        })->toArray();
        //2.获取所有rule_id对应的规则
        $ruleid = array_column($list, 'bn', 'rule_id');
        $rulebn = array_column($list, 'rule_id', 'bn');

        $map_rule = array_keys($ruleid);
        $ruleList = $this->_db->table($this->promotion_voucher_blacklist_rule)
            ->select('blacklist_rule_id', 'start_time', 'end_time')
            ->where(array('type' => 1))
            ->whereIn('blacklist_rule_id', $map_rule)
            ->get()
            ->all();

        foreach ($ruleList as $key => $val) {
            if ($val->start_time <= $create_data_array['time'] && $val->end_time >= $create_data_array['time']) {
                $use_rule[] = $val->blacklist_rule_id;
            }
        }
        $a = array_intersect($use_rule, array_flip($ruleid));
        foreach ($create_data_array['rule']['bn'] as $k => $input_bn) {
            if (in_array($rulebn[$input_bn], $a)) {
                $create_data_array['rule']['bn_rzt'][$input_bn]['can_use'] = 0;
            } else {
                $create_data_array['rule']['bn_rzt'][$input_bn]['can_use'] = 1;
            }
        }
        return $create_data_array;

    }


    /**
     * @param string $json_data
     * @return string
     */
    public function saveBlackList($create_data_array)
    {
        \Neigou\Logger::General('action.voucher', array('action' => 'saveBlackList'));
        if (empty($create_data_array) ||
            !isset($create_data_array['type']) ||
            !isset($create_data_array['blacklist_rule_id']) ||
            !isset($create_data_array['rule']) ||
            !isset($create_data_array['start_time']) ||
            !isset($create_data_array['end_time']) ||
            ($create_data_array['start_time'] >= $create_data_array['end_time'])
        ) {
            \Neigou\Logger::General('action.voucher', array(
                'action' => 'saveBlackList',
                'success' => 0,
                'reason' => 'unvalid_params',
                "params_data" => $create_data_array
            ));
            return '参数错误';
        }
        if (!is_numeric($create_data_array['blacklist_rule_id']) ||
            !is_numeric($create_data_array['type']) ||
            !is_numeric($create_data_array['start_time']) ||
            !is_numeric($create_data_array['end_time'])
        ) {
            \Neigou\Logger::General(
                'action.voucher',
                array(
                    'action' => 'saveBlackList',
                    'success' => 0,
                    'reason' => 'unvalid_params',
                    "params_data" => $create_data_array
                )
            );
            return '参数错误';
        }
        $data = array(
            'type' => $create_data_array['type'],
            'rule' => $create_data_array['rule'],
            'start_time' => $create_data_array['start_time'],
            'end_time' => $create_data_array['end_time'],
            'last_modify' => time()
        );

        $where = [
            ['blacklist_rule_id', $create_data_array['blacklist_rule_id']]
        ];
        //开始事务
        $this->_db->beginTransaction();
        $result = $this->_db->table($this->promotion_voucher_blacklist_rule)->where($where)->update($data);
        $bn = json_decode($create_data_array['rule'], true);
        $data = array(
            'bn' => $bn['bn'],
            'rule_id' => $create_data_array['blacklist_rule_id'],
        );
        $log_rzt = $this->_db->table('promotion_voucher_blacklist_products')->where(array('rule_id' => $create_data_array['blacklist_rule_id']))->update($data);
        if ($result === false && $log_rzt === false) {
            $this->_db->roolback();
            $message = "更新失败";
            return $message;
        } else {
            $this->_db->commit();
            return $create_data_array['blacklist_rule_id'];
        }
    }

    /**
     * @param string $json_data
     * @return string
     */
    public function deleteBlackList($create_data_array)
    {
        \Neigou\Logger::General('action.voucher', array('action' => 'saveBlackList'));
        if (empty($create_data_array) ||
            !isset($create_data_array['blacklist_rule_id'])
        ) {
            \Neigou\Logger::General('action.voucher', array(
                'action' => 'deleteBlackList',
                'success' => 0,
                'reason' => 'unvalid_params',
                "params_data" => $create_data_array
            ));
            return '参数错误';
        }
        if (!is_numeric($create_data_array['blacklist_rule_id'])
        ) {
            \Neigou\Logger::General(
                'action.voucher',
                array(
                    'action' => 'deleteBlackList',
                    'success' => 0,
                    'reason' => 'unvalid_params',
                    "params_data" => $create_data_array
                )
            );
            return '参数错误';
        }

        $this->_db->beginTransaction();
        $result = $this->_db->table($this->promotion_voucher_blacklist_rule)->where('blacklist_rule_id',
            $create_data_array['blacklist_rule_id'])->delete();
        $log_rzt = $this->_db->table('promotion_voucher_blacklist_products')
            ->where('rule_id', $create_data_array['blacklist_rule_id'])
            ->delete();

        if ($result === false && $log_rzt === false) {
            $this->_db->roolback();
            $message = "删除失败";
            return $message;
        } else {
            $this->_db->commit();
            return $create_data_array['blacklist_rule_id'];
        }
    }

    /**
     * 查询内购券数量
     *
     * 可按用户、公司、类型、时间组合查询
     * @param   string $json_data 查询条件数据
     *          company_id       null or int             公司 ID
     *          member_id        null or int or array    用户 ID
     *          source_type      null or string or array 券类型
     *          status           null or string or array 券状态
     *          start_time       null or int             开始时间
     *          end_time         null or int             结束时间
     *          external_id      null or int or array    外部活动 ID
     *          group            null or string          分组查询(rule or external)
     * @return  string
     */
    public function queryVoucherCount($json_data = '')
    {
//        $json_data = json_decode($json_data, true);
        if (empty($json_data)) {
            \Neigou\Logger::General(
                'queryVoucherCount.error',
                array(
                    'action' => 'voucherCode',
                    'success' => 0,
                    'reason' => 'unvalid_params',
                    "params_data" => $json_data
                )
            );

            return '参数错误';
        }

        $companyId = isset($json_data['company_id']) ? intval($json_data['company_id']) : null;
        $memberIds = isset($json_data['member_id']) ? $json_data['member_id'] : null;
        $sourceType = isset($json_data['source_type']) ? $json_data['source_type'] : null;
        $status = isset($json_data['status']) ? $json_data['status'] : null;
        $startTime = isset($json_data['start_time']) ? intval($json_data['start_time']) : null;
        $endTime = isset($json_data['end_time']) ? intval($json_data['end_time']) : null;
        $externalId = isset($json_data['external_id']) ? $json_data['external_id'] : null;
        $group = isset($json_data['group']) ? $json_data['group'] : '';

        if (empty($companyId) && empty($memberIds) && empty($sourceType) && empty($status) && is_null($startTime) && is_null($endTime) && is_null($externalId)) {
            \Neigou\Logger::General(
                'queryVoucherCount.error',
                array(
                    'action' => 'voucherCode',
                    'success' => 0,
                    'reason' => 'unvalid_params',
                    "params_data" => $json_data
                )
            );

            return '参数错误';
        }

        // 根据用户数量分组
        $memberGroups = array();
        if (!empty($memberIds)) {
            if (!is_array($memberIds)) {
                $memberIds = array($memberIds);
            }
            $groupLimitMemberCount = 1000;
            $index = 0;
            foreach ($memberIds as $memberId) {
                $memberGroups[$index][] = $memberId;
                if (($index + 1) % $groupLimitMemberCount == 0) {
                    $index++;
                }
            }
        }

        if (empty($memberGroups)) {
            $memberGroups[] = array();
        }

        $voucherCount = array('count' => 0, 'member_count' => 0, 'company_count' => 0);
        $createId = array();
        // 外部活动查询条件
        if (!empty($externalId)) {
            $db = $this->_db->table($this->table_voucher_create_log)->select('id','external_id');
            if (!is_array($externalId)) {
                $externalId = array($externalId);
            }

            $db->whereIn('external_id',$externalId);

            if (!empty($sourceType)) {
                if (!is_array($sourceType)) {
                    $sourceType = array($sourceType);
                }
                $db->whereIn('source_type',$sourceType);
            }

            // 公司查询条件
            if (!empty($companyId)) {
                $db->where('company_id','=',$companyId);
            }

            $result = $db->get()->toArray();

            if (empty($result)) {
                return $voucherCount;
            }


            foreach ($result as $v) {
                $createId[$v->id] = $v->external_id;
            }
        }



        // 内购券来源查询条件
        if (!empty($sourceType)) {
            if (!is_array($sourceType)) {
                $sourceType = array($sourceType);
            }
        }

        $whereClosure = function ($query) use($createId,$sourceType,$endTime,$startTime,$companyId,$status,$memberIds){
            if(!empty($createId)){
                $ids = array_keys($createId);
                if (!empty($ids)){
                    $query->whereIn($this->table_voucher.'.create_id',$ids);
                }
            }

            if(!empty($sourceType)){
                $query->whereIn($this->table_voucher_member.'.source_type',$sourceType);
            }

            // 结束时间查询条件
            if (!is_null($endTime)) {
                $query->where($this->table_voucher.'.last_modified','<',$endTime);
            }

            // 开始时间查询条件
            if (!is_null($startTime)) {
                $query->where($this->table_voucher.'.last_modified','>=',$startTime);
            }

            // 公司查询条件
            if (!empty($companyId)) {
                $query->where($this->table_voucher_company.'.company_id','=',$companyId);
            }

            // 状态查询条件
            if (!empty($status)) {
                if (!is_array($status)) {
                    $status = array($status);
                }
                $query->whereIn($this->table_voucher.'.status',$status);
            }

            if (!empty($memberIds)) {
                $query->whereIn($this->table_voucher_member.'.member_id',$memberIds);
            }
        };




        // 支持的分组查询
        $supportGroup = array('rule', 'external');
        $memberList = $companyList = array();
        $groupCount = array();
        foreach ($memberGroups as $memberIds) {
            if ((empty($memberId) OR count($memberGroups)) == 1) {
                $select = "COUNT({$this->table_voucher}.voucher_id) AS count, COUNT(DISTINCT {$this->table_voucher_member}.member_id) AS member_count,";
                $select .= " COUNT(DISTINCT {$this->table_voucher_company}.company_id) AS company_count";
            } else {
                $select = "SELECT {$this->table_voucher_member}.member_id AS member_id, {$this->table_voucher_company}.company_id AS company_id";
            }

            $selectTable  = $this->_db->table($this->table_voucher)->join($this->table_voucher_member,$this->table_voucher.'.voucher_id','=',$this->table_voucher_member.'.voucher_id')->join($this->table_voucher_company,$this->table_voucher.'.voucher_id','=',$this->table_voucher_company.'.voucher_id');

            $selectGroupTable = $this->_db->table($this->table_voucher)->join($this->table_voucher_member,$this->table_voucher.'.voucher_id','=',$this->table_voucher_member.'.voucher_id')->join($this->table_voucher_company,$this->table_voucher.'.voucher_id','=',$this->table_voucher_company.'.voucher_id');



            // 分组查询
            if (in_array($group, $supportGroup)) {
                $groupSql = "COUNT({$this->table_voucher}.voucher_id) AS count, {$this->table_voucher_member}.member_id AS member_id, {$this->table_voucher_company}.company_id AS company_id";
                switch ($group) {
                    case 'rule':
                        $groupSql .= ",{$this->table_voucher}.rule_id AS rule_id" ;
                        break;
                    case 'external':
                        $groupSql .= ",{$this->table_voucher}.create_id AS create_id";
                        break;
                }
            }

            if (empty($memberId) OR count($memberGroups) == 1) {
                $result = $selectTable->select($this->_db->raw($select))->where($whereClosure)->first();
                if (!empty($result)) {
                    $voucherCount = $result;
                }
            } else {
                $result = $selectTable->select($this->_db->raw($select))->where($whereClosure)->get()->toArray();
                if (!empty($result)) {
                    // 排重用户和公司
                    foreach ($result as $v) {
                        $voucherCount['count'] += 1;
                        if (!isset($memberList[$v['member_id']])) {
                            $voucherCount['member_count'] += 1;
                            $memberList[$v['member_id']] = 1;
                        }

                        if (!isset($companyList[$v['company_id']])) {
                            $voucherCount['company_count'] += 1;
                            $companyList[$v['company_id']] = 1;
                        }
                    }
                }
            }

            // 分组查询
            if (in_array($group, $supportGroup)) {
                switch ($group) {
                    case 'rule':
                        $groupKey = 'rule_id';
                        $selectGroupTable->groupBy("{$this->table_voucher}." . $groupKey);
                        break;
                    case 'external':
                        $groupKey = 'create_id';
                        $selectGroupTable->groupBy("{$this->table_voucher}." . $groupKey);
                        break;
                }

                $result = $selectGroupTable->select($this->_db->raw($groupSql))->where($whereClosure)->get()->toArray();
                $result = json_decode(json_encode($result), true);
                if (!empty($result)) {
                    // 排重用户和公司
                    foreach ($result as $v) {
                        if (!isset($groupCount[$v[$groupKey]])) {
                            $groupCount[$v[$groupKey]] = array(
                                'count' => 0,
                                'member_id' => array(),
                                'company_id' => array(),
                            );
                        }

                        $groupCount[$v[$groupKey]]['count'] += $v['count'];
                        if (!isset($groupCount[$v[$groupKey]]['member_id'][$v['member_id']])) {
                            $groupCount[$v[$groupKey]]['member_id'][$v['member_id']] = $v['member_id'];
                        }

                        if (!isset($groupCount[$v[$groupKey]]['company_id'][$v['company_id']])) {
                            $groupCount[$v[$groupKey]]['company_id'][$v['company_id']] = $v['company_id'];
                        }
                    }
                }
            }
        }

        if (!empty($groupCount)) {
            if ($group == 'external') {
                if (!isset($createId) OR empty($createId)) {
                    $ids = array_keys($groupCount);
                    // 获取外部的活动 ID
                    $logTable = $this->_db->table($this->table_voucher_create_log)->select('id','external_id');
                    $logTable->whereIn('id',$ids);
                    if (is_array($externalId)) {
                        $logTable->whereIn('external_id',$externalId);
                    }

                    if (!empty($sourceType)) {
                        if (!is_array($sourceType)) {
                            $sourceType = array($sourceType);
                        }
                        $logTable->whereIn('source_type',$sourceType);

                    }

                    $result = $logTable->get()->toArray();
                    if (empty($result)) {
                        return $voucherCount;
                    }

                    $createId = array();
                    foreach ($result as $v) {
                        $createId[$v->id] = $v->external_id;
                    }
                }

                $newGroupDetail = array();
                foreach ($groupCount as $id => $detail) {
                    if (!isset($createId[$id])) {
                        continue;
                    }

                    $externalId = $createId[$id];
                    if (!isset($newGroupDetail[$externalId])) {
                        $newGroupDetail[$externalId] = $detail;
                    } else {
                        $newGroupDetail[$externalId]['count'] += $detail['count'];
                        $newGroupDetail[$externalId]['member_id'] += $detail['member_id'];
                        $newGroupDetail[$externalId]['company_id'] += $detail['company_id'];
                    }
                }
                $groupCount = $newGroupDetail;
            }

            $voucherCount = json_decode(json_encode($voucherCount), true);
            foreach ($groupCount as $groupId => $detail) {

                $voucherCount['group'][$groupId] = array(
                    'count' => $detail['count'],
                    'member_count' => count($detail['member_id']),
                    'company_count' => count($detail['company_id']),
                );
            }
        }

        return $voucherCount;
    }


    // 使用规则过滤，获取用户可用的内购券列表
    public function queryMemberVoucherWithRuleFilter($member_id, $filter_data)
    {
        if (empty($member_id)) {
            return false;
        }
        $cur_time = time();
        $sql = "select pv.voucher_id, pv.rule_id from (promotion_voucher_member pvm join {$this->table_voucher} pv on pvm.voucher_id=pv.voucher_id)
                where pvm.member_id=:member_id and pv.valid_time>{$cur_time} and pv.start_time<={$cur_time} and pv.status='normal'";

        $valid_voucher_list = $this->_db->select($sql,array($member_id));
        if (empty($valid_voucher_list)) {
            return false;
        }
        $valid_voucher_list = json_decode(json_encode($valid_voucher_list), true);
        $voucher_rule_id_list = array();
        foreach ($valid_voucher_list as $voucher_item) {
            if (!empty($voucher_item['rule_id'])) {
                $voucher_rule_id_list[] = $voucher_item['rule_id'];
            }
        }
        $voucher_rule_id_list_str = implode(',', $voucher_rule_id_list);
        $placeholder = implode(',',array_fill(0,count($voucher_rule_id_list),'?'));
        $sql = "select * from {$this->promotion_voucher_rules} where rule_id in ({$placeholder}) and disabled=0";
        $rule_data_list_tmp = $this->_db->select($sql,$voucher_rule_id_list);
        $rule_data_list_tmp = json_decode(json_encode($rule_data_list_tmp), true);
        foreach ($rule_data_list_tmp as $rule_item) {
            $rule_data_list[$rule_item['rule_id']] = $rule_item;
        }

        // 增加版本兼容
        $func_name = !empty($filter_data['version']) ? 'matchV' . intval($filter_data['version']) : 'match';
        $valid_voucher_id_list = array();
        $rule_process_cache = array();
        $valid_voucher_list = json_decode(json_encode($valid_voucher_list), true);
        foreach ($valid_voucher_list as $voucher_item) {
            if (empty($voucher_item['rule_id'])) {
                // 规则不存在则视为通用规则
                $valid_voucher_id_list[] = $voucher_item["voucher_id"];
            } else {
                if (empty($rule_data_list[$voucher_item['rule_id']])) {
                    continue;
                } else {
                    $condition_array = unserialize($rule_data_list[$voucher_item['rule_id']]['rule_condition']);
                    $condition_item = $condition_array[0];

                    $str_processor = $condition_item['processor'];
                    if (empty($str_processor)) {
                        continue;
                    }
                    $str_processor = 'App\Api\Model\Voucher\Rule\\' . $str_processor;
                    $processor = new $str_processor;
                    if (method_exists($processor, $func_name)) {
                        $cache_key = md5($str_processor . json_encode($condition_item['filter_rule']));
                        if (!isset($rule_process_cache[$cache_key])) {
                            //
                            $time = time();
                            $sql = "select * from {$this->promotion_voucher_blacklist_rule} where type= 1 AND  start_time<={$time} and end_time >={$time}";
                            $retBlackList = $this->_db->select($sql);

                            $ruleBlackList = array();
                            foreach ($retBlackList as $black_item) {
                                if (!empty($black_item->rule)) {
                                    $rule = json_decode($black_item->rule, true);
                                    $ruleBlackList[$rule['bn']] = $rule['bn'];
                                }
                            }
                            $condition_item['filter_rule']['ret_black_list'] = $ruleBlackList;
                            //
                            $match_data = $processor->$func_name($condition_item['filter_rule'], $filter_data);
                            $match_use_money = $match_data['match_use_money'];
                            $rule_process_cache[$cache_key] = $match_use_money;
                        } else {
                            $match_use_money = $rule_process_cache[$cache_key];
                        }
                        if ($match_use_money) {
                            $valid_voucher_id_list[] = $voucher_item["voucher_id"];
                        }
                    }
                }
            }
        }

        if (empty($valid_voucher_id_list)) {
            return false;
        }
        $placeholder = implode(',',array_fill(0,count($valid_voucher_id_list),'?'));
        $sql = "select pv.*, pvc.company_id from {$this->table_voucher} pv join {$this->table_voucher_company} pvc on pv.voucher_id=pvc.voucher_id
                  where pv.voucher_id in (".$placeholder.")";
        $voucher_date_result = $this->_db->select($sql,$valid_voucher_id_list);


        return $voucher_date_result;
    }


    public function queryVoucherUsedCount($json_data = '')
    {

        if (empty($json_data)) {
            \Neigou\Logger::Debug(
                'queryVoucherUsedCount.error',
                array(
                    'action' => 'voucherCode',
                    'success' => 0,
                    'reason' => 'unvalid_params',
                    "params_data" => $json_data
                )
            );

            return '参数错误';
        }

        $companyId = isset($json_data['company_id']) ? $json_data['company_id'] : null;
        $sourceType = isset($json_data['source_type']) ? $json_data['source_type'] : null;
        $startTime = isset($json_data['start_time']) ? intval($json_data['start_time']) : null;
        $endTime = isset($json_data['end_time']) ? intval($json_data['end_time']) : null;

        if (empty($companyId) && empty($sourceType) && is_null($startTime) && is_null($endTime)) {
            \Neigou\Logger::Debug(
                'queryVoucherCount.error',
                array(
                    'action' => 'voucherCode',
                    'success' => 0,
                    'reason' => 'unvalid_params',
                    "params_data" => $json_data
                )
            );

            return '参数错误';
        }

        $where = array(
            // 已使用的
            "v.status = 'finish'",
            // 未废弃的
            'u.disabled = 0'
        );
        if (!empty($companyId)) {
            // 按公司ID筛选，支持多个公司ID
            if (!is_array($companyId)) {
                $companyId = array($companyId);
            }
            if (count($companyId) > 1) {
                $where[] = ' l.company_id IN(' . implode(',', $companyId) . ') ';
            } else {
                $where[] = ' l.company_id=' . $companyId[0];
            }
        }

        if (!empty($sourceType)) {
            // 按福利券类型筛选
            $where[] = " l.source_type='{$sourceType}'";
        }

        if (!empty($startTime) && !empty($endTime)) {
            // 按使用时间筛选
            $where[] = "u.use_time BETWEEN {$startTime} AND {$endTime}";
        } elseif (!empty($startTime)) {
            $where[] = "u.use_time >= {$startTime}";
        } elseif (!empty($startTime)) {
            $where[] = "u.use_time <= {$endTime}";
        }

        $whereString = implode(' AND ', $where);

        $sql = "SELECT 
                    COUNT(*) AS voucher_count,
                    COUNT(DISTINCT u.member_id) AS member_count,
                    SUM(u.use_money) AS used_money
                FROM promotion_voucher_order_user AS u
                   LEFT JOIN promotion_voucher AS v ON v.voucher_id = u.voucher_id
                   LEFT JOIN promotion_voucher_create_log AS l ON l.id = v.create_id
                WHERE {$whereString}";

        $result = $this->_db->select($sql);
        if (!empty($result) && $result[0]) {
            return $result[0];
        }
    }


}
