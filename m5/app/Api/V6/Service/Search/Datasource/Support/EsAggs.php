<?php

namespace App\Api\V6\Service\Search\Datasource\Support;

/**
 * 聚合查询逻辑
 */
class EsAggs extends EsPredefined
{
    /**
     * 聚合参数组合【入口】
     * @param array $aggs_data
     * @return array
     */
    public function ParseAggs(array $aggs_data)
    {
        if (!$aggs_data) {
            return [];
        }
        $this->SetDriver(['filter', 'order', 'source',]);
        $aggs = [
            "size" => 0,
            "aggs" => []
        ];
        foreach ($aggs_data as $aggs_k => $aggs_v) {
            $aggs_data = $this->Aggs($aggs_v, $aggs_k);
            if (in_array($aggs_k, [
                'outlet|outlet_list',
                'outlet|goods_list',
                'outlet|brand_list'])) {
                if (!isset($aggs['aggs']['nested_outlet']["nested"]['path'])) {
                    $aggs['aggs']['nested_outlet']["nested"]['path'] = 'outlet_list';
                }
            }

            switch ($aggs_k) {
                case "outlet|outlet_list":
                    $aggs_tmp = $this->ParseAggsOutlet($aggs_data);
                    break;
                case "outlet|goods_list":
                    $aggs_tmp = $this->ParseAggsOutletGoods($aggs_data);
                    break;
                case "outlet|brand_list":
                    $aggs_tmp = $this->ParseAggsOutletBrand($aggs_data);
                    break;
                case "mall":
                    $aggs_tmp = $this->ParseAggsMall($aggs_data);
                    break;
                case "brand":
                    $aggs_tmp = $this->ParseAggsBrand($aggs_data);
                    break;
                case "cat_list":
                    $aggs_tmp = $this->ParseAggsCatList($aggs_data);
                    break;
            }

            $aggs_tmp['aggs'] && $aggs['aggs'] = array_merge_recursive($aggs['aggs'], $aggs_tmp['aggs']);
        }
//        return $tmp;
        return $aggs;
    }

    /**
     * 解析处理Aggs聚合查询中的各种条件
     * @param array $aggs_data
     * @param $aggs_key
     * @return array
     */
    protected function Aggs(array $aggs_data, $aggs_key)
    {
        $source = [];
        $order = [];
//        解析当前聚合的查询条件[正查and反查]
        $filter_array = $this->es_filter_service->ParseAggsFilter($aggs_data['filter'] ?? [], $aggs_data['filter_not'] ?? []);

        if (isset($aggs_data['includes']) && $aggs_data['includes']) {
            //解析当前聚合的指定查询字段
            $source = $this->es_source_service->ParseSource($aggs_data['includes'], $aggs_key);
        }
        if (isset($aggs_data['order']) && $aggs_data['order']) {
            //解析当前聚合的排序条件
            $order = $this->es_order_service->ParseAggsScriptOrder($aggs_data['order']);
        }

        return ['filter' => $filter_array, 'source' => $source, 'order' => $order];
    }

    /**
     * 组合门店聚合查询语句
     * @param array $aggs_data
     * @return array
     */
    protected function ParseAggsOutlet(array $aggs_data): array
    {

        $data['aggs']['nested_outlet']['aggs']['outlet_list']['filter'] = $aggs_data['filter'] ?? (object)[];

        $data['aggs']['nested_outlet']['aggs']['outlet_list']['aggs']['outlet_filter'] = [
            //解决重复数据，分桶处理
            'terms' => [
                'field' => 'outlet_list.outlet_id',
                'size' => 3000,
                "collect_mode" => "breadth_first",//改深度优先为广度优先
            ],
        ];
        //组合条件查询语句
//        $data['aggs']['nested_outlet']['aggs']['outlet_list']['aggs']['outlet_filter']['filter'] = $aggs_data['filter'] ?? (object)[];
        //组合聚合查询语句
//        $data['aggs']['nested_outlet']['aggs']['outlet_list']['aggs']['outlet_filter']['aggs'] = [
//            'outlet_group' => [
//                //数据查询
//                'top_hits' => [
//                    'size' => 1,
//                ],
//            ],
//        ];

        //完善聚合查询语句-限定查询字段 todo 去掉查询指定的字段
//        if (isset($aggs_data['source'])) {
//            $data['aggs']['nested_outlet']['aggs']['outlet_list']['aggs']['outlet_filter']['aggs']['outlet_group']['top_hits']['_source'] = $aggs_data['source']['_source'];
//        }

        //完善聚合查询排序语句，因可能存在多个排序语句，因此使用下标区分不同排序语句组
        foreach ($aggs_data['order']['outlet_script_order'] as $order_k => $order_v) {
            if($order_v['type'] == 'geo_point'){
                //兼容经纬度距离排序
                $order_k = 'geo_point';
            }
            $data['aggs']['nested_outlet']['aggs']['outlet_list']['aggs']['outlet_filter']['terms']['order']["top_hit_order_{$order_k}"] = $order_v['top_hit_order'];
            $data['aggs']['nested_outlet']['aggs']['outlet_list']['aggs']['outlet_filter']['aggs']["top_hit_order_{$order_k}"] = $order_v['top_hit'];
        }

        return $data;
    }

    /**
     * 组合品牌聚合查询语句
     * @param array $aggs_data
     * @return array
     */
    protected function ParseAggsOutletBrand(array $aggs_data): array
    {
        //组合条件查询语句
        $data['aggs']['nested_outlet']['aggs']['brand_list']['filter'] = $aggs_data['filter'] ?? (object)[];

        $data['aggs']['nested_outlet']['aggs']['brand_list']["aggs"]["brand_screen"]["reverse_nested"] = (object)[];

        $data['aggs']['nested_outlet']['aggs']['brand_list']["aggs"]["brand_screen"]['aggs']['outlet_filter'] = [
            //解决重复数据，分桶处理
            'terms' => [
                'field' => 'brand_id',
                'size' => 3000,
                "collect_mode" => "breadth_first",//改深度优先为广度优先
            ],
        ];
        //组合聚合查询语句
//        $data['aggs']['nested_outlet']['aggs']['brand_list']["aggs"]["brand_screen"]['aggs']['outlet_filter']['aggs'] = [
//            'outlet_group' => [
//                //数据查询
//                'top_hits' => [
//                    'size' => 1,
//                ],
//            ],
//        ];

        //完善聚合查询语句-限定查询字段
//        if (isset($aggs_data['source'])) {
//            $data['aggs']['nested_outlet']['aggs']['brand_list']["aggs"]["brand_screen"]['aggs']['outlet_filter']['aggs']['outlet_group']['top_hits']['_source'] = $aggs_data['source']['_source'];
//        }

        //完善聚合查询排序语句，因可能存在多个排序语句，因此使用下标区分不同排序语句组
        foreach ($aggs_data['order']['brand_script_order'] as $order_k => $order_v) {
            $data['aggs']['nested_outlet']['aggs']['brand_list']["aggs"]["brand_screen"]['aggs']['outlet_filter']['terms']['order']["top_hit_order_{$order_k}"] = $order_v['top_hit_order'];
            $data['aggs']['nested_outlet']['aggs']['brand_list']["aggs"]["brand_screen"]['aggs']['outlet_filter']['aggs']["top_hit_order_{$order_k}"] = $order_v['top_hit'];
        }

        return $data;
    }

    /**
     * 组合商品聚合查询语句
     * @param array $aggs_data
     * @return array
     */
    protected function ParseAggsOutletGoods(array $aggs_data): array
    {
        $data['aggs']['nested_outlet']['aggs']['goods_list']['filter'] = $aggs_data['filter'] ?? (object)[];

        $data['aggs']['nested_outlet']['aggs']['goods_list']['aggs']['goods_screen'] = [
            //解决重复数据，分桶处理
            'terms' => [
                "field" => "outlet_list.outlet_id",
                "size" => 3000,
                "collect_mode" => "breadth_first",//改深度优先为广度优先
            ],
        ];
        //组合条件查询语句
        $data['aggs']['nested_outlet']['aggs']['goods_list']['aggs']['goods_screen']['aggs']['goods_filter']['reverse_nested'] = (object)[];

        $data['aggs']['nested_outlet']['aggs']['goods_list']['aggs']['goods_screen']['aggs']['goods_filter']['aggs']['goods_reverse'] = [
            "terms" => [
                "field" => "goods_id",
                'size' => 3,
            ],
        ];
//        //组合聚合查询语句
//        $data['aggs']['nested_outlet']['aggs']['goods_list']['aggs']['goods_screen']['aggs']['goods_filter']['aggs']['goods_reverse']['aggs'] = [
//            'outlet_group' => [
//                //数据查询
//                'top_hits' => [
//                    'size' => 1,
//                ],
//            ],
//        ];
//
//        //完善聚合查询语句-限定查询字段
//        if (isset($aggs_data['source'])) {
//            $data['aggs']['nested_outlet']['aggs']['goods_list']['aggs']['goods_screen']['aggs']['goods_filter']['aggs']['goods_reverse']['aggs']['outlet_group']['top_hits']['_source'] = $aggs_data['source']['_source'];
//        }

        //完善聚合查询排序语句，因可能存在多个排序语句，因此使用下标区分不同排序语句组
        foreach ($aggs_data['order']['goods_script_order'] as $order_k => $order_v) {
            $data['aggs']['nested_outlet']['aggs']['goods_list']['aggs']['goods_screen']['aggs']['goods_filter']['aggs']['goods_reverse']['terms']['order']["top_hit_order_{$order_k}"] = $order_v['top_hit_order'];
            $data['aggs']['nested_outlet']['aggs']['goods_list']['aggs']['goods_screen']['aggs']['goods_filter']['aggs']['goods_reverse']['aggs']["top_hit_order_{$order_k}"] = $order_v['top_hit'];
        }

        return $data;
    }

    /**
     * 商城信息聚合
     * @param array $aggs_data
     * @return array
     */
    protected function ParseAggsMall(array $aggs_data)
    {
//        $data['aggs']['mall']['filter'] = (object)null;
        $data['aggs']['mall']['filter'] = $aggs_data['filter'] ?? (object)[];
        $data['aggs']['mall']['aggs']['moduled'] = array(
            'terms' => array(
                'field' => 'moduled',
                'size' => 200,
            )
        );

        return $data;
    }

    /**
     * 品牌信息聚合
     * @param array $aggs_data
     * @return array
     */
    protected function ParseAggsBrand(array $aggs_data): array
    {
        $data['aggs']['brand']['filter'] = $aggs_data['filter'] ?? (object)[];
        $data['aggs']['brand']['aggs']['brand_id'] = array(
            'terms' => array(
                'field' => 'brand_id',
                'size' => 200,
            ),
            'aggs' => array(
                'brand_name' => array(
                    'terms' => array(
                        'field' => 'brand_name.raw',
                        'size' => 200,
                    ),
                    "aggs" => array(
                        "brand_list" => array(
                            "top_hits" => array(
                                "size" => 1,
                                "_source" => array(
                                    "includes" => "brand_logo",
                                )
                            )
                        )
                    )
                )
            )
        );

        //完善聚合查询排序语句，因可能存在多个排序语句，因此使用下标区分不同排序语句组
        foreach ($aggs_data['order']['brand_ordernum_script_order'] as $order_k => $order_v) {
            $data['aggs']['brand']['aggs']['brand_id']['terms']['order']["top_hit_order_{$order_k}"] = $order_v['top_hit_order'];
            $data['aggs']['brand']['aggs']['brand_id']['aggs']["top_hit_order_{$order_k}"] = $order_v['top_hit'];
        }

        return $data;
    }

    /**
     * 标签信息聚合
     * @param array $aggs_data
     * @return array
     */
    protected function ParseAggsCatList(array $aggs_data): array
    {
        $data['aggs']['cat_list']['filter'] = $aggs_data['filter'] ?? (object)[];
        $data['aggs']['cat_list']['aggs']['cat_level_1'] = array(
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

        return $data;
    }
}
