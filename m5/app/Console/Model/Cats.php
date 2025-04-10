<?php
namespace App\Console\Model;
use App\Api\Model\BaseModel ;
class Cats extends BaseModel
{

    public function getCatIds($goods_id_arr)
    {
        if (empty($goods_id_arr)) {
            return false;
        }
        $list = $this->select("select goods_id,cat_id from sdb_b2c_goods_mall_cats where goods_id in(" . implode(',', $goods_id_arr) . ")");
        $cat_id3_2_goods_ids = array();
        foreach ($list as $item) {
            if (!in_array($item['goods_id'], (array)$cat_id3_2_goods_ids[$item['cat_id']])) {
                $cat_id3_2_goods_ids[$item['cat_id']][] = $item['goods_id'];
            }
            $goods_cats[$item['goods_id']][] = $item['cat_id'];
        }
        $goods_list = $this->select("select goods_id,mall_goods_cat from sdb_b2c_goods where goods_id in(" . implode(',', $goods_id_arr) . ")");
        foreach ($goods_list as $goods_info) {
            if (!in_array($goods_info['goods_id'], (array)$cat_id3_2_goods_ids[$goods_info['mall_goods_cat']])) {
                $cat_id3_2_goods_ids[$goods_info['mall_goods_cat']][] = $goods_info['goods_id'];
            }
        }
        $level3_cat_id_arr = array_keys($cat_id3_2_goods_ids);
        if (empty($level3_cat_id_arr)) {
            return false;
        }
        $cat_path_list = $this->select("select cat_id,cat_path from sdb_b2c_mall_goods_cat where cat_id in(" . implode(',', $level3_cat_id_arr) . ")");
        $all_cat_id_kv = array();
        $goods_id_2_cats = array();
        foreach ($cat_path_list as $item) {
            $all_cat_id_kv[$item['cat_id']] = 1;
            $p_cat_id_arr = explode(',', trim($item['cat_path'], ','));
            foreach ($p_cat_id_arr as $p_cat_id) {
                if (!empty($p_cat_id)) {
                    $all_cat_id_kv[$p_cat_id] = 1;
                }
            }

            foreach ($cat_id3_2_goods_ids[$item['cat_id']] as $goods_id) {
                $goods_id_2_cats[$goods_id][] = $p_cat_id_arr[0];
                $goods_id_2_cats[$goods_id][] = $p_cat_id_arr[1];
                $goods_id_2_cats[$goods_id][] = $item['cat_id'];
            }
        }
        return $goods_id_2_cats;
    }
}
