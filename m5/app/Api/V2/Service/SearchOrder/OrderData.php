<?php
namespace App\Api\V2\Service\SearchOrder;

use App\Api\V2\Service\Order\Order;
use App\Api\V2\Service\SearchOrder\OrderDataSource\Es;

class OrderData{

    protected $filter = array();  //过滤数据

    protected $filter_not = array();  //反查过滤数据

    protected $order = array();  //排序数据

    protected $start = 0;    //开始位置

    protected $limit = 10;   //取到数据条数

    private $error_msg = '';   //错误信息

    protected $_source = array();  // 指定字段

    protected $includes = array();  // 指定查询字段

    protected $output_format = 'valid_split_order'; // 输出格式

    public function __construct($request_data){
        $this->init($request_data);
    }

    // 初始化入参
    private function init($request_data){
        $this->start = isset($request_data['start']) && $request_data['start'] >= 0 ? $request_data['start'] : $this->start;

        $this->limit = isset($request_data['limit']) && $request_data['limit'] > 0 ? $request_data['limit'] : $this->limit;

        $this->filter = $request_data['filter'];

        $this->filter_not = $request_data['filter_not'];

        $this->order = $request_data['order'];

        $this->_source = $request_data['_source'];

        $this->includes = $request_data['includes'];

        $this->output_format = isset($request_data['output_format']) && !empty($request_data['output_format']) ? $request_data['output_format'] : $this->output_format;
    }

    // 获取订单列表
    public function GetOrderList(){
        // 如果纯数字搜索，则不涉及分词查询
        if (isset($this->filter['product_name']) && preg_match('/^[0-9]+$/', trim($this->filter['product_name'])) === 1 && strlen($this->filter['product_name']) > 10){
            $column_field = 'root_pid';

            $main_order_search = $this->GetDataFromSource('order_list');

            $main_order_list = $main_order_search['order_list'];
            $main_order_count = $main_order_search['order_count'];
        }else{
            // 主单列表
            $column_field = 'order_id';

            $main_order_search = $this->GetMainOrderList();

            $main_order_list = $main_order_search['order_list'];
            $main_order_count = $main_order_search['order_count'];
        }

        $main_order_ids = array_column($main_order_list, $column_field);

        if (empty($main_order_ids)){
            return [
                'order_list' => [],
                'order_count' => 0
            ];
        }

        // 获取到主单id列表后，直接走service查询详情 & 子单
        $orderService = new Order();
        $search_order_list = $orderService->GenerateOrderList($main_order_ids, $this->output_format);

        // 查询子单
        $sub_order_search = $this->GetSubOrderList($main_order_ids);
        $sub_order_list = $sub_order_search['order_list'];

        // 根据ES查询出的子单过滤掉不匹配的子单数据
        $sub_order_list_ids = array_column($sub_order_list, 'order_id');
        $search_order_list = $this->FilterSubOrder($search_order_list, $sub_order_list_ids);

        return [
            'order_list' => $search_order_list,
            'order_count' => $main_order_count
        ];
    }

    // 过滤不符合匹配条件的子单
    public function FilterSubOrder($order_list, $match_sub_order_ids){
        foreach ($order_list as $order_id => $detail){
            if (!isset($detail['split_orders']) || empty($detail['split_orders'])){
                continue;
            }

            foreach ($detail['split_orders'] as $key => $sub_detail){
                if (!in_array($sub_detail['order_id'], $match_sub_order_ids)){
                    unset($order_list[$order_id]['split_orders'][$key]);
                }
            }

            $order_list[$order_id]['split_orders'] = array_values($order_list[$order_id]['split_orders']);
        }

        return $order_list;
    }

    // 合并主、子单数据
    public function JoinOrderData($main_orders, $sub_orders){
        $split_orders = [];

        foreach ($sub_orders as $sub){
            $split_orders[$sub['root_pid']][] = $sub;
        }

        foreach ($main_orders as &$main){
            $main['split_orders'] = isset($split_orders[$main['order_id']]) ? $split_orders[$main['order_id']] : [];
        }

        return $main_orders;
    }


    // 主单
    public function GetMainOrderList(){
        $this->filter['create_source'] = 'main';

        $response = $this->GetDataFromSource('order_list');

        unset($this->filter['create_source']);

        return $response;
    }

    // 子单
    public function GetSubOrderList($main_order_ids = []){
        $this->filter['split'] = 1;
        $this->filter['create_source'] = ['split_order', 'wms_order'];
        $this->filter['root_pid'] = $main_order_ids;

        $response = $this->GetDataFromSource('order_list', 0, 99999);

        unset($this->filter['split'], $this->filter['create_source'], $this->filter['root_pid']);

        return $response;
    }

    // 获取订单结果条数
    public function GetOrderCount(){
        // 从数据源中实时获取数据
        $this->filter['create_source'] = 'main';

        $response = $this->GetDataFromSource('order_count');

        unset($this->filter['create_source']);

        return $response;
    }

    // 调用ES-API获取条件检索的结果数据
    public function GetDataFromSource($type, $start = null, $limit = null){
        $request_data = array(
            'filter' => $this->filter,
            'filter_not' =>  $this->filter_not,
            'order' => $this->order,
            'start' => is_null($start) ? $this->start : intval($start),
            'limit' => is_null($limit) ? $this->limit : intval($limit),
            '_source' => $this->_source,
            'includes' => $this->includes,
        );

        $es = new Es();

        $action = '';

        switch ($type) {
            case 'order_list' :
                $action = 'Select';
                break;
            case 'order_count' :
                $action = "Count";
                break;
        }

        if (empty($action)) {
            $this->error_msg = '操作类型错误';
            return false;
        }

        $data = $es->$action($request_data);

        return $data;
    }

    // 获取错误信息
    public function GetErrorMsg(){
        return $this->error_msg;
    }
}
