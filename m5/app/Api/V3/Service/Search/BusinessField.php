<?php
/**
 * 公用商品信息
 * @version 0.1
 * @package ectools.lib.api
 */

namespace App\Api\V3\Service\Search;


class BusinessField
{
    private $_db_store;
    private $_db_server;
    private $table_search_business_filed = 'server_search_business_field';

    public function __construct()
    {
        $this->_db_store = app('api_db')->connection('neigou_store');
        $this->_db_server = app('api_db');
    }


    public function getBusinessField($business_code, $business_fields = null)
    {
        $db = $this->_db_server->table($this->table_search_business_filed)
            ->where(['business_code' => $business_code]);
        if ($business_fields) {
            $db->whereIn('business_field', $business_fields);
        }
        $business2es_fields = $db->get()->map(function ($value) {
            return (array)$value;
        })
            ->keyBy('business_field')
            ->toArray();
        return $business2es_fields;
    }
}