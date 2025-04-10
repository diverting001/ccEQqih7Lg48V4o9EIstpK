<?php

namespace App\Api\Logic\Operate\LimitMoney;

use App\Api\Logic\Goods as GoodsLogic;
use App\Api\Logic\Service;

class Platform
{
    // 获取商品限购
    public function getProductMoneyLimit($products, $companyId, $memberId)
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

        foreach ($goodsList as $v)
        {
            $products[] = array(
                'product_bn' => $v['product_bn'],
                'goods_bn' => $v['goods_bn'],
            );
        }
        $serviceLogic = new Service();

        $promotionRes = $serviceLogic->ServiceCall(
            'promotion_get', [
                'company_id' => $companyId,
                'member_id' => $memberId,
                'product' => $products,
            ]
        );

        if (empty($promotionRes['data']))
        {
            return $return;
        }
        $data = $promotionRes['data'];
        $promotionLimitBuy = array();
        foreach ($data['rule_list'] as $rule)
        {
            if ($rule['status'] != 1 OR time() < $rule['start_time'] OR time() > $rule['end_time'])
            {
                continue;
            }
            $rule['condition'] = json_decode($rule['condition'], true);
            $extendData = $rule['condition']['extend_data'] ? : array();

            if ($rule['condition']['operator_class'] === 'limit_money')
            {
                $promotionLimitBuy[$rule['id']] = array(
                    'max_amount'   => $extendData['max_amount'],
                    'tips'         => $extendData['tips'],
                    'refresh_time' => !empty($extendData['refresh_time']) ? $extendData['refresh_time'] : 'no_refresh',
                    'sort'      => $rule['sort'],
                );
            }
        }

        $productSort = array();
        foreach ($data['product'] as $product)
        {
            foreach ($product['promotion_rule'] as $id)
            {
                if ( ! isset($promotionLimitBuy[$id]))
                {
                    continue;
                }
                $productBn = $product['product_bn'];
                if ( ! isset($productSort[$productBn]) OR $promotionLimitBuy[$id]['sort'] > $productSort[$productBn])
                {
                    $productSort[$productBn] = $promotionLimitBuy[$id]['sort'];
                    $return[$productBn] = array(
                        'max_amount' => $promotionLimitBuy[$id]['max_amount'],
                        'tips' => $promotionLimitBuy[$id]['tips'],
                        'refresh_time' => $promotionLimitBuy[$id]['refresh_time'],
                    );
                }
            }
        }

        return $return;
    }

}
