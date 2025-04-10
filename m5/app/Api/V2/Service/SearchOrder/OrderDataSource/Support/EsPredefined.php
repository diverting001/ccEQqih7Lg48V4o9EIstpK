<?php

namespace App\Api\V2\Service\SearchOrder\OrderDataSource\Support;

abstract class EsPredefined{
    protected $elasticsearch_host;

    protected $_index;

    protected $_index_alias;

    protected $_type;

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

    /** @var EsRecombine $es_recombine_service */
    protected $es_recombine_service;

    public function __construct(){
        $this->_db_store = app('api_db')->connection('neigou_store');

        $this->elasticsearch_host = config('neigou.ESSEARCH_HOST') . ':' . config('neigou.ESSEARCH_PORT');

        $this->_index = 'search_orders';

        $this->_index_alias = 'search_orders_v1';

        $this->_type = 'order_data';
    }

    // 实例化需要的支持类
    public function SetDriver(array $driver = []){
        if (in_array('filter', $driver) && !$this->es_filter_service){
            $this->es_filter_service = new EsFilter();
        }

        if (in_array('order', $driver) && !$this->es_order_service){
            $this->es_order_service = new EsOrder();
        }

        if (in_array('source', $driver) && !$this->es_source_service){
            $this->es_source_service = new EsSource();
        }

        if (in_array('recombine', $driver) && !$this->es_recombine_service){
            $this->es_recombine_service = new EsRecombine();
        }

        if (in_array('curl', $driver) && !$this->curl_ojb){
            $this->curl_ojb = new \Neigou\Curl();
        }
    }
}
