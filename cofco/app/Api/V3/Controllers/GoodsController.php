<?php
/**
 * Created by PhpStorm.
 * User: chuanbin
 * Date: 2018/4/2
 * Time: 14:35
 */

namespace App\Api\V3\Controllers;


use App\Api\Common\Controllers\BaseController;
use App\Api\V3\Service\Search\Goodsdata;
use App\Api\Logic\Goods as GoodsLogic;
use Illuminate\Http\Request;

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
        $goods_data_obj = new Goodsdata($pars);
        $goods_list = $goods_data_obj->GetGoodsList();    //商品列表open
        $goods_total = $goods_data_obj->GetGoodsTotal();    //商品总条数
        $return_data['list'] = is_array($goods_list['goods_list']) ? $goods_list['goods_list'] : array();

        if ($return_data['list']) {
            $fields = [
                'goods_type',
                'product_id',
                'marketable',
                'product_bn',
                'name',
                'bn',
                'goods_id',
                'brand_id',
                's_url',
                'm_url',
                'l_url',
                'last_modify',
                'is_soldout',
                'products',
                'cat_level_3',
            ];
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
                unset($goods['cat_level_3']);

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

    /*
     * @todo 查询商品列表
     */
    public function GetList(Request $request)
    {
        $pars = $this->getContentArray($request);
        if ($this->check($pars) === false) {
            return $this->outputFormat([
                'count' => 0,
                'list' => [],
            ]);
        }
        $goods_logic = new GoodsLogic;
        $return_data = $goods_logic->GetList($pars);
        return $this->outputFormat($return_data);
    }

    /*
     * @todo 商品详情
     */
    public function Get(Request $request)
    {
        $pars = $this->getContentArray($request);
        if ($this->check($pars) === false) {
            return $this->outputFormat([]);
        }
        $goods_logic = new GoodsLogic;
        $return_data = $goods_logic->Get($pars);
        return $this->outputFormat($return_data);
    }
}
