<?php
/**
 * Created by PhpStorm.
 * User: chuanbin
 * Date: 2017/11/3
 * Time: 19:08
 */

namespace App\Api\Model\Voucher;


class VoucherPackage
{

    private $member_vouchermgr;
    private $_db;
    private $table_voucher_package_rule = 'promotion_voucher_package_rule';
    private $table_voucher_package_rule_voucher = 'promotion_voucher_package_rule_voucher';
    private $table_voucher_package_member = 'promotion_voucher_package_member';
    private $table_voucher_package_member_sourcetype = 'promotion_voucher_package_member_sourcetype';
    private $table_voucher_rules = 'promotion_voucher_rules';

    public function __construct()
    {
        $this->member_vouchermgr = new VoucherMember();
        $this->_db = app('api_db')->connection('neigou_store');;
    }

    // 创建礼包规则
    public function createPackageRule($create_params)
    {
        if (empty($create_params['name']) ||
            empty($create_params['money']) ||
            !is_array($create_params['voucher_list']) ||
            count($create_params['voucher_list']) == 0) {
            \Neigou\Logger::General('action.voucher', array(
                'action' => 'createPackageRule',
                'create_params' => json_encode($create_params),
                'success' => 0,
                'reason' => 'params_invalid'
            ));
            return '参数错误';//err 1001
        }
        $create_time = time();

        $b_create_suc = true;
        $message = "";
        $err_code = 0;
        $this->_db->beginTransaction();
        $rule_data = array(
            'name' => $create_params['name'],
            'money' => $create_params['money'],
            'op_name' => $create_params['op_name'],
            'create_time' => $create_time,
            'memo' => $create_params['memo'],
        );
        $pkg_rule_id = $this->_db->table($this->table_voucher_package_rule)->insertGetId($rule_data);

        if (!empty($pkg_rule_id)) {
            foreach ($create_params['voucher_list'] as $voucher_item) {
                if (!isset($voucher_item['voucher_name']) ||
                    !isset($voucher_item['money']) ||
                    !isset($voucher_item['voucher_count']) ||
                    !isset($voucher_item['voucher_nature']) ||
                    !isset($voucher_item['valid_days'])) {
                    \Neigou\Logger::General('action.voucher', array(
                        'action' => 'createPackageRule',
                        'create_params' => json_encode($create_params),
                        'success' => 0,
                        'reason' => 'params_invalid'
                    ));
                    $message = "内购券参数错误";
                    $b_create_suc = false;
                    break;
                }
                if (isset($voucher_item['rule_id'])) {
//                    $sql = "select name from {$this->table_voucher_rules} where rule_id={$voucher_item['rule_id']}";
                    $rule_data = $this->_db->table($this->table_voucher_rules)->where('rule_id',
                        $voucher_item['rule_id'])->first();
                    $rule_name = $rule_data->name;
                }
                $voucher_data = array(
                    "pkg_rule_id" => $pkg_rule_id,
                    "money" => $voucher_item['money'],
                    "voucher_count" => $voucher_item['voucher_count'],
                    "voucher_nature" => $voucher_item['voucher_nature'],
                    "voucher_name" => $voucher_item['voucher_name'],
                    "valid_days" => $voucher_item['valid_days'],
                    "rule_id" => $voucher_item['rule_id'],
                    "rule_name" => $rule_name,
                );
                $result_voucher_id = $this->_db->table($this->table_voucher_package_rule_voucher)->insertGetId($voucher_data);
                if (empty($result_voucher_id)) {
                    $message = "创建礼包券失败";
                    $b_create_suc = false;
                    break;
                }
            }
        } else {
            \Neigou\Logger::General('action.voucher',
                array('action' => 'createPackageRule', 'success' => 0, 'reason' => 'createrulesqlfailed'));
            $message = "创建内购券礼包规则失败";
            $b_create_suc = false;
        }
        if ($b_create_suc) {
            $this->_db->commit();
            return $pkg_rule_id;
        } else {
            $this->_db->rollback();//TODO 错误处理
            return $message;
        }
    }

    // 创建礼包
    public function createPackage($create_params)
    {
        return true;
    }

    // 礼包应用
    public function applyVoucherPkg($apply_params)
    {
        if (empty($apply_params['member_id']) ||
            empty($apply_params['pkg_rule_id'])) {
            \Neigou\Logger::General('action.voucher', array(
                'action' => 'applyVoucherPkg',
                'create_params' => json_encode($apply_params),
                'success' => 0,
                'reason' => 'params_invalid'
            ));
            return '参数错误';
        }

        if (empty($apply_params['source_type'])) {
            $apply_params['source_type'] = 'common';
        }

        $member_id = $apply_params['member_id'];
        $pkg_source_type_data = $this->_db->table($this->table_voucher_package_member_sourcetype)->where('source_type',
            $apply_params['source_type'])->first();
        if (empty($pkg_source_type_data)) {
            \Neigou\Logger::General('action.voucher', array(
                'action' => 'applyVoucherPkg',
                'member_id' => $member_id,
                'state' => 'fail',
                'reason' => 'invalid_source_type'
            ));
            return '内购券礼包类型错误';
        }

        if ($pkg_source_type_data->is_single == 1) {
            $where = [
                ['source_type_id', $pkg_source_type_data->type_id],
                ['member_id', $member_id]
            ];
            $voucher_pkg_mem_data = $this->_db->table($this->table_voucher_package_member)->where($where)->first();
            if ($voucher_pkg_mem_data) {
                \Neigou\Logger::General('action.voucher', array(
                    'action' => 'applyVoucherPkg',
                    'member_id' => $member_id,
                    'state' => 'fail',
                    'reason' => 'already_acquired'
                ));
                return '此类型内购券礼包只能领取一次';
            }
        }

        $pkg_rule_data = $this->_db->table($this->table_voucher_package_rule)->where('pkg_rule_id',
            $apply_params['pkg_rule_id'])->first();
        if (empty($pkg_rule_data)) {
            \Neigou\Logger::General('action.voucher', array(
                'action' => 'applyVoucherPkg',
                'create_params' => json_encode($apply_params),
                'success' => 0,
                'reason' => 'pkg_rule_error'
            ));
            return '内购券礼包不存在';
        }

        $rule_voucher_data = $this->_db->table($this->table_voucher_package_rule_voucher)->where('pkg_rule_id',
            $apply_params['pkg_rule_id'])->get()->all();
        if (empty($rule_voucher_data)) {
            \Neigou\Logger::General(
                'action.voucher',
                array(
                    'action' => 'applyVoucherPkg',
                    'create_params' => json_encode($apply_params),
                    'success' => 0,
                    'reason' => 'pkg_not_exist'
                )
            );
            return '内购券礼包不存在';
        }
        $rule_voucher_data = json_decode(json_encode($rule_voucher_data), true);
        $this->_db->beginTransaction();
        $member_voucer_list = array();
        foreach ($rule_voucher_data as $rule_item_data) {
            $valid_time = strtotime("{$rule_item_data['valid_days']} days");
            $valid_time = mktime(23, 59, 59, date("m", $valid_time), date("d", $valid_time), date("Y", $valid_time));
            $member_voucer_data = array(
                'money' => $rule_item_data['money'],
                'count' => $rule_item_data['voucher_count'],
                'valid_time' => $valid_time,
                'company_id' => $apply_params['company_id'],
                'op_id' => 1,
                'op_name' => $pkg_rule_data->op_name,
                'comment' => $pkg_rule_data->memo,
                'num_limit' => 10,
                'exclusive' => 1,
                'source_type' => 'common',
                'rule_id' => $rule_item_data['rule_id'],
                'voucher_name' => $rule_item_data['voucher_name']
            );
            $member_voucer_list[] = $member_voucer_data;
        }
        $message = "";
        $apply_success = $this->member_vouchermgr->createMulMemberVoucher($apply_params['member_id'],
            $member_voucer_list, $message);
        if (!empty($apply_success)) {
            $package_member_data = array(
                "pkg_rule_id" => $apply_params['pkg_rule_id'],
                "member_id" => $apply_params['member_id'],
                "company_id" => $apply_params['company_id'],
                "source_type_id" => $pkg_source_type_data->type_id,
                "create_time" => time(),
            );
            $insert_result = $this->_db->table($this->table_voucher_package_member)->insertGetId($package_member_data);
            if (empty($insert_result)) {
                $message = "插入用户使用记录失败";
            }
        }
        if ($apply_success) {
            $this->_db->commit();
            return $insert_result;
        } else {
            $this->_db->rollback();
            return $message;
        }
    }

    // 礼包查询
    public function queryVoucherPkg($query_params)
    {
        if (empty($query_params['pkg_rule_id'])) {
            \Neigou\Logger::General('action.voucher', array(
                'action' => 'queryVoucherPkg',
                'create_params' => json_encode($query_params),
                'success' => 0,
                'reason' => 'params_invalid'
            ));
            return '参数错误';
        }

        $pkg_rule_data = $this->_db->table($this->table_voucher_package_rule)->where('pkg_rule_id',
            $query_params['pkg_rule_id'])->first();
        if (empty($pkg_rule_data)) {
            \Neigou\Logger::General('action.voucher', array(
                'action' => 'queryVoucherPkg',
                'create_params' => json_encode($query_params),
                'success' => 0,
                'reason' => 'pkg_rule_error'
            ));
            return '内购券礼包不存在';
        }

        $rule_voucher_data = $this->_db->table($this->table_voucher_package_rule_voucher)->where('pkg_rule_id',
            $query_params['pkg_rule_id'])->get()->all();
        if (empty($rule_voucher_data)) {
            \Neigou\Logger::General(
                'action.voucher',
                array(
                    'action' => 'queryVoucherPkg',
                    'create_params' => json_encode($query_params),
                    'success' => 0,
                    'reason' => 'pkg_not_exist'
                )
            );
            return '内购券礼包不存在';
        }
        $pkg_rule_data->voucher_list = $rule_voucher_data;

        return $pkg_rule_data;
    }


}
