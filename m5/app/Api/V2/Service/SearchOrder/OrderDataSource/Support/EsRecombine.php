<?php

namespace App\Api\V2\Service\SearchOrder\OrderDataSource\Support;
/**
 * 解析Es查询结果
 */
class EsRecombine extends EsPredefined{
    public function ParseSearchData(array $response_data){
        $search_res = [
            'order_list' => [],
            'order_count' => 0
        ];

        if (isset($response_data['hits']['hits']) && $response_data['hits']['total'] > 0){
            $search_res['order_list'] = $this->ParseEsQueryData($response_data['hits']['hits']);
            // hits-total 为匹配条件的总数
            $search_res['order_count'] = $response_data['hits']['total'];
        }

        return $search_res;
    }

    // 解析ES返回的数据，提取订单列表数据
    protected function ParseEsQueryData(array $ES_Data){
        $after_parse = [];

        foreach ($ES_Data as $val){
            $after_parse[] = $val['_source'];
        }

        return $after_parse;
    }
}
