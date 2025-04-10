<?php
/**
 * 数据源ES
 * @version 0.1
 * @package ectools.lib.api
 */

namespace App\Api\V1\Service\Search\Datasource;

use App\Api\V1\Service\Search\Elasticsearchcreateindex;
use App\Api\Model\Search\BusinessKeywordModel;

class Es
{
    private $elasticsearch_host = '';
    private $index = '';
    private $type = 'goods_test_v1';
    private $curl_ojb = null;
    protected $query_data = array();
    private $_db_store;
    public $province = array(
        "北京" => "p1",
        "上海" => "p2",
        "天津" => "p3",
        "重庆" => "p4",
        "安徽" => "p5",
        "福建" => "p6",
        "甘肃" => "p7",
        "广东" => "p8",
        "广西" => "p9",
        "贵州" => "p10",
        "海南" => "p11",
        "河北" => "p12",
        "河南" => "p13",
        "黑龙江" => "p14",
        "湖北" => "p15",
        "湖南" => "p16",
        "吉林" => "p17",
        "江苏" => "p18",
        "江西" => "p19",
        "辽宁" => "p20",
        "内蒙古" => "p21",
        "宁夏" => "p22",
        "青海" => "p23",
        "山东" => "p24",
        "山西" => "p25",
        "陕西" => "p26",
        "四川" => "p27",
        "西藏" => "p28",
        "新疆" => "p29",
        "云南" => "p30",
        "浙江" => "p31"
    );

    public function __construct()
    {
        $this->_db_store = app('api_db')->connection('neigou_store');
        $this->elasticsearch_host = config('neigou.ESSEARCH_HOST') . ':' . config('neigou.ESSEARCH_PORT');
        $this->index = config('neigou.ESSEARCH_INDEX');
        $this->type = config('neigou.ESSEARCH_TYPE');
        $this->curl_ojb = new \Neigou\Curl();
    }

    /*
     * @todo 数据查询
     * @parameter $request_data 请求数据 $moduled 使用数据模块
     */
    public function Select($request_data)
    {
        $request_data['start'] = intval($request_data['start']) >= 0 ? intval($request_data['start']) : 0;
        $request_data['from'] = intval($request_data['from']) >= 0 ? intval($request_data['from']) : 0;
        $this->query_data['from'] = $request_data['start'];
        $this->query_data['size'] = $request_data['limit'];
        //过滤
        $this->ParseFilter($request_data['filter'], $request_data['stages']);
        $this->ParseSource($request_data);
        $this->ParseOrder($request_data['order'], $request_data['stages']);

        $index_name = $this->index;
        $index_type = $this->GetIndexTypeName($request_data['filter']['branch_id']);
        //执行结果
        $respnse_data = $this->Query('_search', $index_name, $index_type);
        if ($respnse_data === false || isset($respnse_data['error'])) {
            //执行失败,记录
            \Neigou\Logger::General('es_query_error',
                array('result' => json_encode($respnse_data), 'sender' => json_decode($this->query_data)));
            return false;
        } else {
            //聚合数据
            $aggs_list = array();
            if ($request_data['is_aggs'] == 'true') {
                $aggs_list = $this->Aggs($request_data['filter'], $request_data['stages']);
            }
            $respnse_data = $this->ParseSearchData($respnse_data);
            $respnse_data['aggs'] = $aggs_list;
            return $respnse_data;
        }
    }

    /*
     * @todo 数据统计
     * @parameter $request_data 请求数据 $type数据源类型 es|mysql
     */
    public function Count($request_data)
    {
        //过滤
        $this->ParseFilter($request_data['filter'], $request_data['stages']);
        //执行结果
        $index_name = $this->index;
        $index_type = $this->GetIndexTypeName($request_data['filter']['branch_id']);
        $respnse_data = $this->Query('_count', $index_name, $index_type);
        if ($respnse_data === false || isset($respnse_data['error'])) {
            //执行失败,记录
            \Neigou\Logger::General('es_query_error',
                array('result' => json_encode($respnse_data), 'sender' => json_decode($this->query_data)));
            return false;
        } else {
            return $respnse_data['count'];
        }
    }

    /*
     * @todo 获取聚合信息
     */
    public function Aggs($filter_data, $stages = array())
    {
        $this->query_data['size'] = 0;
        //指定模块数据
        if (isset($filter_data['mall_id']) && !empty($filter_data['mall_id'])) {
            $this->query_data['query']['bool']['filter'][]['terms']['moduled'] = $filter_data['mall_id'];
        }
        //keyword搜索
        if (isset($filter_data['keyword']) && !empty($filter_data['keyword'])) {
            $this->query_data['query']['bool']['must'][] = array(
                'match_phrase' => array(
                    'name' => array(
                        'query' => $filter_data['keyword'],
                        'slop' => 40,
                    ),
                ),
            );
        }
        //商品库存筛选
        if (isset($filter_data['store']) && !empty($filter_data['store'])) {
            if (!empty($filter_data['store']['gt']) || $filter_data['store']['gt'] === 0) {
                $this->query_data['query']['bool']['filter'][]['range']['store']['gt'] = $filter_data['store']['gt'];
            }
            if (!empty($filter_data['store']['lte']) || $filter_data['store']['lte'] === 0) {
                $this->query_data['query']['bool']['filter'][]['range']['store']['lte'] = $filter_data['store']['lte'];
            }
        }
        //上下架筛选
        if (isset($filter_data['marketable']) && !empty($filter_data['marketable'])) {
            $this->query_data['query']['bool']['filter'][]['term']['marketable'] = $filter_data['marketable'];
        }
        //地区筛选
        if (isset($filter_data['province']) && !empty($filter_data['province'])) {
            $province_key = $this->province[$filter_data['province']];
            if (!empty($province_key)) {
                $this->query_data['query']['bool']['filter'][]['term']['province_stock.' . $province_key] = 1;
            }
        }
        //价格筛选
        if (isset($filter_data['price']) && !empty($filter_data['price'])) {
            $price_filter = $this->GetPriceFilter($stages, $filter_data['price']['gte'], $filter_data['price']['lte']);
            if (!empty($price_filter)) {
                $this->query_data['query']['bool']['filter'][] = $price_filter;
            }
        }
        //积点价格筛选
        if (isset($filter_data['point_price']) && !empty($filter_data['point_price'])) {
            $this->query_data['query']['bool']['filter'][]['range']['point_price'] = array(
                'gte' => $filter_data['point_price']['gte'],
                'lte' => $filter_data['point_price']['lte'],
            );
        }
        //vmall虚拟分类筛选
        if (isset($filter_data['vmall_cat_id']) && !empty($filter_data['vmall_cat_id'])) {
            $this->query_data['query']['bool']['filter'][]['term']['vmall_cat_id'] = intval($filter_data['vmall_cat_id']);
        }
        $this->ParseAggs($filter_data);
        //执行结果
        $index_name = $this->index;
        $index_type = $this->GetIndexTypeName($filter_data['branch_id']);
        $respnse_data = $this->Query('_search', $index_name, $index_type);
        if ($respnse_data === false || isset($respnse_data['error'])) {
            //执行失败,记录
            \Neigou\Logger::General('es_query_error',
                array('result' => json_encode($respnse_data), 'sender' => json_decode($this->query_data)));
            return array();
        } else {
            //聚合数据
            $respnse_data = $this->ParseSearchAggsData($respnse_data);
            return $respnse_data;
        }

    }

    public function ParseSource($filter_data)
    {
        // 限制字段
        if (isset($filter_data['_source'])) {
            $this->query_data['_source'] = $filter_data['_source'];
        }
    }

    /*
     * @todo 解析请求参数中的filter
     */
    public function ParseFilter($filter_data, $stages = array())
    {
        //商品模块筛选
        if (isset($filter_data['mall_id']) && !empty($filter_data['mall_id'])) {
            $this->query_data['query']['bool']['filter'][]['terms']['moduled'] = $filter_data['mall_id'];
        }
        //keyword搜索
        if (isset($filter_data['keyword']) && !empty($filter_data['keyword'])) {
            $this->query_data['query']['bool']['must'][] = array(
                'match_phrase' => array(
                    'name' => array(
                        'query' => $filter_data['keyword'],
                        'slop' => 40,
                    ),
                ),
            );
        }
        //商品库存筛选
        if (isset($filter_data['store']) && !empty($filter_data['store'])) {
            if (!empty($filter_data['store']['gt']) || $filter_data['store']['gt'] === 0) {
                $this->query_data['query']['bool']['filter'][]['range']['store']['gt'] = floatval($filter_data['store']['gt']);
            }
            if (!empty($filter_data['store']['lte']) || $filter_data['store']['lte'] === 0) {
                $this->query_data['query']['bool']['filter'][]['range']['store']['lte'] = floatval($filter_data['store']['lte']);
            }
        }
        //价格筛选
        if (isset($filter_data['price']) && !empty($filter_data['price'])) {
            $price_filter = $this->GetPriceFilter($stages, $filter_data['price']['gte'], $filter_data['price']['lte']);
            if (!empty($price_filter)) {
                $this->query_data['query']['bool']['filter'][] = $price_filter;
            }
        }
        //积点价格筛选
        if (isset($filter_data['point_price']) && !empty($filter_data['point_price'])) {
            $this->query_data['query']['bool']['filter'][]['range']['point_price'] = array(
                'gte' => $filter_data['point_price']['gte'],
                'lte' => $filter_data['point_price']['lte'],
            );
        }

        //上下架筛选
        if (isset($filter_data['marketable']) && !empty($filter_data['marketable'])) {
            $this->query_data['query']['bool']['filter'][]['term']['marketable'] = $filter_data['marketable'];
        }
        //地区筛选
        if (isset($filter_data['province']) && !empty($filter_data['province'])) {
            $province_key = $this->province[$filter_data['province']];
            if (!empty($province_key)) {
                $this->query_data['query']['bool']['filter'][]['term']['province_stock.' . $province_key] = 1;
            }
        }
        //品牌筛选
        if (isset($filter_data['brand_id']) && !empty($filter_data['brand_id'])) {
            $this->query_data['query']['bool']['filter'][]['term']['brand_id'] = intval($filter_data['brand_id']);
        }
        if (isset($filter_data['brand_name']) && !empty($filter_data['brand_name'])) {
            $this->query_data['query']['bool']['filter'][]['term']['brand_name.raw'] = $filter_data['brand_name'];
        }
        //分类筛选
        if (isset($filter_data['cat_level_1']) && !empty($filter_data['cat_level_1'])) {
            if (!empty($filter_data['cat_level_1']['cat_id'])) {
                $this->query_data['query']['bool']['filter'][]['term']['cat_level_1.cat_id'] = intval($filter_data['cat_level_1']['cat_id']);
            }
            if (!empty($filter_data['cat_level_1']['cat_name'])) {
                $this->query_data['query']['bool']['filter'][]['term']['cat_level_1.cat_name.raw'] = $filter_data['cat_level_1']['cat_name'];
            }
        }
        if (isset($filter_data['cat_level_2']) && !empty($filter_data['cat_level_2'])) {
            if (!empty($filter_data['cat_level_2']['cat_id'])) {
                $this->query_data['query']['bool']['filter'][]['term']['cat_level_2.cat_id'] = intval($filter_data['cat_level_2']['cat_id']);
            }
            if (!empty($filter_data['cat_level_2']['cat_name'])) {
                $this->query_data['query']['bool']['filter'][]['term']['cat_level_2.cat_name.raw'] = $filter_data['cat_level_2']['cat_name'];
            }
        }
        if (isset($filter_data['cat_level_3']) && !empty($filter_data['cat_level_3'])) {
            if (!empty($filter_data['cat_level_3']['cat_id'])) {
                $this->query_data['query']['bool']['filter'][]['term']['cat_level_3.cat_id'] = intval($filter_data['cat_level_3']['cat_id']);
            }
            if (!empty($filter_data['cat_level_3']['cat_name'])) {
                $this->query_data['query']['bool']['filter'][]['term']['cat_level_3.cat_name.raw'] = $filter_data['cat_level_3']['cat_name'];
            }
        }
        //所属国家
        if (isset($filter_data['country_id']) && !empty($filter_data['country_id'])) {
            $this->query_data['query']['bool']['filter'][]['term']['country_id'] = intval($filter_data['country_id']);
        }
        //所属国家馆
        if (isset($filter_data['pavilion_id']) && !empty($filter_data['pavilion_id'])) {
            $this->query_data['query']['bool']['filter'][]['term']['pavilion_id'] = intval($filter_data['pavilion_id']);
        }

        //vmall虚拟分类筛选
        if (isset($filter_data['vmall_cat_id']) && !empty($filter_data['vmall_cat_id'])) {
            $this->query_data['query']['bool']['filter'][]['term']['vmall_cat_id'] = intval($filter_data['vmall_cat_id']);
        }

        // goods_id筛选
        if (isset($filter_data['goods_ids'])) {
            $this->query_data['query']['bool']['filter'][]['terms']['_id'] = $filter_data['goods_ids'];
        }

        // 筛选预定义字段
        foreach ([
                     'int1',
                     'int2',
                     'int3',
                     'int4',
                     'int5',
                     'double1',
                     'double2',
                     'double3',
                     'double4',
                     'double5',
                     'str1',
                     'str2',
                     'str3',
                     'str4',
                     'str5'
                 ] as $es_field) {
            if (isset($filter_data[$es_field]) && !empty($filter_data[$es_field])) {
                $this->query_data['query']['bool']['filter'][]['terms'][$es_field] = $filter_data[$es_field];
            }
        }
    }

    /*
     * @todo 解析请求参数中的aggs
     */
    public function ParseAggs($filter_data)
    {
        //品牌筛选
        if (isset($filter_data['brand_id']) && !empty($filter_data['brand_id'])) {
            $aggs_filter['brand_id']['term']['brand_id'] = intval($filter_data['brand_id']);
        }
        if (isset($filter_data['brand_name']) && !empty($filter_data['brand_name'])) {
            $aggs_filter['brand_name']['term']['brand_name.raw'] = $filter_data['brand_name'];
        }
        //分类筛选
        if (isset($filter_data['cat_level_1']) && !empty($filter_data['cat_level_1'])) {
            if (!empty($filter_data['cat_level_1']['cat_id'])) {
                $aggs_filter['cat_level_1_id']['term']['cat_level_1.cat_id'] = intval($filter_data['cat_level_1']['cat_id']);
            }
            if (!empty($filter_data['cat_level_1']['cat_name'])) {
                $aggs_filter['cat_level_1_name']['term']['cat_level_1.cat_name.raw'] = $filter_data['cat_level_1']['cat_name'];
            }
        }
        if (isset($filter_data['cat_level_2']) && !empty($filter_data['cat_level_2'])) {
            if (!empty($filter_data['cat_level_2']['cat_id'])) {
                $aggs_filter['cat_level_2_id']['term']['cat_level_2.cat_id'] = intval($filter_data['cat_level_2']['cat_id']);
            }
            if (!empty($filter_data['cat_level_2']['cat_name'])) {
                $aggs_filter['cat_level_2_name']['term']['cat_level_2.cat_name.raw'] = $filter_data['cat_level_2']['cat_name'];
            }
        }
        if (isset($filter_data['cat_level_3']) && !empty($filter_data['cat_level_3'])) {
            if (!empty($filter_data['cat_level_3']['cat_id'])) {
                $aggs_filter['cat_level_3_name']['term']['cat_level_3.cat_id'] = intval($filter_data['cat_level_3']['cat_id']);
            }
            if (!empty($filter_data['cat_level_3']['cat_name'])) {
                $aggs_filter['cat_level_3_name']['term']['cat_level_3.cat_name.raw'] = $filter_data['cat_level_3']['cat_name'];
            }
        }
        // 商城筛选
        if (isset($filter_data['mall_id']) && !empty($filter_data['mall_id'])) {
            $aggs_filter['mall']['terms']['moduled'] = $filter_data['mall_id'];
        }
        $brand_filter = $cat_filter = $mall_filter = $aggs_filter;

        unset($mall_filter['mall']);
        $this->query_data['aggs']['mall']['filter'] = (object)null;
        $this->query_data['aggs']['mall']['aggs']['moduled'] = array(
            'terms' => array(
                'field' => 'moduled',
                'size' => 200,
            )
        );
//        if (isset($filter_data['mall_id']) && !empty($filter_data['mall_id'])) {
//            $this->query_data['aggs']['mall']['filter']['bool']['must'][] = array(
//                'terms' => array(
//                    'moduled' => $filter_data['mall_id'],
//                )
//            );
//        }
        if (!empty($mall_filter)) {
            $this->query_data['aggs']['mall']['filter'] = array();
            foreach ($mall_filter as $v) {
                $this->query_data['aggs']['mall']['filter']['bool']['must'][] = $v;
            }
        }

        //品牌使用过滤内容
        unset($brand_filter['brand_id'], $brand_filter['brand_name']);
        $this->query_data['aggs']['brand']['filter'] = (object)null;
        $this->query_data['aggs']['brand']['aggs']['brand_id'] = array(
            'terms' => array(
                'field' => 'brand_id',
                'size' => 200,
            ),
            'aggs' => array(
                'brand_name' => array(
                    'terms' => array(
                        'field' => 'brand_name.raw',
                        'size' => 200,
                    )
                )
            )
        );
//        if (isset($filter_data['mall_id']) && !empty($filter_data['mall_id'])) {
//            $this->query_data['aggs']['brand']['filter']['bool']['must'][] = array(
//                'terms' => array(
//                    'moduled' => $filter_data['mall_id'],
//                )
//            );
//        }
        if (!empty($brand_filter)) {
            $this->query_data['aggs']['brand']['filter'] = array();
            foreach ($brand_filter as $v) {
                $this->query_data['aggs']['brand']['filter']['bool']['must'][] = $v;
            }
        }
        //分类使用过滤内容
        unset($cat_filter['cat_level_1_name'], $cat_filter['cat_level_1_id'], $cat_filter['cat_level_2_name'], $cat_filter['cat_level_2_id'], $cat_filter['cat_level_3_name'], $cat_filter['cat_level_3_id']);
        $this->query_data['aggs']['cat_list']['filter'] = (object)null;
        $this->query_data['aggs']['cat_list']['aggs']['cat_level_1'] = array(
            'terms' => array(
                'field' => 'cat_level_1.cat_id',
                'size' => 200,
            ),
            'aggs' => array(
                'cat_name' => array(
                    'terms' => array(
                        'field' => 'cat_level_1.cat_name.raw',
                        'size' => 200,
                    )
                ),
                'cat_level_2' => array(
                    'terms' => array(
                        'field' => 'cat_level_2.cat_id',
                        'size' => 200,
                    ),
                    'aggs' => array(
                        'cat_name' => array(
                            'terms' => array(
                                'field' => 'cat_level_2.cat_name.raw',
                                'size' => 200,
                            )
                        ),
                        'cat_level_3' => array(
                            'terms' => array(
                                'field' => 'cat_level_3.cat_id',
                                'size' => 200,
                            ),
                            'aggs' => array(
                                'cat_name' => array(
                                    'terms' => array(
                                        'field' => 'cat_level_3.cat_name.raw',
                                        'size' => 200,
                                    )
                                )
                            ),
                        ),
                    ),
                ),
            ),
        );
        if (!empty($cat_filter)) {
            $this->query_data['aggs']['cat_list']['filter'] = array();
            foreach ($cat_filter as $v) {
                $this->query_data['aggs']['cat_list']['filter']['bool']['must'][] = $v;
            }
        }

        unset($aggs_filter, $brand_filter, $cat_filter);
    }

    /*
     * @todo 解析请求参数中的order
     */
    public function ParseOrder($order_data, $stages = array())
    {
        if (!empty($order_data)) {
            foreach ($order_data as $order_key => $order_val) {
                switch ($order_key) {
                    case 'weight' :
                        $this->query_data['sort']['weight']['order'] = $order_val['by'];
                        break;
                    case 'goods_id' :
                        $this->query_data['sort']['goods_id']['order'] = $order_val['by'];
                        break;
                    case 'is_soldout' :
                        $this->query_data['sort']['is_soldout']['order'] = $order_val['by'];
                        break;
                    case 'province' :
                        $province_key = $this->province[$order_val['name']];
                        if (!empty($province_key)) {
                            $this->query_data['sort']['province_stock.' . $province_key]['order'] = $order_val['by'];
                        }
                        break;
                    case 'price' :
                        $this->GetPriceOrder($stages, 'price', $order_val['by']);
                        break;
                    case 'point_price' :
                        $this->GetPriceOrder($stages, 'point_price', $order_val['by']);
                        break;
                    case 'keyword':
                        $this->sortByKeyword($order_val);
                        break;
                    case 'weight_factor':
                        $this->sortGoodsWeightFactor($order_val);
                        break;
                    case 'default':
                        $this->query_data['sort']['_score']['order'] = 'desc';
                        break;
                }
                // 预定义字段排序
                if (in_array($order_key, [
                    'int1',
                    'int2',
                    'int3',
                    'int4',
                    'int5',
                    'double1',
                    'double2',
                    'double3',
                    'double4',
                    'double5',
                    'str1',
                    'str2',
                    'str3',
                    'str4',
                    'str5'
                ])) {
                    $this->query_data['sort'][$order_key]['order'] = $order_val['by'];
                }
            }
        } else {
            $this->query_data['sort']['_score']['order'] = 'desc';
        }
        \Neigou\Logger::Debug('es_ParseOrder', array(
            'order_data' => json_encode($order_data),
            'query_data' => $this->query_data,
        ));
    }

    /*
     * @todo 执行
     * @parameter $pattern 操作模式 _count(统计) _search(搜索)
     */
    public function Query($pattern, $index_name = '', $index_type = '')
    {
        $index_name = !empty($index_name) ? $index_name : $this->index;
        $index_type = !empty($index_type) ? $index_type : $this->type;
        $post_url = $this->elasticsearch_host . '/' . $index_name . '/' . $index_type . '/' . $pattern;
        $dsl = json_encode($this->query_data);

        $this->query_data = array();  //执行完成清空执行数组
        $data = $this->curl_ojb->Post($post_url, $dsl);
        if (empty($data)) {
            return false;
        } else {
            $data = json_decode($data, true);
        }
        return $data;
    }

    /*
     * @todo 解析serach中返回 的数据
     */
    public function ParseSearchData($data)
    {
        $new_data = array();
        if (empty($data['hits']['hits'])) {
            return $new_data;
        }
        //搜索数据
        foreach ($data['hits']['hits'] as $k => $v) {
            if (isset($v['_source']['expansion']) && !empty($v['_source']['expansion'])) {
                $v['_source'] = array_merge($v['_source'], $v['_source']['expansion']);
                unset($v['_source']['expansion']);
            }
            $new_data['goods_list'][] = $v['_source'];
        }
        return $new_data;
    }

    /*
     * @todo 解析serach中返回 的数据
     */
    public function ParseSearchDataWithKey($data)
    {
        $new_data = array();
        if (empty($data['hits']['hits'])) {
            return $new_data;
        }
        //搜索数据
        foreach ($data['hits']['hits'] as $k => $v) {
            if (isset($v['_source']['expansion']) && !empty($v['_source']['expansion'])) {
                $v['_source'] = array_merge($v['_source'], $v['_source']['expansion']);
                unset($v['_source']['expansion']);
            }
            $new_data['goods_list'][$v['_id']] = $v['_source'];
        }
        return $new_data;
    }


    /*
     * @todo   格式化聚合返回信息
     */
    public function ParseSearchAggsData($data)
    {
        $new_data = array();
        //筛选结果
        if (!empty($data['aggregations'])) {
            foreach ($data['aggregations'] as $field => $aggs) {
                switch ($field) {
                    case 'cat_list':
                        if (!empty($aggs['cat_level_1']['buckets'])) {
                            foreach ($aggs['cat_level_1']['buckets'] as $k => $v) {
                                $lever_1_son = array();
                                $new_data['cat_list'][] = array(
                                    'cat_name' => $v['cat_name']['buckets'][0]['key'],
                                    'cat_id' => $v['key'],
                                    'son' => &$lever_1_son,
                                );
                                if (!empty($v['cat_level_2']['buckets'])) {
                                    foreach ($v['cat_level_2']['buckets'] as $cat_level_2) {
                                        $lever_2_son = array();
                                        $lever_1_son[] = array(
                                            'cat_name' => $cat_level_2['cat_name']['buckets'][0]['key'],
                                            'cat_id' => $cat_level_2['key'],
                                            'son' => &$lever_2_son,
                                        );
                                        if (!empty($cat_level_2['cat_level_3']['buckets'])) {
                                            foreach ($cat_level_2['cat_level_3']['buckets'] as $cat_level_3) {
                                                $lever_2_son[] = array(
                                                    'cat_name' => $cat_level_3['cat_name']['buckets'][0]['key'],
                                                    'cat_id' => $cat_level_3['key'],
                                                );
                                            }
                                        }
                                        unset($lever_2_son);
                                    }
                                }
                                unset($lever_1_son);
                            }
                        } else {
                            $new_data['cat_list'] = array();
                        }
                        break;
                    case 'brand':
                        if (!empty($aggs['brand_id']['buckets'])) {
                            foreach ($aggs['brand_id']['buckets'] as $k => $v) {
                                $new_data['brand'][] = array(
                                    'brand_name' => $v['brand_name']['buckets'][0]['key'],
                                    'brand_id' => $v['key'],
                                );
                            }
                        } else {
                            $new_data['brand'] = array();
                        }
                        break;
                    case 'mall':
                        if (!empty($aggs['moduled']['buckets'])) {
                            foreach ($aggs['moduled']['buckets'] as $k => $v) {
                                $new_data['mall'][] = array(
                                    'mall_id' => $v['key'],
                                );
                            }
                        } else {
                            $new_data['brand'] = array();
                        }
                        break;
                }
            }
        }
        return $new_data;
    }

    /*
     * @todo 获取价格筛选
     */
    public function GetPriceFilter($stages, $gte_price, $lte_price)
    {
        $price_filter['range']['price'] = array(
            array(
                'gte' => $gte_price,
                'lte' => $lte_price,
            )
        );
        return $price_filter;
        $stage_price_field = $this->GetEsFiled($stages);
        if (empty($stage_price_field)) {
            $price_filter['range']['price'] = array(
                array(
                    'gte' => $gte_price,
                    'lte' => $lte_price,
                )
            );
//        }else if(count($stage_price_field) == 1){
//            $filter     = current($stage_price_field);
//            $price_filter['range'][$filter]   = array(
//                array(
//                    'gte'   => $gte_price,
//                    'lte'   => $lte_price,
//                )
//            );
        } else {
            $price_filter['script']['script'] = array(
                "inline" => '
                def price = 0.00;
                def price_value = new ArrayList();
                for(int i=0;i < params.filter.length;i++){
                    if(doc.containsKey(params.filter[i])){
                        price  = doc[params.filter[i]].value;
                    }else{
                        price = 0;
                    }
                    if(price > 0.001){
                        price_value.add(price);
                    }
                }
                if(price_value.length > 0){
                    price_value.sort((x, y) -> x - y);
                    price = price_value[0];
                }else{
                    price = doc["price"].value;
                }
                if(params.range.lte > 0.001){
                    return price >= params.range.gte && price <= params.range.lte;
                }else{
                    return price >= params.range.gte
                }
                ',
                'lang' => 'painless',
                'params' => array(
                    'filter' => $stage_price_field,
                    'range' => array(
                        'gte' => floatval($gte_price),
                        'lte' => floatval($lte_price),
                    )
                )
            );
        }
        return $price_filter;
    }

    private function sortGoodsWeightFactor($weight_factor = array())
    {
        /*
        $weight_factor = array(
            array(
                'field' => 'bn',
                'value' => 'JD-4334444',
                'boost' => 44,
            )
        );*/

        $field_index = [];

        $data = [];
        foreach ($weight_factor as $row) {
            $data['fields'][] = [
                'field' => isset($field_index["{$row['field']}"]) ? $field_index["{$row['field']}"] : $row['field'],
                'value' => $row['value'],
                'boost' => $row['boost'],
            ];
        }

        if ($data) {
            $this->query_data['sort']['_script'] = [
                'type' => 'number',
                'script' => [
                    'lang' => 'painless',
                    'inline' => "
                            long total = 0;
                            for (int i = 0; i< params.data.fields.length; i++) {
                                if(doc[params.data.fields[i].field].value == params.data.fields[i].value) {
                                    total += params.data.fields[i].boost;
                                }
                            }
                            return total;",
                    'params' => [
                        'data' => $data,
                    ]
                ],
                'order' => 'desc'
            ];


        }

    }

    private function sortByKeyword($keyword)
    {
        $m_keyword = new BusinessKeywordModel();
        $keywordRules = $m_keyword->getKeywords($keyword);
        if ($keywordRules === false) {
            return;
        }
        $tran_boosts = [16, 8, 4, 2, 1];
        $data = [];
        for ($i = 0, $count = count($keywordRules); $i < $count; $i++) {
            $keywordRule = $keywordRules[$i];
            if (in_array($keywordRule['es_field'],
                ['brand_id', 'cat_level_1.cat_id', 'cat_level_2.cat_id', 'cat_level_3.cat_id'])) {
                $es_value = (int)$keywordRule['es_value'];
            } else {
                $es_value = $keywordRule['es_value'];
            }
            $data['fields'][] = [
                'field' => $keywordRule['es_field'],
                'value' => $es_value,
//                'boost' => $keywordRule['boost'] * pow(10, $count - $i),
                'boost' => $tran_boosts[$i],
            ];
        }
        //分类 + 品牌/ 分类/品牌
        if ($data) {
            $this->query_data['sort']['_script'] = [
                'type' => 'number',
                'script' => [
                    'lang' => 'painless',
                    'inline' => "
                            long total = 0;
                            for (int i = 0; i< params.data.fields.length; i++) {
                                if(doc[params.data.fields[i].field].value == params.data.fields[i].value) {
                                    total += params.data.fields[i].boost;
                                }
                            }
                            return total;",
                    'params' => [
                        'data' => $data,
                    ]
                ],
                'order' => 'desc'
            ];
        }
    }

    /*
     * @todo 获取价格排序
     */
    private function GetPriceOrder($stages, $order_filed = 'price', $order_by = 'asc')
    {
        $this->query_data['sort'][$order_filed]['order'] = $order_by;
        return true;
        $price_filter = array();
        $stage_price_field = $this->GetEsFiled($stages);
        if (empty($stage_price_field)) {
            $this->query_data['sort'][$order_filed]['order'] = $order_by;
        } else {
            $this->query_data['sort']['_script'] = array(
                'type' => 'number',
                'script' => array(
                    'lang' => 'painless',
                    'inline' => '
                        def price = 0.00;
                        def price_value = new ArrayList();
                        for(int i=0;i < params.price.length;i++){
                            if(doc.containsKey(params.price[i])){
                                price  = doc[params.price[i]].value;
                            }else{
                                price = 0;
                            }
                            if(price > 0.001){
                                price_value.add(price);
                            }
                        }
                        if(price_value.length > 0){
                            price_value.sort((x, y) -> x - y);
                            price = price_value[0];
                        }else{
                            price = doc["' . $order_filed . '"].value;
                        }
                        return price;',
                    'params' => array(
                        'price' => $stage_price_field
                    )
                ),
                'order' => $order_by
            );
        }
        return true;
    }

    /*
     * @todo 获取场景对象ES字段
     */
    private function GetEsFiled($stages)
    {
        return [];
        if (empty($stages)) {
            return array();
        }
        $stages_key = md5(json_encode($stages));
        if (!$this->_stages_es_filed[$stages_key]) {
//            $stage_es_mdl = APP::get('b2c')->model('pricing_stagees');
            $stage_price_field = array();
//            $pricing_stagees_mapping = $stage_es_mdl->GetAll();

            $pricing_stagees_mapping = $this->_db_store->table('server_pricing_stage_es_mapping')->get()->map(function (
                $value
            ) {
                return (array)$value;
            })->toArray();;

            if (!empty($pricing_stagees_mapping)) {
                foreach ($pricing_stagees_mapping as $stagees_es_filed) {
                    if ($stagees_es_filed['exclusive'] == 1) {
                        if ($stages[$stagees_es_filed['stage_type']] != $stagees_es_filed['stage_value']) {
                            $stage_price_field[$stagees_es_filed['es_field']] = $stagees_es_filed['es_field'];
                        }
                    } else {
                        if ($stages[$stagees_es_filed['stage_type']] == $stagees_es_filed['stage_value']) {
                            $stage_price_field[$stagees_es_filed['es_field']] = $stagees_es_filed['es_field'];
                        }
                    }
                }
            }
            $this->_stages_es_filed[$stages_key] = array_values($stage_price_field);
        }
        return $this->_stages_es_filed[$stages_key];
    }

    /*
    * @todo 获取搜索使用TYPE名
    */
    private function GetIndexTypeName($branch_ids)
    {
        $es_index = new Elasticsearchcreateindex();
        $index_type_name_list = array();
        if (!is_array($branch_ids)) {
            $branch_ids = array($branch_ids);
        }
        if (!in_array(0, $branch_ids)) {
            $branch_ids[] = 0;
        }
        foreach ($branch_ids as $branch_id) {
            $branch_id = intval($branch_id);
            $index_type_name_list[] = $es_index->GetIndexTypeName($branch_id);
        }
        $index_type_name = implode(',', $index_type_name_list);
        return $index_type_name;
    }
}
