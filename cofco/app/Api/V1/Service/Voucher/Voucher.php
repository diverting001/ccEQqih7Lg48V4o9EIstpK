<?php
/**
 * Created by PhpStorm.
 * User: chuanbin
 * Date: 2018/6/8
 * Time: 11:08
 */

namespace app\Api\V1\Service\Voucher;

use App\Api\Model\Voucher\DutyFreeCouponModel;
use App\Api\Model\Voucher\FreeShippingCouponModel;
use App\Api\Model\Voucher\RuleManager;
use App\Api\Model\Voucher\TaskManager;
use App\Api\Model\Voucher\VoucherMember;
use Neigou\RedisNeigou;

class Voucher
{
    /**
     * 通过商品列表聚合用户优惠券列表
     * @param $member_id
     * @param $filter
     * @return mixed
     */
    public function getVoucherWithProduct($member_id, $filter)
    {
        $member_model = new VoucherMember();
        //获取用户所有 内购券、打折券列表
        $voucher_list = $member_model->queryMemberBindedVoucher($member_id, 'normal');
        //免税、免邮 券 valid Rule
        $where = "valid_time>" . time() . " AND status=0 AND start_time<" . time();
        //获取用户所有 免邮券列表
        $freeshipping_model = new FreeShippingCouponModel();
        $freeshipping_voucher_list = $freeshipping_model->queryMemberCoupon($member_id, $where, '', $code, $msg);
        //获取用户所有 免税券列表
        $dutyfree_model = new DutyFreeCouponModel();
        $dutyfree_voucher_list = $dutyfree_model->queryMemberCoupon($member_id, $where, '');
//        //获取券黑名单
        $black = $this->getBlackList();
        $gidA = array();
        foreach ($filter['products'] as $goods) {
            //过滤黑名单商品不能使用内购券 免邮券 免税券
            if (!in_array($goods['bn'], $black)) {
                $gidA[] = $goods['goods_id'];
            }
        }
        //查询DB 商品对应的 内购券 RuleID
        $rules = $this->getRuleIdWithProduct($gidA);
        $ret['voucher_list'] = $this->filter_rule($voucher_list, $rules['voucher']);
        $ret['freeshipping_list'] = $this->filter_rule($freeshipping_voucher_list, $rules['freeshipping']);
        $ret['dutyfree_list'] = $this->filter_rule($dutyfree_voucher_list, $rules['dutyfree']);
        return $ret;
    }

    /**
     * 获取券黑名单
     * @return array
     */
    private function getBlackList()
    {
        $time = time();
        $sql = "select * from promotion_voucher_blacklist_rule where type= 1 AND  start_time<={$time} and end_time >={$time}";
        $retBlackList = app('api_db')->connection('neigou_store')->select($sql);
        $ruleBlackList = array();
        foreach ($retBlackList as $black_item) {
            if (!empty($black_item->rule)) {
                $rule = json_decode($black_item->rule, true);
                $ruleBlackList[$rule['bn']] = $rule['bn'];
            }
        }
        return $ruleBlackList;
    }

    /**
     * 通过规则规律优惠券
     * @param $voucher_list
     * @param $rules
     * @return mixed
     */
    private function filter_rule($voucher_list, $rules)
    {
        foreach ($voucher_list as $key => $voucher) {
            if (empty($rules[$voucher->rule_id]) || $voucher->start_time > time()) {
                unset($voucher_list[$key]);
            } else {
                $voucher_list[$key]->match_goods = $rules[$voucher->rule_id];
                $rule_condition = unserialize($voucher->rule_condition);
                $voucher_list[$key]->limit_cost = $rule_condition[0]['filter_rule']['limit_cost'];
                unset($voucher_list[$key]->rule_condition);
                unset($voucher_list[$key]->last_modified);
                unset($voucher_list[$key]->create_id);
            }

        }
        return $voucher_list;
    }

    /**
     * 通过ES聚合商品和券规则的对应关系
     * @param $goods_list
     * @return mixed
     */
    public function getRuleIdWithProduct($goods_list)
    {
        $key = "service:voucher:get:" . md5(json_encode($goods_list));
        $redis = new RedisNeigou();
        $rules = $redis->_redis_connection->get($key);
        if ($rules == false) {
            $rule_manager = new RuleManager();
            $db_res = $rule_manager->getRuleIdByGoods($goods_list);
            $db_data = array();
            $rules = array();
            //对DB结果集进行重新索引
            foreach ($db_res as $db_key => $db_val) {
                $rule_val = json_decode($db_val->value);
                $db_data[$db_val->goods_id][$db_val->business_field] = $rule_val;
                foreach ($rule_val as $rule_id) {
                    $rules[$db_val->business_field][$rule_id][] = $db_val->goods_id;
                }
            }
            $redis->_redis_connection->set($key, json_encode($rules), 60);
        } else {
            $rules = json_decode($rules, true);
        }
        return $rules;
    }


    /**
     * 通过ECstore openapi 发送规则变更task
     * @param $rule_id
     * @param $rule_condition
     * @param $old_rule_condition
     * @param $promotion_type
     * @return bool
     */
    public function sendEsTask($rule_id, $rule_condition, $old_rule_condition, $promotion_type)
    {
        $md5_new = md5($rule_condition);
        $md5_old = md5($old_rule_condition);
        $task_mdl = new TaskManager();
        if ($md5_new == $md5_old) {
            //无变更 无需存入
            return true;
        } else {
            //存入数据库
            if (!empty($old_rule_condition)) {
                //添加rm记录
                $rzt_rm = $task_mdl->addTask($rule_id, $old_rule_condition, 'rm', $promotion_type);
                $rzt_add = $task_mdl->addTask($rule_id, $rule_condition, 'add', $promotion_type);
            } else {
                //添加add记录
                $rzt_add = $task_mdl->addTask($rule_id, $rule_condition, 'add', $promotion_type);
            }
        }
        if (intval($rzt_add) <= 0 && intval($rzt_rm) <= 0) {
            return false;
        } else {
            return true;
        }
    }
}
