<?php

namespace App\Api\V3\Service\Search\Datasource;

class GoodsOutletEs extends Es
{
    /** @var string 门店嵌套字段名称 */
    private $outlet_nested_name = 'outlet_list';
    /** @var string 门店排序顺序 */
    private $outlet_order_by = 'asc';
    /** @var string 商品排序顺序 */
    private $goods_order_by = 'asc';
    /** @var string 品牌排序顺序 */
    private $brand_order_by = 'asc';
    /** @var int 门店聚合查询数量限制 */
    private $outlet_size = 3000;
    /** @var int 商品聚合查询数量限制 */
    private $good_size = 5;
    /** @var int 获取开始下标 */
    private $start = 0;
    /** @var int 获取结束下标 */
    private $limit = 10;
    /** @var array 门店查询字段 */
    private $outlet_field = [
        "outlet_id",
        "outlet_name",
        "coordinate",
        "outlet_address",
        "outlet_logo",
    ];
    /** @var array 商品查询字段 */
    private $goods_field = [
        "goods_id",
        "name",
        "s_url",
    ];
    /** @var array 品牌查询字段 */
    private $brand_field = [
        "brand_id",
        "brand_name",
        "brand_logo",
    ];
    private $order_lat = 0;
    private $order_lon = 0;

    /**
     * 数据查询
     * @parameter $request_data 请求数据 $moduled 使用数据模块
     */
    public function Select($request_data)
    {
        $this->start = intval($request_data['start']) >= 0 ? intval($request_data['start']) : 0;
        $this->limit = intval($request_data['limit']) > 0 ? intval($request_data['limit']) : 10;
        //过滤
        $this->ParseOrder($request_data['order']);
        $this->ParseSource($request_data['aggs']);
        $this->ParseAggs($request_data['filter']);
//        return $this->query_data;
        if (!$this->query_data) {
            return [];
        }

        $index_name = $this->index;
        $index_type = $this->GetIndexTypeName($request_data['filter']['branch_id']);
        //执行结果
        $respnse_data = $this->Query('_search', $index_name, $index_type);
        if ($respnse_data === false || isset($respnse_data['error'])) {
            //执行失败,记录
            \Neigou\Logger::General('es_query_outlet_error',
                array('result' => json_encode($respnse_data), 'sender' => json_decode($this->query_data)));
            return false;
        } else {
            //聚合数据
            return $this->ParseSearchData($respnse_data);
        }
    }

    /**
     * 解析请求参数中的filter
     * @param $filter_data
     * @return void
     */
    public function ParseFilter($filter_data)
    {
    }

    /**
     * 解析请求参数中的aggs
     * @param $filter_data
     * @return false|void
     */
    public function ParseAggs($filter_data)
    {
        if (!($this->outlet_field || $this->brand_field || $this->goods_field)) {
            return false;
        }
        //处理经纬度参数
        $lat = 0;
        $lon = 0;
        $radius = 0;
        if (isset($filter_data['coordinate']) && !empty($filter_data['coordinate'])) {
            $coordinate = $filter_data['coordinate'];
            if ((isset($coordinate['lat']) && $coordinate['lat'])
                && (isset($coordinate['lon']) && $coordinate['lon'])
                && (isset($coordinate['radius']) && $coordinate['radius'])) {
                //radius 单位是m，查询时转换为千米
                $radius = round($coordinate['radius'] / 1000, 1);
                $lat = $coordinate['lat'];
                $lon = $coordinate['lon'];
            }
        }

        $father_filter = $this->ParamsAggsFirstFilter($filter_data);

        $child_filter = $this->ParamsAggsChildFilter($filter_data, $lat, $lon, $radius);

        //公共条件部分
        $aggs = [
            'size' => 0,//不查询父文档的内容
            'aggs' => [
                'goods_filter' => [
                    //父文档条件过滤
                    'filter' => $father_filter['filter'] ?? (object)[],
                    'aggs' => [
                        //进入子文档
                        'nested_outlet' => [
                            'nested' => ['path' => $this->outlet_nested_name,],
                        ],
                    ],
                ]
            ],
        ];

        //子文档过滤条件
        $aggs['aggs']['goods_filter']['aggs']['nested_outlet']['aggs'] = [
            'outlet_filter' => ['filter' => $child_filter['filter'] ?? (object)[],],
        ];

        //聚合详情
        $aggs_tmp = [];
        if ($this->outlet_field) {
            $aggs_tmp['outlet_list'] = $this->ParseAggsOutlet();
        }
        if ($this->brand_field) {
            $aggs_tmp['brand_list'] = $this->ParseAggsBrand();
        }
        if ($this->goods_field) {
            $aggs_tmp['goods_list'] = $this->ParseAggsGoods();
        }
        $aggs['aggs']['goods_filter']
        ['aggs']['nested_outlet']
        ['aggs']['outlet_filter']
        ['aggs'] = $aggs_tmp;
        $this->query_data = $aggs;
    }

    /**
     * 解析请求参数中的order
     * @param $order_data
     * @param $stages
     * @return void
     */
    public function ParseOrder($order_data, $stages = array())
    {

        //按照经纬度进行排序
        if (isset($order_data['coordinate']) && !empty($order_data['coordinate'])) {
            $coordinate = $order_data['coordinate'];
            if ((isset($coordinate['lat']) && $coordinate['lat'])
                && (isset($coordinate['lon']) && $coordinate['lon'])) {
                $this->order_lat = $coordinate['lat'];
                $this->order_lon = $coordinate['lon'];
            }
        }

        //门店数据排序顺序
        if (isset($order_data['distance']) && $order_data['distance']) {
            $this->outlet_order_by = $order_data['distance'];
        }

        //商品数据排序顺序
        if (isset($order_data['goods_id']) && $order_data['goods_id']) {
            $this->goods_order_by = $order_data['goods_id'];
        }

        //品牌数据排序顺序
        if (isset($order_data['brand_id']) && $order_data['brand_id']) {
            $this->brand_order_by = $order_data['brand_id'];
        }
    }

    /**
     * 控制每个聚合的查询内容
     * @param array $_source_data
     * @return false|void
     */
    public function ParseSource($_source_data)
    {

        if (isset($_source_data['outlet_field']) && $_source_data['outlet_field']) {
            $outlet_merge = array_intersect($this->outlet_field, $_source_data['outlet_field']);
            $outlet_tmp = $outlet_merge ?? $this->outlet_field;
            $this->outlet_field = [];
            foreach ($outlet_tmp as $outlet_tmp_v) {
                $this->outlet_field[] = $outlet_tmp_v;
            }
        } else {
            $this->outlet_field = [];
        }

        if (isset($_source_data['brand_field']) && $_source_data['brand_field']) {
            $this->brand_field = array_values(array_intersect($this->brand_field, $_source_data['brand_field']));
        } else {
            $this->brand_field = [];
        }

        if (isset($_source_data['goods_field']) && $_source_data['goods_field']) {
            $this->goods_field = array_values(array_intersect($this->goods_field, $_source_data['goods_field']));
        } else {
            $this->goods_field = [];
        }
    }

    /**
     * 组合父文档条件组合
     * @param array $filter_data
     * @return array
     */
    protected function ParamsAggsFirstFilter(array $filter_data): array
    {
        /***********************父文档条件组合*****************************/
        $father_filter = [];
        //商品模块筛选
        if (isset($filter_data['mall_id']) && !empty($filter_data['mall_id'])) {
            if (!is_array($filter_data['mall_id'])) {
                $mall_ids = explode(',', $filter_data['mall_id']);
            }
            $father_filter['filter']['bool']['must'][]['terms']['moduled'] = $mall_ids ?? $filter_data['mall_id'];
        }

        //商品ID筛选
        if (isset($filter_data['goods_ids']) && !empty($filter_data['goods_ids'])) {
            $father_filter['filter']['bool']['must'][]['term']['goods_id'] = $filter_data['goods_ids'];
        }

        //品牌ID筛选
        if (isset($filter_data['brand_id']) && !empty($filter_data['brand_id'])) {
            $father_filter['filter']['bool']['must'][]['terms']['brand_id'] = $filter_data['brand_id'];
        }

        //品牌名字筛选
        if (isset($filter_data['brand_name']) && !empty($filter_data['brand_name'])) {
            $father_filter['filter']['bool']['must'][]['term']['brand_name.raw'] = $filter_data['brand_name'];
        }
        /***********************父文档条件组合*****************************/
        return $father_filter;
    }

    /**
     * 获取嵌套子文档条件组合
     * @param array $filter_data
     * @param float $lat
     * @param float $lon
     * @param float $radius
     * @return array
     */
    public function ParamsAggsChildFilter(array $filter_data, float $lat, float $lon, float $radius): array
    {
        /***********************嵌套子文档条件组合*****************************/
        $child_filter = [];
        //市地址ID筛选
        if (isset($filter_data['city_id']) && !empty($filter_data['city_id'])) {
            $child_filter['filter']['bool']['must'][]['term'][$this->outlet_nested_name . '.city_id'] = $filter_data['city_id'];
        }

        //区县地址ID筛选
        if (isset($filter_data['area_id']) && !empty($filter_data['area_id'])) {
            $child_filter['filter']['bool']['must'][]['term'][$this->outlet_nested_name . '.area_id'] = $filter_data['area_id'];
        }

        //门店ID筛选
        if (isset($filter_data['outlet_id']) && !empty($filter_data['outlet_id'])) {
            $child_filter['filter']['bool']['must'][]['term'][$this->outlet_nested_name . '.outlet_id'] = $filter_data['outlet_id'];
        }

        //经纬度范围筛选
        //isset($filter_data['distance_sort']) && $filter_data['distance_sort']
        if ($lat && $lon && $radius) {
            $child_filter['filter']['bool']['must'][]['geo_distance'] = [
                'distance' => $radius . 'km',
                $this->outlet_nested_name . '.coordinate' => [
                    'lat' => $lat,
                    'lon' => $lon,
                ],
            ];
        }
        /***********************嵌套子文档条件组合*****************************/
        return $child_filter;
    }

    /**
     * 解析serach中返回 的数据
     */
    public function ParseSearchData($data)
    {
        //保留指定值
        $outlet_field = array_fill_keys($this->outlet_field, '');
        $brand_field = array_fill_keys($this->brand_field, '');
        $goods_field = array_fill_keys($this->goods_field, '');

        $new_data = array(
            'outlet_list' => [],
            'brand_list' => [],
            'goods_list' => [],
            'count' => 0,
        );
        $buckets_array = $data['aggregations']['goods_filter']['nested_outlet']['outlet_filter'];

        //处理门店数据
        $outlet_list_data = $buckets_array['outlet_list'] ?? ['buckets' => []];
        $i = 0;
        foreach ($outlet_list_data['buckets'] as $outlet_v) {
            $new_data['outlet_list'][$i] = array_merge(
                $outlet_field,
                $outlet_v['outlet_group']['hits']['hits'][0]['_source']['outlet_list']
            );
            if ($this->order_lat && $this->order_lon) {
                $new_data['outlet_list'][$i]['_distance'] = round($outlet_v['top_hit']['value'], 3);
            } else {
                $new_data['outlet_list'][$i]['_distance'] = 0;
            }
            $i++;
        }
        if ($new_data['outlet_list']) {
            $new_data['count'] = $i;
            $new_data['outlet_list'] = array_slice($new_data['outlet_list'], $this->start, $this->limit);
        }

        //处理品牌数据
        $brand_list_data = $buckets_array['brand_list'] ?? ['brand_screen' => ['buckets' => []]];
        foreach ($brand_list_data['brand_screen']['buckets'] as $brand_v) {
            $brand_v_tmp = $brand_v['outlet_group']['hits']['hits'][0]['_source'];
            if (isset($brand_v_tmp[''])) {
                unset($brand_v_tmp['']);
            }
            $new_data['brand_list'][] = array_merge($brand_field, $brand_v_tmp);
        }

        //处理商品数据
        $goods_list_data = $buckets_array['goods_list'] ?? ['buckets' => []];
        $j = 0;
        foreach ($goods_list_data['buckets'] as $goods_v) {
            $goods_array = $goods_v['goods_screen']['goods_reverse']['buckets'];
            $new_data['goods_list'][$j] = [
                'outlet_id' => $goods_v['key']
            ];
            foreach ($goods_array as $goods_array_v) {
                $goods_array_v_tmp = $goods_array_v['outlet_group']['hits']['hits'][0]['_source'];
                if (isset($goods_array_v_tmp[''])) {
                    unset($goods_array_v_tmp['']);
                }
                $new_data['goods_list'][$j]['list'][] = array_merge($goods_field, $goods_array_v_tmp);
            }
            $j++;
        }
        return $new_data;
    }

    /**
     * 直接指定Es的预定查询语句
     * @param array $query_data
     * @return void
     */
    public function SetQueryData(array $query_data)
    {
        $this->query_data = $query_data;
    }

    /**
     * 组合门店聚合查询语句
     * @param float $lat
     * @param float $lon
     * @return array[]
     */
    protected function ParseAggsOutlet(): array
    {
        $outlet_field = [];
        foreach ($this->outlet_field as $outlet_field_v) {
            $outlet_field[] = $this->outlet_nested_name . '.' . $outlet_field_v;
        }

        $data = [
            //解决重复数据，分桶处理
            'terms' => [
                'field' => $this->outlet_nested_name . '.outlet_id',
                'order' => ['top_hit' => $this->outlet_order_by],
                'size' => $this->outlet_size,
                "collect_mode" => "breadth_first",//改深度优先为广度优先
            ],
            'aggs' => [
                'outlet_group' => [
                    //数据查询
                    'top_hits' => [
                        'size' => 1,
                        '_source' => [
                            'includes' => $outlet_field,
                        ],
                    ],
                ],
                //排序字段，有经纬度按照由近到远的顺序排序，没有经纬度，根据门店ID排序
                'top_hit' => [
//                    'min' => [
//                        'script' => $top_hit_script,
//                    ],
                ],
            ],
        ];
        //组合排序条件
        if ($this->order_lat && $this->order_lon) {
            $data['aggs']['top_hit']['min']['script'] = [
                "params" => [
                    "lat" => $this->order_lat,
                    "lon" => $this->order_lon
                ],
                "inline" => "doc['outlet_list.coordinate'].arcDistance(params.lat, params.lon)/1000"
            ];
        } else {
            $data['aggs']['top_hit']['max']['script'] = [
                "inline" => "doc['outlet_list.outlet_id']"
            ];
        }

        return $data;
    }

    /**
     * 组合品牌聚合查询语句
     * @return array
     */
    protected function ParseAggsBrand(): array
    {
        return [
            "reverse_nested" => (object)[],
            "aggs" => [
                "brand_screen" => [
                    "terms" => [
                        "field" => "brand_id",
                        'order' => ['top_hit' => $this->brand_order_by],
                    ],
                    "aggs" => [
                        "outlet_group" => [
                            "top_hits" => [
                                "size" => 1,
                                "_source" => [
                                    "includes" => $this->brand_field
                                ]
                            ]
                        ],
                        "top_hit" => [
                            "max" => [
                                "script" => [
                                    "inline" => "doc['brand_id']"
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * 组合商品聚合查询语句
     * @return array
     */
    protected function ParseAggsGoods(): array
    {
        return [
            "terms" => [
                "field" => "outlet_list.outlet_id"
            ],
            "aggs" => [
                "goods_screen" => [
                    "reverse_nested" => (object)[],
                    "aggs" => [
                        "goods_reverse" => [
                            "terms" => [
                                "field" => "goods_id",
                                'order' => ['top_hit' => $this->goods_order_by],
                                'size' => $this->good_size,
                            ],
                            "aggs" => [
                                "outlet_group" => [
                                    "top_hits" => [
                                        "size" => 1,
                                        "_source" => [
                                            "includes" => $this->goods_field
                                        ]
                                    ]
                                ],
                                "top_hit" => [
                                    "max" => [
                                        "script" => [
                                            "inline" => "doc['goods_id']"
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }
}
