<?php

namespace App\Api\V5\Service\Search\Datasource\Support;

/**
 * 查询条件组合
 */
class EsFilter extends EsPredefined
{
    /** @var array $filter_data 对本次传入参数进行拆解组合后，新生成的参数合集 */
    private $filter_data;

    /**  @var string $field_name 本次查询关键词，依据正查，反差决定,正查 -> filter，反查 -> must_not */
    private $field_name;

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 将传入的参数重构为实际所需的参数
     * @param array $filter_data
     */
    public function RestructureParams(array $filter_data)
    {
        $filter = [];
        foreach ($filter_data as $k => $v) {
            //解析条件语句，将key中的|分隔关系转换为数组关系
            if (!strpos($k, '|')) {
                $filter[$k] = $v;
                continue;
            }
            $tmp = explode('|', $k);
            //按照分割的字符顺序，重组对应的多维数组
            $child = $v;
            $res = [];
            //从最后一位开始构建新数组
            while ($pop = array_pop($tmp)) {
                $res = [$pop => $child];
                $child = $res;
            }
            //将新解析的元素与已有的元素对比合并同类项【基于相同key】
            $filter = array_merge_recursive($filter, $res);
        }
        $this->filter_data = $filter;
    }

    /**
     * 解析请求参数中的filter
     */
    public function ParseFilter(array $filter_data, $is_contain = 1): array
    {
        $this->field_name = $is_contain ? 'filter' : 'must_not';
        //解析参数
        $this->RestructureParams($filter_data);
        //处理参数
        $res = $this->BeforeFilter();
        list($normal_res, $object_res, $nested_res) = $res;

        //根据返回的进行合并处理
        if (isset($normal_res[$this->field_name])
            || isset($object_res[$this->field_name])
            || isset($nested_res['filter'])) {
            $query_data['query']['bool'][$this->field_name] = array_merge(
                $normal_res[$this->field_name] ?? [],
                $object_res[$this->field_name] ?? [],
                $nested_res[$this->field_name] ?? []
            );
        }
        if (isset($normal_res['must']) && $normal_res['must']) {
            $query_data['query']['bool']['must'] = $normal_res['must'];
        }
        return $query_data ?? [];
    }

    /**
     * 解析聚合请求参数中的filter,接收正反查参数
     * @param array $filter_data 正查参数
     * @param array $filter_not_data 反差参数
     * @return array|object
     */
    public function ParseAggsFilter(array $filter_data, array $filter_not_data)
    {
        if ($filter_data) {
            $filter = $this->ParseAggsFilterSpecific($filter_data);
        }
        if ($filter_not_data) {
            $filter_not = $this->ParseAggsFilterSpecific($filter_not_data, 0);
        }
        //单独处理正查和反差对应的聚合过滤条件
        $filters = [];
        if (isset($filter['filter']) && $filter['filter']) {
            $filters = array_merge($filters, $filter['filter']['bool']);
        }
        if (isset($filter_not['must_not']) && $filter_not['must_not']) {
            $filters = array_merge($filters, $filter_not['must_not']['bool']);
        }
        if ($filters) {
            $filter_array['bool'] = $filters;//bool要在这里提前给，否则aggs组合的语句有问题
        } else {
            $filter_array = (object)[];//没有条件的直接返回对象
        }
        return $filter_array;
    }

    /**
     * 解析聚合请求参数中的filter
     * @param array $filter_data
     * @param int $is_contain 是否为包含查询 0反差【不包含】 1正查【包含】
     * @return array
     */
    protected function ParseAggsFilterSpecific(array $filter_data, $is_contain = 1): array
    {
        $this->field_name = $is_contain ? 'filter' : 'must_not';
        $this->RestructureParams($filter_data);
        $res = $this->BeforeFilter();
        list($normal_res, $object_res, $nested_res) = $res;

        //组合聚合查询子条件组合语句
        $nested_array = [];
        if (isset($nested_res[$this->field_name]) && $nested_res[$this->field_name]) {
            foreach ($nested_res[$this->field_name] as $nested) {
                $nested_array = $nested_array + $nested['nested']['query']['bool'][$this->field_name]['bool']['must'];
            }
        }

        if (isset($normal_res[$this->field_name])
            || isset($object_res[$this->field_name])
            || $nested_array) {
            //对多层过滤语句进行组合，根据正查和反差进行单独处理
            if ($is_contain) {
                $name = 'must';
            } else {
                $name = 'must_not';
            }
            $query_data[$this->field_name]['bool'][$name] = array_merge(
                $normal_res[$this->field_name] ?? [],
                $object_res[$this->field_name] ?? [],
                $nested_array
            );
        }

        //$normal_res, $object_res, $nested_res,
        return $query_data ?? [];
    }

    /**
     * 解析请求参数中的filter-预处理
     * @return array
     */
    protected function BeforeFilter(): array
    {
        $object_field = ['cat_level_1', 'cat_level_2', 'cat_level_3', 'compare', 'province'];
        $nested_field = ['cats', 'outlet_list'];
        $normal = [];
        $object = [];
        $nested = [];
        foreach ($this->filter_data as $key => $value) {
            //判断key是否为object，nested
            if (in_array($key, $object_field)) {
                $object[$key] = $value;
            } elseif (in_array($key, $nested_field)) {
                $nested[$key] = $value;
            } else {
                $normal[$key] = $value;
            }
        }
        $normal_res = $this->ParseFilterNormal($normal);
        $object_res = $this->ParseFilterObject($object);
        $nested_res = $this->ParseFilterNested($nested);
        return [
            $normal_res ?? [],
            $object_res ?? [],
            $nested_res ?? [],
        ];
    }

    /**
     * 解析组合一级文档中普通属性的条件
     * @param $filter_data
     * @return array
     */
    protected function ParseFilterNormal($filter_data)
    {
        $normal_filter = [];
        //商品模块筛选
        if (isset($filter_data['mall_id']) && !empty($filter_data['mall_id'])) {
            $normal_filter[$this->field_name][]['terms']['moduled'] = $filter_data['mall_id'];
        }
        //keyword搜索
        if (isset($filter_data['keyword']) && !empty($filter_data['keyword'])) {
            $keyword_search_filed = config('neigou.ESSEARCH_GOODS_KEYWORD_SEARCH_FILED') ?: 'name';
            $keyword['bool']['should'][] = array(
                'match_phrase' => array(
                    $keyword_search_filed => array(
                        'query' => $filter_data['keyword'],
                        'analyzer' => 'search_analyzer',
                        'slop' => 40,
                        'boost' => 10
                    ),
                ),
            );
            $keyword['bool']['should'][] = array(
                'match_phrase' => array(
                    $keyword_search_filed => array(
                        'query' => $filter_data['keyword'],
                        'analyzer' => 'standard',
                        'slop' => 40,
                    ),
                ),
            );
            $normal_filter['must'][] = $keyword;
        }
        //商品库存筛选
        if (isset($filter_data['store']) && !empty($filter_data['store'])) {
            if (!empty($filter_data['store']['gt']) || $filter_data['store']['gt'] === 0) {
                $normal_filter[$this->field_name][]['range']['store']['gt'] = floatval($filter_data['store']['gt']);
            }
            if (!empty($filter_data['store']['lte']) || $filter_data['store']['lte'] === 0) {
                $normal_filter[$this->field_name][]['range']['store']['lte'] = floatval($filter_data['store']['lte']);
            }
        }
        //价格筛选
        if (isset($filter_data['price']) && !empty($filter_data['price'])) {
            $price_filter = $this->GetPriceFilter($filter_data['price']['gte'], $filter_data['price']['lte']);
            if (!empty($price_filter)) {
                $normal_filter[$this->field_name][] = $price_filter;
            }
        }
        //积点价格筛选
        if (isset($filter_data['point_price']) && !empty($filter_data['point_price'])) {
            $normal_filter[$this->field_name][]['range']['point_price'] = array(
                'gte' => $filter_data['point_price']['gte'],
                'lte' => $filter_data['point_price']['lte'],
            );
        }

        //上下架筛选
        if (isset($filter_data['marketable']) && !empty($filter_data['marketable'])) {
            $normal_filter[$this->field_name][]['term']['marketable'] = $filter_data['marketable'];
        }

        //品牌筛选
        if (isset($filter_data['brand_id']) && !empty($filter_data['brand_id'])) {
            if (is_int($filter_data['brand_id'])) {
                $normal_filter[$this->field_name][]['term']['brand_id'] = $filter_data['brand_id'];
            } elseif (is_array($filter_data['brand_id'])) {
                if (count($filter_data['brand_id']) == 1) {
                    $normal_filter[$this->field_name][]['term']['brand_id'] = intval($filter_data['brand_id'][0]);
                } else {
                    $normal_filter[$this->field_name][]['terms']['brand_id'] = $filter_data['brand_id'];
                }
            }
        }
        if (isset($filter_data['brand_name']) && !empty($filter_data['brand_name'])) {
            $normal_filter[$this->field_name][]['term']['brand_name.raw'] = $filter_data['brand_name'];
        }

        //所属国家
        if (isset($filter_data['country_id']) && !empty($filter_data['country_id'])) {
            $normal_filter[$this->field_name][]['term']['country_id'] = intval($filter_data['country_id']);
        }
        //所属国家馆
        if (isset($filter_data['pavilion_id']) && !empty($filter_data['pavilion_id'])) {
            $normal_filter[$this->field_name][]['term']['pavilion_id'] = intval($filter_data['pavilion_id']);
        }

        //vmall虚拟分类筛选
        if (isset($filter_data['vmall_cat_id']) && !empty($filter_data['vmall_cat_id'])) {
            $normal_filter[$this->field_name][]['term']['vmall_cat_id'] = intval($filter_data['vmall_cat_id']);
        }

        // goods_id筛选
        if (isset($filter_data['goods_ids']) && $filter_data['goods_ids']) {
            $normal_filter[$this->field_name][]['terms']['_id'] = $filter_data['goods_ids'];
        }

        //使用 OR 方式组合查询条件，查询未配置区域化或区域化省为空的数据，保证查询结果
        $region_exists_flag = false;
        if (isset($filter_data['region_exists_filed']) && $filter_data['region_exists_filed'] == 1) {
            $region_exists_flag = true;
        }

        //商品可见范围省级条件筛选
        $province_flag = false;
        if (isset($filter_data['region_visible_province']) && $filter_data['region_visible_province']) {
            if (!$region_exists_flag) {
                $normal_filter[$this->field_name][]['terms']['region_visible_province'] = (array)$filter_data['region_visible_province'];
            } else {
                $region_array['bool']['filter'][] = array(
                    'terms' => array(
                        'region_visible_province' => (array)$filter_data['region_visible_province'],
                    ),
                );
                $province_flag = true;
            }
        }

        //商品可见范围市级条件筛选
        $city_flag = false;
        if (isset($filter_data['region_visible_city']) && $filter_data['region_visible_city']) {
            if (!$region_exists_flag) {
                $normal_filter[$this->field_name][]['terms']['region_visible_city'] = (array)$filter_data['region_visible_city'];
            } else {
                $region_array['bool']['filter'][] = array(
                    'terms' => array(
                        'region_visible_city' => (array)$filter_data['region_visible_city'],
                    ),
                );
                $city_flag = true;
            }
        }

        //商品可见范围区/县级条件筛选
        $area_flag = false;
        if (isset($filter_data['region_visible_area']) && $filter_data['region_visible_area']) {
            if (!$region_exists_flag) {
                $normal_filter[$this->field_name][]['terms']['region_visible_area'] = (array)$filter_data['region_visible_area'];
            } else {
                $region_array['bool']['filter'][] = array(
                    'terms' => array(
                        'region_visible_area' => (array)$filter_data['region_visible_area'],
                    ),
                );
                $area_flag = true;
            }
        }

        //有区域化条件则查询时，同时将没有配置区域化信息的全部数据查出来
        //使用 OR 方式组合查询条件，保证查询结果
        if (($province_flag || $city_flag || $area_flag)) {
            isset($region_array) && $region['bool']['should'][] = $region_array;
            if ($region_exists_flag) {
                $region['bool']['should'][]['bool'] = array(
                    'must_not' => array(
                        'exists' => array(
                            'field' => 'region_visible_province',
                        ),
                    ),
                );
            }
            isset($region) && $normal_filter['must'][] = $region;
        }

        // 商品类型条件筛选
        if (isset($filter_data['goods_type']) && $filter_data['goods_type']) {
            $normal_filter[$this->field_name][]['term']['goods_type'] = $filter_data['goods_type'];
        }

        // 配送类型条件筛选
        if (isset($filter_data['shipping_type']) && $filter_data['shipping_type']) {
            $normal_filter[$this->field_name][]['term']['shipping_type'] = $filter_data['shipping_type'];
        }

        //供应商ID条件筛选
        if (isset($filter_data['pop_shop_id']) && $filter_data['pop_shop_id']) {
            $normal_filter[$this->field_name][]['terms']['pop_shop_id'] = $filter_data['pop_shop_id'];
        }

        //bn条件筛选
        if (isset($filter_data['bn']) && $filter_data['bn']) {
            $normal_filter[$this->field_name][]['terms']['bn'] = $filter_data['bn'];
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
                $normal_filter[$this->field_name][]['terms'][$es_field] = $filter_data[$es_field];
            }
        }
        return $normal_filter;
    }

    /**
     * 解析组合一级文档中一般对象属性的条件
     * @param $filter_data
     * @return array
     */
    protected function ParseFilterObject($filter_data)
    {
        $object_filter = [];
        //分类筛选
        if (isset($filter_data['cat_level_1']) && !empty($filter_data['cat_level_1'])) {
            if (!empty($filter_data['cat_level_1']['cat_id'])) {
                $object_filter[$this->field_name][]['term']['cat_level_1.cat_id'] = intval($filter_data['cat_level_1']['cat_id']);
            }
            if (!empty($filter_data['cat_level_1']['cat_name'])) {
                $object_filter[$this->field_name][]['term']['cat_level_1.cat_name.raw'] = $filter_data['cat_level_1']['cat_name'];
            }
        }
        if (isset($filter_data['cat_level_2']) && !empty($filter_data['cat_level_2'])) {
            if (!empty($filter_data['cat_level_2']['cat_id'])) {
                $object_filter[$this->field_name][]['term']['cat_level_2.cat_id'] = intval($filter_data['cat_level_2']['cat_id']);
            }
            if (!empty($filter_data['cat_level_2']['cat_name'])) {
                $object_filter[$this->field_name][]['term']['cat_level_2.cat_name.raw'] = $filter_data['cat_level_2']['cat_name'];
            }
        }
        if (isset($filter_data['cat_level_3']) && !empty($filter_data['cat_level_3'])) {
            if (!empty($filter_data['cat_level_3']['cat_id'])) {
                $object_filter[$this->field_name][]['term']['cat_level_3.cat_id'] = intval($filter_data['cat_level_3']['cat_id']);
            }
            if (!empty($filter_data['cat_level_3']['cat_name'])) {
                $object_filter[$this->field_name][]['term']['cat_level_3.cat_name.raw'] = $filter_data['cat_level_3']['cat_name'];
            }
        }

        //地区筛选
        if (isset($filter_data['province']) && !empty($filter_data['province'])) {
            $province_key = $this->province[$filter_data['province']];
            if (!empty($province_key)) {
                $object_filter[$this->field_name][]['term']['province_stock.' . $province_key] = 1;
            }
        }

        return $object_filter;
    }

    /**
     * 解析组合一级文档中嵌套对象属性的条件
     * @param $filter_data
     * @return array
     */
    protected function ParseFilterNested($filter_data)
    {
        $ret = [];
        foreach ($filter_data as $filter_key => $filter_value) {
            switch ($filter_key) {
                //门店
                case "outlet_list":
                    $ret[$this->field_name][] = $this->ParseFilterNestedOutlet($filter_value);
                    break;
                case "cats":
                    $ret[$this->field_name][] = $this->ParseFilterNestedCats($filter_value);
                    break;
            }
        }
        return $ret;
    }

    /**
     * 专项-解析处理商品中嵌套文档-门店的条件
     * @param array $filter_data
     * @return array|array[]
     */
    protected function ParseFilterNestedOutlet(array $filter_data)
    {
        $child_filter = [];
        //处理经纬度参数
        $lat = 0;
        $lon = 0;
        $radius = 0;
        if (isset($filter_data['distance']) && $filter_data['distance']) {
            $distance = $filter_data['distance'];
            if (isset($distance['coordinate']) && $distance['coordinate']) {
                $coordinate = $distance['coordinate'];
                if ((isset($coordinate['lat']) && $coordinate['lat']) && (isset($coordinate['lon']) && $coordinate['lon'])) {
                    //radius 单位是m，查询时转换为千米
                    $distance['radius'] = (int)$distance['radius'];
                    $radius = round($distance['radius'] / 1000, 2);
                    $lat = (float)$coordinate['lat'];
                    $lon = (float)$coordinate['lon'];
                }
            }
        }

        //经纬度范围筛选
        if ($lat && $lon && $radius) {
            $child_filter[$this->field_name]['bool']['must'][]['geo_distance'] = [
                'distance' => $radius . 'km',
                'outlet_list.coordinate' => [
                    'lat' => $lat,
                    'lon' => $lon,
                ],
            ];
        }

        //市地址ID筛选
        if (isset($filter_data['city_id']) && (int)$filter_data['city_id'] > 0) {
            $child_filter[$this->field_name]['bool']['must'][]['term']['outlet_list.city_id'] = (int)$filter_data['city_id'];
        }

        //区县地址ID筛选
        if (isset($filter_data['area_id']) && (int)$filter_data['area_id'] > 0) {
            $child_filter[$this->field_name]['bool']['must'][]['term']['outlet_list.area_id'] = (int)$filter_data['area_id'];
        }

        //门店ID筛选
        if (isset($filter_data['outlet_id']) && (int)$filter_data['outlet_id'] > 0) {
            $child_filter[$this->field_name]['bool']['must'][]['terms']['outlet_list.outlet_id'] = $filter_data['outlet_id'];
        }

        if ($child_filter) {
            return [
                'nested' => [
                    'path' => 'outlet_list',
                    'query' => ['bool' => $child_filter],
                ],
            ];
        }
        return $child_filter;
    }

    /**
     * 专项-解析处理商品中嵌套文档-分类筛选的条件
     * @param array $filter_data
     * @return array|array[]
     */
    protected function ParseFilterNestedCats(array $filter_data)
    {
        $child_filter = [];
        if ($child_filter) {
            return [
                'nested' => [
                    'path' => 'cats',
                    'query' => ['bool' => $child_filter],
                ],
            ];
        }
        return $child_filter;
    }

    /**
     * 获取价格筛选
     */
    protected function GetPriceFilter($gte_price, $lte_price): array
    {
        $price_filter['range']['price'] = array(
            array(
                'gte' => $gte_price,
                'lte' => $lte_price,
            )
        );
        return $price_filter;
    }

}
