<?php
/**
 * Created by PhpStorm.
 * User: chuanbin
 * Date: 2017/10/20
 * Time: 10:29
 */

namespace App\Api\Model\Voucher;


use App\Api\V1\Service\Voucher\Voucher as VoucherService;

class RuleManager
{
    private $promotion_voucher_rules = 'promotion_voucher_rules';
    private $promotion_freeshipping_rules = 'promotion_freeshipping_rules';
    private $_db;
    private $_server_db;

    public function __construct() {
        $this->_db = app('api_db')->connection('neigou_store');
        $this->_server_db = app('api_db');
    }

    //获取券规则
    public function getRuleList($params_data_array){
//        $params_data_array = json_decode($query_params, true);
        if (!isset($params_data_array["rule_id_list"]) &&
            !is_array($params_data_array["rule_id_list"]) &&
            empty($params_data_array["rule_id_list"])) {
            \Neigou\Logger::General('action.voucher', array('action'=>'getRuleList','success'=>0, 'reason'=>'invalid_params'));
            return "代金券参数不正确";//err 1
        }
        $ruleIdListStr = implode(',', $params_data_array["rule_id_list"]);
        if(empty($ruleIdListStr)){
            return "免邮券参数不正确";//err 1
        }
        //$sql = "select * from {$this->promotion_voucher_rules} where rule_id in ({$ruleIdListStr})";
        $placeholder = implode(',',array_fill(0,count($params_data_array["rule_id_list"]),'?'));
        $sql = "select * from {$this->promotion_voucher_rules} where rule_id in ({$placeholder})";
        $result = $this->_db->select($sql,$params_data_array["rule_id_list"]);
        if(!$result){
            return "规则ID指向规则不存在";//err 1
        }
        $ruleInfoList = array();
        foreach ($result as $ruleInfoItem) {
            $ruleInfoList[$ruleInfoItem->rule_id] = $ruleInfoItem;
        }
        return $ruleInfoList;
    }


    public function createRule($type, $create_data_array) {
        \Neigou\Logger::General('action.voucher', array('action'=>'createRule',
            'type'=>$type));
//        $create_data_array = json_decode($json_data, true);
        if (empty($type)) {
            \Neigou\Logger::General('action.voucher', array('action'=>'createRule',
                'success'=>0, 'reason'=>'null_type'));
            return "传入的type为空";//err 1
        }
        if (empty($create_data_array) ||
            !isset($create_data_array['name']) ||
            !isset($create_data_array['description']) ||
            !isset($create_data_array['op_name']) ||
            !isset($create_data_array['log_text']) ||
            !isset($create_data_array['rule_data'])) {
            \Neigou\Logger::General('action.voucher', array('action'=>'createRule',
                'success'=>0, 'reason'=>'invalid_params'));
            return "无效参数";//err 1
        }
        $generator = new $type;
        if (method_exists($generator, "create")) {
            $create_rule = $generator->create($create_data_array['rule_data']);
            if ($create_rule) {
                $create_data = array(
                    'name'=>$create_data_array['name'],
                    'description'=>$create_data_array['description'],
                    'op_name'=>$create_data_array['op_name'],
                    'log_text'=>$create_data_array['log_text'],
                    'create_time'=>time(),
                    'rule_condition'=>serialize(array($create_rule))
                );
                $result_rule_id = $this->store_sqllink->insert($create_data, $this->promotion_voucher_rules);
                if ($result_rule_id) {
                    \Neigou\Logger::General('action.voucher', array('action'=>'createRule',
                        'type'=>$type, 'success'=>1, 'rule_id'=>$result_rule_id));
                    return $this->success();
                } else {
                    \Neigou\Logger::General('action.voucher', array('action'=>'createRule',
                        'type'=>$type, 'success'=>0, 'reason'=>'insert_failed'));
                    return $this->error(1, "规则插入失败");
                }
            } else {
                \Neigou\Logger::General('action.voucher', array('action'=>'createRule',
                    'type'=>$type, 'success'=>0, 'reason'=>'invalid_type'));
                return $this->error(1, "传入创建规则不合理");
            }
        } else {
            \Neigou\Logger::General('action.voucher', array('action'=>'createRule',
                'type'=>$type, 'success'=>0, 'reason'=>'invalid_type'));
            return $this->error(1, "传入的type不存在");
        }
    }

    public function saveRule($save_data_array) {
        if (empty($save_data_array) ||
            !isset($save_data_array['rule_type']) ||
            !isset($save_data_array['description']) ||
            !isset($save_data_array['op_name']) ||
            !isset($save_data_array['log_text']) ||
            !isset($save_data_array['rule_data'])) {
            \Neigou\Logger::General('action.voucher', array('action'=>'saveRule',
                'success'=>0, 'reason'=>'invalid_params'));
            return "无效参数";
        }
        $str_generator = 'App\Api\Model\Voucher\Generate\\'.$save_data_array['rule_type'];
        $generator = new $str_generator;
        if (method_exists($generator, "create")) {
            $create_rule = $generator->create($save_data_array['rule_data']);
            if ($create_rule) {
                if (empty($save_data_array['rule_id'])) {
                    if (!isset($save_data_array['name'])) {
                        \Neigou\Logger::General('action.voucher', array('action'=>'saveRule',
                            'success'=>0, 'reason'=>'invalid_params'));
                        return "无效参数";
                    }
                    $create_data = array(
                        'name'=>$save_data_array['name'],
                        'tag' => $save_data_array['tag'],
                        'description'=>$save_data_array['description'],
                        'op_name'=>$save_data_array['op_name'],
                        'log_text'=>$save_data_array['log_text'],
                        'create_time'=>time(),
                        'rule_condition'=>serialize(array($create_rule)),
                        'extend_data'=>$save_data_array['extend_data']
                    );
                    //判断name是否已经存在
                    $map['name'] = $save_data_array['name'];
                    $rzt_info = $this->_db->table($this->promotion_voucher_rules)->where($map)->first();
                    if($rzt_info->rule_id>0){
                        return '规则名称重复请更换';
                    }
                    $result_rule_id = $this->_db->table($this->promotion_voucher_rules)->insertGetId($create_data);
                    $es_res = $this->sendEs($result_rule_id,$create_data['rule_condition'],array());
                    if(!$es_res) return 'ES商品对应关系任务创建失败';
                } else {
                    $save_data = array(
                        'description'=>$save_data_array['description'],
                        'name'=>$save_data_array['name'],
                        'tag' => $save_data_array['tag'],
                        'op_name'=>$save_data_array['op_name'],
                        'log_text'=>$save_data_array['log_text'],
                        'create_time'=>time(),
                        'rule_condition'=>serialize(array($create_rule)),
                        'extend_data'=>$save_data_array['extend_data']
                    );
                    $where = [
                        ['rule_id',$save_data_array['rule_id']]
                    ];
                    $old_rule = $this->_db->table($this->promotion_voucher_rules)->Where($where)->first();
                    $es_res = $this->sendEs($save_data_array['rule_id'],$save_data['rule_condition'],$old_rule->rule_condition);
                    if(!$es_res) return 'ES商品对应关系任务创建失败';
                    $rzt = $this->_db->table($this->promotion_voucher_rules)->where($where)->update($save_data);
                    if($rzt>0){
                        $result_rule_id = $save_data_array['rule_id'];
                    }
                }
                if ($result_rule_id > 0) {
                    \Neigou\Logger::General('action.voucher', array('action'=>'saveRule', 'success'=>1, 'rule_id'=>$result_rule_id));
                    return $result_rule_id;
                } else {
                    \Neigou\Logger::General('action.voucher', array('action'=>'saveRule',  'success'=>0, 'reason'=>'insert_failed'));
                    return "规则插入失败";
                }
            } else {
                \Neigou\Logger::General('action.voucher', array('action'=>'saveRule', 'success'=>0, 'reason'=>'invalid_type'));
                return "传入创建规则不合理";
            }
        } else {
            \Neigou\Logger::General('action.voucher', array('action'=>'saveRule',  'success'=>0, 'reason'=>'invalid_type'));
            return "传入的type不存在";
        }
    }

    /**
     * 创建ES商品对应关系更新任务
     * @param $rule_id
     * @param $rule_condition
     * @param $old_condition
     * @return bool
     */
    private function sendEs($rule_id,$rule_condition,$old_condition){
        $service = new VoucherService();
        $res = $service->sendEsTask($rule_id,$rule_condition,$old_condition,'voucher');
        if($res){
            return true;
        } else {
            return false;
        }
    }

    /**
     * 通过商品列表获取对应的规则列表
     * @param $goods_id
     * @return mixed
     */
    public function getRuleIdByGoods($goods_id){
        $where['business_code'] = 'voucher';
        return $this->_server_db->table('server_search_business_value')->where($where)->whereIn('goods_id',$goods_id)->get()->all();
    }

    //获取券规则
    public function getActiveRules($types = [])
    {
        $types = $types ?: ['voucher', 'freeshipping', 'dutyfree'];
        $rules_key_type = [];
        if (in_array('voucher', $types)) {
            $promotion_voucher_rules = $this->_db->table($this->promotion_voucher_rules)->where(['disabled' => 0])
                ->orderBy('rule_id', 'asc')
                ->select([
                    'rule_id',
                    'rule_condition'
                ])->get()->map(function ($value) {
                    return (array)$value;
                })->toArray();
            $rules_key_type['voucher'] = $this->filterByProcessor($promotion_voucher_rules);
        }
        if (array_intersect(['freeshipping', 'dutyfree'], $types)) {
            $promotion_freeshipping_dutyfree_rules = $this->_db->table($this->promotion_freeshipping_rules)->where(['disabled' => 0])
                ->orderBy('rule_id', 'asc')
                ->select([
                    'rule_id',
                    'rule_condition',
                    'voucher_type',
                ])->get()->map(function ($value) {
                    return (array)$value;
                })->toArray();
            $f_d_rules = $this->filterByProcessor($promotion_freeshipping_dutyfree_rules);
            foreach ($f_d_rules as $f_d_rule) {
                if ($f_d_rule['voucher_type'] == 0) {
                    $rules_key_type['freeshipping'][] = $f_d_rule;
                } elseif ($f_d_rule['voucher_type'] == 1) {
                    $rules_key_type['dutyfree'][] = $f_d_rule;
                }
            }
        }
        $rules_key_type['time'] = time();
        return $rules_key_type;
    }

    private function filterByProcessor($rules, $processors = null)
    {
        $return_rules = [];
        $processors = $processors ?: ['CostGoodsRuleProcessor', 'CostBrandRuleProcessor', 'CostMallAndCatRuleProcessor', 'CostReachedRuleProcessor', 'CostShopRuleProcessor'];
        foreach ($rules as $rule) {
            $condition_arr = unserialize($rule['rule_condition']);
            $condition = $condition_arr[0];
            if (!in_array($condition['processor'], $processors)) {
                continue;
            }
            $return_rules[$rule['rule_id']] = [
                'condition' => $condition,
                'rule_id' => $rule['rule_id'],
                'voucher_type' => $rule['voucher_type'],
            ];
        }
        return $return_rules;
    }
}