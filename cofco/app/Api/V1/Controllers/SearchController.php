<?php
/**
 * Created by PhpStorm.
 * User: chuanbin
 * Date: 2018/4/2
 * Time: 14:35
 */

namespace App\Api\V1\Controllers;


use App\Api\Common\Controllers\BaseController;
use App\Api\Model\Search\SearchModel;
use App\Api\Model\Search\BusinessKeywordModel;
use App\Api\V1\Service\Search\Goodsdata;
use App\Api\V1\Service\Search\BusinessField;
use Illuminate\Http\Request;

class SearchController extends BaseController
{
    /*
     * 更新es
     */
    public function BusinessDataPush(Request $request)
    {
        $createParams = $this->getContentArray($request);
        if (!isset($createParams['goods']) || !isset($createParams['business_code'])) {
            \Neigou\Logger::General(
                'GoodsRules.save',
                array('action' => 'Search/SaveGoods', 'reason' => 'invalid_params', 'data' => $createParams)
            );
            $this->setErrorMsg('参数错误');
            return $this->outputFormat(null, 404);
        }

        $err_code = 0;
        $err_msg = "";
        $SearchMdl = new SearchModel();
        $createResult = $SearchMdl->BusinessDataPush($createParams['goods'], $createParams['business_code'], $err_code,
            $err_msg);
        \Neigou\Logger::Debug('DutyFree.MemberCoupon.Create',
            array('action' => 'Search/BusinessDataPush', 'data' => $createParams, 'result' => $createResult));
        if (!empty($createResult)) {
            $this->setErrorMsg('请求成功');
            return $this->outputFormat($createResult);
        } else {
            $this->setErrorMsg('请求失败');
            return $this->outputFormat(null, 404);
        }
    }

    public function BusinessDataGet(Request $request)
    {
        $createParams = $this->getContentArray($request);
        if (!$createParams) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat(null, 404);
        }
        $data = $createParams;
        if (isset($createParams['business_code']) && !empty($createParams['business_code'])) {
            $bf = new BusinessField();
            $business2es_fields = $bf->getBusinessField($createParams['business_code']);
            $business_fields = array_keys($business2es_fields);
            $data['filter'] = [];
            $data['order'] = [];
            // 处理业务字段
            foreach ($createParams['filter'] as $filter_field => $value) {
                if (!in_array($filter_field, $business_fields)) {
                    continue;
                }
                $business2es_field = $business2es_fields[$filter_field];
                $es_field = $business2es_field['es_field'];
                $data['filter'][$es_field] = $value;
                unset($createParams['filter'][$filter_field]);
            }
            $data['filter'] = array_merge($data['filter'], $createParams['filter']);
            foreach ($createParams['order'] as $filter_field => $value) {
                if (!in_array($filter_field, $business_fields)) {
                    continue;
                }
                $business2es_field = $business2es_fields[$filter_field];
                $es_field = $business2es_field['es_field'];
                $data['order'][$es_field] = $value;
                unset($createParams['order'][$filter_field]);
            }
            $data['order'] = array_merge($data['order'], $createParams['order']);
            unset($createParams['business_code']);
            unset($createParams['filter']);
            unset($createParams['order']);
            $data = array_merge($data, $createParams);
        }
        return $this->GetProductsList($data, $business2es_fields);
    }

    public function BusinessKeywordCover(Request $request)
    {
        $createParams = $this->getContentArray($request);
        if (!isset($createParams['keywords']) || !isset($createParams['business_code'])) {
            \Neigou\Logger::General('BusinessKeywordCover', array(
                'action' => 'Search/BusinessKeywordCover',
                'reason' => 'invalid_params',
                'data' => $createParams
            ));
            $this->setErrorMsg('参数错误');
            return $this->outputFormat(null, 404);
        }
        $bf = new BusinessField();
        $business2es_fields = $bf->getBusinessField($createParams['business_code']);
        $business_fields = array_keys($business2es_fields);
        $datas_keyword = [];

        foreach ($createParams['list'] as $item) {
            if (!in_array($item['business_field'], $business_fields)) {
                continue;
            }
            foreach ($createParams['keywords'] as $keyword) {
                $datas_keyword[] = [
                    'keyword' => $keyword,
                    'business_code' => $createParams['business_code'],
                    'business_field' => $item['business_field'],
                    'business_field_id' => $business2es_fields[$item['business_field']]['business_field_id'],
                    'es_field' => $business2es_fields[$item['business_field']]['es_field'],
                    'es_value' => $item['value'],
                    'boost' => $item['boost'],
                ];
            }
        }

        $m_bk = new BusinessKeywordModel();
        $createResult = $m_bk->coverKeywords($createParams['business_code'], $createParams['keywords'], $datas_keyword);
        if ($createResult) {
            $this->setErrorMsg('请求成功');
            return $this->outputFormat($createResult);
        } else {
            $this->setErrorMsg('请求失败');
            return $this->outputFormat(null, 404);
        }
    }

    public function BusinessKeywordDel(Request $request)
    {
        $createParams = $this->getContentArray($request);
        if (!isset($createParams['keywords']) || !isset($createParams['business_code'])) {
            \Neigou\Logger::General(
                'BusinessKeywordDel',
                array('action' => 'Search/BusinessKeywordDel', 'reason' => 'invalid_params', 'data' => $createParams)
            );
            $this->setErrorMsg('参数错误');
            return $this->outputFormat(null, 404);
        }
        $m_bk = new BusinessKeywordModel();
        $createResult = $m_bk->delKeywords($createParams['business_code'], $createParams['keywords']);
        if ($createResult) {
            $this->setErrorMsg('请求成功');
            return $this->outputFormat($createResult);
        } else {
            $this->setErrorMsg('请求失败');
            return $this->outputFormat(null, 404);
        }
    }

    /*
     * @todo 获取商品列表
     */
    private function GetProductsList($data = null, $business2es_fields = null)
    {
        $data['stages']['all'] = 'all';
        $goods_data_obj = new Goodsdata($data);
        $goods_list = $goods_data_obj->GetGoodsList();    //商品列表open
        $goods_total = $goods_data_obj->GetGoodsTotal();    //商品总条数
        $return_data['goods_list'] = is_array($goods_list['goods_list']) ? $goods_list['goods_list'] : array();

        $es2bus_field = [];
        foreach ($business2es_fields as $business2es_field) {
            $es2bus_field[$business2es_field['es_field']] = $business2es_field['business_field'];
        }
        foreach ($return_data['goods_list'] as &$return_datum) {
            foreach ($es2bus_field as $es_field => $business2es_field) {
                if (isset($return_datum[$es_field])) {
                    $return_datum[$business2es_field] = $return_datum[$es_field];
                    unset($return_datum[$es_field]);
                }
            }
        }

        $return_data['aggs'] = is_array($goods_list['aggs']) ? $goods_list['aggs'] : array();
        $return_data['goods_total'] = intval($goods_total);
        $this->setErrorMsg('请求成功');
        return $this->outputFormat($return_data);
    }
}
