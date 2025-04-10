<?php
/**
 * Created by PhpStorm.
 * User: chuanbin
 * Date: 2018/4/2
 * Time: 15:33
 */

namespace App\Api\Model\Search;

class SearchModel
{
    private $_db;
    private $table_search_business_value = "server_search_business_value";
    const MAX_LIMIT = 2;

    public function __construct()
    {
        $this->_db = app('api_db');
    }

    public function BusinessDataPush($goods, $business_code, &$err_code, &$err_msg)
    {
        if (empty($goods)) {
            return false;
        }
        $goods_ids = array_unique(array_column($goods, 'goods_id'));
        $this->_db->enableQueryLog();

        $es_goods = $this->_db->table($this->table_search_business_value)
            ->whereIn('goods_id', $goods_ids)
            ->where([
                'business_code' => $business_code,
            ])
            ->get()->map(function ($value) {
                return (array)$value;
            })
//            ->keyBy('goods_id')
            ->toArray();
//        return array(
//            'res' => 'true',
//            'msg' => $this->_db->getQueryLog(),
//        );
        $es_fields_goods = [];
        foreach ($es_goods as $es_good) {
            $es_fields_goods[$es_good['business_field']][$es_good['goods_id']] = $es_good;
        }


//        print_r($es_fields_goods);die;
//        $this->_db->beginTransaction();
        $res = 1;
        foreach ($goods as $good) {
            $goods_id = $good['goods_id'];
            $es_good = $es_fields_goods[$good['business_field']][$goods_id];
            $where_up['goods_id'] = $goods_id;
            $exist_vals = null;

            if ($good['act'] === 'add') { // 新增

                $modify_values = is_array($good['business_value']) ? $good['business_value'] : array($good['business_value']);
                if (isset($es_good)) {

                    // 已存在的值数组
                    $exist_vals = json_decode($es_good['value']);
                    $new_vals = array_unique(array_merge($exist_vals, $modify_values));
                    if (count($new_vals) === count($exist_vals)) {
                        continue;
                    }

                    $res = $this->_db->table($this->table_search_business_value)
                        ->where([
                            'id' => $es_good['id']
                        ])
                        ->update([
                            'value' => json_encode($new_vals),
                            'es_status' => 2,
                        ]);
                } else {
                    $res = $this->_db->table($this->table_search_business_value)
                        ->insert([
                            'goods_id' => $goods_id,
                            'business_code' => $business_code,
                            'business_field' => $good['business_field'],
                            'value' => json_encode($modify_values),
                            'es_status' => 2,
                        ]);
                }
            } elseif ($good['act'] === 'rm') {
                $modify_values = is_array($good['business_value']) ? $good['business_value'] : array($good['business_value']);
                if (isset($es_good)) {
                    // 已存在规则id
                    $exist_vals = json_decode($es_good['value']);
                    $new_vals = [];
                    foreach ($exist_vals as $exist_val) {
                        if (!in_array($exist_val, $modify_values)) {
                            $new_vals[] = $exist_val;
                        }
                    }
                    $res = $this->_db->table($this->table_search_business_value)
                        ->where([
                            'id' => $es_good['id']
                        ])
                        ->update([
                            'value' => json_encode($new_vals),
                            'es_status' => 2,
                        ]);
                }
            } elseif ($good['act'] === 'cover') {
                $modify_values = $good['business_value'];
                if (isset($es_good)) {
                    if ($es_good['value'] != json_encode($modify_values)) {
                        // 已存在规则id
                        $res = $this->_db->table($this->table_search_business_value)
                            ->where([
                                'id' => $es_good['id']
                            ])
                            ->update([
                                'value' => json_encode($modify_values),
                                'es_status' => 2,
                            ]);
                    }
                } else {
                    $exist_vals[] = $good['business_value'];
                    $res = $this->_db->table($this->table_search_business_value)
                        ->insert([
                            'goods_id' => $goods_id,
                            'business_code' => $business_code,
                            'business_field' => $good['business_field'],
                            'value' => json_encode($modify_values),
                            'es_status' => 2,
                        ]);
                }
            }
        }
        if ($res === false) {
//            $this->_db->rollback();
            return array(
                'res' => 'false',
                'msg' => $this->_db->getQueryLog(),
            );
        }
//        $this->_db->commit();
        return array(
            'res' => 'true',
            'msg' => $res
        );
    }
}