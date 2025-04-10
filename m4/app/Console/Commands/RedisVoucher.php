<?php
/**
 * Created by PhpStorm.
 * User: guke
 * Date: 2018/6/20
 * Time: 13:22
 */

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Api\Logic\Service;
use App\Api\Model\Voucher\RuleManager;
use App\Api\Logic\Neigou\Ec;

class RedisVoucher extends Command
{
    protected $force = '';
    protected $signature = 'RedisVoucher {source?} {page_size?} {max_goods_id?} {cus_goods_ids?*}';
    protected $description = '优惠券任务';
    private $_db_store;
    private $_db_server;
    private $_ec;
    private $_redis;
    private $_rule_manager;

    public function __construct()
    {
        $this->_db_store = app('api_db')->connection('neigou_store');
        $this->_db_server = app('api_db');
        $this->_ec = new Ec();
        $this->_redis = new \Neigou\RedisNeigou();
        $this->_rule_manager = new RuleManager();
        parent::__construct();
    }

    public function handle()
    {
        $page_size = $this->argument('page_size') ?: 10;
        $source = $this->argument('source') ?: 'redis';

        $active_rules = $this->_rule_manager->getActiveRules();
        if ($source === 'redis') {
            $Redis = new \Neigou\RedisClient();
            $Redis->_debug = true;
            $queue_name = 'goods_up_voucher';
            while (1) {
                $goods_ids = [];
                $msgs = [];
                while (count($goods_ids) < $page_size) {
                    $msg = $Redis->get_msg($queue_name);//获取消息
                    $msgs[] = $msg;
                    $val = $msg;
                    //校验
                    $msg = json_decode($msg, true);
                    $rule = $Redis->check_msg($queue_name, $msg, $val);
                    if (!$rule) {
                        //消息为空 检索下是否存在process中的消息 存在回转到消息源中进行处理
                        sleep(5);
                        for ($i = 0; $i < 200; $i++) {
                            $Redis->roll_back($queue_name);
                        }
                        exit;
                    }
                    //处理消息
                    //处理二次json的msg
                    if (is_string($msg['msg'])) {
                        $msg['msg'] = json_decode($msg['msg'], true);
                    }
                    $goods_id = intval($msg['msg']['goods_id']);
                    $goods_ids[] = $goods_id;
                }

                if (!$this->setGoodsVoucher($goods_ids, $active_rules)) {
                    foreach ($msgs as $msg) {
                        $Redis->add_msg($queue_name . '_fail', $msg);
                    }
                } else {
                    foreach ($msgs as $msg) {
                        $Redis->del_process($queue_name, $msg);
                    }
                }
            }
        } elseif ($source === 'db') {
            $cus_goods_ids = $this->argument('cus_goods_ids');
            if ($cus_goods_ids) {
                $goods_ids = $cus_goods_ids;
                $this->setGoodsVoucher($goods_ids, $active_rules);
                echo '完成';
            } else {
                $max_goods_id = $this->argument('max_goods_id') ?: 0;
                $offset = 0;
                while (1) {
                    $goods_ids = $this->_db_store->table('sdb_b2c_goods')
                        ->where('goods_id', '>', $max_goods_id)
                        ->offset($offset)
                        ->limit($page_size)
                        ->orderBy('goods_id', 'asc')
                        ->pluck('goods_id')
                        ->toArray();
                    if (!$goods_ids) {
                        echo '完成';
                        break;
                    }
                    $this->setGoodsVoucher($goods_ids, $active_rules);
                    $max_goods_id = end($goods_ids);
//                $offset += $page_size;
                }
            }
        }
    }

    public function setGoodsVoucher($goods_ids, &$active_rules)
    {
        $rules_update_time = $this->_redis->_redis_connection->get('rules_update_time');
        if ($active_rules['time'] < $rules_update_time) {
            $active_rules = $this->_rule_manager->getActiveRules();
        }
        $goods_branch_list = $this->_ec->GetGoodsBranch($goods_ids);
        $cur_goods_ids = [];
        $cur_branch_ids = [];
        foreach ($goods_branch_list as $goods_branch) {
            if (!in_array($goods_branch['goods_id'], $cur_goods_ids)) {
                $cur_goods_ids[] = $goods_branch['goods_id'];
                $cur_branch_ids[] = $goods_branch['branch_id'];
            }
        }
        $split_goods_list = $this->_ec->GetSplitGoodsList($cur_goods_ids, $cur_branch_ids);
        $goods_list = array_merge((array)$split_goods_list['standard'], (array)$split_goods_list['vbranch']);
        if (!$goods_list) {
            echo 'ec不存在' . implode(',', $cur_goods_ids) . "\n";
            return true;
        }
        $ec_goods_ids = array_column($goods_list, 'goods_id');
        $goods_shop_list = $this->_db_store->table('sdb_b2c_products')
            ->whereIn('goods_id', $ec_goods_ids)
            ->select(['goods_id', 'pop_shop_id'])
            ->get()->map(function ($value) {
                return (array)$value;
            })
            ->toArray();
//        $goods_mall_list = $this->_db_store->table('mall_module_mall_goods')
//            ->whereIn('goods_id', $goods_ids)
//            ->select(['goods_id', 'mall_id'])
//            ->get()->map(function ($value) {
//                return (array)$value;
//            })
//            ->toArray();


        for ($i = 0, $count = count($goods_list); $i < $count; $i++) {
            for ($j = 0, $count2 = count($goods_shop_list); $j < $count2; $j++) {
                if ($goods_shop_list[$j]['goods_id'] == $goods_list[$i]['goods_id']) {
                    $goods_list[$i]['shop_ids'][] = $goods_shop_list[$j]['pop_shop_id'];
                    unset($goods_shop_list[$j]);
                    $count2 = count($goods_shop_list);
                }
            }
//            foreach ($goods_mall_list as &$goods_mall) {
//                if ($goods_mall['goods_id'] == $goods['goods_id']) {
//                    $goods['mall_ids'][] = $goods_mall['mall_id'];
//                    unset($goods_mall);
//                }
//            }
        }
        $voucher_goods = [];
        $freeshipping_goods = [];
        $dutyfree_goods = [];
        foreach ($goods_list as $goods) {
            if (!isset($goods['goods_id'])) {
                continue;
            }
            $this->goods_match_rules($active_rules['voucher'], $goods, 'voucher', $voucher_goods);
            $this->goods_match_rules($active_rules['freeshipping'], $goods, 'freeshipping', $freeshipping_goods);
            $this->goods_match_rules($active_rules['dutyfree'], $goods, 'dutyfree', $dutyfree_goods);
        }
        $ServiceLogic = new Service();
        $req_data = array(
            // 业务码
            'business_code' => 'voucher',
            'goods' => array_merge((array)$voucher_goods, (array)$freeshipping_goods, (array)$dutyfree_goods),
        );
        $ret = $ServiceLogic->ServiceCall('business_data_push', $req_data);
        if ($ret['error_code'] != 'SUCCESS' || $ret['data']['res'] != 'true') {
            echo '异常：' . implode(',', $cur_goods_ids) . json_encode($ret) . "\n";
            return false;
        }
        echo '券规则更新：' . implode(',', $ec_goods_ids) . "\n";
        if ($split_goods_list['miss']) {
            echo 'ec不存在:' . implode(',', $split_goods_list['miss']) . "\n";
        }
        return true;
    }

    function goods_match_rules($rules, $goods, $business_field, &$return_goods)
    {
        foreach ($rules as $rule) {
            $is_match = $this->is_match($goods, $rule);
            if ($is_match) {
                $return_goods[$goods['goods_id']]['goods_id'] = $goods['goods_id'];
                $return_goods[$goods['goods_id']]['business_field'] = $business_field;
                $return_goods[$goods['goods_id']]['business_value'][] = $rule['rule_id'];
                $return_goods[$goods['goods_id']]['act'] = 'cover';
            }
        }
    }

    function is_match($goods, $rule)
    {
        $is_match = 0;
        switch ($rule['condition']['processor']) {
            case 'CostGoodsRuleProcessor': // 指定商品
                $goods_ids = $rule['condition']['filter_rule']['goods'];
                if (in_array($goods['goods_id'], $goods_ids)) {
                    $is_match = 1;
                }
                break;
            case 'CostBrandRuleProcessor': // 指定品牌
                $brand_ids = $rule['condition']['filter_rule']['brand'];
                if (in_array($goods['brand_id'], $brand_ids)) {
                    $is_match = 1;
                }
                break;
            case 'CostMallAndCatRuleProcessor': // 指定商城及类目
                $mall_ids = $rule['condition']['filter_rule']['mall_id_list'];
                $mall_cat_ids = $rule['condition']['filter_rule']['mall_cat_id_list'];
                $goods_cat_ids = array(
                    $goods['cat_level_1']['cat_id'],
                    $goods['cat_level_2']['cat_id'],
                    $goods['cat_level_3']['cat_id']
                );
                if ($rule['condition']['filter_rule']['use_type'] != 'unless') {
                    if (in_array('all', $mall_ids) || array_intersect($mall_ids, (array)$goods['moduled'])) {
                        if (in_array('all', $mall_cat_ids) || array_intersect($goods_cat_ids, $mall_cat_ids)) {
                            $is_match = 1;
                            break;
                        }
                    }
                } else {
                    if (in_array('all', $mall_ids) || in_array('all', $mall_cat_ids)) {
                        $is_match = 0;
                    } elseif (array_intersect($goods_cat_ids, $mall_cat_ids) && array_intersect($mall_ids,
                            (array)$goods['moduled'])) {
                        $is_match = 0;
                    } else {
                        $is_match = 1;
                    }
                }
                break;
            case 'CostReachedRuleProcessor': // 全场通用
                $is_match = 1;
                break;
            case 'CostShopRuleProcessor': // 指定店铺
                if (isset($rule['condition']['filter_rule']['shop'])) {
                    $shop_ids = $rule['condition']['filter_rule']['shop'];
                } else {
                    $shop_ids = $rule['condition']['filter_rule']['shop_id_list'];
                }

                if ($rule['condition']['filter_rule']['use_type'] != 'unless' && array_intersect($shop_ids,
                        $goods['shop_ids']) || $rule['condition']['filter_rule']['use_type'] == 'unless' && !array_intersect($shop_ids,
                        $goods['shop_ids'])) {
                    $is_match = 1;
                }
                break;
        }
        return $is_match;
    }
}
