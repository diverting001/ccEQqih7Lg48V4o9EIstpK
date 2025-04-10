<?php

namespace App\Console\Commands;

use App\Api\Model\Outlet\OutletModel;
use Illuminate\Console\Command;
use App\Api\V1\Service\Search\Elasticsearchupindexdata;
use App\Api\V1\Service\Search\Elasticsearchcreateindex;
use App\Api\V1\Service\Search\BusinessValue;
use App\Api\Logic\Neigou\Ec;

class SearchBusinessUp extends Command
{
    protected $force = '';
    protected $signature = 'SearchBusinessUp {action} {page_size} {business_code?} {cus_goods_ids?*} {--goods_id=}';
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
//        $this->_db_server->enableQueryLog();
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
                //$this->SaveESData($goods_ids);
                // 此处不做分片直接直接将ES卡死
                foreach (array_chunk($goods_ids,5) as $slice_goods_ids) {
                    $this->SaveESData($slice_goods_ids);
                }

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
                $max_goods_id = 0;
                while (1) {
//                    $db = $this->_db_store->table('sdb_b2c_goods');
//                    if (!empty($supplier_bn)) {
//                        $db->where('bn', 'like', $supplier_bn . '-%');
//                    }
                    $goods_ids = $this->_db_store->table('sdb_b2c_goods')
                        ->whereRaw('goods_id>' . $max_goods_id)
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
                    $max_goods_id = end($goods_ids);
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
        } else if ($action == 'update_search_name') {
            //起始商品id
            $goods_id = $this->option('goods_id');
            $goods_id = $goods_id ?: 0;
            $i = 0;
            while ($i < 99999999) {
                $i ++;
                //增量获取商品
                $where = [
                    ['goods_id','>', $goods_id],
                ];
                $goods_list = $this->_db_store->table('sdb_b2c_goods')
                    ->where($where)
                    ->limit($page_size)
                    ->select(['goods_id','name'])
                    ->orderBy('goods_id', 'asc')
                    ->get()->map(function ($value) {
                        return (array)$value;
                    })->toArray();
                if (empty($goods_list)) {
                    echo "同步完成";die;
                }

                //更新商品搜索字段到es
                $end = end($goods_list);
                $goods_id = $end['goods_id'];
                echo sprintf("id:%s,count:%s\r\n", $goods_id, count($goods_list));
                $this->updateSearchName($goods_list);
            }
        }
    }

    /**
     * 更新字段到es
     * @param $goods_list
     * @return false|void
     */
    private function updateSearchName($goods_list){
        if (empty($goods_list)) {
            return false;
        }
        $goods_ids = array_column($goods_list, 'goods_id');

        //获取货品
        $product_list = $this->_db_store->table('sdb_b2c_products')
            ->whereIn('goods_id', $goods_ids)
            ->orderBy('marketable', 'asc')
            ->orderBy('is_default', 'asc')
            ->select(['spec_desc','goods_id'])
            ->get()->map(function ($value) {
                return (array)$value;
            })->toArray();

        $product_list_new = array();
        foreach ($product_list as $product) {
            $product_list_new[$product['goods_id']][] = $product;
        }

        $put_data_str = '';
        foreach ($goods_list as $goods) {
            //遍历商品所有规格
            $search_name = array();
            foreach ($product_list_new[$goods['goods_id']] as $product_new) {

                $spec_desc = unserialize($product_new['spec_desc']);

                $search_name[] = $goods['name'].($spec_desc['spec_value'] ? ' '.implode(' ', $spec_desc['spec_value']) : '');
            }

            //组装修改json
            $inex_type_id = [
                'update' => [
                    '_index' => config('neigou.ESSEARCH_INDEX'),
                    '_type' => config('neigou.ESSEARCH_TYPE'),
                    '_id' => $goods['goods_id']
                ]
            ];

            $update  = [
                'goods_id' => $goods['goods_id'],
                'search_name' => !empty($search_name) ? $search_name : $goods['name']
            ];
            $put_data_str .= json_encode($inex_type_id) . "\n";
            $put_data_str .= json_encode(['doc' => $update]) . "\n";
        }
        $curl = new \Neigou\Curl();
        $url = config('neigou.ESSEARCH_HOST') . ':' . config('neigou.ESSEARCH_PORT') . '/_bulk';
        $res = $curl->Post($url, $put_data_str);
    }

    private function SaveESData($goods_ids)
    {
        $goods_branch_list = $this->_ec->GetGoodsBranch($goods_ids);
        $ec_goods_ids = array_unique(array_column($goods_branch_list, 'goods_id'));
        if (count($goods_ids) != count($ec_goods_ids)) {
            $ec_miss_goods_ids = array_diff($goods_ids, $ec_goods_ids);
            if ($ec_miss_goods_ids) {
                $this->_db_server->table($this->table_search_business_value)
                    ->whereIn('goods_id', $ec_miss_goods_ids)
                    ->update([
                        'es_status' => 5,
                        'es_status_reason' => 'ec不存在',
                    ]);
                echo 'ec不存在:' . implode(',', $ec_miss_goods_ids) . "\n";
            }
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
                // 过滤错误数据
                if (empty($es_good['es_field'])) {
                    continue;
                }
                foreach ($cur_goods_branch_list as &$goods_branch) {
                    if ($goods_branch['goods_id'] == $es_good['goods_id']) {
                        //$goods_branch[$es_good['es_field']] = json_decode($es_good['value']);
                        if ($es_good['es_field'] != "outlet_list") {
                            $goods_branch[$es_good['es_field']] = json_decode($es_good['value']);
                        } else {
                            // 组装门店信息
                            $outletModel = new OutletModel();
                            $outletInfoList = array();
                            $outlets = json_decode($es_good['value'],true);
                            if (!empty($outlets)) {
                                if (is_array($outlets[0])) {
                                    $goods_branch[$es_good['es_field']] = $outlets;
                                } else {
                                    $outletIds = $outlets;
                                    $outletList = $outletModel->getList(0, count($outletIds), $outletIds);
                                    foreach ($outletList as $outletItem) {
                                        $outletInfoList[] = array(
                                            'coordinate' => array(
                                                'lon' => floatval($outletItem->longitude),
                                                'lat' => floatval($outletItem->latitude),
                                            ),
                                            'outlet_id' => intval($outletItem->outlet_id),
                                            'province_id' => intval($outletItem->province_id),
                                            'outlet_address' => strval($outletItem->outlet_address),
                                            'outlet_name' => strval($outletItem->outlet_name),
                                            'area_id' => intval($outletItem->area_id),
                                            'outlet_logo' => strval($outletItem->outlet_logo),
                                            'city_id' => intval($outletItem->city_id),
                                        );
                                    }
                                    $goods_branch[$es_good['es_field']] = $outletInfoList;
                                }
                            }
                        }
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
