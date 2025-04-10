<?php

namespace App\Api\V5\Service\Search\Datasource\Support;

/**
 * 指定查询字段
 */
class EsSource extends EsPredefined
{
    /**
     * 门店聚合允许查询的字段
     * @var string[]
     */
    public static $outlet_list_field = [
        "outlet_list.outlet_id",
        "outlet_list.outlet_name",
        "outlet_list.coordinate",
        "outlet_list.outlet_address",
        "outlet_list.outlet_logo",
    ];

    /**
     * 门店下商品聚合允许查询的字段
     * @var string[]
     */
    public static $outlet_goods_field = [
        "goods_id",
        "name",
        "s_url",
    ];

    /**
     * 门店下品牌聚合允许查询的字段
     * @var string[]
     */
    public static $outlet_brand_field = [
        "brand_id",
        "brand_name",
        "brand_logo",
    ];

    /**
     * 商品运行查询的字段
     * @var string[]
     */
    public static $goods_field = [
        'goods_type',
        'product_id',
        'marketable',
        'product_bn',
        'name',
        'bn',
        'goods_id',
        'brand_id',
        'brand_name',
        's_url',
        'm_url',
        'l_url',
        'last_modify',
        'is_soldout',
        'products',
        'cat_level_1',
        'cat_level_2',
        'cat_level_3',
        'shipping_type',
        "pop_shop_id",
        "disabled",
    ];

    /**
     * 根据传入的type，判断是否需要进行参数过滤
     * @param $source_data
     * @param $type
     * @return array
     */
    public function ParseSource($source_data, $type): array
    {
        $query_data = [];
        // 过滤类型
        if ($type) {
            $my_source_data = [];
            //获取在允许范围内的字段
            switch ($type) {
                case "outlet|outlet_list":
                    $tmp = array_values(array_intersect(self::$outlet_list_field, $source_data));
                    $my_source_data = !empty($tmp) ? $tmp : self::$outlet_list_field;
                    break;
                case "outlet|goods_list":
                    $tmp = array_values(array_intersect(self::$outlet_goods_field, $source_data));
                    $my_source_data = !empty($tmp) ? $tmp : self::$outlet_goods_field;
                    break;
                case "outlet|brand_list":
                    $tmp = array_values(array_intersect(self::$outlet_brand_field, $source_data));
                    $my_source_data = !empty($tmp) ? $tmp : self::$outlet_brand_field;
                    break;
                case "goods":
                    $tmp = array_values(array_intersect(self::$goods_field, $source_data));
                    $my_source_data = !empty($tmp) ? $tmp : self::$goods_field;
                    break;
            }
            $query_data['_source'] = [
                'includes' => $my_source_data,
            ];
        }
        return $query_data;
    }
}
