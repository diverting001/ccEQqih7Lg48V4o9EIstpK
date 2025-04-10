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

class VoucherTask extends Command
{
    protected $force = '';
    protected $signature = 'voucherTask {voucher_type} {page_size?} {rule_id?}';
    protected $description = '优惠券任务';
    private $_db_store;

    public function __construct()
    {
        $this->_db_store = app('api_db')->connection('neigou_store');
        parent::__construct();
    }

    public function handle()
    {
        set_time_limit(0);
//        $this->_db_store->enableQueryLog();
        $voucher_type = $this->argument('voucher_type');
        $page_size = $this->argument('page_size') ?: 100;
        $rule_id = $this->argument('rule_id');
        $where = [];
        if (in_array($voucher_type, ['voucher', 'freeshipping', 'dutyfree'])) {
            $where['service_type'] = $voucher_type;
        }
        if ($rule_id) {
            $where['rule_id'] = $rule_id;
        }

        while (1) {
            // 获取待处理任务
            $task = $this->_db_store->table('sdb_b2c_voucher_rules2goods_task')
                ->whereIn('status', [2, 3])
                ->where($where)
                ->orderBy('task_id', 'asc')
                ->first();
//            print_r($this->_db_store->getQueryLog());die;
            if ($task === null) {
                echo '处理完成';
                break;
            }
            $max_goods_id = 0;
            $task->process = 0;
            while (1) {
                // 获取待处理任务包含商品
                $res = $this->get_goods_by_task($task, $page_size, $max_goods_id);
                $goods_ids = $res['goods_ids'];
                $max_goods_id = end($goods_ids);
                echo 'task:' . $task->task_id . ' rule_id:' . $task->rule_id . ' totel:' . $task->total . ' process:' . $task->process . '(' . implode(',', $goods_ids) . ')' . "\n";
                // 处理完成
                if ($goods_ids === null) {
                    $this->_db_store->table('sdb_b2c_voucher_rules2goods_task')
                        ->where('task_id', $task->task_id)
                        ->update([
                            'status' => 1,
                            'total' => $res['count']
                        ]);
                    break;
                }
                $goods = array();
                foreach ($goods_ids as $goods_id) {
                    $goods[] = [
                        'goods_id' => $goods_id,
                        'business_field' => $task->service_type,
                        'business_value' => $task->rule_id,
                        'act' => $task->act // add rm cover
                    ];
                }
                $es_data = array(
                    // 业务码
                    'business_code' => 'voucher',
                    'goods' => $goods,
                );
                $res_update = $this->update_es_goods($es_data);
                if ($res_update['res'] == true) {
                    // 更新process
                    $task->process += $page_size;
                    $this->_db_store->table('sdb_b2c_voucher_rules2goods_task')
                        ->where('task_id', $task->task_id)
                        ->update([
                            'process' => $task->process,
                            'status' => 3,
                            'total' => $res['count']
                        ]);
                } else {
                    $save_data = [
                        'fail_count' => $task->fail_count + 1
                    ];
                    if ($task->fail_count == 5) {
                        $save_data['status'] = 4;
                    }
                    $this->_db_store->table('sdb_b2c_voucher_rules2goods_task')
                        ->where('task_id', $task->task_id)
                        ->update($save_data);
                    \Neigou\Logger::Debug('VoucherTask.failed', ['task_id' => $task->task_id, 'data' => $goods_ids, 'res' => $res_update]);
                }
            }
        }
    }

    public function update_es_goods($req_data)
    {
        $ServiceLogic = new Service();
        $ret = $ServiceLogic->ServiceCall('business_data_push', $req_data);
        if ($ret['error_code'] === 'SUCCESS' && $ret['data']['res'] === 'true') {
            return array(
                'res' => true
            );
        }
        return array(
            'error_msg' => $ret['error_msg'],
            'res' => false,
            'msg' => $ret
        );
    }

    public function get_goods_by_task(&$task, $page_size = 100, $max_goods_id = 0)
    {
        $condition_arr = unserialize($task->rule_condition);
        $condition = $condition_arr[0];
        $where = null;
        switch ($condition['processor']) {
            case 'CostGoodsRuleProcessor': // 指定商品
                $goods_ids = $condition['filter_rule']['goods'];
                if (count($goods_ids) > 0) {
                    $sql = 'select goods_id from sdb_b2c_goods WHERE goods_id in(' . implode(',', $goods_ids) . ')
                     order by goods_id asc limit ' . $task->process . ',' . $page_size;
                    $sql_count = 'select count(*) from sdb_b2c_goods WHERE goods_id in(' . implode(',', $goods_ids) . ')';
                } else {
                    return null;
                }
                break;
            case 'CostBrandRuleProcessor': // 指定品牌
                $brand_ids = $condition['filter_rule']['brand'];
                if (count($brand_ids) > 0) {
                    $sql = 'select goods_id from sdb_b2c_goods WHERE brand_id in(' . implode(',', $brand_ids) . ') AND goods_id>' . $max_goods_id . '
                     order by goods_id asc limit 0,' . $page_size;
                    $sql_count = 'select count(*) AS count from sdb_b2c_goods WHERE brand_id in(' . implode(',', $brand_ids) . ')';
                }
                break;
            case 'CostMallAndCatRuleProcessor': // 指定商城及类目
                if (!isset($condition['filter_rule']['use_type']) || $condition['filter_rule']['use_type'] == 'include') {
                    // 全部商城
                    if (in_array('all', $condition['filter_rule']['mall_id_list'])) {
                        if (in_array('all', $condition['filter_rule']['mall_cat_id_list'])) { // “全部”分类
                            $sql = 'select goods_id from sdb_b2c_goods where goods_id>' . $max_goods_id . ' 
                            order by goods_id asc limit 0,' . $page_size;
                            $sql_count = 'select count(*) AS count from sdb_b2c_goods';
                        } else { // 非全部分类
                            $mall_cat_ids_str = $this->getLastLevelCatIdsStr($condition['filter_rule']['mall_cat_id_list']);
                            $sql = 'SELECT goods.goods_id FROM sdb_b2c_goods goods
  LEFT JOIN sdb_b2c_goods_mall_cats mc on goods.goods_id=mc.goods_id
  WHERE (goods.mall_goods_cat IN(' . $mall_cat_ids_str . ') OR mc.cat_id in(' . $mall_cat_ids_str . ')) AND goods.goods_id>' . $max_goods_id . '
  ORDER BY goods.goods_id ASC 
  LIMIT 0,' . $page_size;
                            $sql_count = 'SELECT count(*) AS count FROM sdb_b2c_goods goods
  WHERE mall_goods_cat IN(' . $mall_cat_ids_str . ')';
                        }
                    } elseif (!in_array('all', $condition['filter_rule']['mall_id_list'])) { // 非全部商城
                        $mall_ids_str = implode(',', $condition['filter_rule']['mall_id_list']);
                        if (!in_array('all', $condition['filter_rule']['mall_cat_id_list'])) { // 非全部分类
                            $mall_cat_ids_str = $this->getLastLevelCatIdsStr($condition['filter_rule']['mall_cat_id_list']);
                            $sql = 'SELECT m2g.goods_id FROM mall_module_mall_goods m2g
  LEFT JOIN sdb_b2c_goods goods ON goods.goods_id = m2g.goods_id
  LEFT JOIN sdb_b2c_goods_mall_cats mc on goods.goods_id=mc.goods_id
  WHERE m2g.mall_id IN(' . $mall_ids_str . ') AND (goods.mall_goods_cat IN(' . $mall_cat_ids_str . ') OR mc.cat_id in(' . $mall_cat_ids_str . ')) AND m2g.goods_id>' . $max_goods_id . '
  ORDER BY m2g.goods_id ASC 
  LIMIT 0,' . $page_size;
                            $sql_count = 'SELECT count(*) AS count FROM mall_module_mall_goods m2g
  LEFT JOIN sdb_b2c_goods goods ON goods.goods_id = m2g.goods_id
  WHERE m2g.mall_id IN(' . $mall_ids_str . ') AND goods.mall_goods_cat IN(' . $mall_cat_ids_str . ')';
                            $sql_count = null;
                        } else { // 全部分类
                            $sql = 'SELECT goods_id FROM mall_module_mall_goods 
  WHERE mall_id IN(' . $mall_ids_str . ') AND goods_id>' . $max_goods_id . '
  ORDER BY goods_id ASC 
  LIMIT 0,' . $page_size;
                            $sql_count = 'SELECT count(*) AS count FROM mall_module_mall_goods WHERE mall_id IN(' . $mall_ids_str . ')';
                        }
                    }
                } elseif ($condition['filter_rule']['use_type'] == 'unless') {
                    // 全部商城
                    if (in_array('all', $condition['filter_rule']['mall_id_list'])) {
                        if (in_array('all', $condition['filter_rule']['mall_cat_id_list'])) { // “全部”分类
                            $sql = 'select goods_id from sdb_b2c_goods where 1!=1';
                            $sql_count = 'select count(*) AS count from sdb_b2c_goods where 1!=1';
                        } else { // 非全部分类
                            $mall_cat_ids_str = $this->getLastLevelCatIdsStr($condition['filter_rule']['mall_cat_id_list']);
                            $sql = 'SELECT goods.goods_id FROM sdb_b2c_goods goods
  LEFT JOIN sdb_b2c_goods_mall_cats mc on goods.goods_id=mc.goods_id
  WHERE (goods.mall_goods_cat NOT IN(' . $mall_cat_ids_str . ') AND mc.cat_id NOT IN(' . $mall_cat_ids_str . ')) AND goods.goods_id>' . $max_goods_id . '
  ORDER BY goods.goods_id ASC 
  LIMIT 0,' . $page_size;
                            $sql_count = 'SELECT count(*) AS count FROM sdb_b2c_goods 
  WHERE mall_goods_cat NOT IN(' . $mall_cat_ids_str . ')';
                        }
                    } elseif (!in_array('all', $condition['filter_rule']['mall_id_list'])) { // 非全部商城
                        $mall_ids_str = implode(',', $condition['filter_rule']['mall_id_list']);
                        if (!in_array('all', $condition['filter_rule']['mall_cat_id_list'])) { // 非全部分类
                            $mall_cat_ids_str = $this->getLastLevelCatIdsStr($condition['filter_rule']['mall_cat_id_list']);
                            $sql = 'SELECT m2g.goods_id FROM mall_module_mall_goods m2g
  LEFT JOIN sdb_b2c_goods goods ON goods.goods_id = m2g.goods_id
  LEFT JOIN sdb_b2c_goods_mall_cats mc on goods.goods_id=mc.goods_id
  WHERE (m2g.mall_id NOT IN(' . $mall_ids_str . ') OR (goods.mall_goods_cat NOT IN(' . $mall_cat_ids_str . ')  AND mc.cat_id NOT IN(' . $mall_cat_ids_str . '))) AND m2g.goods_id>' . $max_goods_id . '
  ORDER BY m2g.goods_id ASC
  LIMIT 0,' . $page_size;
                            $sql_count = 'SELECT count(*) AS count FROM mall_module_mall_goods m2g
  LEFT JOIN sdb_b2c_goods goods ON goods.goods_id = m2g.goods_id
  WHERE m2g.mall_id NOT IN(' . $mall_ids_str . ') OR goods.mall_goods_cat NOT IN(' . $mall_cat_ids_str . ')';
                        } else { // 全部分类
                            $sql = 'SELECT DISTINCT goods_id FROM mall_module_mall_goods
  WHERE mall_id NOT IN(' . $mall_ids_str . ') AND goods_id>' . $max_goods_id . '
  ORDER BY goods_id ASC
  LIMIT 0,' . $page_size;
                            $sql_count = 'SELECT count(DISTINCT goods_id) AS count FROM mall_module_mall_goods
  WHERE mall_id NOT IN(' . $mall_ids_str . ')';
                        }
                    }
                }
                break;
            case 'CostReachedRuleProcessor': // 全场通用
                $sql = 'select goods_id from sdb_b2c_goods where goods_id>' . $max_goods_id . ' 
                            order by goods_id asc limit 0,' . $page_size;
                $sql_count = 'select count(*) AS count from sdb_b2c_goods';
                break;
            case 'CostShopRuleProcessor': // 指定店铺
                $shop_id_list = [];
                if (isset($condition['filter_rule']['shop'])) {
                    $shop_id_list = $condition['filter_rule']['shop'];
                } else {
                    $shop_id_list = $condition['filter_rule']['shop_id_list'];
                }

                $shop_ids_str = implode(',', $shop_id_list);
                $op = 'IN';
                if ($condition['filter_rule']['use_type'] == 'unless') {
                    $op = 'NOT IN';
                }
                $sql = 'SELECT DISTINCT products.goods_id FROM sdb_b2c_products products
 WHERE products.pop_shop_id ' . $op . ' (' . $shop_ids_str . ') AND products.goods_id>' . $max_goods_id . '
 ORDER BY products.product_id ASC
 LIMIT 0,' . $page_size;
                $sql_count = null;
                break;
            case 'CostModuleAndClassRuleProcessor': // 指定模块及分类
                return [
                    'goods_ids' => null
                ];
                break;
        }
        $rows = $this->_db_store->select($sql);
//        if ($sql_count) {
//            $count = $this->_db_store->select($sql_count);
//        }
        if (!$rows) {
            return [
                'goods_ids' => null,
                'count' => 0//$count[0]->count
            ];
        }
        $goods_ids = array();
        foreach ($rows as $row) {
            $goods_ids[] = $row->goods_id;
        }
        return [
            'goods_ids' => $goods_ids,
            'count' => 0//$count[0]->count
        ];
    }

    private function getLastLevelCatIdsStr($catIds)
    {
        $catIds_str = implode(',', $catIds);
        $sql = 'SELECT cat_id from sdb_b2c_mall_goods_cat where (cat_id in(' . $catIds_str . ')';
        foreach ($catIds as $catId) {
            $sql .= ' or cat_path like \'%,' . $catId . ',%\'';
        }
        $sql .= ") and cat_path like ',%,%,'";
        $rows = $this->_db_store->select($sql);
        $return_catIds = array_column($rows, 'cat_id');
        $return_catIds_str = implode(',', $return_catIds);
        return $return_catIds_str;
    }
}
