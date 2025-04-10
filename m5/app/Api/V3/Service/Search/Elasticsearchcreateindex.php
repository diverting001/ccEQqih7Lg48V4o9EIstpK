<?php
/**
 * elasticsearch 索引创建
 * @version 0.1
 * @package ectools.lib.api
 */

namespace App\Api\V3\Service\Search;

use Neigou\RedisNeigou;

class Elasticsearchcreateindex
{
    private $_index_version = 'v4';
    private $_index_cache_key = 'index_name';
    private $_index_cache_prefix = 'elasticsearch';
    private $_type_cache_key = 'type_name';


    /*
     * @todo 创建索引
     */
    public function CreateIndex($index_list)
    {
        if (empty($index_list) || !is_array($index_list)) return false;
        foreach ($index_list as $index_name) {
            $index_query_data = $this->GetIndexQueryData();
            if (!empty($index_query_data)) {
                $dsl = json_encode($index_query_data);
                $curl = new \Neigou\Curl();
                $url = config('neigou.ESSEARCH_HOST') . ':' . config('neigou.ESSEARCH_PORT') . '/' . $index_name . '/';
                $res = $curl->Put($url, $dsl);
            }
        }
        return $res;
    }

    public function CreateType($type_list, $index_name)
    {
        if (empty($type_list) || !is_array($type_list)) return false;
        foreach ($type_list as $type_name) {
            $this->createTypeByName($type_name, $index_name);
        }
    }

    protected function createTypeByName($type_name, $index_name)
    {
        $index_query_data = $this->GetIndexQueryData();
        $mapping_data = $index_query_data['mappings'][config('neigou.ESSEARCH_TYPE')];
        $dsl = json_encode($mapping_data);
        $curl = new \Neigou\Curl();
        $url = config('neigou.ESSEARCH_HOST') . ':' . config('neigou.ESSEARCH_PORT') . '/' . $index_name . '/' . $type_name . '/_mapping';
        $res = $curl->Post($url, $dsl);
        return $res;
    }

    /*
     * @todo 创建分支Type _mapping
     */
    public function createBranchType($branch_id, $index_name)
    {
        $type_name = $this->getBranchTypeName($branch_id);
        return $this->createTypeByName($type_name, $index_name);
    }

    public function getBranchTypeName($branch_id)
    {
        return $branch_id . '_' . config('neigou.ESSEARCH_TYPE');
    }

    /*
     * @todo 获取index创建请求数组
     */
    protected function GetIndexQueryData()
    {
        $index_query_data = array(
            'settings' => array(
                'index' => array(
                    'number_of_shards' => 3,
                    'number_of_replicas' => 2,
                    'max_result_window' => 500000
                ),
                'analysis' => array(
                    'analyzer' => array(
                        'search_analyzer' => array(
                            'type' => 'custom',
                            'search_analyzer' => 'ik_smart',
                            'tokenizer' => 'ik_smart',
                            'filter' => array(
                                'my_word_delimiter',
                                'my_lowercase',
                            )
                        ),
                        'tokenizer_analyzer' => array(
                            'type' => 'custom',
                            'search_analyzer' => 'ik_smart',
                            'tokenizer' => 'ik_max_word',
                            'filter' => array(
                                'my_word_delimiter',
                                'my_lowercase',
                                "ngram_filter",
                            )
                        ),
                    ),
                    'filter' => array(
                        'my_word_delimiter' => array(
                            'type' => 'word_delimiter',
                            'preserve_original' => true,
                        ),
                        'my_lowercase' => array(
                            'type' => 'lowercase',
                        ),
                        'edge_ngram_filter' => array(
                            'type' => 'edge_ngram',
                            'min_gram' => 1,
                            'max_gram' => 25,
                        ),
                        'ngram_filter' => array(
                            'type' => 'ngram',
                            'min_gram' => 1,
                            'max_gram' => 16,
                        ),
                    )
                )
            ),
            'mappings' => array(
                config('neigou.ESSEARCH_TYPE') => array(
                    'dynamic' => 'false', //忽略新字段
                    'properties' => array(
                        'product_id' => array(
                            'type' => 'integer',
                        ),
                        'bn' => array(
                            'type' => 'keyword',
                        ),
                        'name' => array(
                            'type' => 'text',
                            'analyzer' => 'tokenizer_analyzer'
                        ),
                        'search_name' => array(
                            'type' => 'text',
                            'analyzer' => 'tokenizer_analyzer'
                        ),
                        'cat_level_1' => array(
                            'properties' => array(
                                'cat_name' => array(
                                    'type' => 'text',
                                    'fields' => array(
                                        'raw' => array(
                                            'type' => 'keyword',
                                        ),
                                    )
                                ),
                                'cat_id' => array(
                                    'type' => 'integer'
                                )
                            )
                        ),
                        'cat_level_2' => array(
                            'properties' => array(
                                'cat_name' => array(
                                    'type' => 'text',
                                    'fields' => array(
                                        'raw' => array(
                                            'type' => 'keyword',
                                        ),
                                    )
                                ),
                                'cat_id' => array(
                                    'type' => 'integer'
                                )
                            )
                        ),
                        'cat_level_3' => array(
                            'properties' => array(
                                'cat_name' => array(
                                    'type' => 'text',
                                    'fields' => array(
                                        'raw' => array(
                                            'type' => 'keyword',
                                        ),
                                    )
                                ),
                                'cat_id' => array(
                                    'type' => 'integer'
                                )
                            )
                        ),
                        'brand_name' => array(
                            'type' => 'text',
                            'fields' => array(
                                'raw' => array(
                                    'type' => 'keyword',
                                )
                            )
                        ),
                        'brand_id' => array(
                            'type' => 'integer'
                        ),
                        'marketable' => array(
                            'type' => 'keyword',
                        ),
                        'store' => array(
                            'type' => 'integer',
                        ),
                        'price' => array(
                            'type' => 'double',
                        ),
                        'point_price' => array(
                            'type' => 'double',
                        ),
                        'goods_id' => array(
                            'type' => 'integer',
                        ),
                        's_url' => array(
                            'type' => 'keyword',
                        ),
                        'm_url' => array(
                            'type' => 'keyword',
                        ),
                        'l_url' => array(
                            'type' => 'keyword',
                        ),
                        'd_url' => array(
                            'type' => 'keyword',
                        ),
                        'type_id' => array(
                            'type' => 'integer',
                        ),
                        'spec_info' => array(
                            'type' => 'text',
                        ),
                        'spec_desc' => array(
                            'type' => 'keyword',
                        ),
                        'goods_type' => array(
                            'type' => 'keyword',
                        ),
                        'goods_bonded_type' => array(
                            'type' => 'keyword',
                        ),
                        'compare' => array(
                            'properties' => array(
                                'competitor_site' => array(
                                    'type' => 'keyword',
                                ),
                                'siteName' => array(
                                    'type' => 'keyword',
                                ),
                                'goods_id' => array(
                                    'type' => 'integer',
                                ),
                                'competitor_price' => array(
                                    'type' => 'double',
                                )
                            )
                        ),
                        'moduled' => array(
                            'type' => 'keyword',
                        ),
                        'weight' => array(
                            'type' => 'integer',
                        ),
                        'province_stock' => array(
                            'properties' => array(
                                'p1' => array(
                                    'type' => 'integer',
                                ),
                                'p2' => array(
                                    'type' => 'integer',
                                ),
                                'p3' => array(
                                    'type' => 'integer',
                                ),
                                'p4' => array(
                                    'type' => 'integer',
                                ),
                                'p5' => array(
                                    'type' => 'integer',
                                ),
                                'p6' => array(
                                    'type' => 'integer',
                                ),
                                'p7' => array(
                                    'type' => 'integer',
                                ),
                                'p8' => array(
                                    'type' => 'integer',
                                ),
                                'p9' => array(
                                    'type' => 'integer',
                                ),
                                'p10' => array(
                                    'type' => 'integer',
                                ),
                                'p11' => array(
                                    'type' => 'integer',
                                ),
                                'p12' => array(
                                    'type' => 'integer',
                                ),
                                'p13' => array(
                                    'type' => 'integer',
                                ),
                                'p14' => array(
                                    'type' => 'integer',
                                ),
                                'p15' => array(
                                    'type' => 'integer',
                                ),
                                'p16' => array(
                                    'type' => 'integer',
                                ),
                                'p17' => array(
                                    'type' => 'integer',
                                ),
                                'p18' => array(
                                    'type' => 'integer',
                                ),
                                'p19' => array(
                                    'type' => 'integer',
                                ),
                                'p20' => array(
                                    'type' => 'integer',
                                ),
                                'p21' => array(
                                    'type' => 'integer',
                                ),
                                'p22' => array(
                                    'type' => 'integer',
                                ),
                                'p23' => array(
                                    'type' => 'integer',
                                ),
                                'p24' => array(
                                    'type' => 'integer',
                                ),
                                'p25' => array(
                                    'type' => 'integer',
                                ),
                                'p26' => array(
                                    'type' => 'integer',
                                ),
                                'p27' => array(
                                    'type' => 'integer',
                                ),
                                'p28' => array(
                                    'type' => 'integer',
                                ),
                                'p29' => array(
                                    'type' => 'integer',
                                ),
                                'p30' => array(
                                    'type' => 'integer',
                                ),
                                'p31' => array(
                                    'type' => 'integer',
                                ),
                            ),
                        ),
                        'products' => array(
                            'type' => 'keyword',
                        ),
                        'pavilion_id' => array(
                            'type' => 'integer',
                        ),
                        'country_id' => array(
                            'type' => 'integer',
                        ),
                        'time_local' => array(
                            'type' => 'date',
                            'format' => 'dateOptionalTime',
                            'index' => 'not_analyzed'
                        ),
                        'last_modify' => array(
                            'type' => 'integer',
                        ),
                        'is_soldout' => array(
                            'type' => 'integer',
                        ),
                        'p1' => array(
                            'type' => 'double',
                        ),
                        'p2' => array(
                            'type' => 'double',
                        ),
                        'p3' => array(
                            'type' => 'double',
                        ),
                        'p4' => array(
                            'type' => 'double',
                        ),
                        'p5' => array(
                            'type' => 'double',
                        ),
                        'p6' => array(
                            'type' => 'double',
                        ),
                        'p7' => array(
                            'type' => 'double',
                        ),
                        'p8' => array(
                            'type' => 'double',
                        ),
                        'p9' => array(
                            'type' => 'double',
                        ),
                        'p10' => array(
                            'type' => 'double',
                        ),
                        'vmall_cat_id' => array(
                            'type' => 'integer'
                        ),
                        'int1' => array(
                            'type' => 'integer'
                        ),
                        'int2' => array(
                            'type' => 'integer'
                        ),
                        'int3' => array(
                            'type' => 'integer'
                        ),
                        'int4' => array(
                            'type' => 'integer'
                        ),
                        'int5' => array(
                            'type' => 'integer'
                        ),
                        'double1' => array(
                            'type' => 'double'
                        ),
                        'double2' => array(
                            'type' => 'double'
                        ),
                        'double3' => array(
                            'type' => 'double'
                        ),
                        'double4' => array(
                            'type' => 'double'
                        ),
                        'double5' => array(
                            'type' => 'double'
                        ),
                        'str1' => array(
                            'type' => 'text'
                        ),
                        'str2' => array(
                            'type' => 'text'
                        ),
                        'str3' => array(
                            'type' => 'text'
                        ),
                        'str4' => array(
                            'type' => 'text'
                        ),
                        'str5' => array(
                            'type' => 'text'
                        ),
                    )
                )
            )
        );
        return $index_query_data;
    }

    /*
     * @todo 获取索引名称
     */
    public function GetIndexName($index_name)
    {
        if (empty($index_name)) $index_name = 0;
        $index_name = $this->_index_version . '_store_' . $index_name;
        return $index_name;
    }

    public function GetIndexTypeName($branch_id = 0)
    {
        if (empty($branch_id)) $type_name = config('neigou.ESSEARCH_TYPE');
        else {
            $type_name = $this->getBranchTypeName($branch_id);
        }
        return $type_name;
    }

    /*
     * @todo 获取索引名称缓存
     */
    public function GetUseIndexList()
    {
        $index_name_list = array();
        $cache_obj = kernel::single('base_sharedkvstore');
        $cache_obj->fetch($this->_index_cache_prefix, $this->_index_version . '_' . $this->_index_cache_key, $index_name_list);
        return $index_name_list;
    }

    /*
    * @todo 获取索引Type名称缓存
    */
    public function GetUseIndexTypeList()
    {
//        $type_name_list = array();
//        $cache_obj = kernel::single('base_sharedkvstore');
//        $cache_obj->fetch($this->_index_cache_prefix, $this->_index_version . '_' . $this->_type_cache_key, $type_name_list);
//
        $redis = new RedisNeigou();
        $data = $redis->_redis_connection->get($this->_index_cache_prefix . '-' . $this->_index_version . '_' . $this->_type_cache_key);
        if (false !== $data) {
            $value = $data;

            $decoded_value = json_decode($value, true);
            if (!empty($decoded_value)) {
                $type_name_list = $decoded_value;
            }
        }
        return $type_name_list;
    }

    /*
     * @todo
     */
    public function SaveUseIndexList($use_index_list)
    {
        print_r($use_index_list);
        $cache_obj = kernel::single('base_sharedkvstore');
        $res = $cache_obj->store($this->_index_cache_prefix, $this->_index_version . '_' . $this->_index_cache_key, $use_index_list, 0);
    }

    /*
     * @todo
     */
    public function SaveUseIndexTypeList($use_type_list)
    {
        $res = $this->store($this->_index_cache_prefix, $this->_index_version . '_' . $this->_type_cache_key, $use_type_list, 0);
    }

    public function store($shared_prefix, $shared_key, $array_value, $ttl = 0)
    {
        $redis = new RedisNeigou();
        $actual_key = $shared_prefix . '-' . $shared_key;
        if ($ttl) {
            $result = $redis->setex($actual_key, $ttl, json_encode($array_value));
        } else {
            $result = $redis->set($actual_key, json_encode($array_value));
        }
        return $result;
    }

}

