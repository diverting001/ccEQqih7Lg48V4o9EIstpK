<?php

namespace App\Api\V3\Controllers\Goods;

use App\Api\Common\Controllers\BaseController;
use App\Api\Logic\Goods as GoodsLogic;
use Illuminate\Http\Request;

class ManageController extends BaseController
{
    private function check(&$pars)
    {
        if (empty($pars)) {
            return false;
        }
    }

    /*
     * @todo 查询商品列表
     */
    public function SearchList(Request $request)
    {
        $pars = $this->getContentArray($request);
        if ($this->check($pars) === false) {
            return $this->outputFormat([
                'count' => 0,
                'list'  => [],
            ]);
        }

        $pars['filter'] = $pars['filter'] ?: [];

        $pars['limit'] = (isset($pars['limit']) && intval($pars['limit']) > 1) ? intval($pars['limit']) : 20;
        $pars['start'] = (isset($pars['start']) && intval($pars['start']) >= 0) ? intval($pars['start']) : 0;

        unset($pars['page']);

        $goods_logic = new GoodsLogic();

        $return_data = $goods_logic->GetGoodsList($pars['filter'], $pars['start'], $pars['limit']);

        foreach ($return_data['list'] as &$returnDatum) {
            $returnDatum['weight'] = $returnDatum['weight'] === null ? 0 : $returnDatum['weight'];
            unset($returnDatum['goods_type'], $returnDatum['goods_bonded_type'], $returnDatum['image_default_id']);
        }

        $return_data['totalCount'] = $return_data['count'];
        $return_data['totalPage']  = ceil($return_data['count'] / $pars['limit']);

        unset($return_data['count']);

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
        $goods_logic = new GoodsLogic();
        $return_data = $goods_logic->Get($pars);

        return $this->outputFormat($return_data);
    }

    /**
     *  $pars['goods_bn'] array/string
     *
     * @param Request $request
     *
     * @return array
     */
    public function Update(Request $request)
    {
        $pars = $this->getContentArray($request);

        if (empty($pars['goods_bn'])) {
            return $this->outputFormat(['参数缺失'], '4001');
        }

        $data = [];
        if ( ! empty($pars['mall_goods_cat'])) {
            $data['mall_goods_cat'] = $pars['mall_goods_cat'];
        }

        if ( ! empty($pars['brand_id'])) {
            $data['brand_id'] = $pars['brand_id'];
        }

        if ( ! empty($pars['last_modify'])) {
            $data['last_modify'] = $pars['last_modify'];
        }

        if ( ! $data) {
            return $this->outputFormat(['参数缺失'], '4002');
        }

        $goods_logic = new GoodsLogic();
        $goods_logic->updateByGoodsBn($pars['goods_bn'], $data);

        return $this->outputFormat([]);
    }

    public function UpdateList(Request $request)
    {
        $pars = $this->getContentArray($request);

        if (empty($pars['list'])) {
            return $this->outputFormat(['参数缺失'], '4001');
        }

        $goods_logic = new GoodsLogic();
        $res         = $goods_logic->UpdateList($pars['list']);

        return $this->outputFormat($res);
    }
}
