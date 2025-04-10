<?php

namespace App\Api\Logic\Operate\LimitBuy;

use App\Api\Logic\Goods as GoodsLogic;
use App\Api\Logic\Shop as ShopLogic;

class Supplier
{
    public function getProductBuyLimit($products, $companyId = null, $memberId = null)
    {
        $return = array();

        // 获取商品信息
        $productBns = array();
        foreach ($products as $productInfo)
        {
            $productBns[] = $productInfo['product_bn'];
        }
        $goods_logic = new GoodsLogic();
        $goodsList = $goods_logic->getGoodsByProductBn($productBns);

        if (empty($goodsList))
        {
            return $return;
        }

        $products = array();
        $shopId = array();
        foreach ($goodsList as $v)
        {
            $shopId[] = $v['shop_id'];
        }

        // 获取商品供应平台
        $logic = new ShopLogic();
        $shopList = $logic->GetList(array('filter' => array('shop_id_list' => $shopId)));

        if (empty($shopList))
        {
            return $return;
        }
        $shopWms = array();
        foreach ($shopList as $v)
        {
            $shopWms[$v['shop_id']] = $v['pop_wms_code'];
        }
        foreach ($goodsList as $v)
        {
            if (isset($shopWms[$v['shop_id']]))
            {
                $products[$shopWms[$v['shop_id']]][] = $v['product_bn'];
            }
        }

        if ( ! empty($products['SALYUT']))
        {
            $salyutLimitBuy = $this->_getSalyutProductLimitBuy(array('product_bn' => $products['SALYUT']));
            if ( ! empty($salyutLimitBuy))
            {
                $return = $salyutLimitBuy;
            }
        }

        return $return;
    }

    /*
     * 获取 salyut 产品限制
     */
    private function _getSalyutProductLimitBuy($products)
    {
        $request_params = array(
            'class_obj' => 'SalyutGoods',
            'method' => 'getGoodsLimitBuy',
            'data' => json_encode($products)
        );

        $return = array();
        $curl = new \Neigou\Curl();
        $curl->time_out = 15;

        $request_params['token'] = $this->_getSalyutToken($request_params);

        $result = $curl->Post(config('neigou.SALYUT_DOMIN') . '/OpenApi/apirun', $request_params);

        if ($curl->GetHttpCode() == 200)
        {
            $result = json_decode($result, true);
            $return = $result['Data'];
        }

        return $return;
    }

    // 获取 salyut token
    private function _getSalyutToken($arr)
    {
        ksort($arr);
        $sign_ori_string = "";
        foreach ($arr as $key => $value)
        {
            if ( ! empty($value) && ! is_array($value))
            {
                if ( ! empty($sign_ori_string))
                {
                    $sign_ori_string .= "&$key=$value";
                }
                else
                {
                    $sign_ori_string = "$key=$value";
                }
            }
        }
        $sign_ori_string .= ("&key=" . config('neigou.SALYUT_SIGN'));

        return strtoupper(md5($sign_ori_string));
    }

}
