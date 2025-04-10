<?php

namespace App\Api\V6\Service\Search\Datasource;

use App\Api\V6\Service\Search\Datasource\Support\EsPredefined;
use App\Api\V6\Service\Search\Elasticsearchcreateindex;

/**
 * elasticsearch查询入口
 */
class Es extends EsPredefined
{

    /*
     * @todo 数据查询【商品查询主逻辑】
     * @parameter $request_data 请求数据 $moduled 使用数据模块
     */
    public function Select($request_data)
    {
        $this->SetDriver(['filter', 'order', 'source', 'aggs',]);
        $query = $this->es_filter_service->ParseFilter($request_data['filter'] ?? []);
        $query_not = $this->es_filter_service->ParseFilter($request_data['filter_not'] ?? [],0);
        $sort = $this->es_order_service->ParseOrder($request_data['order'] ?? []);
        $source = $this->es_source_service->ParseSource($request_data['includes'] ?? [],'goods');
        $this->query_data = array_merge_recursive($query,$query_not, $sort, $source);

        $request_data['start'] = intval($request_data['start']) >= 0 ? intval($request_data['start']) : 0;
        $request_data['from'] = intval($request_data['from']) >= 0 ? intval($request_data['from']) : 0;
        //不进行聚合查询，则有分页功能
        $this->query_data['from'] = $request_data['start'];
        $this->query_data['size'] = $request_data['limit'];
//        return $this->query_data;

        $index_name = $this->index;
        $index_type = $this->GetIndexTypeName($request_data['filter']['branch_id']);
//        return [$index_name,$index_type];
        //执行结果
       $response_data = $this->Query('_search', $index_name, $index_type);
        if ($response_data === false || isset($response_data['error'])) {
            //执行失败,记录
            \Neigou\Logger::General('es_query_error',
                array('result' => json_encode($response_data), 'sender' => json_decode($this->query_data)));
//            return $response_data;
            return false;
        } else {
            $this->SetDriver(['recombine',]);
            return $this->es_recombine_service->ParseSearchData($response_data);
        }
    }

    /**
     * 聚合查询主逻辑
     * @param $request_data
     * @return array|false
     */
    public function SelectAggs($request_data)
    {
        $this->SetDriver(['filter', 'aggs',]);
        $query = $this->es_filter_service->ParseFilter($request_data['filter'] ?? []);
        $query_not = $this->es_filter_service->ParseFilter($request_data['filter_not'] ?? [],0);
        $this->query_data = array_merge_recursive($query,$query_not);
        $aggs = $this->es_aggs_service->ParseAggs($request_data['aggs'] ?? []);
        $this->query_data = array_merge($this->query_data, $aggs);
//        return $this->query_data;

        $index_name = $this->index;
        $index_type = $this->GetIndexTypeName($request_data['filter']['branch_id']);
        //执行结果
        $response_data = $this->Query('_search', $index_name, $index_type);
        if ($response_data === false || isset($response_data['error'])) {
            //执行失败,记录
            \Neigou\Logger::General('es_query_error',
                array('result' => json_encode($response_data), 'sender' => json_decode($this->query_data)));
//            return $response_data;
            return false;
        } else {
            $this->SetDriver(['recombine',]);
//            return $response_data;
            return $this->es_recombine_service->ParseSearchData($response_data);
        }
    }

    /*
     * @todo 数据统计【商品查询数量统计主逻辑】
     * @parameter $request_data 请求数据 $type数据源类型 es|mysql
     */
    public function Count($request_data)
    {
        $this->SetDriver(['filter',]);
        $query = $this->es_filter_service->ParseFilter($request_data['filter'] ?? []);
        $query_not = $this->es_filter_service->ParseFilter($request_data['filter_not'] ?? [],0);

        $this->query_data = array_merge_recursive($query,$query_not);

//        return $this->query_data;
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

    /**
     * @todo 获取搜索使用TYPE名
     */
    protected function GetIndexTypeName($branch_ids)
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

    /**
     * @todo 执行
     * @parameter $pattern 操作模式 _count(统计) _search(搜索)
     */
    public function Query($pattern, $index_name = '', $index_type = '')
    {
        $index_name = !empty($index_name) ? $index_name : $this->index;
        $index_type = !empty($index_type) ? $index_type : $this->type;
        $post_url = $this->elasticsearch_host . '/' . $index_name . '/' . $index_type . '/' . $pattern;
        $dsl = '';
        if (!empty($this->query_data)) {
            $dsl = json_encode($this->query_data);
        }

        $this->query_data = array();  //执行完成清空执行数组
        $this->SetDriver(['curl',]);
        $data = $this->curl_ojb->Post($post_url, $dsl);
        if (empty($data)) {
            return false;
        } else {
            $data = json_decode($data, true);
        }
        return $data;
    }

}
