<?php

namespace App\Api\V1\Service\Calculate;

use App\Api\Logic\Service;
use App\Api\Model\Promotion\CalculateLog;

/**
 * Description of Calculate
 *
 * @author zhaolong
 */
class Calculate
{

    private $request_data;
    private $goods_list;
    private $shop_list;
    private $instance_data;

    /**
     *
     * @param type $memberInfo
     * @param type $goodsList
     * @param type $assetsList
     * @param type $delivery
     * @param type $extendData
     * @return type
     */
    public function run($memberInfo, $goodsList, $assetsList, $delivery, $extendData = array())
    {
        //初始化资产
        $this->request_data['voucher'] = isset($assetsList['voucher']) && $assetsList['voucher'] ? (is_array($assetsList['voucher']) ? $assetsList['voucher'] : array($assetsList['voucher'])) : array();
        $this->request_data['point'] = isset($assetsList['point']) ? $assetsList['point'] : array();
        $this->request_data['freeshipping'] = isset($assetsList['freeshipping']) ? $assetsList['freeshipping'] : "";
        $this->request_data['dutyfree'] = isset($assetsList['dutyfree']) && $assetsList['dutyfree'] ? (is_array($assetsList['dutyfree']) ? $assetsList['dutyfree'] : array($assetsList['dutyfree'])) : array();

        //初始化用户属性
        $this->request_data['member_id'] = $memberInfo['member_id'];
        $this->request_data['company_id'] = $memberInfo['company_id'];

        //初始化商品列表
        foreach ($goodsList as $product) {
            $this->goods_list[$product['product_bn']] = $this->initProduct($product);
        }

        ksort($this->goods_list);

        //初始化地址信息
        $this->request_data['shipping_id'] = $delivery['shipping_id'];
        $this->request_data['area_id'] = isset($delivery['ship_area']) ? $delivery['ship_area'] : "";

        //调用运营活动
        $flag = $this->promotion_prosess();
        if (!$flag) {
            return [
                "status" => false,
                "msg" => '运营服务失败',
                "code" => '1011'
            ];
        }

        //计算运费
        foreach ($this->goods_list as $product) {
            if (!$product['is_free_shipping'] && $product['ziti'] != 1) {
                if (isset($this->shop_list[$product['shop_id']])) {
                    $this->shop_list[$product['shop_id']]['subtotal'] += $product['amount'];
                    $this->shop_list[$product['shop_id']]['weight'] += $product['total_weight'];
                    $this->shop_list[$product['shop_id']]['shop_bn_list'][] = $product['product_bn'];
                } else {
                    $this->shop_list[$product['shop_id']] = array(
                        'subtotal' => $product['amount'],
                        'weight' => $product['total_weight'],
                        'shipping_id' => $this->request_data['shipping_id'],
                        'shop_id' => $product['shop_id'],
                        'shipping_area' => array(
                            'area_id' => $this->request_data['area_id'],
                        ),
                        "shop_bn_list" => array(
                            $product['product_bn']
                        )
                    );
                }
            }
            $this->request_data['total_taxfees'] += $product['taxfees']['cost_tax'];
        }
        $msg = "";
        $shopListFerightRes = $this->GetFreight($msg);
        if (!$shopListFerightRes) {
            return [
                "status" => false,
                "msg" => $msg ? $msg : "运费获取失败",
                "code" => '1012'
            ];
        }
        $totalStatus = $this->total($msg);
        if (!$totalStatus) {
            return [
                "status" => false,
                "msg" => $msg ? $msg : "计算异常",
                "code" => '1013'
            ];
        }
        return [
            "status" => true,
            "data" => [
                "price" => [
                    "goods_amount" => $this->number2price($this->request_data['goods_amount']),
                    "freight_amount" => $this->number2price($this->request_data['total_feright']),
                    "point_amount" => $this->request_data['point_amount'],
                    "point_amount_price" => $this->number2price($this->request_data['point_amount_price']),
                    "taxfees_amount" => $this->number2price($this->request_data['total_taxfees']),
                ],
                "discount" => [
                    "promotion" => $this->number2price($this->request_data['promotion_discount']),
                    "voucher" => $this->number2price($this->request_data['voucher_discount']),
                    "shipping" => $this->number2price($this->request_data['shipping_discount']),
                    "dutyfree" => $this->number2price($this->request_data['dutyfree_discount']),
                    "point" => $this->request_data['point_amount'],
                ],
                "voucher_operate" => $this->request_data['voucher_operate'],
                "promotion_rules" => $this->request_data['promotion_rules'],
                "product_list" => $this->goods_list,
                "extend_data" => [
                    "deductible_point" => $this->request_data['deductible_point'],
                    "deductible_point_price" => $this->number2price($this->request_data['deductible_point_price']),
                ]
            ]
        ];
    }

    private function total(&$msg)
    {
        $post_data['member_id'] = $this->request_data['member_id'];
        $post_data['company_id'] = $this->request_data['company_id'];
        $post_data['freight'] = $this->request_data['total_feright'];
        $post_data['subtotal'] = $this->request_data['goods_amount'];
        $post_data['voucher']['voucher'] = $this->request_data['voucher'];
        $post_data['voucher']['dutyfree'] = $this->request_data['dutyfree'];
        $post_data['voucher']['shipping'] = $this->request_data['freeshipping'];
        $goods_list = array();
        foreach ($this->goods_list as $productInfo) {
            $product = array(
                "bn" => $productInfo['product_bn'],
                "goods_id" => $productInfo['goods_id'],
                "price" => $productInfo['price'],
                "quantity" => $productInfo['quantity'],
                "tax" => $productInfo['taxfees']['cost_tax'],
            );
            $goods_list[] = $product;
        }
        $post_data['goods'] = $goods_list;
        $post_data['token'] = \App\Api\Common\Common::GetEcStoreSign($post_data);
        $_curl = new \Neigou\Curl();
        $_curl->time_out = 10;
        $resultJsonStr = $_curl->Post(config('neigou.STORE_DOMIN') . '/openapi/operate/total', $post_data);
        $result = json_decode($resultJsonStr, true);
        if ($result['Result'] != 'true' || !$result['Data']) {
            \Neigou\Logger::Debug("calculate.false",
                array('action' => 'total', "sparam1" => json_encode($post_data), "sparam2" => $resultJsonStr));
            $msg = $result['ErrorMsg'];
            return false;
        }
        $voucherOperate = $result['Data']['operate'];
        $voucherOperate['voucher']['price'] = isset($voucherOperate['voucher']['price']) ? $this->number2price($voucherOperate['voucher']['price']) : 0;
        $this->request_data['voucher_operate'] = $voucherOperate;
        //券优惠金额
        $this->request_data['voucher_discount'] = is_numeric($voucherOperate['voucher']['price']) ? $voucherOperate['voucher']['price'] : 0;
        $this->request_data['voucher_operate_used'] = isset($voucherOperate['voucher']['used']) ? $voucherOperate['voucher']['used'] : [];

        $this->request_data['shipping_discount'] = is_numeric($voucherOperate['voucher']['freight']) ? $voucherOperate['voucher']['freight'] : 0;
        $this->request_data['dutyfree_discount'] = is_numeric($voucherOperate['voucher']['dutyfree']['price']) ? $voucherOperate['voucher']['dutyfree']['price'] : 0;
        //使用过免邮券
        $this->request_data['cur_total_feright'] = $this->request_data['total_feright'] - $this->request_data['shipping_discount'];
        //当出现运费抵扣且抵扣金额与实际运费不等则先抛出异常（当前情况一定是相等的）
        if ($this->request_data['cur_total_feright'] != 0 && $this->request_data['cur_total_feright'] != $this->request_data['total_feright']) {
            $msg = "运费抵扣计算异常";
            return false;
        }

        $this->request_data['cur_total_taxfees'] = $this->request_data['total_taxfees'] - $this->request_data['dutyfree_discount'];

        $this->splitCostAmount();
        $this->splitVoucherAmount();
        $this->splitPointAmount();
        return true;
    }

    private function splitCostAmount()
    {
        if ($this->request_data['cur_total_feright']) {
            foreach ($this->shop_list as $shopInfo) {
                $i = 1;
                $goods_count = count($shopInfo['shop_bn_list']);
                $totalAmount = $shopInfo['subtotal'];
                $costValue = $this->request_data['split_freight'][$shopInfo['shop_id']]['total'];
                $waitProportional = $costValue;
                foreach ($shopInfo['shop_bn_list'] as $goodsBn) {
                    $product = &$this->goods_list[$goodsBn];
                    if ($i == $goods_count) {
                        $product['freight']['freight_amount'] = $this->number2price($waitProportional);
                    } else {
                        $proportional = $this->number2price(ceil((($product['amount']) / $totalAmount) * $costValue * 100) / 100);
                        if ($proportional <= 0) {
                            $proportional = 0.01;
                        }
                        if ($waitProportional - $proportional <= 0) {
                            $proportional = $waitProportional;
                        }
                        $product['freight']['freight_amount'] = $this->number2price($proportional);
                        $waitProportional -= $proportional;
                        if ($waitProportional < 0.01) {
                            $waitProportional = 0;
                        }
                    }
                    $i++;
                }
            }
        }

        if ($this->request_data['total_taxfees']) {
            if ($this->request_data['cur_total_taxfees'] == 0) {
                foreach ($this->goods_list as &$goodsInfo) {
                    $goodsInfo['taxfees']['dutyfree_discount'] = $goodsInfo['taxfees']['cost_tax'];
                }
            } else {
                $goodsFreeArr = array();
                foreach ($this->goods_list as &$goodsInfo) {
                    $curGoodsFree = $this->number2price($goodsInfo['taxfees']['cost_tax'] / $this->request_data['total_taxfees'] * $this->request_data['dutyfree_discount'],
                        2);
                    $goodsInfo['taxfees']['dutyfree_discount'] = $curGoodsFree;
                    $goodsFreeArr[$goodsInfo['product_bn']] = $curGoodsFree;
                }
                asort($goodsFreeArr);
                if (abs(array_sum($goodsFreeArr) - $this->request_data['dutyfree_discount']) >= 0.01) {
                    $goodsFreeArrTmp = $goodsFreeArr;
                    $key = array_pop(array_keys($goodsFreeArrTmp));
                    $differ = $this->request_data['dutyfree_discount'] - array_sum($goodsFreeArr);
                    $this->goods_list[$key]['taxfees']['dutyfree_discount'] = $this->number2price($this->goods_list[$key]['taxfees']['dutyfree_discount'] - $this->number2price($this->goods_list[$key]['taxfees']['dutyfree_discount'] - $differ));
                }
            }
        }
        \Neigou\Logger::Debug("calculate.debug.split",
            array('action' => 'splitCostAmount', "sparam1" => json_encode($this->goods_list)));
    }

    private function splitPointAmount()
    {
        // 订单可用积分抵扣数 = 商品可用积分抵扣数 + 运费积分抵扣数
        $orderPointMoney = $this->request_data['total_feright'] - $this->request_data['shipping_discount'];
        $productDiscount = $this->request_data['product_discount_list'];
        foreach ($this->goods_list as $bn => $goods) {
            if ($goods['jifen_pay'] != 1 && array_key_exists($goods['product_bn'], $productDiscount)) {
                unset($productDiscount[$goods['product_bn']]);
            }
            if ($goods['jifen_pay'] == 1) {
                $orderPointMoney = $orderPointMoney + $goods['split_price']['cash_amount'];
            }
        }
        $orderPoint = $this->money2point($this->number2price($orderPointMoney),
            $this->request_data['point']['channel']);
        $this->request_data['deductible_point'] = $orderPoint;
        $this->request_data['deductible_point_price'] = $this->point2money($orderPoint,
            $this->request_data['point']['channel']);
        if ($this->request_data['point']['use_point']) {
            $memberPointData = $this->getMemberPoint();
            $memberPoint = is_numeric($memberPointData['point']) ? $memberPointData['point'] : 0;
            // 计算最大可使用积分数 最大可用积分策略 min(用户输入，用户剩余积分，订单可用积分数)
            $this->request_data['point_amount'] = min($this->request_data['point']['use_point'], $memberPoint,
                $orderPoint);
            $this->request_data['point_amount_price'] = $this->point2money($this->request_data['point_amount'],
                $this->request_data['point']['channel']);
            if ($this->request_data['point_amount'] > 0) {
                $productPointSplit = array();
                foreach ($this->goods_list as $bn => $goodsInfo) {
                    if ($goodsInfo['jifen_pay']) {
                        $maxUsePoint = $this->money2point($goodsInfo['split_price']['cash_amount'] + $goodsInfo['freight']['freight_amount'],
                            $this->request_data['point']['channel']);
                        $goodsUsePoint = $this->number2price(($maxUsePoint / $orderPoint) * $this->request_data['point_amount']);
                    } else {
                        $maxUsePoint = $this->money2point($goodsInfo['freight']['freight_amount'],
                            $this->request_data['point']['channel']);
                        $goodsUsePoint = $this->number2price(($maxUsePoint / $orderPoint) * $this->request_data['point_amount']);
                    }
                    if ($maxUsePoint - $goodsUsePoint <= 0) {
                        $goodsUsePoint = $this->number2price($maxUsePoint);
                    }
                    $pointMoney = $this->point2money($goodsUsePoint, $this->request_data['point']['channel']);
                    $goodsUsePoint = $this->money2point($pointMoney, $this->request_data['point']['channel']);
                    $this->goods_list[$bn]['point']['point'] = $goodsUsePoint;
                    $this->goods_list[$bn]['point']['point_money'] = $pointMoney;
                    $this->goods_list[$bn]['split_price']['use_point_money'] = $pointMoney;
                    $this->goods_list[$bn]['split_price']['cash_amount'] = $this->number2price($this->goods_list[$bn]['split_price']['cash_amount'] + $this->goods_list[$bn]['freight']['freight_amount'] - $pointMoney);
                    $productPointSplit[$bn] = $goodsUsePoint;
                }

                asort($productPointSplit);
                // 如果进位后金额与优惠券金额不一致 保留后一位做减法
                if (abs(array_sum($productPointSplit) - $this->request_data['point_amount']) > 0.001) {
                    $key = array_pop(array_keys($productPointSplit));
                    $differ = $this->request_data['point_amount'] - array_sum($productPointSplit);
                    $differMoney = $this->point2money($this->number2price($differ),
                        $this->request_data['point']['channel']);
                    $this->goods_list[$key]['point']['point'] = $this->number2price($this->goods_list[$key]['point']['point'] + $differ);
                    $this->goods_list[$key]['point']['point_money'] = $this->number2price($this->goods_list[$key]['point']['point_money'] + $differMoney);
                    $this->goods_list[$key]['split_price']['use_point_money'] = $this->number2price($this->goods_list[$key]['split_price']['use_point_money'] + $differMoney);
                    $this->goods_list[$key]['split_price']['cash_amount'] = $this->number2price($this->goods_list[$key]['split_price']['cash_amount'] - $differMoney);
                }
            }
        } else {
            $this->request_data['point_amount'] = 0;
        }
        \Neigou\Logger::Debug("calculate.debug.split",
            array('action' => 'splitPointAmount', "sparam1" => json_encode($this->goods_list)));
    }

    private function splitVoucherAmount()
    {
        //优惠券优惠bn列表
        if ($this->request_data['voucher']) {
            $bnList = array();
            foreach ($this->request_data['voucher'] as $number) {
                if (array_key_exists($number, $this->request_data['voucher_operate_used'])) {
                    $bnList = array_merge((array)$this->request_data['voucher_operate_used'][$number]['bn'], $bnList);
                }
            }
            if ($bnList) {
                $productSubtotalList = array();
                foreach ($this->goods_list as $goods) {
                    if (in_array($goods['product_bn'], $bnList)) {
                        $productSubtotalList[$goods['product_bn']] = $goods['split_price']['cash_amount'];
                    }
                }

                //参与优惠券的商品总金额
                $goodsSubtotal = array_sum($productSubtotalList);

                //根据单品总金额/参与优惠商品总金额 * 优惠券金额 算出各个bn所占比例
                $productDiscount = array();
                foreach ($productSubtotalList as $bn => $productPrice) {
                    $productVoucherDiscount = $this->number2price($productPrice / $goodsSubtotal * $this->request_data['voucher_discount']);
                    $productDiscount[$bn] = $productVoucherDiscount;
                    $this->goods_list[$bn]['split_price']['voucher_discount'] = $productVoucherDiscount;
                    $this->goods_list[$bn]['split_price']['cash_amount'] = $this->number2price($this->goods_list[$bn]['split_price']['cash_amount'] - $productVoucherDiscount);
                }
                //确保要减去的值不能为0
                asort($productDiscount);
                $this->request_data['product_discount_list'] = $productDiscount;
                //如果进位后金额与优惠券金额不一致 保留后一位做减法
                if ($this->request_data['voucher_discount'] > 0 && (abs($this->request_data['voucher_discount'] - array_sum($productDiscount)) > 0.001)) {
                    $key = array_pop(array_keys($productDiscount));
                    $differ = $this->request_data['voucher_discount'] - array_sum($productDiscount);
                    $this->goods_list[$key]['split_price']['voucher_discount'] = $this->number2price($this->goods_list[$key]['split_price']['voucher_discount'] + $differ);
                    $this->goods_list[$key]['split_price']['cash_amount'] = $this->number2price($this->goods_list[$key]['split_price']['cash_amount'] - $differ);
                }
            }
        }
        \Neigou\Logger::Debug("calculate.debug.split",
            array('action' => 'splitVoucherAmount', "sparam1" => json_encode($this->goods_list)));
    }

    private function getMemberPoint()
    {
        $service_logic = new Service();
        $res = $service_logic->ServiceCall('get_member_point', [
            'member_id' => $this->request_data['member_id'],
            'company_id' => $this->request_data['company_id'],
            'channel' => $this->request_data['point']['channel']
        ]);
        if ('SUCCESS' == $res['error_code']) {
            return $res['data'];
        }
        return array();
    }

    /**
     * 各种积分转钱
     */
    private function point2money($point = 0, $channel = '')
    {
        if (!$point || !$channel) {
            return 0;
        }
        $info = $this->pointInfo($channel);
        $rate1 = $info['exchange_rate'] ? $info['exchange_rate'] : 0;
        $rate = 1 / $rate1;
        $money1 = $point * $rate;
        $money = $this->number2price($money1, 2, 0);
        return $money;
    }

    /**
     * 钱转积分
     */
    private function money2point($money = 0, $channel = '')
    {
        if (!$money || !$channel) {
            return 0;
        }
        $info = $this->pointInfo($channel);
        $rate1 = $info['exchange_rate'] ? $info['exchange_rate'] : 0;
        $rate = 1 / $rate1;

        $point = $money / $rate;
        return $point;
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
     * @todo   获取指定渠道积分信息
     */
    private function pointInfo($channel)
    {
        if (!$channel) {
            return array();
        }

        $instance = $channel . $this->request_data['member_id'];

        if (isset($this->instance_data[$instance])) {
            return $this->instance_data[$instance];
        }
        $service_logic = new Service();
        $res = $service_logic->ServiceCall('get_channel_point',
            ['channel' => $channel, 'member_id' => $this->request_data['member_id']]);
        if ('SUCCESS' == $res['error_code']) {
            $this->instance_data[$instance] = $res['data'];
            return $res['data'];
        } else {
            \Neigou\Logger::Debug("calculate.false", array(
                'action' => 'get_freight',
                "sparam1" => json_encode(['channel' => $channel, 'member_id' => $this->request_data['member_id']]),
                "sparam2" => json_encode($res)
            ));
        }
        return array();
    }

    /**
     * 计算运费
     * @return int
     */
    private function getFreight(&$msg)
    {
        if ($this->request_data['shipping_id']) {
            $this->request_data['total_feright'] = 0;
            $this->request_data['split_freight'] = array();
            if ($this->shop_list) {
                $service_logic = new Service();
                $ret = $service_logic->ServiceCall('get_freight', array_values($this->shop_list));
                if ('SUCCESS' == $ret['error_code']) {
                    foreach ($ret['data'] as $v) {
                        $freight = $v['freight'];
                        $this->request_data['total_feright'] += $freight;
                        $this->request_data['split_freight'][$v['shop_id']]['total'] = $freight;
                    }
                } else {
                    \Neigou\Logger::Debug("calculate.false", array(
                        'action' => 'get_freight',
                        "sparam1" => json_encode($this->shop_list),
                        "sparam2" => json_encode($ret)
                    ));
                    $msg = $ret['error_msg'];
                    return false;
                }
            }
        } else {
            $this->request_data['total_feright'] = 0;
        }
        return true;
    }

    /**
     * 获取运营服务计算结果
     * @return bool
     */
    private function get_promotion()
    {
        $goods_list = array();
        foreach ($this->goods_list as $productInfo) {
            $product = array(
                "name" => $productInfo['product_name'],
                "brand_id" => $productInfo['brand_id'],
                "cat_path" => $productInfo['cat_path'],
                "mall_list_id" => $productInfo['mall_list_id'],
                "id" => $productInfo['product_id'],
                "goods_id" => $productInfo['goods_id'],
                "nums" => $productInfo['quantity'],
                "price" => $productInfo['price'],
                "shop_id" => $productInfo['shop_id'],
                "bn" => $productInfo['product_bn'],
            );
            $goods_list[] = $product;
        }
        $member_id = $this->request_data['member_id'];
        $company_id = $this->request_data['company_id'];
        $service_logic = new Service();
        $ret = $service_logic->ServiceCall('get_promotion_goods',
            ['product' => $goods_list, 'company_id' => $company_id, 'member_id' => $member_id]);
        if ('SUCCESS' == $ret['error_code']) {
            $promotion_calc = $ret['data']['calc'];
            $promotion_all = $ret['data']['all'];
            $promotion_rules = $ret['data']['rules'];
            $promotion_product = $ret['data']['product'];
            if (isset($promotion_all['amount']) && is_numeric($promotion_all['amount']) && isset($promotion_all['discount']) && is_numeric($promotion_all['discount'])) {
                $this->request_data['promotion_calc'] = $promotion_calc;
                $this->request_data['promotion_all'] = $promotion_all;
                $this->request_data['promotion_rules'] = $promotion_rules;
                $this->request_data['promotion_product'] = $promotion_product;
                return true;
            }
        } else {
            \Neigou\Logger::Debug("calculate.false", array(
                'action' => 'get_promotion',
                "sparam1" => json_encode([
                    'product' => $goods_list,
                    'company_id' => $company_id,
                    'member_id' => $member_id
                ]),
                "sparam2" => json_encode($ret)
            ));
        }
        return false;
    }

    /**
     * 运营计算信息处理
     */
    private function promotion_prosess()
    {
        $flag = $this->get_promotion();
        if (false == $flag) {
            return false;
        }
        if (!isset($this->request_data['promotion_calc']) || !count($this->request_data['promotion_calc']) > 0) {
            return false;
        }
        foreach ($this->request_data['promotion_calc'] as $goods) {
            $bn = $goods['product_bn'];
            if (isset($this->goods_list[$bn])) {
                //单商品优惠*数量优惠额度
                $this->goods_list[$bn]['split_price']['promotion_discount'] = $this->number2price($goods['discount']);
                $this->goods_list[$bn]['split_price']['cash_amount'] = $this->number2price($this->goods_list[$bn]['split_price']['cash_amount'] - $goods['discount']);
                $this->goods_list[$bn]['amount'] = $this->number2price($this->goods_list[$bn]['amount'] - $goods['discount']);
                $this->goods_list[$bn]['split_price']['cash_amount'] = $this->goods_list[$bn]['amount'];
                //是否免邮
                $this->goods_list[$bn]['is_free_shipping'] = $goods['free_shipping'] || $this->goods_list[$bn]['is_free_shipping'] ? 1 : 0;
            }
        }
        foreach ($this->request_data['promotion_product'] as $goods) {
            $bn = $goods['bn'];
            $this->goods_list[$bn]['promotion_rules'] = $goods['use_rule'];
        }
        //折扣、优惠、减免合计金额
        $this->request_data['promotion_discount'] = $this->number2price($this->request_data['promotion_all']['discount']);
        //优惠后的商品总金额
        $this->request_data['goods_amount'] = $this->number2price($this->request_data['promotion_all']['amount']);
        return true;
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
        $info['shop_id'] = $product['shop_id'];
        $info['brand_id'] = $product['brand_id'];
        $info['cat_path'] = $product['cat_path'];
        $info['mall_list_id'] = $product['mall_list_id'];
        $info['goods_id'] = $product['goods_id'];
        $info['product_id'] = $product['product_id'];
        $info['product_bn'] = $product['product_bn'];
        $info['product_name'] = $product['product_name'];
        $info['weight'] = $product['weight'];
        //是否支持积分抵扣
        $info['jifen_pay'] = isset($product['jifen_pay']) ? $product['jifen_pay'] : 0;
        //货品数量
        $info['quantity'] = $product['quantity'];
        //货品总重量
        $info['total_weight'] = $product['weight'] * $info['quantity'];
        //货品单价
        $info['price'] = $product['price'];
        //货品总价格（货品单价*货品数量）
        $info['amount'] = $this->number2price($info['price'] * $info['quantity']);
        //货品运营属性
        $info['promotion_rules'] = [];
        $info['is_free_shipping'] = $product['is_free_shipping'] ? $product['is_free_shipping'] : false;
        //订单结算资产拆分
        $info['split_price'] = [
            'cash_amount' => $info['amount'],
            'promotion_discount' => 0,
            'voucher_discount' => 0,
            'use_point_money' => 0,
        ];
        //货品使用积分
        $info['point'] = [
            'point' => 0,
            'point_money' => 0
        ];
        //货品税金
        $info['taxfees'] = [
            'cost_tax' => isset($product['taxfees']) ? $product['taxfees'] : 0,
            'dutyfree_discount' => 0
        ];
        //货品运费
        $info['freight'] = [
            "freight_amount" => 0
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
    public function saveCalculateLog($temp_order_id, $data, $version = 'V1')
    {
        $log_data = [
            'order_id' => $temp_order_id,
            'data' => json_encode($data),
            'version' => $version,
        ];
        return CalculateLog::save($log_data);
    }

}
