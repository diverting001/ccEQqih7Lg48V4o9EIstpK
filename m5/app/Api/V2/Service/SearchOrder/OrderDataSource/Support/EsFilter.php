<?php

namespace App\Api\V2\Service\SearchOrder\OrderDataSource\Support;

class EsFilter extends EsPredefined{
    // 本次查询关键词，依据正查，反查决定,正查 -> filter，反查 -> must_not
    private $field_name;

    // 对本次传入参数进行拆解组合后，新生成的参数合集
    private $filter_data;

    public function __construct()
    {
        parent::__construct();
    }

    // 解析请求参数中的filter
    public function ParseFilter(array $filter_data, $is_contain = 1): array
    {
        if (empty($filter_data)){
            return [];
        }

        // filter 必须匹配，不会根据结果算分，效率更高
        $this->field_name = $is_contain ? 'filter' : 'must_not';
        // must 必须匹配，会根据结果算分
        // $this->field_name = $is_contain ? 'must' : 'must_not';

        // 解析参数
        $this->RestructureParams($filter_data);

        // 处理参数
        $res = $this->BeforeFilter();

        return $res;
    }

    // 将传入的参数重构为实际所需的参数
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

    // 解析请求参数中的filter-预处理
    protected function BeforeFilter(): array
    {
        // 根据字段的条件关系去组装查询语句
        $filter_all = $this->filter_data;

        $filter_or['product_name'] = $filter_all['product_name'];
        $filter_or['order_id'] = $filter_all['order_id'];

        unset($filter_all['product_name'], $filter_all['order_id']);

        $and_res = $this->ParseFilterAnd($filter_all);

        $or_res = $this->ParseFilterOr($filter_or);

        // 将and_res - or_res 查询条件组合
        $filter_res = array_merge($and_res, $or_res);

        $query_fitler['query']['bool'][$this->field_name] = $filter_res;

        return $query_fitler;
    }

    protected function ParseFilterAnd($filter_data){
        $ret = [];

        foreach ($filter_data as $key => $val){
            if (is_array($val)){
                $ret[]['terms'][$key] = array_values($val);
            }else{
                $ret[]['term'][$key] = $val;
            }
        }

        return $ret;
    }

    protected function ParseFilterOr($filter_data){
        $ret = [];

        $term = [];

        // nested: product_list
        foreach ($filter_data as $key => $val){
            if ($key == 'product_name'){
                $filed_format = preg_match('/^[0-9]+$/', trim($val)) === 1 ? 'product_list.name.raw' : 'product_list.name';

                $term[]['nested'] = [
                    'path' => 'product_list',
                    'query' => [
                        'match' => [
                            $filed_format => $val
                        ]
                    ]
                ];

                continue;
            }

            $term[]['term'][$key] = $val;
        }

        $ret[]['bool']['should'] = $term;

        return $ret;
    }

    // 解析组合一级文档中嵌套对象属性的条件
    protected function ParseFilterNested($filter_data){
        $ret = [];

        foreach ($filter_data as $filter_key => $filter_value) {
            switch ($filter_key) {
                // 订单中的商品列表
                case "product_list":
                    $ret[$this->field_name][] = $this->ParseFilterNestedProductList($filter_value);
                    break;
            }
        }

        return $ret;
    }

    // 解析商品列表中的匹配字段
    protected function ParseFilterNestedProductList(array $filter_data){
        $child_filter = [];

        // 商品名称
        if (isset($filter_data['product_name']) && mb_strlen($filter_data['product_name'], 'utf-8') > 0){
            $child_filter['product_list.name'] = $filter_data['product_name'];
        }

        if ($child_filter) {
            return [
                'nested' => [
                    'path' => 'product_list',
                    'query' => ['match' => $child_filter],
                ],
            ];
        }

        return $child_filter;
    }
}
