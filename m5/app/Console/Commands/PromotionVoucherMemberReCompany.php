<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * 单次运行脚本，需要手动触发，主要为 promotion_voucher_member 用户领券记录表，补充company_id 字段
 * 当这个表的 company_id 字段都被补齐后，本脚本将不会进行响应
 */
class PromotionVoucherMemberReCompany extends Command
{
    protected $signature = 'promotion_voucher_member_re_company';
    protected $description = '为用户领到的券补充公司id';

    private $table_voucher = 'promotion_voucher';
    private $table_voucher_member = 'promotion_voucher_member';
    private $table_voucher_create_log = 'promotion_voucher_create_log';

    private $_db;

    public function __construct()
    {
        parent::__construct();
        $this->_db = app('db')->connection('neigou_store');
    }

    public function handle()
    {
        set_time_limit(0);

        $page = 1;
        while (true) {
            $v_m_r = $this->_db->table($this->table_voucher_member)->where('company_id', '=', null)->select(
                ['id', 'voucher_id']
            )->limit(100)->get()->toArray();
            if (empty($v_m_r)) {
                echo '没有待补充公司id的用户领券记录了' . PHP_EOL;
                exit();
            }
            $voucher_id = $id_voucher = array();
            foreach ($v_m_r as $tmp) {
                $voucher_id[$tmp->id] = $tmp->voucher_id;
                $id_voucher[$tmp->voucher_id] = $tmp->id;
            }
            $v_r = $this->_db->table($this->table_voucher)->whereIn('voucher_id', $voucher_id)->select(
                ['voucher_id', 'create_id']
            )->get()->toArray();
            if (empty($v_r)) {
                echo $this->table_voucher . ' 表没有对应的券记录，voucher_id = ' . PHP_EOL;
                echo implode(',', $voucher_id);
                exit();
            }
            $create_id = $voucher_create = array();
            foreach ($v_r as $v_r_tmp) {
                $create_id[$v_r_tmp->voucher_id] = $v_r_tmp->create_id;
                $voucher_create[$v_r_tmp->create_id] = $v_r_tmp->voucher_id;
            }
            $v_c_l_r = $this->_db->table($this->table_voucher_create_log)->whereIn('id', $create_id)->select(
                ['id', 'company_id']
            )->get()->toArray();
            if (empty($v_c_l_r)) {
                echo $this->table_voucher_create_log . ' 表没有对应的券记录，id = ' . PHP_EOL;
                echo implode(',', $create_id);
                exit();
            }
            foreach ($v_c_l_r as $v_c_l_r_tmp) {
                if (empty($voucher_create[$v_c_l_r_tmp->id])) {
                    echo '这个 create_id: ' . $v_c_l_r_tmp->id . ' 没有找到对应的 voucher_id ' . PHP_EOL;
                    continue;
                }
                $tmp_voucher_id = $voucher_create[$v_c_l_r_tmp->id];
                if (empty($id_voucher[$tmp_voucher_id])) {
                    echo '这个 voucher_id: ' . $tmp_voucher_id . ' 没有找到对应的 promotion_voucher_member 主键id ' . PHP_EOL;
                    continue;
                }
                $pk = $id_voucher[$tmp_voucher_id];
                $company_id = $v_c_l_r_tmp->company_id;
                if (!empty($company_id)) {
                    $u_r = $this->_db->table($this->table_voucher_member)->where('id', '=', $pk)->update([
                        'company_id' => $company_id,
                    ]);
                    if ($u_r) {
                        echo '这个 create_id = ' . $v_c_l_r_tmp->id . ' & voucher_id = ' . $tmp_voucher_id . ' 更新成功；company_id: ' . $company_id . PHP_EOL;
                    } else {
                        echo '这个 create_id = ' . $v_c_l_r_tmp->id . ' & voucher_id = ' . ' 更新失败；company_id: ' . $company_id . PHP_EOL;
                    }
                } else {
                    echo '这个 create_id = ' . $v_c_l_r_tmp->id . ' & voucher_id = ' . $tmp_voucher_id . ' 没有对应的company_id: ' . $company_id . PHP_EOL;
                }
            }
            echo 'page' . $page . ' 结束' . PHP_EOL;
            $page++;
        }
    }
}
