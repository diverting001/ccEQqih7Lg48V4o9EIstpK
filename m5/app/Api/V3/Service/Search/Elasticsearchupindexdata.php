<?php
/**
 * elasticsearch 更新/全量索引数据
 * @version 0.1
 * @package ectools.lib.api
 */

namespace App\Api\V3\Service\Search;

use App\Api\Logic\Neigou\Ec;
use App\Api\V3\Service\Search\BusinessValue;

class Elasticsearchupindexdata
{
    /*
     * @Redis 商品索引更新
     */
    public function UpIndexRedis($data)
    {
        $gid = $data['goods_id'];
        $goods_ids[$gid] = $gid;
        $res = $this->SaveElasticSearchsData($goods_ids);
        if ($res) {
            $loger_data = array(
                'remark' => 'query es up succ',
                'data' => $data,
                'index_name' => $goods_ids,
                'goods_ids' => $gid,
                'save_goods_list' => $goods_ids,
                'message' => $res
            );
            \Neigou\Logger::Debug('up_es_redis', $loger_data);
            return true;
        } else {
            $loger_data = array(
                'remark' => 'query es up fail',
                'data' => $data,
                'index_name' => $goods_ids,
                'goods_ids' => $gid,
                'save_goods_list' => $goods_ids,
                'message' => $res
            );
            \Neigou\Logger::Debug('up_es_redis', $loger_data);
            return false;
        }
    }


    /*
     * @Redis 商品每日优鲜ES索引更新
     */
    public function MRYXUpIndexRedis($data)
    {
        $gid = $data['goods_id'];
        if (empty($gid)) return;
        $branch_list = $data['branch_id_list'];
        $goods_ids[$gid] = $gid;
        //按商品仓库更新es
        $res = $this->SaveElasticSearchsData($goods_ids, $branch_list);
        if ($res) {
            $loger_data = array(
                'remark' => 'query es up succ',
                'data' => $data,
                'goods_ids' => $gid,
                'save_goods_list' => $goods_ids,
                'message' => $res
            );
            \Neigou\Logger::Debug('up_mryx_es_redis', $loger_data);
            return true;
        } else {
            $loger_data = array(
                'remark' => 'query es up fail',
                'data' => $data,
                'goods_ids' => $gid,
                'save_goods_list' => $goods_ids,
                'message' => $res
            );
            \Neigou\Logger::Debug('up_mryx_es_redis', $loger_data);
            return false;
        }
    }

    /*
     * @todo 获取索引最大更新时间
     */
    public function GetMaxTime($index_name)
    {
        $max_query_data = array(
            'aggs' => array(
                'max_time' => array(
                    'max' => array(
                        'field' => 'last_modify'
                    )
                )
            )
        );
        $curl = new \Neigou\Curl();
        $url = config('neigou.ESSEARCH_HOST') . ':' . config('neigou.ESSEARCH_PORT') . '/' . $index_name . '/' . config('neigou.ESSEARCH_TYPE') . '/_search';
        $dsl = json_encode($max_query_data);
        $res = $curl->Post($url, $dsl);
        $res = json_decode($res, true);
        if (!empty($res['aggregations']['max_time']['value'])) {
            $max_time = $res['aggregations']['max_time']['value'];
        } else {
            $max_time = 0;
        }
        return $max_time;
    }

    /*
     * @todo 保存es数据
     */
    public function SaveElasticSearchsData($goods_ids, $branch_list = array())
    {
        if (empty($goods_ids)) {
            return false;
        }
        $ec = new Ec();
        $split_goods_list = $ec->GetSplitGoodsList($goods_ids, $branch_list);
        print_r($split_goods_list);die;
        $businessValue = new BusinessValue();
        //保存非包含微仓库
        if (!empty($split_goods_list['standard'])) {
            $split_goods_list['standard'] = $businessValue->putBusinessValue($split_goods_list['standard']);
            $res = $this->ElasticSearchsSave($split_goods_list['standard']);
        }
        //保存包含微仓库的商品
        if (!empty($split_goods_list['vbranch'])) {
            $split_goods_list['vbranch'] = $businessValue->putBusinessValue($split_goods_list['vbranch']);
            $this->SaveVbranchData($split_goods_list['vbranch']);
        }
        return true;
    }

    /*
     * @todo 保存es数据
     */
    public function SaveMRYXData($goods_ids, $branch_list = array())
    {
        if (empty($goods_ids)) {
            return false;
        }
        $ec = new Ec();
        $split_goods_list = $ec->GetSplitGoodsList($goods_ids, $branch_list);
        $businessValue = new BusinessValue();
        //保存包含微仓库的商品
        if (!empty($split_goods_list['vbranch'])) {
            $split_goods_list['vbranch'] = $businessValue->putBusinessValue($split_goods_list['vbranch']);
            $this->SaveVbranchData_2($split_goods_list['vbranch']);
        }
        return true;
    }

    /*
     * @todo 更新es指定字段
     */
    public function EsFieldUpdate($goods_list)
    {
        if (empty($goods_list)) return false;
        $es_index = new Elasticsearchcreateindex();
        $use_type_list = $es_index->GetUseIndexTypeList();
        $save_type_list = array();
        //组织指同步数据
        $put_data_str = '';
        foreach ($goods_list as $goods) {
            $goods['branch_id'] = intval($goods['branch_id']);
            $index_type = $es_index->GetIndexTypeName($goods['branch_id']);
            $goods['time_local'] = date('c');
            $inex_type_id = [
                'update' => [
                    '_index' => config('neigou.ESSEARCH_UP_INDEX'),
                    '_type' => $index_type,
                    '_id' => $goods['goods_id']
                ]
            ];
            $put_data_str .= json_encode($inex_type_id) . "\n";
            $put_data_str .= json_encode(['doc' => $goods]) . "\n";
            $save_type_list[$goods['branch_id']] = $index_type;
        }

        //不存在的type
        $null_type_list = array_diff($save_type_list, $use_type_list);
        if (!empty($null_type_list)) {
            //创建不存在的TYPE
            $es_index->CreateType($null_type_list, config('neigou.ESSEARCH_UP_INDEX'));
            //保存使用TYPE缓存
            $es_index->SaveUseIndexTypeList(array_merge($use_type_list, $null_type_list));
        }

        $curl = new \Neigou\Curl();
        $url = config('neigou.ESSEARCH_HOST') . ':' . config('neigou.ESSEARCH_PORT') . '/_bulk';
        $res = $curl->Post($url, $put_data_str);
        return $res;
    }


    /*
     * @todo 保存es数据信息
     */
    public function ElasticSearchsSave($save_data)
    {
        if (empty($save_data)) return false;
        $es_index = new Elasticsearchcreateindex();
        $use_type_list = $es_index->GetUseIndexTypeList();
        $save_type_list = array();
        //组织指同步数据
        $put_data_str = '';
        foreach ($save_data as $k => $v) {
            $v['branch_id'] = intval($v['branch_id']);
            $index_type = $es_index->GetIndexTypeName($v['branch_id']);
            $v['time_local'] = date('c');
            $inex_type_id = array(
                'index' => array(
                    '_index' => config('neigou.ESSEARCH_UP_INDEX'),
                    '_type' => $index_type,
                    '_id' => $v['goods_id']
                )
            );
            $put_data_str .= json_encode($inex_type_id) . "\n";
            $put_data_str .= json_encode($v) . "\n";
            $save_type_list[$v['branch_id']] = $index_type;
        }
        //不存在的type
        $null_type_list = array_diff($save_type_list, $use_type_list);
        if (!empty($null_type_list)) {
            //创建不存在的TYPE
            $es_index->CreateType($null_type_list, config('neigou.ESSEARCH_UP_INDEX'));
            //保存使用TYPE缓存
            $es_index->SaveUseIndexTypeList(array_merge($use_type_list, $null_type_list));
        }

        $curl = new \Neigou\Curl();
        $url = config('neigou.ESSEARCH_HOST') . ':' . config('neigou.ESSEARCH_PORT') . '/_bulk';
        $res = $curl->Post($url, $put_data_str);
        return $res;
    }


    /*
     * @todo
     */
    private function SplitPranch($goods_list)
    {
        $split_goods_list = array();
        if (empty($goods_list)) return $split_goods_list;
        foreach ($goods_list as $v) {
            if (!empty($v['vbranch_list'])) {
                $split_goods_list['vbranch'][] = $v;
            } else {
                $split_goods_list['standard'][] = $v;
            }
        }
        return $split_goods_list;
    }

    /*
     * @todo
     */
    private function SaveVbranchData($goods_list)
    {
//        return false;
        if (empty($goods_list)) return false;
        foreach ($goods_list as $goods_info) {
            $es_save_data = array();
            $temp_vbranch_list = $goods_info['vbranch_list'];
            unset($goods_info['vbranch_list']);
            $i = 1;
            foreach ($temp_vbranch_list as $vbranch_product) {
                $es_save_data[] = array_merge($goods_info, $vbranch_product);
                if ($i % 25 == 0) {
                    $this->ElasticSearchsSave($es_save_data);
                    $es_save_data = array();
                }
                $i++;
            }
            if ($es_save_data) {
                $this->ElasticSearchsSave($es_save_data);
            }
        }
    }

    /*
 * @todo
 */
    private function SaveVbranchData_2($goods_list)
    {
        if (empty($goods_list)) return false;
        foreach ($goods_list as $goods_info) {
            $es_save_data = array();
            $temp_vbranch_list = $goods_info['vbranch_list'];
            unset($goods_info['vbranch_list']);
            $i = 1;
            foreach ($temp_vbranch_list as $vbranch_product) {
                $es_save_data[] = array_merge($goods_info, $vbranch_product);
                if ($i % 100 == 0) {
                    $this->ElasticSearchsSave($es_save_data);
                    $es_save_data = array();
                }
                $i++;
            }
            if ($es_save_data) {
                $this->ElasticSearchsSave($es_save_data);
            }
        }
    }

    function aa($msg)
    {
        echo $msg . "：";
        $end = memory_get_usage();
        echo ($end / 1024 / 1024) . "\n";
    }

}
