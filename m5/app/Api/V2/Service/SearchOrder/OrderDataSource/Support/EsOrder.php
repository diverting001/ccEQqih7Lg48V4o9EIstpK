<?php

namespace App\Api\V2\Service\SearchOrder\OrderDataSource\Support;
/**
 * Class EsOrder
 * @package App\Api\V2\Service\SearchOrder\OrderDataSource\Support
 * 解析请求的排序字段
 */
class EsOrder extends EsPredefined{
    public function ParseOrder(array $order_data): array
    {
        $sort = [];

        foreach ($order_data as $key => $val){
            $sort[$val['field']] = [
                'order' => $val['by']
            ];
        }

        // 如果没有，则使用默认排序
        if (empty($sort)){
            $sort['create_time'] = [
                'order' => 'desc'
            ];
        }

        $res_sort['sort'] = $sort;

        return $res_sort;
    }
}
