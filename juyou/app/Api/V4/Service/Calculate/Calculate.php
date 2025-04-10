<?php

namespace App\Api\V4\Service\Calculate;

use App\Api\Logic\Service;
use App\Api\Logic\Freight;
use App\Api\Model\Promotion\CalculateLog;

/**
 * Description of Calculate
 *
 * @author zhaolong
 */
class Calculate
{

    private $serviceLogic;
    private $companyId;
    private $memberId;
    private $productList;
    private $delivery;
    private $delivery_time;
    private $region_info;
    private $member_point;
    private $assetsList = [
        'point'        => [],
        'voucher'      => [],
        'freeshipping' => [],
        'dutyfree'     => []
    ];
    private $resultData = array(
        'price'       => [
            "goods_amount"   => 0,
            'freight_amount' => 0,
            "taxfees_amount" => 0
        ],
        'discount'    => [
            'promotion'    => 0,
            "voucher"      => 0,
            "freeshipping" => 0,
            "dutyfree"     => 0,
            "point"        => [],
            "point_money"  => []
        ],
        "extend_data" => [
            "point"        => [],
            'voucher'      => [],
            'freeshipping' => [],
            'dutyfree'     => []
        ]
    );

    /**
     *
     * @param type $memberInfo
     * @param type $goodsList
     * @param type $assetsList
     * @param type $delivery
     * @param array $extendData
     * @param array $region_info
     * @param int $region_info
     * @return type
     */
    public function run(
        $memberInfo,
        $goodsList,
        $assetsList,
        $delivery,
        $extendData = array(),
        $region_info = array(),
        $delivery_time = 0
    ) {
        $this->serviceLogic = new Service();
        //初始化资产
        $this->assetsList['point']        = isset($assetsList['point']) ? $assetsList['point'] : array();
        $this->assetsList['voucher']      = is_array($assetsList['voucher']) ? $assetsList['voucher'] : array();
        $this->assetsList['freeshipping'] = is_array($assetsList['freeshipping']) ? $assetsList['freeshipping'] : array();
        $this->assetsList['dutyfree']     = is_array($assetsList['dutyfree']) ? $assetsList['dutyfree'] : array();

        //初始化用户属性
        $this->companyId = $memberInfo['company_id'];
        $this->memberId  = $memberInfo['member_id'];

        $goodsBnList   = [];
        $goodsIdList   = [];
        $productBnList = [];
        foreach ($goodsList as $product) {
            $goodsIdList[]   = $product['goods_id'];
            $goodsBnList[]   = $product['goods_bn'];
            $productBnList[] = $product['product_bn'];
        }
        $storeDB          = app('api_db')->connection('neigou_store');
        $goodsMallList    = $storeDB->table('mall_module_mall_goods')
            ->select('mall_id', 'goods_id', 'bn')
            ->whereIn('goods_id', array_filter($goodsIdList))
            ->get();
        $goodsMallListArr = [];
        foreach ($goodsMallList as $item) {
            $goodsMallListArr[$item->bn][] = $item->mall_id;
        }

        $goodsExtList    = $storeDB->table('sdb_b2c_goods as goods')
            ->leftJoin('sdb_b2c_mall_goods_cat as cat', 'goods.mall_goods_cat', '=', 'cat.cat_id')
            ->select('goods.bn', 'goods.ziti', 'goods.jifen_pay', 'goods.brand_id', 'goods.brand_id', 'cat.cat_path')
            ->whereIn('bn', array_filter($goodsBnList))
            ->get();
        $goodsExtListArr = [];
        foreach ($goodsExtList as $item) {
            $item->cat_path     = trim($item->cat_path, ',');
            $item->mall_list_id = isset($goodsMallListArr[$item->bn]) ? implode(',', $goodsMallListArr[$item->bn]) : '';

            $goodsExtListArr[$item->bn] = json_decode(json_encode($item), true);
        }

        //o2o免邮
        $o2oProductList   = $storeDB->table('o2o_products')
            ->select('bn')
            ->whereIn('bn', array_filter($productBnList))
            ->get();
        $o2oProductBnList = $o2oProductList->count() ? array_column($o2oProductList->toArray(), 'bn') : [];


        //初始化商品列表
        $totalTaxfees = 0;
        foreach ($goodsList as $product) {
            $product = isset($goodsExtListArr[$product['goods_bn']]) ?
                array_merge($product, $goodsExtListArr[$product['goods_bn']]) : $product;
            if (in_array($product['product_bn'], $o2oProductBnList) || $product['ziti']) {
                $product['is_free_shipping'] = 1;
            }

            $this->productList[$product['product_bn']] = $this->initProduct($product);

            $totalTaxfees += $this->productList[$product['product_bn']]['taxfees']['cost_tax'];
        }
        $this->resultData['price']['taxfees_amount'] = $this->number2price($totalTaxfees);

        //初始化地址信息
        $this->delivery      = $delivery;
        $this->region_info   = $region_info;
        $this->delivery_time = $delivery_time;

        //运营活动
        $promotionRes = $this->promotionProsess();
        if (!$promotionRes['status']) {
            return $promotionRes;
        }

        //运费计算
        $freightRes = $this->freightProsess();
        if (!$freightRes['status']) {
            return $freightRes;
        }

        //免邮券计算
        $freeshippingRes = $this->freeshippingProsess();
        if (!$freeshippingRes['status']) {
            return $freeshippingRes;
        }

        //免税券计算
        $taxationRes = $this->taxationProsess();
        if (!$taxationRes['status']) {
            return $taxationRes;
        }

        //内购券计算
        $voucherRes = $this->voucherProsess();
        if (!$voucherRes['status']) {
            return $voucherRes;
        }

        //积分计算
        $pointRes = $this->pointProsess();
        if (!$pointRes['status']) {
            return $pointRes;
        }

        $this->resultData['product_list'] = $this->productList;

        return [
            "status" => true,
            "data"   => $this->resultData
        ];
    }

    private function pointProsess()
    {
        if (!$this->assetsList['point']['channel']) {
            return [
                "status" => true
            ];
        }

        $pointChannel       = $this->assetsList['point']['channel'];
        $this->member_point = $this->getMemberPoint($this->memberId, $this->companyId, $pointChannel);

        $filterProductData = array();
        foreach ($this->productList as $productInfo) {
            if ($productInfo['jifen_pay']) {
                $filterProductData[] = array(
                    'goods_bn' => $productInfo['goods_bn']
                );
            }
        }

        $withRuleParm = array(
            'company_id'  => $this->companyId,
            'member_id'   => $this->memberId,
            'channel'     => $pointChannel,
            'filter_data' => array(
                'product' => $filterProductData
            )
        );

        $withRuleRes = $this->serviceLogic->ServiceCall('scene_point_v3_with_rule', $withRuleParm);
        if ('SUCCESS' != $withRuleRes['error_code'] || !$withRuleRes['data']['product']) {
            \Neigou\Logger::Debug("calculate.v2.false", array(
                'action'          => 'point_with_rule',
                "request_params"  => $withRuleParm,
                "response_result" => $withRuleRes
            ));
            return [
                "status" => true
            ];
        }

        foreach ($withRuleRes['data']['product'] as $withProductRule) {
            $sceneId         = $withProductRule['scene_id'];
            $orderPointMoney = 0;
            //订单可用积分抵扣数 = 商品可用积分抵扣数(商品金额-运营活动抵扣-优惠券抵扣) + 运费积分抵扣数(运费总计-运费抵扣)
            foreach ($this->productList as $bn => $productInfo) {
                if (array_key_exists($productInfo['goods_bn'], $withProductRule['goods_list'])) {
                    if ($productInfo['jifen_pay']) {
                        $orderPointMoney = $this->number2price($orderPointMoney + $productInfo['split_price']['cash_amount'] + $productInfo['freight']['cost_freight'] - $productInfo['freight']['freight_discount']);
                    } else {
                        $orderPointMoney = $this->number2price($orderPointMoney + $productInfo['freight']['cost_freight'] - $productInfo['freight']['freight_discount']);
                    }
                }
            }

            $orderPoint = $this->money2point($orderPointMoney, $pointChannel, $sceneId);

            $this->resultData['extend_data']['point'][$sceneId] = array(
                'scene_id'               => $sceneId,
                'deductible_point'       => $orderPoint > 0 ? $orderPoint : 0,
                'deductible_point_price' => $orderPointMoney > 0 ? $orderPointMoney : 0
            );
        }

        if (!isset($this->assetsList['point']['use_point']) || count($this->assetsList['point']['use_point']) != 1) {
            return [
                "status" => true
            ];
        }

        foreach ($this->assetsList['point']['use_point'] as $sceneId => $curPointData) {
            //当前场景账户余额
            $curMemberPoint     = is_numeric($this->member_point[$sceneId]['point']) ? $this->member_point[$sceneId]['point'] : 0;
            $sceneDiscountPoint = is_numeric($this->resultData['extend_data']['point'][$sceneId]['deductible_point']) ? $this->resultData['extend_data']['point'][$sceneId]['deductible_point'] : 0;
            //计算最大可使用积分数 最大可用积分策略 min(用户输入，用户剩余积分，订单可用积分数)
            $sceneMaxDiscountPoint = min($curPointData['point'], $curMemberPoint, $sceneDiscountPoint);

            $this->resultData['discount']['point'][$sceneId] = [
                'scene_id' => $sceneId,
                'point'    => $sceneMaxDiscountPoint,
            ];

            $this->resultData['discount']['point_money'][$sceneId] = [
                'scene_id'    => $sceneId,
                'point_money' => $this->point2money($sceneMaxDiscountPoint, $pointChannel, $sceneId)
            ];

            foreach ($this->productList as $bn => $productInfo) {
                $this->productList[$bn]['point'] = [
                    $sceneId => [
                        'scene_id'    => $sceneId,
                        'point'       => 0,
                        'point_money' => 0,
                    ]
                ];

                $this->productList[$bn]['split_price']['use_point_money'] = [
                    $sceneId => [
                        'scene_id'    => $sceneId,
                        'point_money' => 0,
                    ]
                ];
            }

            if (!$sceneMaxDiscountPoint) {
                continue;
            }

            $curSceneRule      = $withRuleRes['data']['product'][$sceneId];
            $productPointSplit = array();
            foreach ($this->productList as $bn => $productInfo) {
                if (!array_key_exists($productInfo['goods_bn'], $curSceneRule['goods_list'])) {
                    continue;
                }

                $productFreight = $productInfo['freight']['cost_freight'] - $productInfo['freight']['freight_discount'];
                if ($productInfo['jifen_pay']) {
                    $goodsMaxUsePoint = $this->money2point(
                        $productInfo['split_price']['cash_amount'] + $productFreight - $productInfo['point']['point_money'],
                        $pointChannel,
                        $sceneId
                    );
                } else {
                    $goodsMaxUsePoint = $this->money2point(
                        $productFreight - $productInfo['point']['point_money'],
                        $pointChannel,
                        $sceneId
                    );
                }

                $goodsUsePoint = ($goodsMaxUsePoint / $sceneDiscountPoint) * $sceneMaxDiscountPoint;
                if ($goodsMaxUsePoint - $goodsUsePoint < 0) {
                    $goodsUsePoint = $goodsMaxUsePoint;
                }

                $goodsUsePointMoney = $this->point2money($goodsUsePoint, $pointChannel, $sceneId);
                $goodsUsePoint      = $this->money2point($goodsUsePointMoney, $pointChannel, $sceneId);

                $this->productList[$bn]['point'] = [
                    $sceneId => [
                        'scene_id'    => $sceneId,
                        'point'       => $goodsUsePoint,
                        'point_money' => $goodsUsePointMoney,
                    ]
                ];

                $this->productList[$bn]['split_price']['use_point_money'] = [
                    $sceneId => [
                        'scene_id'    => $sceneId,
                        'point_money' => $goodsUsePointMoney,
                    ]
                ];

                $this->productList[$bn]['split_price']['cash_amount'] = $this->number2price($this->productList[$bn]['split_price']['cash_amount'] + $productFreight - $goodsUsePointMoney);

                $productPointSplit[$sceneId][$bn] = $goodsUsePoint;
            }
            asort($productPointSplit[$sceneId]);
            // 如果进位后金额与优惠券金额不一致 保留后一位做减法
            if (abs(array_sum($productPointSplit[$sceneId]) - $sceneMaxDiscountPoint) > 0.001) {
                $key = array_pop(array_keys($productPointSplit[$sceneId]));
                //还原商品原始现金金额
                $this->productList[$key]['split_price']['cash_amount'] += $this->productList[$key]['point']['point_money'];
                //重新做积分抵扣
                $differ       = $sceneMaxDiscountPoint - array_sum($productPointSplit[$sceneId]);
                $resetProduct = &$this->productList[$key];

                $resetProduct['point'][$sceneId]['point'] = $this->number2price(
                    $resetProduct['point'][$sceneId]['point'] + $differ
                );

                $resetProduct['point'][$sceneId]['point_money'] = $this->point2money(
                    $resetProduct['point'][$sceneId]['point'],
                    $pointChannel,
                    $sceneId
                );

                $resetProduct['split_price']['use_point_money'][$sceneId]['point_money'] = $resetProduct['point'][$sceneId]['point_money'];

                $resetProduct['split_price']['cash_amount'] = $this->number2price($resetProduct['split_price']['cash_amount'] - $resetProduct['point'][$sceneId]['point_money']);
            }
        }
        \Neigou\Logger::Debug("calculate.debug.split", array(
            'action'          => 'point',
            "request_params"  => $this->assetsList['point'],
            "response_result" => $this->member_point,
            "sparam1"         => json_encode($this->productList)
        ));
        return [
            "status" => true
        ];
    }

    private function voucherProsess()
    {
        if (!$this->assetsList['voucher']) {
            return [
                "status" => true
            ];
        }
        $postData = [
            'json_data' => [
                'version'      => 6,
                'newcart'      => 1,
                'voucher_data' => []
            ]
        ];
        foreach ($this->assetsList['voucher'] as $voucherNumArr => $voucherInfo) {
            $voucherData = [
                'voucher_number' => $voucherNumArr,
                'filter_data'    => [
                    'products' => []
                ]
            ];
            foreach ($voucherInfo['match_product_bn'] as $productBn) {
                $productInfo                              = $this->productList[$productBn];
                $voucherData['filter_data']['products'][] = [
                    "bn"       => $productInfo['product_bn'],
                    "goods_id" => $productInfo['goods_id'],
                    "price"    => $productInfo['price'],
                    "quantity" => $productInfo['quantity'],
                    "tax"      => $productInfo['taxfees']['cost_tax']
                ];
            }
            $postData['json_data']['voucher_data'][] = $voucherData;
        }
        $ret = $this->serviceLogic->ServiceCall('voucher_with_rule', $postData);
        if ('SUCCESS' != $ret['error_code']) {
            \Neigou\Logger::Debug(
                "calculate.v2.false",
                array(
                    'action'          => 'voucher_with_rule',
                    "request_params"  => $postData,
                    "response_result" => $ret
                )
            );
            $msg = $ret['error_msg'];
            return [
                "status" => false,
                "msg"    => $msg ? $msg : "优惠券异常",
                "code"   => '1013'
            ];
        }
        $voucherData = $ret['data'];
        //券抵扣金额拆分到products
        foreach ($voucherData['voucher_data'] as $userVoucherArr) {
            $useStatus = $userVoucherArr['result']['status'];
            if ($useStatus) {
                $productSubtotalList       = array();
                $productOldVoucherDiscount = array();
                $matchUseMoney             = $userVoucherArr['result']['match_use_money'] ? $this->number2price($userVoucherArr['result']['match_use_money']) : 0;
                foreach ($userVoucherArr['result']['product_bn_list'] as $productBn) {
                    $productSubtotalList[$productBn]       = $this->productList[$productBn]['split_price']['cash_amount'];
                    $productOldVoucherDiscount[$productBn] = $this->productList[$productBn]['split_price']['voucher_discount'];
                }
                $maxProductPrice = array_sum($productSubtotalList);
                if ($maxProductPrice < $matchUseMoney) {
                    $matchUseMoney = $maxProductPrice;
                    if ($maxProductPrice <= 0) {
                        $this->resultData["extend_data"]['voucher'][$userVoucherArr['voucher_number']] = [
                            "use_status"      => $useStatus,
                            "use_money"       => 0,
                            "need_money"      => isset($userVoucherArr['result']['need_money']) ? $this->number2price($userVoucherArr['result']['need_money']) : $this->number2price($userVoucherArr['result']['limit_cost']),
                            "limit_cost"      => $userVoucherArr['result']['limit_cost'] ? $userVoucherArr['result']['limit_cost'] : 0,
                            "product_bn_list" => $userVoucherArr['result']['product_bn_list'] ? $userVoucherArr['result']['product_bn_list'] : [],
                        ];
                        continue;
                    }
                }

                $this->resultData['discount']['voucher'] = $this->number2price($this->resultData['discount']['voucher'] + $matchUseMoney);

                //参与使用券的商品总额
                $goodsSubtotal = array_sum($productSubtotalList);

                $productDiscount = array();
                foreach ($productSubtotalList as $bn => $productPrice) {
                    $productVoucherDiscount = $this->number2price($productPrice / $goodsSubtotal * $matchUseMoney);
                    $productDiscount[$bn]   = $productVoucherDiscount;

                    $this->productList[$bn]['split_price']['voucher_discount'] = $productVoucherDiscount;

                    $this->productList[$bn]['split_price']['voucher_list'][$userVoucherArr['voucher_number']] = $productVoucherDiscount;

                    $this->productList[$bn]['split_price']['cash_amount'] = $this->number2price($this->productList[$bn]['split_price']['cash_amount'] - $productVoucherDiscount);
                }
                //确保要减去的值不能为0
                asort($productDiscount);
                //如果进位后金额与优惠券金额不一致 保留后一位做减法
                if (abs($matchUseMoney - array_sum($productDiscount)) > 0.001) {
                    $key = array_pop(array_keys($productDiscount));

                    $differ = $matchUseMoney - array_sum($productDiscount);

                    $this->productList[$key]['split_price']['voucher_discount'] = $this->number2price($this->productList[$key]['split_price']['voucher_discount'] + $differ);

                    $this->productList[$key]['split_price']['voucher_list'][$userVoucherArr['voucher_number']] = $this->number2price($this->productList[$key]['split_price']['voucher_discount'] + $differ);

                    $this->productList[$key]['split_price']['cash_amount'] = $this->number2price($this->productList[$key]['split_price']['cash_amount'] - $differ);
                }

                foreach ($productSubtotalList as $bn => $productPrice) {
                    $this->productList[$bn]['split_price']['voucher_discount'] = $this->number2price($productOldVoucherDiscount[$bn] + $this->productList[$bn]['split_price']['voucher_discount']);
                }
            }
            $this->resultData["extend_data"]['voucher'][$userVoucherArr['voucher_number']] = [
                "use_status"      => $useStatus,
                "use_money"       => $matchUseMoney,
                "need_money"      => isset($userVoucherArr['result']['need_money']) ? $this->number2price($userVoucherArr['result']['need_money']) : $this->number2price($userVoucherArr['result']['limit_cost']),
                "limit_cost"      => $userVoucherArr['result']['limit_cost'] ? $userVoucherArr['result']['limit_cost'] : 0,
                "product_bn_list" => $userVoucherArr['result']['product_bn_list'] ? $userVoucherArr['result']['product_bn_list'] : [],
            ];
        }
        \Neigou\Logger::Debug("calculate.debug.split", array(
            'action'          => 'voucher_with_rule',
            "request_params"  => $postData,
            "response_result" => $ret,
            "sparam1"         => json_encode($this->productList)
        ));
        return [
            "status" => true
        ];
    }

    private function taxationProsess()
    {
        if (!$this->assetsList['dutyfree']) {
            return [
                "status" => true
            ];
        }
        $postData = [
            'version'      => 6,
            'newcart'      => 1,
            'voucher_data' => []
        ];

        foreach ($this->assetsList['dutyfree'] as $couponId => $couponInfo) {
            $voucherData = [
                'coupon_id'   => $couponId,
                'member_id'   => $this->memberId,
                'company_id'  => $this->companyId,
                'filter_data' => [
                    'products' => []
                ]
            ];
            foreach ($couponInfo['match_product_bn'] as $productBn) {
                $productInfo                              = $this->productList[$productBn];
                $voucherData['filter_data']['products'][] = [
                    "bn"       => $productInfo['product_bn'],
                    "goods_id" => $productInfo['goods_id'],
                    "price"    => $productInfo['price'],
                    "quantity" => $productInfo['quantity'],
                    'tax'      => $productInfo['taxfees_price']
                ];
            }
            $postData['voucher_data'][] = $voucherData;
        }
        $ret = $this->serviceLogic->ServiceCall('dutyfree_with_rule', $postData);
        if ('SUCCESS' != $ret['error_code']) {
            \Neigou\Logger::Debug(
                "calculate.v2.false",
                array(
                    'action'          => 'dutyfree_with_rule',
                    "request_params"  => $postData,
                    "response_result" => $ret
                )
            );
            $msg = $ret['error_msg'];
            return [
                "status" => false,
                "msg"    => $msg ? $msg : "免税券异常",
                "code"   => '1013'
            ];
        }
        $voucherData = $ret['data'];
        //券抵扣金额拆分到products
        foreach ($voucherData['voucher_data'] as $userVoucherArr) {
            $useStatus = $userVoucherArr['result']['status'];
            if ($useStatus) {
                $matchUseMoney  = $userVoucherArr['result']['match_use_money'];
                $productTaxList = array();
                foreach ($userVoucherArr['result']['product_bn_list'] as $productBn) {
                    $productTaxList[$productBn] = $this->productList[$productBn]['taxfees']['cost_tax'] - $this->productList[$productBn]['taxfees']['dutyfree_discount'];
                }
                $goodsTax = array_sum($productTaxList);
                if ($goodsTax < $matchUseMoney) {
                    $matchUseMoney = $goodsTax;
                    if ($goodsTax <= 0) {
                        $this->resultData["extend_data"]['dutyfree'][$userVoucherArr['coupon_id']] = [
                            "use_status"      => $useStatus,
                            "use_money"       => 0,
                            "need_money"      => $userVoucherArr['result']['need_money'] ? $this->number2price($userVoucherArr['result']['need_money']) : 0,
                            "limit_cost"      => $userVoucherArr['result']['limit_cost'] ? $userVoucherArr['result']['limit_cost'] : 0,
                            "product_bn_list" => $userVoucherArr['result']['product_bn_list'] ? $userVoucherArr['result']['product_bn_list'] : [],
                        ];
                        continue;
                    }
                }

                $this->resultData['discount']['dutyfree'] = $this->number2price($this->resultData['discount']['dutyfree'] + $matchUseMoney);
                if ($goodsTax - $matchUseMoney == 0) {
                    foreach ($productTaxList as $productBn => $productPrice) {
                        $this->productList[$productBn]['taxfees']['dutyfree_discount']                          = $this->number2price($productPrice + $this->productList[$productBn]['taxfees']['dutyfree_discount']);
                        $this->productList[$productBn]['taxfees']['voucher_list'][$userVoucherArr['coupon_id']] = $this->number2price($productPrice);
                    }
                } else {
                    $productDiscount = array();
                    foreach ($productTaxList as $bn => $productPrice) {
                        $productVoucherDiscount                                                          = $this->number2price(($productPrice / $goodsTax) * $matchUseMoney);
                        $productDiscount[$bn]                                                            = $productVoucherDiscount;
                        $this->productList[$bn]['taxfees']['dutyfree_discount']                          = $this->number2price($this->productList[$bn]['taxfees']['dutyfree_discount'] + $productVoucherDiscount);
                        $this->productList[$bn]['taxfees']['voucher_list'][$userVoucherArr['coupon_id']] = $productVoucherDiscount;
                    }
                    //确保要减去的值不能为0
                    asort($productDiscount);
                    //如果进位后金额与优惠券金额不一致 保留后一位做减法
                    if (abs($matchUseMoney - array_sum($productDiscount)) > 0.001) {
                        $key                                                                              = array_pop(array_keys($productDiscount));
                        $differ                                                                           = $matchUseMoney - array_sum($productDiscount);
                        $this->productList[$key]['taxfees']['dutyfree_discount']                          = $this->number2price($this->productList[$key]['taxfees']['dutyfree_discount'] + $differ);
                        $this->productList[$key]['taxfees']['voucher_list'][$userVoucherArr['coupon_id']] = $this->productList[$key]['taxfees']['dutyfree_discount'];
                    }
                }
            }
            $this->resultData["extend_data"]['dutyfree'][$userVoucherArr['coupon_id']] = [
                "use_status"      => $useStatus,
                "use_money"       => $matchUseMoney,
                "need_money"      => $userVoucherArr['result']['need_money'] ? $this->number2price($userVoucherArr['result']['need_money']) : 0,
                "limit_cost"      => $userVoucherArr['result']['limit_cost'] ? $userVoucherArr['result']['limit_cost'] : 0,
                "product_bn_list" => $userVoucherArr['result']['product_bn_list'] ? $userVoucherArr['result']['product_bn_list'] : [],
            ];
        }
        \Neigou\Logger::Debug("calculate.debug.split", array(
            'action'          => 'dutyfree_with_rule',
            "request_params"  => $postData,
            "response_result" => $ret,
            "sparam1"         => json_encode($this->productList)
        ));
        return [
            "status" => true
        ];
    }

    private function freightProsess()
    {
        //没有传地址不计算运费
        if (!$this->delivery['shipping_id']) {
            return [
                "status" => true
            ];
        }
        $pars = array(
            'delivery_time' => $this->delivery_time,
            'region_info'   => $this->region_info,
        );
        foreach ($this->productList as $product_info) {
            if (!$product_info['is_free_shipping'] && $product_info['ziti'] != 1) {
                $pars['product_info'][] = array(
                    'product_bn' => $product_info['product_bn'],
                    'weight'     => $product_info['total_weight'],
                    'subtotal'   => $product_info['amount'],
                    'nums'       => $product_info['quantity'],
                );
            }
        }
        if (!$pars['product_info']) {
            return [
                "status" => true
            ];
        }

        $freightLogic = new Freight();
        $freight_list = $freightLogic->getProductDeliveryFreight($pars);

        foreach ($freight_list as $freight_info) {
            $this->resultData['price']['freight_amount'] = $this->number2price(bcadd(
                $this->resultData['price']['freight_amount'],
                $freight_info['freight'],
                2
            ));
            if (count($pars['product_info']) === 1) {
                $this->productList[$freight_info['product_bn'][0]]['freight']['cost_freight'] = $this->number2price($freight_info['freight']);
            } else {
                $allQuantity = 0;
                foreach ($freight_info['product_bn'] as $product_bn) {
                    $allQuantity += $this->productList[$product_bn]['quantity'];
                }

                $productFreightArr = [];
                foreach ($freight_info['product_bn'] as $product_bn) {
                    $productFreight                                            = $this->number2price(
                        ($this->productList[$product_bn]['quantity'] / $allQuantity) * $freight_info['freight'],
                        0,
                        0
                    );
                    $productFreightArr[$product_bn]                            = $productFreight;
                    $this->productList[$product_bn]['freight']['cost_freight'] = $productFreight;
                }
                //确保要减去的值不能为0
                asort($productFreightArr);
                //如果进位后金额与优惠券金额不一致 保留后一位做减法
                if (abs($freight_info['freight'] - array_sum($productFreightArr)) > 0.001) {
                    $key    = array_pop(array_keys($productFreightArr));
                    $differ = $freight_info['freight'] - array_sum($productFreightArr);

                    $this->productList[$key]['freight']['cost_freight'] = $this->number2price(
                        $this->productList[$key]['freight']['cost_freight'] + $differ,
                        2
                    );
                }
            }
        }
        return [
            "status" => true
        ];
    }

    private function freeshippingProsess()
    {
        if (!$this->assetsList['freeshipping']) {
            return [
                "status" => true
            ];
        }
        $postData = [
            'version'      => 6,
            'newcart'      => 1,
            'voucher_data' => []
        ];
        foreach ($this->assetsList['freeshipping'] as $couponId => $couponInfo) {
            $voucherData = [
                'coupon_id'   => $couponId,
                'member_id'   => $this->memberId,
                'company_id'  => $this->companyId,
                'filter_data' => [
                    'products' => []
                ]
            ];
            foreach ($couponInfo['match_product_bn'] as $productBn) {
                $productInfo                              = $this->productList[$productBn];
                $voucherData['filter_data']['products'][] = [
                    "bn"       => $productInfo['product_bn'],
                    "goods_id" => $productInfo['goods_id'],
                    "price"    => $productInfo['price'],
                    "quantity" => $productInfo['quantity'],
                    'freight'  => $productInfo['freight']['cost_freight']
                ];
            }
            $postData['voucher_data'][] = $voucherData;
        }
        $ret = $this->serviceLogic->ServiceCall('freeshipping_with_rule', $postData);
        if ('SUCCESS' != $ret['error_code']) {
            \Neigou\Logger::Debug("calculate.v2.false",
                array('action' => 'freeshipping_with_rule', "request_params" => $postData, "response_result" => $ret));
            $msg = $ret['error_msg'];
            return [
                "status" => false,
                "msg"    => $msg ? $msg : "免邮券异常",
                "code"   => '1013'
            ];
        }
        $voucherData = $ret['data'];
        //券抵扣金额拆分到products
        foreach ($voucherData['voucher_data'] as $userVoucherArr) {
            $useStatus = $userVoucherArr['result']['status'];
            if ($useStatus) {
                $matchUseMoney      = $userVoucherArr['result']['match_use_money'];
                $productFreightList = array();
                foreach ($userVoucherArr['result']['product_bn_list'] as $productBn) {
                    $productFreightList[$productBn] = $this->productList[$productBn]['freight']['cost_freight'] - $this->productList[$productBn]['freight']['freight_discount'];
                }
                $goodsFreight = array_sum($productFreightList);
                if ($goodsFreight < $matchUseMoney) {
                    $matchUseMoney = $goodsFreight;
                    if ($goodsFreight <= 0) {
                        $this->resultData["extend_data"]['freeshipping'][$userVoucherArr['coupon_id']] = [
                            "use_status"      => $useStatus,
                            "use_money"       => 0,
                            "need_money"      => $userVoucherArr['result']['need_money'] ? $this->number2price($userVoucherArr['result']['need_money']) : 0,
                            "limit_cost"      => $userVoucherArr['result']['limit_cost'] ? $userVoucherArr['result']['limit_cost'] : 0,
                            "product_bn_list" => $userVoucherArr['result']['product_bn_list'] ? $userVoucherArr['result']['product_bn_list'] : [],
                        ];
                        continue;
                    }
                }

                $this->resultData['discount']['freeshipping'] = $this->number2price($this->resultData['discount']['freeshipping'] + $matchUseMoney);
                if ($goodsFreight - $matchUseMoney == 0) {
                    foreach ($productFreightList as $productBn => $productPrice) {
                        $this->productList[$productBn]['freight']['freight_discount']                           = $this->number2price($productPrice + $this->productList[$productBn]['freight']['freight_discount']);
                        $this->productList[$productBn]['freight']['voucher_list'][$userVoucherArr['coupon_id']] = $this->number2price($productPrice);
                    }
                } else {
                    $productDiscount = array();
                    foreach ($productFreightList as $bn => $productPrice) {
                        $productVoucherDiscount                                                          = $this->number2price(($productPrice / $goodsFreight) * $matchUseMoney);
                        $productDiscount[$bn]                                                            = $productVoucherDiscount;
                        $this->productList[$bn]['freight']['freight_discount']                           = $this->number2price($this->productList[$bn]['freight']['freight_discount'] + $productVoucherDiscount);
                        $this->productList[$bn]['freight']['voucher_list'][$userVoucherArr['coupon_id']] = $productVoucherDiscount;
                    }
                    //确保要减去的值不能为0
                    asort($productDiscount);
                    //如果进位后金额与优惠券金额不一致 保留后一位做减法
                    if (abs($matchUseMoney - array_sum($productDiscount)) > 0.001) {
                        $key                                                                              = array_pop(array_keys($productDiscount));
                        $differ                                                                           = $matchUseMoney - array_sum($productDiscount);
                        $this->productList[$key]['freight']['freight_discount']                           = $this->number2price($this->productList[$key]['freight']['freight_discount'] + $differ);
                        $this->productList[$key]['freight']['voucher_list'][$userVoucherArr['coupon_id']] = $this->productList[$key]['freight']['freight_discount'];
                    }
                }
            }
            $this->resultData["extend_data"]['freeshipping'][$userVoucherArr['coupon_id']] = [
                "use_status"      => $useStatus,
                "use_money"       => $matchUseMoney,
                "need_money"      => $userVoucherArr['result']['need_money'] ? $this->number2price($userVoucherArr['result']['need_money']) : 0,
                "limit_cost"      => $userVoucherArr['result']['limit_cost'] ? $userVoucherArr['result']['limit_cost'] : 0,
                "product_bn_list" => $userVoucherArr['result']['product_bn_list'] ? $userVoucherArr['result']['product_bn_list'] : [],
            ];
        }
        \Neigou\Logger::Debug("calculate.debug.split", array(
            'action'          => 'freeshipping_with_rule',
            "request_params"  => $postData,
            "response_result" => $ret,
            "sparam1"         => json_encode($this->productList)
        ));
        return [
            "status" => true
        ];
    }

    private function promotionProsess()
    {
        $postData = [
            'company_id' => $this->companyId,
            'member_id'  => $this->memberId,
            'product'    => []
        ];
        foreach ($this->productList as $productInfo) {
            $product               = array(
                "id"           => $productInfo['product_id'],
                "bn"           => $productInfo['product_bn'],
                "goods_id"     => $productInfo['goods_id'],
                "shop_id"      => $productInfo['shop_id'],
                "brand_id"     => $productInfo['brand_id'],
                "cat_path"     => $productInfo['cat_path'],
                "mall_list_id" => $productInfo['mall_list_id'],
                "name"         => $productInfo['product_name'],
                "nums"         => $productInfo['quantity'],
                "price"        => $productInfo['price'],
            );
            $postData['product'][] = $product;
        }

        $ret = $this->serviceLogic->ServiceCall('get_promotion_goods', $postData);
        if ('SUCCESS' != $ret['error_code']) {
            \Neigou\Logger::Debug("calculate.v2.false", array(
                'action'          => 'get_promotion',
                "request_params"  => $postData,
                "response_result" => $ret
            ));
            return [
                "status" => false,
                "msg"    => '运营服务失败',
                "code"   => '1011'
            ];
        }
        $promotionAll     = $ret['data']['all'];
        $promotionCalc    = $ret['data']['calc'];
        $promotionProduct = $ret['data']['product'];
        if (!is_numeric($promotionAll['amount']) || !is_numeric($promotionAll['discount'])) {
            \Neigou\Logger::Debug("calculate.v2.false", array(
                'action'          => 'get_promotion',
                "request_params"  => $postData,
                "response_result" => $ret
            ));
            return [
                "status" => false,
                "msg"    => '运营服务失败',
                "code"   => '1012'
            ];
        }
        foreach ($promotionProduct as $productInfo) {
            foreach ($productInfo['rule'] as $ruleInfo) {
                if ($ruleInfo['type'] == 'limit_buy') {
                    $this->productList[$productInfo['bn']]['limit_buy']['min_num']  = $ruleInfo['min_num'];
                    $this->productList[$productInfo['bn']]['limit_buy']['max_num']  = $ruleInfo['max_buy'];
                    $this->productList[$productInfo['bn']]['limit_buy']['max_sale'] = $ruleInfo['max_sale'];
                }
            }
        }
        //根据运营数据重新计算价格
        foreach ($promotionCalc as $proProduct) {
            $bn                                               = $proProduct['product_bn'];
            $productInfo                                      = &$this->productList[$bn];
            $productInfo['amount']                            = $this->number2price($proProduct['total']);
            $productInfo['split_price']['cash_amount']        = $this->number2price($productInfo['amount']);
            $productInfo['split_price']['promotion_discount'] = $this->number2price($proProduct['discount']);
            $productInfo['is_free_shipping']                  = $productInfo['is_free_shipping'] ? $productInfo['is_free_shipping'] : $proProduct['free_shipping'];
        }
        $this->resultData['price']['goods_amount'] = $this->number2price($promotionAll['amount'] + $promotionAll['discount']);
        $this->resultData['discount']['promotion'] = $this->number2price($promotionAll['discount']);
        return [
            "status" => true
        ];
    }

    private function getMemberPoint($memberId, $companyId, $pointChannel)
    {
        $res = $this->serviceLogic->ServiceCall(
            'get_member_point',
            [
                'member_id'  => $memberId,
                'company_id' => $companyId,
                'channel'    => $pointChannel
            ],
            'v3'
        );
        if ('SUCCESS' == $res['error_code']) {
            foreach ($res['data'] as &$account) {
                $account['scene_id']   = $account['account'];
                $account['scene_name'] = $account['account_name'];
                unset($account['account']);
                unset($account['account_name']);
            }
            return $res['data'];
        }
        return array();
    }

    /**
     * 各种积分转钱
     */
    private function point2money($point = 0, $channel = '', $account = '')
    {
        if (!$point || !$channel) {
            return 0;
        }
        $rate1 = 0;
        if ($account) {
            $rate1 = $this->member_point[$account]['exchange_rate'] ?? 0;
        }

        if (!$rate1) {
            $info  = $this->pointInfo($channel);
            $rate1 = $info['exchange_rate'] ?? 0;
        }

        $rate   = 1 / $rate1;
        $money1 = $point * $rate;
        $money  = $this->number2price($money1);
        return $money;
    }

    /**
     * 钱转积分
     */
    private function money2point($money = 0, $channel = '', $account = '')
    {
        if (!$money || !$channel) {
            return 0;
        }
        $rate1 = 0;
        if ($account) {
            $rate1 = $this->member_point[$account]['exchange_rate'] ?? 0;
        }

        if (!$rate1) {
            $info  = $this->pointInfo($channel);
            $rate1 = $info['exchange_rate'] ?? 0;
        }

        $expArr    = explode('.', $rate1 / 100);
        $precision = isset($expArr[1]) ? strlen($expArr[1]) : 0;
        $rate      = 1 / $rate1;
        $point     = $this->number2price($money / $rate, $precision);
        return $point;
    }

    /**
     * @todo   获取指定渠道积分信息
     */
    private function pointInfo($channel)
    {
        if (!$channel) {
            return array();
        }
        if (isset($this->instanceData[$channel])) {
            return $this->instanceData[$channel];
        }
        $res = $this->serviceLogic->ServiceCall(
            'get_channel_point',
            [
                'channel'    => $channel,
                'member_id'  => $this->memberId,
                'company_id' => $this->companyId
            ]
        );
        if ('SUCCESS' == $res['error_code']) {
            $this->instanceData[$channel] = $res['data'];
            return $res['data'];
        } else {
            \Neigou\Logger::Debug("calculate.v2.false", array(
                'action'  => 'get_freight',
                "sparam1" => json_encode([
                    'channel'    => $channel,
                    'member_id'  => $this->memberId,
                    'company_id' => $this->companyId
                ]),
                "sparam2" => json_encode($res)
            ));
        }
        return array();
    }

    /**
     * 数字转化成价格
     *
     * @param   $number     string  字符串
     * @param   $precision  int     保留的位数
     * @param   $mode       mixed   浮点数处理策略 null or 0 floor 1 ceil 2 round
     * @return string
     */
    private function number2price($number = null, $precision = 2, $mode = 2)
    {
        if (!is_numeric($number)) {
            $number = floatval($number);
        }

        $p = pow(10, $precision);

        // 设置精度计算小数位
        bcscale($precision + 5);

        $number1 = bcmul($number, $p);
        switch ($mode) {
            case 0:
                $number = floor($number1);
                break;
            case 1:
                $number = ceil($number1);
                break;
            case 2:
                $number = round($number1);
                break;
            default:
                $number = floor($number1);
                break;
        }

        return sprintf("%.{$precision}f", bcdiv($number, $p));
    }

    /**
     * 商品明细信息
     * @param $product
     * @return array
     */
    private function initProduct($product)
    {
        $info = array();
        //货品基础属性
        $info['shop_id']               = $product['shop_id'];
        $info['goods_id']              = $product['goods_id'];
        $info['goods_bn']              = $product['goods_bn'];
        $info['product_id']            = $product['product_id'];
        $info['product_bn']            = $product['product_bn'];
        $info['product_name']          = $product['product_name'];
        $info['weight']                = $product['weight'];
        $info['limit_buy']['min_num']  = 0;
        $info['limit_buy']['max_num']  = 0;
        $info['limit_buy']['max_sale'] = 0;
        //货品数量
        $info['quantity'] = $product['quantity'];
        //货品总重量
        $info['total_weight'] = $product['weight'] * $info['quantity'];
        //货品单价
        $info['price'] = $this->number2price($product['price']);
        //货品总价格（货品单价*货品数量）
        $info['amount'] = $this->number2price($info['price'] * $info['quantity']);
        //货品税金
        $taxfees = is_numeric($product['taxfees']) ? $this->number2price($product['taxfees']) : 0;

        $info['taxfees_price'] = $taxfees;
        //货品运营属性
        $info['promotion_rules'] = [];

        //内部获取字段----开始
        $info['brand_id']     = $product['brand_id'];
        $info['cat_path']     = $product['cat_path'];
        $info['mall_list_id'] = $product['mall_list_id'];
        //是否免运费
        $info['is_free_shipping'] = $product['is_free_shipping'];
        //是否支持积分抵扣
        $info['jifen_pay'] = isset($product['jifen_pay']) ? $product['jifen_pay'] : 0;
        //内部获取字段----结束

        //订单结算资产拆分
        $info['split_price'] = [
            'cash_amount'        => $info['amount'],
            'promotion_discount' => 0,
            'voucher_discount'   => 0,
            'use_point_money'    => array(),
            'voucher_list'       => array()
        ];
        //货品使用积分
        $info['point']       = [];
        $info['scnen_point'] = [];

        $info['taxfees'] = [
            'cost_tax'          => $this->number2price($taxfees * $info['quantity']),
            'dutyfree_discount' => 0,
            'voucher_list'      => array()
        ];
        //货品运费
        $info['freight'] = [
            "cost_freight"     => 0,
            'freight_discount' => 0,
            'voucher_list'     => array()
        ];
        return $info;
    }

    /**
     * 保存计算日志
     * @param $temp_order_id
     * @param $data type array
     * @param $version type string
     * @return bool
     */
    public function saveCalculateLog($temp_order_id, $data, $version = 'V2')
    {
        $log_data = [
            'order_id' => $temp_order_id,
            'data'     => json_encode($data),
            'version'  => $version,
        ];
        return CalculateLog::save($log_data);
    }

    public function getCalculateLogByMainOrderId($order_id)
    {
        return CalculateLog::getCalculateLogByMainOrderId($order_id);
    }

}
