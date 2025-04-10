<?php

namespace App\Api\V6\Service\Search\Datasource\Support;

/**
 * 解析Es查询结果
 */
class EsRecombine extends EsPredefined
{
    /**
     * 处理商品查询结果
     * @param array $response_data
     * @return array
     */
    public function ParseSearchData(array $response_data)
    {
        $new_data = [];
        if (isset($response_data['aggregations'])) {
            //聚合数据
            $new_data['aggs_list'] = $this->ParseAggsSearchData($response_data['aggregations']);
        } elseif (isset($response_data['hits']['hits'])) {
            //直接查询的数据
            $new_data['goods_list'] = $this->ParseDirectQuerySearchData($response_data['hits']['hits']);
        }
        return $new_data;
    }

    /**
     * 处理商品聚合后的查询结果
     * @param array $aggs_data
     * @return array
     */
    protected function ParseAggsSearchData(array $aggs_data)
    {
        $new_data = [
            'mall' => [],
            'brand' => [],
            'cat_list' => [],
            'outlet' => [
                'outlet_list' => [],
                'brand_list' => [],
                'goods_list' => [],
                'count' => 0,
            ]
        ];
        foreach ($aggs_data as $k => $v) {
            switch ($k) {
                case "mall":
                    $new_data['mall'] = $this->ParseMallSearchData($v);
                    break;
                case "brand":
                    $new_data['brand'] = $this->ParseBrandSearchData($v);
                    break;
                case "cat_list":
                    $new_data['cat_list'] = $this->ParseCatListSearchData($v);
                    break;
                case "nested_outlet":
                    $new_data['outlet'] = $this->ParseOutletSearchData($v);
                    break;
            }
        }
        return $new_data;
    }

    /**
     * 解析serach中返回 的数据【商城相关查询】
     * @param array $aggs
     * @return array
     */
    protected function ParseMallSearchData(array $aggs): array
    {
        $new_data = [];
        if (!empty($aggs['moduled']['buckets'])) {
            foreach ($aggs['moduled']['buckets'] as $k => $v) {
                $new_data[] = array(
                    'mall_id' => $v['key'],
                );
            }
        }
        return $new_data;
    }

    /**
     * 解析serach中返回 的数据【品牌相关查询】
     * @param array $aggs
     * @return array
     */
    protected function ParseBrandSearchData(array $aggs): array
    {
        $new_data = [];
        if (!empty($aggs['brand_id']['buckets'])) {
            foreach ($aggs['brand_id']['buckets'] as $k => $v) {
                $new_data[] = array(
                    'brand_name' => $v['brand_name']['buckets'][0]['key'],
                    'brand_id' => $v['key'],
                    'brand_logo' => $v['brand_name']['buckets'][0]['brand_list']['hits']['hits'][0]['_source']['brand_logo'] ?? '',
                );
            }
        }
        return $new_data;
    }

    /**
     * 解析serach中返回 的数据【标签相关查询】
     * @param array $aggs
     * @return array
     */
    protected function ParseCatListSearchData(array $aggs): array
    {
        $new_data = [];
        if (!empty($aggs['cat_level_1']['buckets'])) {
            foreach ($aggs['cat_level_1']['buckets'] as $k => $v) {
                $lever_1_son = array();
                $new_data[] = array(
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
        }
        return $new_data;
    }

    /**
     * 解析serach中返回 的数据【门店相关查询】
     */
    public function ParseOutletSearchData(array $aggs): array
    {

        $new_data = array(
            'outlet_list' => [],
            'brand_list' => [],
            'goods_list' => [],
            'count' => 0,
        );
        $buckets_array = $aggs;

        //处理门店数据
        $outlet_list_data = $buckets_array['outlet_list']['outlet_filter'] ?? ['buckets' => []];
        $i = 0;
        foreach ($outlet_list_data['buckets'] as $outlet_v) {
            $new_data['outlet_list'][$i]['outlet_id'] =$outlet_v['key'];
//            $new_data['outlet_list'][$i] = $outlet_v['outlet_group']['hits']['hits'][0]['_source']['outlet_list'];
            if (isset($outlet_v['top_hit_order_geo_point'])) {
                if (strpos($outlet_v['top_hit_order_geo_point']['value'], '.')) {
                    $new_data['outlet_list'][$i]['_distance'] = round($outlet_v['top_hit_order_geo_point']['value'], 3);
                } else {
                    $new_data['outlet_list'][$i]['_distance'] = 0;
                }
            }else{
                $new_data['outlet_list'][$i]['_distance'] = 0;
            }

            $i++;
        }
        if ($new_data['outlet_list']) {
            $new_data['count'] = $i;
            $new_data['outlet_list'] = array_slice($new_data['outlet_list'], $this->start, $this->limit);
        }

        //处理品牌数据
        $brand_list_data = $buckets_array['brand_list']['brand_screen'] ?? ['outlet_filter' => ['buckets' => []]];
        foreach ($brand_list_data['outlet_filter']['buckets'] as $brand_v) {
//            $brand_v_tmp = $brand_v['outlet_group']['hits']['hits'][0]['_source'];
//            if (isset($brand_v_tmp[''])) {
//                unset($brand_v_tmp['']);
//            }
            $new_data['brand_list'][] = ['brand_id'=>$brand_v['key']];
        }

        //处理商品数据
        $goods_list_data = $buckets_array['goods_list']['goods_screen'] ?? ['buckets' => []];
        $j = 0;
        foreach ($goods_list_data['buckets'] as $goods_v) {
            $goods_array = $goods_v['goods_filter']['goods_reverse']['buckets'];
            $new_data['goods_list'][$j] = [
                'outlet_id' => $goods_v['key']
            ];
            foreach ($goods_array as $goods_array_v) {
//                $goods_array_v_tmp = $goods_array_v['outlet_group']['hits']['hits'][0]['_source'];
//                if (isset($goods_array_v_tmp[''])) {
//                    unset($goods_array_v_tmp['']);
//                }
                $new_data['goods_list'][$j]['list'][] = ['goods_id'=>$goods_array_v['key']];
            }
            $j++;
        }
        return $new_data;
    }


    /**
     * 重新加工组合直接查询数据
     * @param array $direct_data
     * @return array
     */
    protected function ParseDirectQuerySearchData(array $direct_data)
    {
        $new_data = [];
        foreach ($direct_data as $v) {
            if (isset($v['_source']['expansion']) && !empty($v['_source']['expansion'])) {
                $v['_source'] = array_merge($v['_source'], $v['_source']['expansion']);
                unset($v['_source']['expansion']);
            }
            $new_data[] = $v['_source'];
        }
        return $new_data;
    }
}
