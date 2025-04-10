<?php

namespace App\Api\V6\Service\Search\Datasource\Support;

use App\Api\Model\Search\BusinessKeywordModel;

/**
 * Es排序处理
 */
class EsOrder extends EsPredefined
{

    /**
     * 处理索引一级元素排序需要
     * @param array $order_data
     * @return array
     */
    public function ParseOrder(array $order_data): array
    {
        foreach ($order_data as $order_key => $order_val) {
            $key = $order_val['field'] ?? $order_key;
            switch ($key) {
                case 'weight' :
                    $this->query_data['sort']['weight']['order'] = $order_val['by'];
                    break;
                case 'brand_id' :
                    $this->query_data['sort']['brand_id']['order'] = $order_val['by'];
                    break;
//                    case 'goods_id' :
//                        $this->query_data['sort']['goods_id']['order'] = $order_val['by'];
//                        break;
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
                    $this->GetPriceOrder('price', $order_val['by']);
                    break;
                case 'point_price' :
                    $this->GetPriceOrder('point_price', $order_val['by']);
                    break;
                case 'keyword':
                    if($order_val['value']){
                        $this->sortByKeyword($order_val['value']);
                    }
                    break;
                case 'weight_factor':
                    if($order_val['value']) {
                        $this->sortGoodsWeightFactor($order_val['value']);
                    }
                    break;
                case 'marketable':
                    $this->query_data['sort']['marketable']['order'] = 'desc';
                    break;
                case 'default':
                    $this->query_data['sort']['_score']['order'] = 'desc';
                    break;
            }
            // 预定义字段排序
            if (in_array($key, [
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
                $this->query_data['sort'][$key]['order'] = $order_val['by'];
            }
        }

        //商品的默认排序
        $this->query_data['sort']['_score']['order'] = 'desc';
        $this->query_data['sort']['goods_id']['order'] = 'desc';
        return $this->query_data;
    }

    /**
     * 加工处理聚合查询中指定的排序
     * @param array $order_data
     * @return array
     */
    public function ParseAggsScriptOrder(array $order_data)
    {
        $order_script_array = [];

        //基于传值类型组合桶排序语句 和 桶排序顺序
        $i = 0;
        foreach ($order_data as $order_data_v) {
            //只有规定好的排序filed进行处理
            if (!array_key_exists($order_data_v['field'], $this->aggs_script_order_field)) {
                continue;
            }
            $key = $this->aggs_script_order_field[$order_data_v['field']];
            switch ($order_data_v['type']) {
                case 'geo_point':
                    //进行经纬度排序处理
                    $order_script_array[$key][$i]['top_hit'] = [
                        "min" => [
                            "script" => [
                                "inline" => "doc['{$order_data_v['field']}'].arcDistance(params.lat, params.lon)/1000",
                                "params" => [
                                    "lat" => (float)$order_data_v['params']['lat'],
                                    "lon" => (float)$order_data_v['params']['lon']
                                ],
                            ],
                        ],
                    ];
                    $order_script_array[$key][$i]['top_hit_order'] = $order_data_v['by'];
                    break;
                case 'int':
                case 'string':
                default:
                    //进行一般排序处理
                    $order_script_array[$key][$i]['top_hit'] = [
                        "min" => [
                            "script" => [
                                "inline" => "doc['{$order_data_v['field']}']",
                            ],
                        ],
                    ];
                    $order_script_array[$key][$i]['top_hit_order'] = $order_data_v['by'];
                    break;
            }
            $order_script_array[$key][$i]['type'] = $order_data_v['type'];
            $i++;
        }
        return $order_script_array;
    }

    /*
     * @todo 获取价格排序
     */
    private function GetPriceOrder($order_filed = 'price', $order_by = 'asc')
    {
        $this->query_data['sort'][$order_filed]['order'] = $order_by;
        return true;
    }

    /**
     * 根据商品名称进行排序
     * @param $keyword
     * @return void
     */
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

    /**
     * 根据权重进行排序
     * @param $weight_factor
     * @return void
     */
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
}
