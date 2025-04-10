<?php

namespace App\Api\V6\Service\Search\Datasource\Support;

/**
 * es基础信息配置类
 */
abstract class  EsPredefined
{
    protected $elasticsearch_host = '';
    protected $index = '';
    protected $type = 'goods_test_v1';
    /** @var \Neigou\Curl $curl_ojb*/
    protected $curl_ojb;
    protected $query_data = array();
    protected $_db_store;

    /** @var EsFilter $es_filter_service */
    protected $es_filter_service;

    /** @var EsOrder $es_order_service */
    protected $es_order_service;

    /** @var EsSource $es_source_service */
    protected $es_source_service;

    /** @var EsAggs $es_aggs_service */
    protected $es_aggs_service;

    /** @var EsRecombine $es_recombine_service */
    protected $es_recombine_service;

    public $province = array(
        '北京' => 'p1',
        '上海' => 'p2',
        '天津' => 'p3',
        '重庆' => 'p4',
        '安徽' => 'p5',
        '福建' => 'p6',
        '甘肃' => 'p7',
        '广东' => 'p8',
        '广西' => 'p9',
        '贵州' => 'p10',
        '海南' => 'p11',
        '河北' => 'p12',
        '河南' => 'p13',
        '黑龙江' => 'p14',
        '湖北' => 'p15',
        '湖南' => 'p16',
        '吉林' => 'p17',
        '江苏' => 'p18',
        '江西' => 'p19',
        '辽宁' => 'p20',
        '内蒙古' => 'p21',
        '宁夏' => 'p22',
        '青海' => 'p23',
        '山东' => 'p24',
        '山西' => 'p25',
        '陕西' => 'p26',
        '四川' => 'p27',
        '西藏' => 'p28',
        '新疆' => 'p29',
        '云南' => 'p30',
        '浙江' => 'p31'
    );

    //规定好的排序filed,只对设定过的进行处理
    protected $aggs_script_order_field = [
        'outlet_list.coordinate' => "outlet_script_order",
        'outlet_list.outlet_id' => "outlet_script_order",
        'goods_id' => 'goods_script_order',
        'brand_id' => 'brand_script_order',
        'ordernum' => 'brand_ordernum_script_order'
    ];

    public function __construct()
    {
        $this->_db_store = app('api_db')->connection('neigou_store');
        $this->elasticsearch_host = config('neigou.ESSEARCH_HOST') . ':' . config('neigou.ESSEARCH_PORT');
        $this->index = config('neigou.ESSEARCH_INDEX');
        $this->type = config('neigou.ESSEARCH_TYPE');
    }

    /**
     * 实例化需要的类
     * $this->SetDriver(['filter','order','source','aggs','recombine','curl',]);
     * @param array $driver
     * @return void
     */
    public function SetDriver(array $driver = [])
    {
        if (in_array('filter', $driver) && !$this->es_filter_service) $this->es_filter_service = new EsFilter();
        if (in_array('order', $driver) && !$this->es_order_service) $this->es_order_service = new EsOrder();
        if (in_array('source', $driver) && !$this->es_source_service) $this->es_source_service = new EsSource();
        if (in_array('aggs', $driver) && !$this->es_aggs_service) $this->es_aggs_service = new EsAggs();
        if (in_array('recombine', $driver) && !$this->es_recombine_service) $this->es_recombine_service = new EsRecombine();
        if (in_array('curl', $driver) && !$this->curl_ojb) $this->curl_ojb = new \Neigou\Curl();

    }
}
