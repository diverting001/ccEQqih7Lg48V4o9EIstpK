<?php
/**
 * 公用商品信息
 * @version 0.1
 * @package ectools.lib.api
 */

namespace App\Api\V1\Service\Search;
use App\Api\Model\Outlet\OutletModel;

class BusinessValue
{
    private $_db_store;
    private $_db_server;
    private $_table_search_business_value = 'server_search_business_value';
    private $_table_search_business_filed = 'server_search_business_field';

    public function __construct()
    {
        $this->_db_store = app('api_db')->connection('neigou_store');
        $this->_db_server = app('api_db');
    }

    // 给goods写入业务数据
    public function putBusinessValue($goods_list)
    {
        if (!$goods_list) {
            return false;
        }
        $goods_ids = array_column($goods_list, 'goods_id');
        $business_values = $this->getBusinessValue($goods_ids);
        foreach ($business_values as $business_value) {
            //$goods_list[$business_value['goods_id']][$business_value['es_field']] = json_decode($business_value['value']);
            if ($business_value['es_field'] == "outlet_list") {
                $outlets = json_decode($business_value['value'],true);
                if (is_array($outlets[0])) {
                    $goods_list[$business_value['goods_id']][$business_value['es_field']] = $outlets;
                } else {
                    $outletIds = $outlets;
                    $outletItemList = array();
                    if (!empty($outlets)) {
                        $outletModel = new OutletModel();
                        $outletInfoList = $outletModel->getList(0, count($outletIds), $outletIds);

                        foreach ($outletInfoList as $outletItem) {
                            $outletItemList[] = array(
                                'coordinate' => array(
                                    'lon' => floatval($outletItem->longitude),
                                    'lat' => floatval($outletItem->latitude),
                                ),
                                'outlet_id' => intval($outletItem->outlet_id),
                                'province_id' => intval($outletItem->province_id),
                                'outlet_address' => strval($outletItem->outlet_address),
                                'outlet_name' => strval($outletItem->outlet_name),
                                'area_id' => intval($outletItem->area_id),
                                'outlet_logo' => strval($outletItem->outlet_logo),
                                'city_id' => intval($outletItem->city_id),
                            );
                        }
                    }
                    $goods_list[$business_value['goods_id']][$business_value['es_field']] = $outletItemList;

                }
            } else {
                $goods_list[$business_value['goods_id']][$business_value['es_field']] = json_decode($business_value['value']);
            }
        }
        return $goods_list;
    }

    public function getBusinessValue($goods_ids)
    {
        $business_values = $es_goods = $this->_db_server->table($this->_table_search_business_value . ' as value')
            ->join($this->_table_search_business_filed . ' as field', function ($join) {
                $join->on('value.business_code', '=', 'field.business_code')
                    ->on('value.business_field', '=', 'field.business_field');
            }, null, null, 'left')
            ->whereIn('goods_id', $goods_ids)
            ->get()->map(function ($value) {
                return (array)$value;
            })
            ->toArray();
        return $business_values;
    }
}
