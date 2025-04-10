<?php
/**
 * Created by PhpStorm.
 * User: zhoulixin
 * Date: 2022/08/23
 * Time: 10:35
 */

namespace App\Api\V6\Controllers\Goods;


use App\Api\Common\Controllers\BaseController;
use App\Api\Logic\Goods as GoodsLogic;
use App\Api\V6\Service\Search\Datasource\Support\EsSource;
use App\Api\V6\Service\Search\Goodsdata;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class GoodsController extends BaseController
{
    private function check(&$pars)
    {
        if (empty($pars) || empty($pars['environment']['project_code'])) {
            return false;
        }
        if ($pars['environment']['project_code'] != 'neigou') {
            $pars_ext = $this->tranProjectCode($pars['environment']['project_code']);
            if ($pars_ext === false) {
                return false;
            }
            $pars['filter'] = array_merge($pars['filter'], $pars_ext);
        }
    }

    public function tranProjectCode($project_code)
    {
        $goods_logic = new GoodsLogic();
        $data['mall_id'] = $goods_logic->GetMallIdsByProjectCode($project_code);
        if (empty($data['mall_id'])) {
            return false;
        }
        return $data;
    }

    /*
     * @todo 搜索商品列表
     */
    public function SearchList(Request $request)
    {
        $pars = $this->getContentArray($request);
        if ($this->check($pars) === false) {
            $this->setErrorMsg('请求成功');
            return $this->outputFormat([
                'count' => 0,
                'aggs' => [],
                'list' => [],
            ]);
        }

        $pars['stages']['all'] = 'all';    //增加全部场景
        $goods_data_obj = new GoodsData($pars);
        $goods_list = $goods_data_obj->GetGoodsList();    //商品列表open
        $goods_total = $goods_data_obj->GetGoodsTotal();    //商品总条数
        $return_data['list'] = is_array($goods_list['goods_list']) ? $goods_list['goods_list'] : array();

        if ($return_data['list']) {
            $fields = EsSource::$goods_field;
            $fields_all = array_keys($return_data['list'][0]);
            foreach ($return_data['list'] as &$goods) {
                foreach ($fields_all as $field) {
                    if (!in_array($field, $fields)) {
                        unset($goods[$field]);
                    }
                }
                $goods['goods_bn'] = $goods['bn'];
                $goods['cat_id'] = $goods['cat_level_3']['cat_id'];
                unset($goods['bn']);
//                unset($goods['cat_level_3']);

                $goods['is_soldout'] = $goods['is_soldout'] == 0 ? 1 : 0;
            }
        }

        $es2bus_field = [];
        foreach ($business2es_fields as $business2es_field) {
            $es2bus_field[$business2es_field['es_field']] = $business2es_field['business_field'];
        }
        foreach ($return_data['list'] as &$return_datum) {
            foreach ($es2bus_field as $es_field => $business2es_field) {
                if (isset($return_datum[$es_field])) {
                    $return_datum[$business2es_field] = $return_datum[$es_field];
                    unset($return_datum[$es_field]);
                }
            }
        }

        $return_data['aggs'] = is_array($goods_list['aggs']) ? $goods_list['aggs'] : array();
        $return_data['count'] = intval($goods_total);
        $this->setErrorMsg('请求成功');
        return $this->outputFormat($return_data);
    }

    public function SearchCount(Request $request)
    {
        $pars = $this->getContentArray($request);
        if ($this->check($pars) === false) {
            $this->setErrorMsg('请求成功');
            return $this->outputFormat([
                'count' => 0,
            ]);
        }

        $pars['stages']['all'] = 'all';    //增加全部场景
        $goods_data_obj = new GoodsData($pars);
        $goods_total = $goods_data_obj->GetGoodsTotal();    //商品总条数
        $return_data['count'] = intval($goods_total);
        $this->setErrorMsg('请求成功');
        return $this->outputFormat($return_data);
    }

    /**
     * 根据门店区域信息聚合获取指定维度数据
     * @param Request $request
     * @return mixed
     */
    public function SearchAggsList(Request $request)
    {
        $pars = $this->getContentArray($request);
        $validator = Validator::make($pars, [
            'start' => "integer|gte:0",
            'limit' => 'integer|gt:0',
            'filter' => 'present|array',
            'filter.brand_id' => 'array',
            'filter.brand_id.*' => 'distinct|integer|gt:0',
            'filter.mall_id' => 'array',
            'filter.mall_id.*' => 'distinct|integer',
            'filter.goods_id' => 'array',
            'filter.goods_id.*' => 'distinct|integer',
            'aggs' => 'present|array',
            'aggs.*.includes' => 'array',
            'aggs.*.order' => 'present|array',
            'aggs.*.order.*.by' => 'in:asc,desc',
            'aggs.*.order.*.type' => 'in:int,string,geo_point',
        ]);
        if ($validator->fails()) {
            $this->setErrorMsg($validator->errors()->getMessages());
            return $this->outputFormat([], 400);
        }

        if (isset($pars['start']) && isset($pars['limit']) && $pars['start'] >= $pars['limit']) {
            $this->setErrorMsg('limit 必须大于 start');
            return $this->outputFormat([], 400);
        }

        if(isset($pars['filter']['outlet.city_id']) && !is_int($pars['filter']['outlet.city_id'])){
            $this->setErrorMsg('outlet.city_id 不是整数');
            return $this->outputFormat([], 400);
        }

        if(isset($pars['filter']['outlet.area_id']) && !is_int($pars['filter']['outlet.area_id'])){
            $this->setErrorMsg('outlet.area_id 不是整数');
            return $this->outputFormat([], 400);
        }

        if (isset($pars['filter']['outlet.distance']) && $pars['filter']['outlet.distance']) {
            $tmp = $pars['filter']['outlet.distance'];
            if (!isset($tmp['radius']) || !is_int($tmp['radius']) || !isset($tmp['coordinate']) || !$tmp['coordinate']) {
                $this->setErrorMsg('outlet.distance 参数错误');
                return $this->outputFormat([], 400);
            }
            if (!isset($tmp['coordinate']['lat']) || !is_float($tmp['coordinate']['lat'])) {
                $this->setErrorMsg('纬度参数错误');
                return $this->outputFormat([], 400);
            }
            if (!isset($tmp['coordinate']['lon']) || !is_float($tmp['coordinate']['lon'])) {
                $this->setErrorMsg('经度参数错误');
                return $this->outputFormat([], 400);
            }
        }


        $goods_data_obj = new GoodsData($pars);
        $goods_list = $goods_data_obj->GetGoodsAggsList();    //商品列表open
        $this->setErrorMsg('请求成功');
        return $this->outputFormat($goods_list);
    }
}
