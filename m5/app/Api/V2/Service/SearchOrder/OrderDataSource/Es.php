<?php

namespace App\Api\V2\Service\SearchOrder\OrderDataSource;

use App\Api\V2\Service\SearchOrder\OrderDataSource\Support\EsPredefined;
/**
 * elasticsearch查询入口
 */
class Es extends EsPredefined{
    // 查询订单列表
    public function Select($request_data){
        $support_obj = ['filter', 'order', 'source'];

        $this->SetDriver($support_obj);

        // 需要匹配的条件
        $query = $this->es_filter_service->ParseFilter($request_data['filter'] ?? []);
        // 不需要包含的条件
        $query_not = $this->es_filter_service->ParseFilter($request_data['filter_not'] ?? [], 0);
        // 排序
        $sort = $this->es_order_service->ParseOrder($request_data['order'] ?? []);
        // 查询的字段
        $source = $this->es_source_service->ParseSource($request_data['includes'] ?? []);

        $this->query_data = array_merge_recursive($query, $query_not, $sort, $source);

        // 分页
        $this->query_data['from'] = $request_data['start'];
        $this->query_data['size'] = $request_data['limit'];

        // ES索引
        $index_name = $this->_index_alias;
        $index_type = $this->_type;

        // 执行 & 获取结果
        $response_data = $this->Query('_search', $index_name, $index_type);

        if ($response_data === false || isset($response_data['error'])) {
            //执行失败,记录
            \Neigou\Logger::General('es_query_error_order',
                array('result' => json_encode($response_data), 'sender' => json_decode($this->query_data)));

            return false;
        } else {
            $this->SetDriver(['recombine']);

            return $this->es_recombine_service->ParseSearchData($response_data);
        }
    }

    // 查询结果条数
    public function Count($request_data){
        $support_obj = ['filter'];

        $this->SetDriver($support_obj);

        // 需要匹配的条件
        $query = $this->es_filter_service->ParseFilter($request_data['filter'] ?? []);
        // 不需要包含的条件
        $query_not = $this->es_filter_service->ParseFilter($request_data['filter_not'] ?? [], 0);

        $this->query_data = array_merge_recursive($query, $query_not);

        // ES索引
        $index_name = $this->_index_alias;
        $index_type = $this->_type;

        // 执行 & 获取结果
        $response_data = $this->Query('_count', $index_name, $index_type);

        if ($response_data === false || isset($response_data['error'])) {
            //执行失败,记录
            \Neigou\Logger::General('es_query_error_order',
                array('result' => json_encode($response_data), 'sender' => json_decode($this->query_data)));

            return false;
        } else {
            return $response_data['count'];
        }
    }

    // 执行ES接口查询
    public function Query($pattern, $index_name = '', $index_type = ''){
        $index_name = !empty($index_name) ? $index_name : $this->_index_alias;

        $index_type = !empty($index_type) ? $index_type : $this->_type;

        $post_url = $this->elasticsearch_host . '/' . $index_name . '/' . $index_type . '/' . $pattern;

        $dsl = '';

        if (!empty($this->query_data)) {
            $dsl = json_encode($this->query_data);
        }

        $this->query_data = array();  //执行完成清空执行数组

        $this->SetDriver(['curl']);

        $data = $this->curl_ojb->Post($post_url, $dsl);

        if (empty($data)) {
            return false;
        } else {
            $data = json_decode($data, true);
        }

        return $data;
    }

    // 更新ES订单指定字段 - update
    public function UpdateEsFields($request_data, &$err_msg, $index_name = '', $index_type = ''){
        $es_update_res = $this->ExecuteBulkWrite($request_data, 'update', $err_msg, $index_name, $index_type);

        // 执行的每条记录均无错误，全部成功
        if (isset($es_update_res['errors']) && empty($es_update_res['errors'])){
            return true;
        }

        // 全部失败
        if (empty($es_update_res)){
            $err_msg = 'ES执行失败，未知错误';
        }

        // 全部失败 or 部分失败，获取失败的记录错误原因
        if (isset($es_update_res['errors']) && $es_update_res['errors'] == 1){
            foreach ($es_update_res['items'] as $error){
                if ($error['update']['status'] != 200 && isset($error['update']['error'])){
                    $err_msg .= $error['update']['error']['reason'] . '; ';
                }
            }
        }

        // 记录错误日志
        $log['action'] = 'UpdateEsFields';
        $log['result'] = $err_msg;
        $log['data'] = $request_data;

        \Neigou\Logger::General('es_order_update_error', $log);

        return false;
    }

    // 覆盖ES订单指定记录的所有数据 - index
    public function CoverEsDoc($request_data, &$err_msg, $index_name = '', $index_type = ''){
        $es_cover_res = $this->ExecuteBulkWrite($request_data, 'cover', $err_msg, $index_name, $index_type);

        // 执行的每条记录均无错误，全部成功(如果order_id不存在则会直接新建)
        if (isset($es_cover_res['errors']) && empty($es_cover_res['errors'])){
            return true;
        }

        // 全部失败
        if (empty($es_cover_res)){
            $err_msg = 'ES执行失败，未知错误';
        }

        // 记录错误日志
        $log['action'] = 'CoverEsDoc';
        $log['result'] = $err_msg;
        $log['data'] = $request_data;

        \Neigou\Logger::General('es_order_cover_error', $log);

        return false;
    }

    // 创建新ES订单数据 - create
    public function CreateEsDoc($request_data, &$err_msg, $index_name = '', $index_type = ''){
        $es_create_res = $this->ExecuteBulkWrite($request_data, 'create', $err_msg, $index_name, $index_type);

        // 执行的每条记录均无错误，全部成功
        if (isset($es_create_res['errors']) && empty($es_create_res['errors'])){
            return true;
        }

        // 全部失败
        if (empty($es_create_res)){
            $err_msg = 'ES执行失败，未知错误';
        }

        // 全部失败 or 部分失败，获取失败的记录错误原因
        if (isset($es_create_res['errors']) && $es_create_res['errors'] == 1){
            echo 'fail';
            foreach ($es_create_res['items'] as $error){
                if ($error['create']['status'] != 200 && isset($error['create']['error'])){
                    $err_msg .= $error['create']['error']['reason'] . '; ';
                }
            }
        }

        // 记录错误日志
        $log['action'] = 'CreateEsDoc';
        $log['result'] = $err_msg;
        $log['data'] = $request_data;

        \Neigou\Logger::General('es_order_create_error', $log);

        return false;
    }

    // 删除ES文档
    public function DeleteEsDoc($request_data, &$err_msg, $index_name = '', $index_type = ''){
        $es_delete_res = $this->ExecuteBulkWrite($request_data, 'delete', $err_msg, $index_name, $index_type);

        // 执行的每条记录均无错误，全部成功
        if (isset($es_delete_res['errors']) && empty($es_delete_res['errors'])){
            return true;
        }

        // 全部失败
        if (empty($es_create_res)){
            $err_msg = 'ES执行失败，未知错误';
        }

        return false;
    }

    /**
     * Notes  : ES _bulk通用批量写操作
     * 更新 - update 更新一个文档，如果文档不存在就返回错误
     * 覆盖 - index  如果文档不存在就创建，如果文档存在就更新
     * 创建 - create 如果文档不存在就创建，但如果文档存在就返回错误
     * 删除 - delete 删除一个文档，如果要删除的文档id不存在，就返回错误
     */
    public function ExecuteBulkWrite($request_data, $bulk_type, &$err_msg, $index_name = '', $index_type = ''){
        if (empty($request_data)){
            return true;
        }

        $bulk_type_arr = [
            'update' => 'update',
            'cover' => 'index',
            'create' => 'create',
            'delete' => 'delete'
        ];

        if (empty($bulk_type) || !array_key_exists($bulk_type, $bulk_type_arr)){
            $err_msg = '不支持的批量操作类型：' . $bulk_type;
            return false;
        }

        $index_name = !empty($index_name) ? $index_name : $this->_index_alias;

        $index_type = !empty($index_type) ? $index_type : $this->_type;

        $bulk_data_str = '';

        foreach ($request_data as $val){
            if (!$val['order_id']){
                continue;
            }

            if (isset($val['order_id'])){
                $val['order_id'] = empty($val['order_id']) ? '' : strval($val['order_id']);
            }
            if (isset($val['parent_id'])){
                $val['parent_id'] = empty($val['parent_id']) ? '' : strval($val['parent_id']);
            }
            if (isset($val['root_pid'])){
                $val['root_pid'] = empty($val['root_pid']) ? '' : strval($val['root_pid']);
            }

            $index_data = [
                $bulk_type_arr[$bulk_type] => [
                    '_index' => $index_name,
                    '_type' => $index_type,
                    '_id' => $val['order_id']
                ]
            ];

            $bulk_data_str .= json_encode($index_data) . "\n";

            if ($bulk_type_arr[$bulk_type] == 'update'){
                $bulk_data_str .= json_encode(['doc' => $val]) . "\n";
            }elseif (in_array($bulk_type_arr[$bulk_type], ['index', 'create'])){
                $bulk_data_str .= json_encode($val) . "\n";
            }
        }

        // 执行ES
        $this->SetDriver(['curl']);

        $post_url = $this->elasticsearch_host . '/_bulk';

        $es_bulk_res = $this->curl_ojb->Post($post_url, $bulk_data_str);

        return empty($es_bulk_res) ? [] : json_decode($es_bulk_res, true);
    }
}
