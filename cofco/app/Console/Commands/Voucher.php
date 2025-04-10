<?php
/**
 * Created by PhpStorm.
 * User: chuanbin
 * Date: 2017/12/5
 * Time: 13:22
 */

namespace App\Console\Commands;


use App\Api\Logic\MessageCenter;
use App\Api\Logic\UserCenterMsg;
use Illuminate\Console\Command;
use Exception;

class Voucher extends Command
{
    protected $force = '';
    protected $signature = 'voucher {action}';
    protected $description = '优惠券处理';

    public function handle()
    {
        set_time_limit(0);
        $action = $this->argument('action');
        switch ($action) {
            case 'order_cancelfail':   //完成订单取消
                $this->order_cancelfail_task();
                break;
            case 'order_createfail':   //完成订单取消
                $this->order_createfail_task();
                break;
            case 'order_payfail':   //完成订单取消
                $this->order_payfail_task();
                break;
            case 'voucher_outtime_remind':   //完成订单取消
                $this->voucher_outtime_remind();
                $this->freeshipping_coupon_outtime_remind();
                break;
            case 'voucher_money_pool_task':   // 券资金池检查
                $this->voucher_money_pool_task();
                break;
            default:
                throw new Exception('～_～不能进行处理   啊哈哈哈哈哈哈～～～～ (^傻^)');
        }

    }

    public function order_cancelfail_task()
    {
        $voucher = new \App\Api\Model\Voucher\Voucher();
        $time = strtotime('-6 hour');
        $sql = "select pv.number from (promotion_voucher pv
                join promotion_voucher_order_user pvou on pv.voucher_id=pvou.voucher_id)
                join sdb_b2c_service_orders sbso on pvou.order_id=sbso.service_order_id
                join sdb_b2c_orders sbo on sbso.order_id = sbo.order_id where pv.status='lock' and
                pvou.disabled=0 and sbo.status='dead' and pvou.use_time>{$time}";
        $_db = app('api_db')->connection('neigou_store');
        $voucher_list = $_db->select($sql);
        foreach ($voucher_list as $voucher_item) {
            $voucher_number_list[] = $voucher_item->number;
        }
        if (isset($voucher_number_list) && count($voucher_number_list) > 0) {
            $voucher_result = $voucher->exchangeStatus($voucher_number_list, 'normal', "crontab检查，完成订单取消");
            if ($voucher_result == true) {
                \Neigou\Logger::General('action.voucher', array(
                    'action' => 'cancel_sync',
                    'states' => 'success',
                    'number' => json_encode($voucher_number_list)
                ));
            } else {
                \Neigou\Logger::General('action.voucher', array(
                    'action' => 'cancel_sync',
                    'states' => 'failed',
                    'number' => json_encode($voucher_number_list),
                    'error_msg' => $voucher_result
                ));
            }
        }
    }

    public function order_createfail_task()
    {
        $voucher = new \App\Api\Model\Voucher\Voucher();
        $time = strtotime('-6 hour');
        $sql = "select pv.number,pvou.order_id from promotion_voucher pv
                join promotion_voucher_order_user pvou on pv.voucher_id=pvou.voucher_id
                where pv.status='lock' and pvou.disabled=0 and pvou.use_time>{$time} group by pv.number";
        $_db = app('api_db')->connection('neigou_store');
        $voucher_order_list = $_db->select($sql);

        foreach ($voucher_order_list as $voucher_order_item) {
            $order_id = $voucher_order_item->order_id;

            // 暂时只处理14位订单号，超过14位新版订单号暂时不处理。
            if (strlen(strval($order_id)) > 14) {
                continue;
            }
            $sql = "select order_id from sdb_b2c_orders where order_id={$order_id}";
            $order_result = $_db->selectOne($sql);
            if (empty($order_result)) {
                $voucher_number_list[] = $voucher_order_item->number;
            }
        }
        if (isset($voucher_number_list) && count($voucher_number_list) > 0) {
            $voucher_result = $voucher->exchangeStatus($voucher_number_list, 'normal', "crontab检查，创建订单失败，回滚状态");
            if ($voucher_result == true) {
                \Neigou\Logger::General('action.voucher', array(
                    'action' => 'create_sync',
                    'states' => 'success',
                    'number' => json_encode($voucher_number_list)
                ));
            } else {
                \Neigou\Logger::General('action.voucher', array(
                    'action' => 'create_sync',
                    'states' => 'failed',
                    'number' => json_encode($voucher_number_list),
                    'error_msg' => $voucher_result
                ));
            }
        }
    }

    public function order_payfail_task()
    {
        $voucher = new \App\Api\Model\Voucher\Voucher();
        $time = strtotime('-6 hour');
        $sql = "select pv.number from (promotion_voucher pv
                join promotion_voucher_order_user pvou on pv.voucher_id=pvou.voucher_id)
                join sdb_b2c_service_orders sbso on pvou.order_id=sbso.service_order_id
                join sdb_b2c_orders sbo on sbso.order_id = sbo.order_id where pv.status='lock' and
                pvou.disabled=0 and sbo.pay_status='1' and sbo.status!='dead' and pvou.use_time>{$time}";
        $_db = app('api_db')->connection('neigou_store');
        $voucher_list = $_db->select($sql);

        foreach ($voucher_list as $voucher_item) {
            $voucher_number_list[] = $voucher_item->number;
        }
        if (isset($voucher_number_list) && count($voucher_number_list) > 0) {
            $voucher_result = $voucher->exchangeStatus($voucher_number_list, "finish", "crontab检查，完成订单支付");
            if ($voucher_result == true) {
                \Neigou\Logger::General('action.voucher', array(
                    'action' => 'pay_sync',
                    'states' => 'success',
                    'number' => json_encode($voucher_number_list)
                ));
            } else {
                \Neigou\Logger::General('action.voucher', array(
                    'action' => 'pay_sync',
                    'states' => 'failed',
                    'number' => json_encode($voucher_number_list),
                    'error_msg' => $voucher_result
                ));
            }
        }
    }

    public function voucher_outtime_remind()
    {
        $begin_time = strtotime(date("Y-m-d", strtotime("+1 day")));
        $end_time = strtotime(date("Y-m-d", strtotime("+2 day"))) - 1;
        $sql = "select pv.type as voucher_type,pv.discount,pv.money,pv.start_time,pv.valid_time,pv.rule_name,sbm.mobile,sbm.wxopenid,sbm.member_id,sbm.company_id from (promotion_voucher_member pvm join promotion_voucher pv on pvm.voucher_id=pv.voucher_id )
                join sdb_b2c_members sbm on pvm.member_id=sbm.member_id
                where pv.valid_time>$begin_time and pv.valid_time<=$end_time and pv.start_time<=$begin_time and pv.status='normal'";
        $_db = app('api_db')->connection('neigou_store');
        $voucher_list = $_db->select($sql);
        $message_data_list = array();
        foreach ($voucher_list as $ind => $voucher_item) {
            $message_data_list[] = array(
                "member_id" => $voucher_item->member_id,
                "money" => intval($voucher_item->money),
                'voucher_type' => $voucher_item->voucher_type,
                'discount' => $voucher_item->discount,
                "company_id" => $voucher_item->company_id,
                "valid_time" => $voucher_item->valid_time,
                "start_time" => $voucher_item->start_time,
                "rule_name" => $voucher_item->rule_name,
                "type" => 1
            );
        }

        $message_center_mgr = new MessageCenter();
        $message_center_mgr->sendMessage($message_data_list);

        $user_center_mgr = new UserCenterMsg();
        $user_center_mgr->sendUserCenterMsg($message_data_list);
    }

    public function freeshipping_coupon_outtime_remind()
    {
        $begin_time = strtotime(date("Y-m-d", strtotime("+1 day")));
        $end_time = strtotime(date("Y-m-d", strtotime("+2 day"))) - 1;
        $sql = "select pfm.valid_time,pfm.start_time,pfm.rule_name,sbm.mobile,sbm.wxopenid,sbm.member_id,sbm.company_id from promotion_freeshipping_member pfm
                join sdb_b2c_members sbm on pfm.member_id=sbm.member_id
                where pfm.valid_time>$begin_time and pfm.valid_time<=$end_time and pfm.start_time >=$begin_time and pfm.status=0";
        $_db = app('api_db')->connection('neigou_store');
        $voucher_list = $_db->select($sql);

        $message_data_list = array();
        foreach ($voucher_list as $ind => $voucher_item) {
            $message_data_list[] = array(
                "member_id" => $voucher_item->member_id,
                "company_id" => $voucher_item->company_id,
                "valid_time" => $voucher_item->valid_time,
                "start_time" => $voucher_item->start_time,
                "rule_name" => $voucher_item->rule_name,
                "type" => 1
            );
        }
        $message_center_mgr = new MessageCenter;
        $message_center_mgr->freeshippingSendMessage($message_data_list);

        $user_center_mgr = new UserCenterMsg;
        $user_center_mgr->freeshippingSendUserCenterMsg($message_data_list);

    }

    // 券资金池检查
    public function voucher_money_pool_task()
    {
        $_db = app('api_db')->connection('neigou_store');

        // 获取所有资金池
        $pool_list = $_db->table('promotion_voucher_money_pool')->where('status', 1)->get();

        if (empty($pool_list)) {
            echo "END \n";
            return;
        }
        $pool_list = json_decode(json_encode($pool_list), true);
        foreach ($pool_list as $pool) {
            // 检查信用额度
            if ($pool['credit_limit'] > 0 && $pool['credit_limit'] - $pool['used_money'] < $pool['credit_limit'] * 0.05) {
                \Neigou\Logger::General('action.voucher', array(
                    'action' => 'voucher_money_pool_task',
                    'states' => 'waring',
                    'pool_id' => $pool['pool_id'],
                    'type' => 'credit_limit',
                    'message' => $pool['remark']
                ));
            }
            $start_time = strtotime(date('Y-m-d'));
            $end_time = $start_time + 86400;
            // 检查单日额度
            if ($pool['per_day_limit'] > 0) {
                $where = [
                    ['pool_id', $pool['pool_id']],
                    ['type', 'money'],
                    ['create_time', '>', $start_time],
                    ['create_time', '<', $end_time],
                ];
                $per_day_money = $_db->table('promotion_voucher_money_pool_log')->where($where)->sum('money');
                if ($per_day_money > $pool['per_day_limit']) {
                    \Neigou\Logger::General('action.voucher', array(
                        'action' => 'voucher_money_pool_task',
                        'states' => 'waring',
                        'pool_id' => $pool['pool_id'],
                        'type' => 'per_day_limit',
                        'message' => $pool['remark']
                    ));
                }
            }

            // 检查单日用户额度
            if ($pool['per_day_people_limit'] > 0) {
                $where = [
                    ['pool_id', $pool['pool_id']],
                    ['type', 'money'],
                    ['create_time', '>', $start_time],
                    ['create_time', '<', $end_time],
                ];
                $member_list = $_db->table('promotion_voucher_money_pool_log')->select('member_id',
                    $_db->raw('SUM(money) AS total'))->where($where)->groupBy('member_id')->havingRaw("SUM(money) > {$pool['per_day_people_limit']}")->get();
                $member_list = json_decode(json_encode($member_list), true);
                if (!empty($member_list)) {
                    \Neigou\Logger::General('action.voucher', array(
                        'action' => 'voucher_money_pool_task',
                        'states' => 'waring',
                        'pool_id' => $pool['pool_id'],
                        'type' => 'per_day_people_limit',
                        'message' => $pool['remark'],
                        'member_list' => json_encode($member_list)
                    ));
                }
            }
        }

        echo "END \n";
        return;
    }

}
