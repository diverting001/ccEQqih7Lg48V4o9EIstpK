<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Api\V1\Service\Search\Elasticsearchupindexdata;
use App\Api\V1\Service\Search\Elasticsearchcreateindex;
use App\Api\V1\Service\Search\BusinessValue;
use App\Api\Logic\Neigou\Ec;

class SearchBusinessUp extends Command
{
    protected $force = '';
    protected $signature = 'SearchBusinessUp {action} {page_size} {business_code?} {cus_goods_ids?*}';
    protected $description = '优惠券任务';
    private $_db_store;
    private $_db_server;
    private $_ec;
    private $table_search_business_value = "server_search_business_value";

    public function __construct()
    {
        $this->_db_store = app('api_db')->connection('neigou_store');
        $this->_db_server = app('api_db');
        $this->_ec = new Ec();
        parent::__construct();
    }

    public function handle()
    {
        set_time_limit(0);
        $this->_db_server->enableQueryLog();
        $action = $this->argument('action');
        $page_size = $this->argument('page_size');
        $cus_goods_ids = $this->argument('cus_goods_ids');
        $business_code = $this->argument('business_code');
//        $supplier_bn = '';
//
//        if (strpos($action, '-')) {
//            $action_arr = explode('-', $action);
//            $action = $action_arr[0];
//            $supplier_bn = $action_arr[1];
//        }

        if ($action == 'update') {
            $where = [];
            $where [] = ['es_status', '=', 2];
            if (!is_null($business_code)) {
                $where[] = ['business_code', '=', $business_code];
            }
            $last_id = 0;

            while (1) {
                //echo '$last_id:'.$last_id.PHP_EOL;
                $select_where   = array_merge($where,[['id', '>', $last_id]]);

                // 获取待处理goods_id
                $results = $this->_db_server->table($this->table_search_business_value)
                    ->where($select_where)
                    ->orderBy('id', 'asc')
                    ->limit($page_size)
                    ->get(['id', 'goods_id'])
                    ->toArray();

                if (!$results) {
                    echo 'over';
                    die;
                }

                $goods_ids = array_column($results, 'goods_id');
                $last_id = max(array_column($results, 'id'));

                $this->SaveESData($goods_ids);
            }
        } elseif ($action == 'all') {
            if ($cus_goods_ids) {
                $goods_ids = $cus_goods_ids;
                $this->SaveESData($goods_ids);
            } else {
                $offset = 0;
                while (1) {
                    $goods_ids = $this->_db_store->table('sdb_b2c_goods')
                        ->offset($offset)
                        ->limit($page_size)
                        ->orderBy('goods_id', 'asc')
                        ->pluck('goods_id')
                        ->toArray();
                    if (!$goods_ids) {
                        echo 'over';
                        die;
                    }
                    if ($this->SaveESData($goods_ids)) {
                        echo 'es存入成功:' . implode(',', $goods_ids), "\n";
                    } else {
                        echo 'es存入失败:' . implode(',', $goods_ids), "\n";
                    }
                    $offset += $page_size;
                }
            }
        } elseif ($action == 'cover') {
            if ($cus_goods_ids) {
                $goods_ids = $cus_goods_ids;
                $this->SaveFullESData($goods_ids);
            } else {
                $offset = 0;
                while (1) {
//                    $db = $this->_db_store->table('sdb_b2c_goods');
//                    if (!empty($supplier_bn)) {
//                        $db->where('bn', 'like', $supplier_bn . '-%');
//                    }
                    $goods_ids = $this->_db_store->table('sdb_b2c_goods')
                        ->offset($offset)
                        ->limit($page_size)
                        ->orderBy('goods_id', 'asc')
                        ->pluck('goods_id')
                        ->toArray();
                    if (!$goods_ids) {
                        echo 'over';
                        die;
                    }
                    if ($this->SaveFullESData($goods_ids)) {
                        echo 'es存入成功:' . implode(',', $goods_ids), "\n";
                    } else {
                        echo 'es存入失败:' . implode(',', $goods_ids), "\n";
                    }
                    $offset += $page_size;
                }
            }
        } elseif ($action == 'mryx') {
            $hour = date('G');
            if ($hour < 6 || $hour >= 21) {
                die;
            }
            $offset = 0;
            while (1) {
                $goods_ids = $this->_db_store->table('sdb_b2c_goods')
                    ->where('bn', 'like', 'MRYX-%')
                    ->offset($offset)
                    ->limit($page_size)
                    ->orderBy('goods_id', 'asc')
                    ->pluck('goods_id')
                    ->toArray();
                if (!$goods_ids) {
                    echo 'over';
                    die;
                }
                if ($this->SaveFullESData_MRYX($goods_ids)) {
                    echo "\n\n" . 'es存入成功:' . implode(',', $goods_ids), "\n\n";
                } else {
                    echo 'es存入失败:' . implode(',', $goods_ids), "\n";
                }
                $offset += $page_size;
            }
        } elseif ($action == 'init') {
            $create_index_obj = new Elasticsearchcreateindex();
            $r = $create_index_obj->CreateIndex(array(config('neigou.ESSEARCH_UP_INDEX')));
            var_dump($r);
        }
    }

    private function SaveESData($goods_ids)
    {
        $goods_branch_list = $this->_ec->GetGoodsBranch($goods_ids);
        $ec_goods_ids = array_unique(array_column($goods_branch_list, 'goods_id'));
        if (count($goods_ids) != count($ec_goods_ids)) {
            $ec_miss_goods_ids = array_diff($goods_ids, $ec_goods_ids);
            $this->_db_server->table($this->table_search_business_value)
                ->whereIn('goods_id', $ec_miss_goods_ids)
                ->update([
                    'es_status' => 5,
                    'es_status_reason' => 'ec不存在',
                ]);
            echo 'ec不存在:' . implode(',', $ec_miss_goods_ids) . "\n";
        }
        $create_index_obj = new Elasticsearchupindexdata();
        // 获取待处理goods
        $bv_obj = new BusinessValue();
        $es_goods = $bv_obj->getBusinessValue($goods_ids);
        $part_index = 0;
        $part_size = 500;
        // 每次请求10个
        while (1) {
            $cur_goods_branch_list = array_slice($goods_branch_list, $part_index * $part_size, $part_size);
            if (!$cur_goods_branch_list) {
                break;
            }
            foreach ($es_goods as $es_good) {
                foreach ($cur_goods_branch_list as &$goods_branch) {
                    if ($goods_branch['goods_id'] == $es_good['goods_id']) {
                        $goods_branch[$es_good['es_field']] = json_decode($es_good['value']);
                    }
                }
            }
            $results = $create_index_obj->EsFieldUpdate($cur_goods_branch_list);
            $results = json_decode($results, true);
            $succ_goods_ids = [];
            $fail_goods_ids = [];
            foreach ($results['items'] as $item) {
                if ($item['update']['status'] == 200) {
                    $succ_goods_ids[] = $item['update']['_id'];
                    echo 'es存入:' . $item['update']['_type'] . '|' . $item['update']['_id'] . "\n";
                } else {
                    $fail_goods_ids[] = $item['update']['_id'];
                    $this->_db_server->table($this->table_search_business_value)
                        ->where(['goods_id' => $item['update']['_id']])
                        ->update([
                            'es_status' => $item['update']['status'],
                            'es_status_reason' => $item['update']['error']['reason'],
                        ]);
                    echo '------失败:' . $item['update']['_type'] . '|' . $item['update']['_id'] . ':' . $item['update']['error']['reason'] . "\n";
                }
            }
            if ($succ_goods_ids) {
                $this->_db_server->table($this->table_search_business_value)
                    ->whereIn('goods_id', $succ_goods_ids)
                    ->update(['es_status' => 200]);
            }
            if ($fail_goods_ids) {
                $this->SaveFullESData($fail_goods_ids);
            }
            $part_index++;
        }
        return true;
    }

    private function SaveFullESData($goods_ids)
    {
        $goods_branch_list = $this->_ec->GetGoodsBranch($goods_ids);
        $ec_goods_ids = array_unique(array_column($goods_branch_list, 'goods_id'));
        if (count($goods_ids) != count($ec_goods_ids)) {
            $ec_miss_goods_ids = array_diff($goods_ids, $ec_goods_ids);
            $this->_db_server->table($this->table_search_business_value)
                ->whereIn('goods_id', $ec_miss_goods_ids)
                ->update([
                    'es_status' => 5,
                    'es_status_reason' => 'ec不存在',
                ]);
            echo 'ec不存在:' . implode(',', $ec_miss_goods_ids) . "\n";
        }
        $create_index_obj = new Elasticsearchupindexdata();
        $part_index = 0;
        // 每次请求300个
        $part_size = 300;
        while (1) {
            $cur_goods_branch_list = array_slice($goods_branch_list, $part_index * $part_size, $part_size);
            if (!$cur_goods_branch_list) {
                break;
            }
            $cur_goods_ids = array_column($cur_goods_branch_list, 'goods_id');
            $cur_goods_ids = array_unique($cur_goods_ids);
            $cur_branch_ids = array_column($cur_goods_branch_list, 'branch_id');
            $cur_branch_ids = array_unique($cur_branch_ids);

            $res_es = $create_index_obj->SaveElasticSearchsData($cur_goods_ids, $cur_branch_ids);
            if ($res_es) {
                $this->_db_server->table($this->table_search_business_value)
                    ->whereIn('goods_id', $cur_goods_ids)
                    ->update(['es_status' => 1]);
                echo 1;
            } else {
                echo 2;
                $this->_db_server->table($this->table_search_business_value)
                    ->whereIn('goods_id', $cur_goods_ids)
                    ->update(['es_status' => 4]);
            }
            echo 'es存入:' . json_encode($cur_goods_ids), ' branch:', implode(',', $cur_branch_ids), "\n";
            $part_index++;
        }
        return true;
    }

    private function SaveFullESData_MRYX($goods_ids)
    {
        $goods_branch_list = $this->_ec->GetGoodsBranch($goods_ids);
        $ec_goods_ids = array_unique(array_column($goods_branch_list, 'goods_id'));
        if (count($goods_ids) != count($ec_goods_ids)) {
            $ec_miss_goods_ids = array_diff($goods_ids, $ec_goods_ids);
            $this->_db_server->table($this->table_search_business_value)
                ->whereIn('goods_id', $ec_miss_goods_ids)
                ->update([
                    'es_status' => 5,
                    'es_status_reason' => 'ec不存在',
                ]);
            echo 'ec不存在:' . implode(',', $ec_miss_goods_ids) . "\n";
        }
        $create_index_obj = new Elasticsearchupindexdata();
        $part_index = 0;
        // 每次请求300个
        $part_size = 300;
        while (1) {
            $cur_goods_branch_list = array_slice($goods_branch_list, $part_index * $part_size, $part_size);
            if (!$cur_goods_branch_list) {
                break;
            }
            $cur_goods_ids = array_column($cur_goods_branch_list, 'goods_id');
            $cur_goods_ids = array_unique($cur_goods_ids);
            $cur_branch_ids = array_column($cur_goods_branch_list, 'branch_id');
            $cur_branch_ids = array_unique($cur_branch_ids);

            $res_es = $create_index_obj->SaveMRYXData($cur_goods_ids, $cur_branch_ids);
            if ($res_es) {
                $this->_db_server->table($this->table_search_business_value)
                    ->whereIn('goods_id', $goods_ids)
                    ->update(['es_status' => 1]);
                echo 1;
            } else {
                echo 2;
                $this->_db_server->table($this->table_search_business_value)
                    ->whereIn('goods_id', $goods_ids)
                    ->update(['es_status' => 4]);
            }
            echo 'es存入:' . json_encode($cur_goods_ids), ' branch:', implode(',', $cur_branch_ids), "\n";
            $part_index++;
        }
        return true;
    }
}
