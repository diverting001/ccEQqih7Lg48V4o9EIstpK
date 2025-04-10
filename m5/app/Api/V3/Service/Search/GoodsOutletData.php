<?php

namespace App\Api\V3\Service\Search;

use App\Api\V3\Service\Search\Datasource\GoodsOutletEs;

class GoodsOutletData
{
    protected $filter = array();  //过滤数据
    protected $order = array();  //排序数据
    protected $cache_time = 180;  //缓存时间
    protected $start = 0;    //开始位置
    protected $limit = 10;   //取到数据条数
    protected $is_use_cache = true;    //是否使用缓存
    private $error_msg = '';   //错误信息
    protected $stages = array();  //访问场景
    protected $stock_filter = array();  //库存筛选
    protected $promotion = array();  //提权数据
    protected $aggs = array();  // 指定字段
    protected $only_source_type = array();  // 指定唯一数据源

    public function __construct($request_data)
    {
        $this->init($request_data);
    }

    /*
    * @todo 初始化请求数据
    */
    private function init($request_data)
    {
        $this->start = $request_data['start'];
        $this->limit = $request_data['limit'];
        $this->filter = $request_data['filter'];
        $this->order = $request_data['order'];
        $this->stages = $request_data['stages'];
        $this->stock_filter = $request_data['stock_filter'];
        $this->promotion = $request_data['promotion'];
        $this->aggs = $request_data['aggs'];
    }

    /**
     * 入口获取数据列表
     * @return false
     */
    public function GetGoodsOutletList()
    {
        // 从数据源中实时获取数据
        return $this->GetDataSource('outlet_list');
    }

    /*
     * @todo 从数据源中获取数据
     * @parameter $type获取数据类型 (goods_list 商品列表 goods_total 商品总条数)
     */
    public function GetDataSource($type, $start = null, $limit = null)
    {
        $request_data = array(
            'filter' => $this->filter,
            'order' => $this->order,
            'start' => is_null($start) ? $this->start : intval($start),
            'limit' => is_null($limit) ? $this->limit : intval($limit),
            'promotion' => $this->promotion,
            'aggs' => $this->aggs,
        );
        $es = new GoodsOutletEs();
        $action = '';
        switch ($type) {
            case 'outlet_list' :
                $action = 'Select';
                break;
            case 'outlet_total' :
                $action = "Count";
                break;
        }
        if (empty($action)) {
            $this->error_msg = '操作类型错误';
            return false;
        }
        return $es->$action($request_data);
    }
}
