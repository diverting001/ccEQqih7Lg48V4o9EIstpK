<?php
/**
 * 公用商品信息
 * @version 0.1
 * @package ectools.lib.api
 */

namespace App\Api\V6\Service\Search;

use App\Api\V6\Service\Search\Datasource\Es;

class Goodsdata
{
    protected $filter = array();  //过滤数据
    protected $filter_not = array();  //反查过滤数据
    protected $order = array();  //排序数据
    protected $cache_time = 180;  //缓存时间
    protected $start = 0;    //开始位置
    protected $limit = 10;   //取到数据条数
    protected $is_use_cache = true;    //是否使用缓存
    private $error_msg = '';   //错误信息
    protected $stages = array();  //访问场景
    protected $stock_filter = array();  //库存筛选
    protected $promotion = array();  //提权数据
    protected $_source = array();  // 指定字段
    protected $includes = array();  // 指定查询字段
    protected $aggs = array();  // 指定分组聚合字段
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
        $this->filter_not = $request_data['filter_not'];
        $this->order = $request_data['order'];
        $this->stages = $request_data['stages'];
        $this->stock_filter = $request_data['stock_filter'];
        $this->promotion = $request_data['promotion'];
        $this->_source = $request_data['_source'];
        $this->includes = $request_data['includes'];
        $this->only_source_type = $request_data['only_source_type'];
        $this->aggs = $request_data['aggs'];

        //使用keyword搜索不管是否指定已使用缓存,都不启用缓存
        $this->is_use_cache = $request_data['filter']['keyword'] ? false : ($request_data['is_cache'] == 'false' ? false : true);
        if (isset($request_data['cache_time'])) {
            $this->cache_time = intval($request_data['cache_time']);
        }
        $this->is_use_cache = $this->is_use_cache && API_GOODS_USE_CACHE;
    }

    /*
     * @todo 入口获取数据列表
     */
    public function GetGoodsList()
    {
        // 从数据源中实时获取数据
        $respnse_data = $this->GetDataSource('goods_list');
        return $respnse_data;
    }

    /*
     * @todo 获取商品总条数
     */
    public function GetGoodsTotal()
    {
        //从数据源中实时获取数据
        $respnse_data = $this->GetDataSource('goods_total');
        return $respnse_data;
    }

    /**
     * 获取聚合数据
     * @return false
     */
    public function GetGoodsAggsList()
    {
        //从数据源中实时获取数据
        $respnse_data = $this->GetDataSource('goods_aggs_list');
        return $respnse_data;
    }

    /*
     * @todo 从数据源中获取数据
     * @parameter $type获取数据类型 (goods_list 商品列表 goods_total 商品总条数)
     */
    public function GetDataSource($type, $start = null, $limit = null)
    {
        $request_data = array(
            'filter' => $this->filter,
            'filter_not' =>  $this->filter_not,
            'order' => $this->order,
            'stages' => $this->stages,
            'start' => is_null($start) ? $this->start : intval($start),
            'limit' => is_null($limit) ? $this->limit : intval($limit),
            'promotion' => $this->promotion,
            '_source' => $this->_source,
            'aggs' => $this->aggs,
            'includes' => $this->includes,
        );
        $es = new Es();
        $action = '';
        switch ($type) {
            case 'goods_list' :
                $action = 'Select';
                break;
            case 'goods_aggs_list' :
                $action = "SelectAggs";
                break;
            case 'goods_total' :
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

    /*
     * @todo 获取错误信息
     */
    public function GetErrorMsg()
    {
        return $this->error_msg;
    }
}
