<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-01-31
 * Time: 15:31
 */

namespace App\Api\Logic\NeigouRule\RuleAnalysis;


use App\Api\Logic\Neigou\Ec;
use App\Api\Model\PointScene\RuleInfo as RuleInfoModel;

class ProductRuleAnalysis extends ARuleAnalysis
{
    private $storeDB = null;

    private function getStoreDB()
    {
        if (!$this->storeDB) {
            $this->storeDB = app('api_db')->connection('neigou_store');
        }
        return $this->storeDB;
    }

    private function initRes($rule_res_all, $filter_key, &$res)
    {
        foreach ($rule_res_all[$filter_key] as $rule_id => $rule_res_item) {
            if (!isset($res[$rule_id])) {
                $res[$rule_id] = [];
            }
        }
    }

    /**
     * 商城使用积分规则解析
     */
    public function WithRule($ruleIdList, $filterData)
    {
        $goodsBnList   = array();
        $goods_bn_arr  = array();
        $goods_bn_2_product_bn  = array();
        $product_bn_2_goods_bn  = array();
        $goodsArr      = array();
        $productBnList = $productArr = $goods2ProductBn = [];
        foreach ($filterData as $goods) {
            if (!isset($goods['goods_bn']) || empty($goods['goods_bn'])) {
                return $this->Response(false, '商品错误', array());
            }
            $goodsBnList[] = "'".  addslashes($goods['goods_bn']) . "'" ;
            $goods_bn_arr[] = $goods['goods_bn'];
            $goodsArr[$goods['goods_bn']] = $goods;

            if ( ! empty($goods['product_bn'])) {
                $productBnList[]                       = "'".addslashes($goods['product_bn'])."'";
                $productArr[$goods['product_bn']]      = $goods;
                $goods2ProductBn[$goods['goods_bn']][] = $goods['product_bn'];
                $goods_bn_2_product_bn[$goods['goods_bn']] = $goods['product_bn'];
                $product_bn_2_goods_bn[$goods['product_bn']] = $goods['goods_bn'];
            }
        }

        $rule_list_all =  RuleInfoModel::GetByIDs($ruleIdList);
        $rule_res_all = [];
        foreach ($rule_list_all as $rule_info) {
            $rule_res_all[$rule_info->filter_key][$rule_info->rule_id] = [];
        }

        $ruleRes = $this->getMallCatRule($ruleIdList, $goodsBnList, $goods_bn_arr);
        $this->initRes($rule_res_all, 'mallcat', $ruleRes);

        $brandRuleRes = $this->getBrandRule($ruleIdList, $goodsBnList);
        $this->initRes($rule_res_all, 'brand', $brandRuleRes);
        if ($brandRuleRes) {
            foreach ($brandRuleRes as $ruleId => $ruleGoodsBnList) {
                if (isset($ruleRes[$ruleId])) {
                    $ruleRes[$ruleId] = array_intersect($ruleRes[$ruleId], $ruleGoodsBnList);
                } else {
                    $ruleRes[$ruleId] = $ruleGoodsBnList;
                }
            }
        }

        $shopRuleRes = $this->getShopRule($ruleIdList, $goodsBnList);
        $this->initRes($rule_res_all, 'shop', $shopRuleRes);
        if ($shopRuleRes) {
            foreach ($shopRuleRes as $ruleId => $ruleGoodsBnList) {
                if (isset($ruleRes[$ruleId])) {
                    $ruleRes[$ruleId] = array_intersect($ruleRes[$ruleId], $ruleGoodsBnList);
                } else {
                    $ruleRes[$ruleId] = $ruleGoodsBnList;
                }
            }
        }

        $priceRuleRes = $this->getPriceRule($ruleIdList, $goods_bn_arr, $goods_bn_2_product_bn, 'price');
        $this->initRes($rule_res_all, 'price', $priceRuleRes);
        if ($priceRuleRes) {
            foreach ($priceRuleRes as $ruleId => $ruleGoodsBnList) {
                if (isset($ruleRes[$ruleId])) {
                    $ruleRes[$ruleId] = array_intersect($ruleRes[$ruleId], $ruleGoodsBnList);
                } else {
                    $ruleRes[$ruleId] = $ruleGoodsBnList;
                }
            }
        }

        $mktPriceRuleRes = $this->getPriceRule($ruleIdList, $goods_bn_arr, $goods_bn_2_product_bn, 'mktprice');
        $this->initRes($rule_res_all, 'mktprice', $mktPriceRuleRes);
        if ($mktPriceRuleRes) {
            foreach ($mktPriceRuleRes as $ruleId => $ruleGoodsBnList) {
                if (isset($ruleRes[$ruleId])) {
                    $ruleRes[$ruleId] = array_intersect($ruleRes[$ruleId], $ruleGoodsBnList);
                } else {
                    $ruleRes[$ruleId] = $ruleGoodsBnList;
                }
            }
        }

        $grossProfitRuleRes = $this->getGrossProfitRule($ruleIdList, $goods_bn_arr, $goods_bn_2_product_bn);
        $this->initRes($rule_res_all, 'gross_profit', $grossProfitRuleRes);
        if ($grossProfitRuleRes) {
            foreach ($grossProfitRuleRes as $ruleId => $ruleGoodsBnList) {
                if (isset($ruleRes[$ruleId])) {
                    $ruleRes[$ruleId] = array_intersect($ruleRes[$ruleId], $ruleGoodsBnList);
                } else {
                    $ruleRes[$ruleId] = $ruleGoodsBnList;
                }
            }
        }

        $mktPriceGrossProfitRuleRes = $this->getMktPriceGrossProfitRule($ruleIdList, $goods_bn_arr, $goods_bn_2_product_bn);
        $this->initRes($rule_res_all, 'mktprice_gross_profit', $mktPriceGrossProfitRuleRes);
        if ($mktPriceGrossProfitRuleRes) {
            foreach ($mktPriceGrossProfitRuleRes as $ruleId => $ruleGoodsBnList) {
                if (isset($ruleRes[$ruleId])) {
                    $ruleRes[$ruleId] = array_intersect($ruleRes[$ruleId], $ruleGoodsBnList);
                } else {
                    $ruleRes[$ruleId] = $ruleGoodsBnList;
                }
            }
        }

        $discountRuleRes = $this->getDiscountRule($ruleIdList, $goods_bn_arr, $goods_bn_2_product_bn);
        $this->initRes($rule_res_all, 'discount', $discountRuleRes);
        if ($discountRuleRes) {
            foreach ($discountRuleRes as $ruleId => $ruleGoodsBnList) {
                if (isset($ruleRes[$ruleId])) {
                    $ruleRes[$ruleId] = array_intersect($ruleRes[$ruleId], $ruleGoodsBnList);
                } else {
                    $ruleRes[$ruleId] = $ruleGoodsBnList;
                }
            }
        }

        $catBrandRuleRes = $this->getCatBrandRule($ruleIdList, $goods_bn_arr, $goodsBnList);
        $this->initRes($rule_res_all, 'cat_brand', $catBrandRuleRes);
        if ($catBrandRuleRes) {
            foreach ($catBrandRuleRes as $ruleId => $ruleGoodsBnList) {
                if (isset($ruleRes[$ruleId])) {
                    $ruleRes[$ruleId] = array_intersect($ruleRes[$ruleId], $ruleGoodsBnList);
                } else {
                    $ruleRes[$ruleId] = $ruleGoodsBnList;
                }
            }
        }

        // 店铺平台
        $wmsRuleRes = $this->getPopWmsRule($ruleIdList, $goodsBnList);
        $this->initRes($rule_res_all, 'pop_wms', $wmsRuleRes);
        if ($wmsRuleRes) {
            foreach ($wmsRuleRes as $ruleId => $ruleGoodsBnList) {
                if (isset($ruleRes[$ruleId])) {
                    $ruleRes[$ruleId] = array_intersect($ruleRes[$ruleId], $ruleGoodsBnList);
                } else {
                    $ruleRes[$ruleId] = $ruleGoodsBnList;
                }
            }
        }

        $goodsRuleRes = $this->getGoodsRule($ruleIdList, $goodsBnList);
        $this->initRes($rule_res_all, 'goods', $goodsRuleRes);
        if ($goodsRuleRes) {
            foreach ($goodsRuleRes as $ruleId => $ruleGoodsBnList) {
                if (isset($ruleRes[$ruleId])) {
                    $is_have_all = false;
                    if (($key = array_search('all', $ruleGoodsBnList)) !== false) {
                        $is_have_all = true;
                        unset($ruleGoodsBnList[$key]);
                    }
                    if ($is_have_all === false || count($ruleGoodsBnList) > 0) {
                        $ruleRes[$ruleId] = array_intersect($ruleRes[$ruleId], $ruleGoodsBnList);
                    }
                } else {
                    $ruleRes[$ruleId] = $ruleGoodsBnList;
                }
            }
        }
        $returnArr = array();
        if ( ! empty($productBnList)) {
            $ruleResByProduct = [];
            $goodsBn2ProductBn = function ($goodsBnArr) use ($goods2ProductBn) {
                $productBnArr = [];

                foreach ($goodsBnArr as $goodsBn) {
                    if ($goodsBn == 'all') {
                        $productBnArr[] = 'all';
                    } elseif ( ! empty($goods2ProductBn[$goodsBn])) {
                        $productBnArr = array_merge($productBnArr, $goods2ProductBn[$goodsBn]);
                    }
                }

                return $productBnArr;
            };

            $ruleProductBnRes = [];
            foreach ($ruleRes as $ruleId => $goodsBnList) {
                $ruleProductBnRes[$ruleId] = $goodsBn2ProductBn($goodsBnList);
            }

            $productRuleRes = $this->getProductRule($ruleIdList, $productBnList);
            $this->initRes($rule_res_all, 'product', $productRuleRes);
            if ($productRuleRes) {
                foreach ($productRuleRes as $ruleId => $ruleProductBnList) {
                    $ruleResByProduct[$ruleId] = [];
                    foreach ($ruleProductBnList as $product_bn) {
                        $ruleResByProduct[$ruleId][] = $product_bn_2_goods_bn[$product_bn];
                    }
//                    for ($i = 0; $i < count($ruleProductBnList); $i++) {
//                        if (!in_array($product_bn_2_goods_bn[$ruleProductBnList[$i]], $ruleRes[$ruleId])) {
//                            unset($ruleProductBnList[$i]);
//                            $i--;
//                        }
//                    }
                    if (isset($ruleProductBnRes[$ruleId])) {
                        $ruleProductBnRes[$ruleId] = array_merge($ruleProductBnRes[$ruleId], $ruleProductBnList);
                    } else {
                        $ruleProductBnRes[$ruleId] = $ruleProductBnList;
                    }
                }
                foreach ($ruleResByProduct as $ruleId => $ruleGoodsBnList) {
                    if (isset($ruleRes[$ruleId]) && $ruleRes[$ruleId][0] != 'all') {
                        $ruleRes[$ruleId] = array_intersect($ruleRes[$ruleId], $ruleGoodsBnList);
                    } else {
                        $ruleRes[$ruleId] = $ruleGoodsBnList;
                    }
                }
            }

            foreach ($ruleProductBnRes as $ruleId => $productBnList) {
                foreach ($productBnList as $bn) {
                    if ($bn == 'all') {
                        foreach ($productArr as $product) {
                            if (in_array($product_bn_2_goods_bn[$product['product_bn']], $ruleRes[$ruleId]) || in_array('all', $ruleRes[$ruleId])) {
                                $returnArr[$ruleId]['product_list'][$product['product_bn']] = $product;
                            }
                        }
                    } else {
                        if (in_array($product_bn_2_goods_bn[$bn], $ruleRes[$ruleId]) || in_array('all', $ruleRes[$ruleId])) {
                            $returnArr[$ruleId]['product_list'][$bn] = $productArr[$bn];
                        }
                    }
                }
            }
        }
        foreach ($ruleRes as $ruleId => $goodsBnList) {
            foreach ($goodsBnList as $goodsBn) {
                if ($goodsBn == 'all') {
                    foreach ($goodsArr as $lGoods) {
                        $returnArr[$ruleId]['goods_list'][$lGoods['goods_bn']] = $lGoods;
                    }
                } else {
                    $returnArr[$ruleId]['goods_list'][$goodsBn] = $goodsArr[$goodsBn];
                }
            }
        }
        return $this->Response(true, '获取成功', $returnArr);
    }

    /**
     * 价格
     */
    private function getPriceRule($rule_id_arr, $goods_bn_arr, $goods_bn_2_product_bn, $price_field)
    {
        $rule_list     = RuleInfoModel::Query('shopping', $price_field, $rule_id_arr, null)->toArray();
        $ruleGoodsArr = array();
//        print_r($rule_list);
        if ($rule_list) {
            $product_bn_2_goods_bn = array();
            $product_bn_arr = array();
            foreach ($goods_bn_arr as $index => $goods_bn) {
                if (!empty($goods_bn_2_product_bn[$goods_bn])) {
                    $product_bn_2_goods_bn[$goods_bn_2_product_bn[$goods_bn]] = $goods_bn;
                    $product_bn_arr[]=$goods_bn_2_product_bn[$goods_bn];
                    unset($goods_bn_arr[$index]);
                }
            }
            $goods_bn_arr = array_filter($goods_bn_arr);
            if ($goods_bn_arr) {
                $goods_bn_str = "'" . implode("','", $goods_bn_arr) . "'";
                $sql = "SELECT goods.bn as goods_bn,product.bn as product_bn from sdb_b2c_goods goods LEFT join sdb_b2c_products product on goods.goods_id=product.goods_id where goods.bn IN({$goods_bn_str}) and product.is_default='true'";
                $goods_list = $this->getStoreDB()->select($sql);
                foreach ($goods_list as $goods_info) {
                    $product_bn_arr[] = $goods_info->product_bn;
                    $product_bn_2_goods_bn[$goods_info->product_bn] = $goods_info->goods_bn;
                }
            }
            $ec = new Ec();
            $product_price_list = $ec->GetProductBasePrice($product_bn_arr);
            foreach ($rule_list as $rule_info) {
                foreach ($product_price_list as $product_bn => $price_info) {
                    if (
                        floatval($price_info[$price_field]) >= floatval($rule_info->filter_val === 'all' ? 0 : $rule_info->filter_val) &&
                        (
                            $rule_info->filter_val1 === 'all' ||
                            $rule_info->filter_val1 !== 'all' && floatval($price_info[$price_field]) <= floatval($rule_info->filter_val1)
                        )
                    ) {
                        $ruleGoodsArr[$rule_info->rule_id][] = $product_bn_2_goods_bn[$product_bn];
                    }
                }
            }
        }
        return $ruleGoodsArr;
    }

    /**
     * 毛利
     */
    private function getGrossProfitRule($rule_id_arr, $goods_bn_arr, $goods_bn_2_product_bn)
    {
        $rule_list     = RuleInfoModel::Query('shopping', 'gross_profit', $rule_id_arr, null)->toArray();
        $ruleGoodsArr = array();
        if ($rule_list) {
            $product_bn_2_goods_bn = array();
            $product_bn_arr = array();
            foreach ($goods_bn_arr as $index => $goods_bn) {
                if (!empty($goods_bn_2_product_bn[$goods_bn])) {
                    $product_bn_2_goods_bn[$goods_bn_2_product_bn[$goods_bn]] = $goods_bn;
                    $product_bn_arr[]=$goods_bn_2_product_bn[$goods_bn];
                    unset($goods_bn_arr[$index]);
                }
            }
            $goods_bn_arr = array_filter($goods_bn_arr);
            if ($goods_bn_arr) {
                $goods_bn_str = "'" . implode("','", $goods_bn_arr) . "'";
                $sql = "SELECT goods.bn as goods_bn,product.bn as product_bn from sdb_b2c_goods goods LEFT join sdb_b2c_products product on goods.goods_id=product.goods_id where goods.bn IN({$goods_bn_str}) and product.is_default='true'";
                $goods_list = $this->getStoreDB()->select($sql);
                foreach ($goods_list as $goods_info) {
                    $product_bn_arr[] = $goods_info->product_bn;
                    $product_bn_2_goods_bn[$goods_info->product_bn] = $goods_info->goods_bn;
                }
            }

            $ec = new Ec();
            $product_price_list = $ec->GetProductBasePrice($product_bn_arr);

            $product_bn_str = "'" . implode("','", $product_bn_arr) . "'";
            $sql = "SELECT bn,cost from sdb_b2c_products where bn IN({$product_bn_str})";
            $product_list = $this->getStoreDB()->select($sql);
            $bn_2_cost = array();
            foreach ($product_list as $product_info) {
                $bn_2_cost[$product_info->bn] = floatval($product_info->cost);
            }

//            print_r($product_price_list);

            foreach ($rule_list as $rule_info) {
                foreach ($product_price_list as $product_bn => $price_info) {
                    $price_info['price'] = floatval($price_info['price']);
                    $gross_profit_rate = ($price_info['price'] - $bn_2_cost[$product_bn]) * 100 / $price_info['price'];
                    if (
                        $gross_profit_rate >= floatval(($rule_info->filter_val === 'all' || empty($rule_info->filter_val)) ? 0 : $rule_info->filter_val) &&
                        (
                            ($rule_info->filter_val1 === 'all' || empty($rule_info->filter_val1)) ||
                            ($rule_info->filter_val1 !== 'all' && !empty($rule_info->filter_val1) && $gross_profit_rate <= floatval($rule_info->filter_val1))
                        )
                    ) {
                        $ruleGoodsArr[$rule_info->rule_id][] = $product_bn_2_goods_bn[$product_bn];
                    }
                }
            }
        }
//        print_r($ruleGoodsArr);
        return $ruleGoodsArr;
    }

    /**
     * 毛利(市场价)
     */
    private function getMktPriceGrossProfitRule($rule_id_arr, $goods_bn_arr, $goods_bn_2_product_bn)
    {
        $rule_list     = RuleInfoModel::Query('shopping', 'mktprice_gross_profit', $rule_id_arr, null)->toArray();
        $ruleGoodsArr = array();
        if ($rule_list) {
            $product_bn_2_goods_bn = array();
            $product_bn_arr = array();
            foreach ($goods_bn_arr as $index => $goods_bn) {
                if (!empty($goods_bn_2_product_bn[$goods_bn])) {
                    $product_bn_2_goods_bn[$goods_bn_2_product_bn[$goods_bn]] = $goods_bn;
                    $product_bn_arr[]=$goods_bn_2_product_bn[$goods_bn];
                    unset($goods_bn_arr[$index]);
                }
            }
            $goods_bn_arr = array_filter($goods_bn_arr);
            if ($goods_bn_arr) {
                $goods_bn_str = "'" . implode("','", $goods_bn_arr) . "'";
                $sql = "SELECT goods.bn as goods_bn,product.bn as product_bn from sdb_b2c_goods goods LEFT join sdb_b2c_products product on goods.goods_id=product.goods_id where goods.bn IN({$goods_bn_str}) and product.is_default='true'";
                $goods_list = $this->getStoreDB()->select($sql);
                foreach ($goods_list as $goods_info) {
                    $product_bn_arr[] = $goods_info->product_bn;
                    $product_bn_2_goods_bn[$goods_info->product_bn] = $goods_info->goods_bn;
                }
            }
            $ec = new Ec();
            $product_price_list = $ec->GetProductBasePrice($product_bn_arr);

            $product_bn_str = "'" . implode("','", $product_bn_arr) . "'";
            $sql = "SELECT bn,cost,mktprice from sdb_b2c_products where bn IN({$product_bn_str})";
            $product_list = $this->getStoreDB()->select($sql);
            $bn_2_cost = array();
            foreach ($product_list as $product_info) {
                $bn_2_cost[$product_info->bn] = floatval($product_info->cost);
            }
            foreach ($rule_list as $rule_info) {
                foreach ($product_price_list as $product_bn => $price_info) {
                    $price_info['mktprice'] = floatval($price_info['mktprice']);
                    $gross_profit_rate = ($price_info['mktprice'] - $bn_2_cost[$product_bn]) * 100 / $price_info['mktprice'];
                    if (
                        $gross_profit_rate >= floatval(($rule_info->filter_val === 'all' || empty($rule_info->filter_val)) ? 0 : $rule_info->filter_val) &&
                        (
                            ($rule_info->filter_val1 === 'all' || empty($rule_info->filter_val1)) ||
                            ($rule_info->filter_val1 !== 'all' && !empty($rule_info->filter_val1) && $gross_profit_rate <= floatval($rule_info->filter_val1))
                        )
                    ) {
                        $ruleGoodsArr[$rule_info->rule_id][] = $product_bn_2_goods_bn[$product_bn];
                    }
                }
            }
        }
        return $ruleGoodsArr;
    }

    /**
     * 指定折扣
     */
    private function getDiscountRule($rule_id_arr, $goods_bn_arr, $goods_bn_2_product_bn)
    {
        $rule_list     = RuleInfoModel::Query('shopping', 'discount', $rule_id_arr, null)->toArray();
        $ruleGoodsArr = array();
        if ($rule_list) {
            $product_bn_2_goods_bn = array();
            $product_bn_arr = array();
            foreach ($goods_bn_arr as $index => $goods_bn) {
                if (!empty($goods_bn_2_product_bn[$goods_bn])) {
                    $product_bn_2_goods_bn[$goods_bn_2_product_bn[$goods_bn]] = $goods_bn;
                    $product_bn_arr[]=$goods_bn_2_product_bn[$goods_bn];
                    unset($goods_bn_arr[$index]);
                }
            }
            $goods_bn_arr = array_filter($goods_bn_arr);
            if ($goods_bn_arr) {
                $goods_bn_str = "'" . implode("','", $goods_bn_arr) . "'";
                $sql = "SELECT goods.bn as goods_bn,product.bn as product_bn from sdb_b2c_goods goods LEFT join sdb_b2c_products product on goods.goods_id=product.goods_id where goods.bn IN({$goods_bn_str}) and product.is_default='true'";
                $goods_list = $this->getStoreDB()->select($sql);
                foreach ($goods_list as $goods_info) {
                    $product_bn_arr[] = $goods_info->product_bn;
                    $product_bn_2_goods_bn[$goods_info->product_bn] = $goods_info->goods_bn;
                }
            }

            $ec = new Ec();
            $product_price_list = $ec->GetProductBasePrice($product_bn_arr);

            foreach ($rule_list as $rule_info) {
                foreach ($product_price_list as $product_bn => $price_info) {
                    $price_info['price'] = floatval($price_info['price']);
                    $discount_rate = $price_info['price'] * 100 / $price_info['mktprice'];
                    if (
                        $discount_rate >= floatval(($rule_info->filter_val === 'all' || empty($rule_info->filter_val)) ? 0 : $rule_info->filter_val) &&
                        (
                            ($rule_info->filter_val1 === 'all' || empty($rule_info->filter_val1)) ||
                            ($rule_info->filter_val1 !== 'all' && !empty($rule_info->filter_val1) && $discount_rate <= floatval($rule_info->filter_val1))
                        )
                    ) {
                        $ruleGoodsArr[$rule_info->rule_id][] = $product_bn_2_goods_bn[$product_bn];
                    }
                }
            }
        }
        return $ruleGoodsArr;
    }


    // 排除类目下品牌
    private function getCatBrandRule($rule_id_arr, $goods_bn_arr, $goodsBnList)
    {
        $rule_info_all_list = RuleInfoModel::Query('shopping', 'cat_brand', $rule_id_arr, null, null, null, null, 'not_in')->toArray();
//        print_r($rule_info_all_list);
        $ruleGoodsArr = array();
        if ($rule_info_all_list) {
            $goods_bn_2_mall_cat = $this->getMallCatPathTreeByGoodsBns($goodsBnList);
            $goods_bn_2_brand_id = $this->getBrandIdByGoodsIds($goodsBnList);

//            print_r($goods_bn_2_mall_cat);
//            print_r($goods_bn_2_brand_id);

            $rule_id_2_rule_info_list = array();
            foreach ($rule_info_all_list as $rule_info_all_info) {
                $rule_id_2_rule_info_list[$rule_info_all_info->rule_id][] = $rule_info_all_info;
            }
//            print_r($rule_id_2_rule_info_list);
            foreach ($rule_id_2_rule_info_list as $rule_id => $rule_info_list) {
                foreach ($goods_bn_2_mall_cat as $goods_bn => $mall_cat) {
                    $is_match = true;
                    foreach ($rule_info_list as $rule_info_info) {
//                        print_r($rule_info_info);
//                        print_r(array($goods_bn_2_brand_id[$goods_bn], 'all'));
//                        print_r($mall_cat);

                        if (
                            in_array($rule_info_info->filter_val, array($goods_bn_2_brand_id[$goods_bn], 'all')) &&
                            in_array($rule_info_info->filter_val1, array($mall_cat[0], 'all')) &&
                            in_array($rule_info_info->filter_val2, array($mall_cat[1], 'all')) &&
                            in_array($rule_info_info->filter_val3, array($mall_cat[2], 'all'))
                        ) {
                            $is_match = false;
                            break;
                        }
                    }
                    if ($is_match) {
                        $ruleGoodsArr[$rule_id][] = $goods_bn;
                    }
                }
            }
        }
//        print_r($ruleGoodsArr);
        return $ruleGoodsArr;
    }


    private function getGoodsRule($ruleIdList, $goodsBnList)
    {
        $goodsBnList[] = 'all';
        $ruleList      = RuleInfoModel::Query(
            'shopping',
            'goods',
            $ruleIdList,
            array_values(
                array_map(function ($val) {
                    return trim($val, "'");
                },
                    $goodsBnList
                )
            )
        );
        $ruleGoodsArr  = array();
        foreach ($ruleList as $ruleInfo) {
            $ruleGoodsArr[$ruleInfo->rule_id][] = $ruleInfo->filter_val;
        }
        return $ruleGoodsArr;
    }


    /**
     * @param $ruleIdList
     * @param $productBnList
     *
     * @return array
     */
    private function getProductRule($ruleIdList, $productBnList)
    {
        $productBnList[] = 'all';
        $ruleList      = RuleInfoModel::Query(
            'shopping',
            'product',
            $ruleIdList,
            array_values(
                array_map(function ($val) {
                    return trim($val, "'");
                },
                    $productBnList
                )
            )
        );

        $ruleGoodsArr  = array();
        foreach ($ruleList as $ruleInfo) {
            $ruleGoodsArr[$ruleInfo->rule_id][] = $ruleInfo->filter_val;
        }
        return $ruleGoodsArr;
    }

    /**
     * shop
     */
    private function getShopRule($ruleIdList, $goodsBnList)
    {
        $goodsShopId = $this->getShopIdByGoodsIds($goodsBnList);
        $shopArr     = array_unique(array_values($goodsShopId));
        $shopArr[]   = 'all';

        $ruleList     = RuleInfoModel::Query('shopping', 'shop', $ruleIdList, $shopArr);
        $ruleGoodsArr = array();
        foreach ($ruleList as $ruleInfo) {
            foreach ($goodsShopId as $goodsBn => $shopId) {
                if ($ruleInfo->filter_val == 'all' || $ruleInfo->filter_val == $shopId) {
                    $ruleGoodsArr[$ruleInfo->rule_id][] = $goodsBn;
                }
            }
        }
        return $ruleGoodsArr;
    }

    /**
     * wms
     */
    private function getPopWmsRule($ruleIdList, $goodsBnList)
    {
        $wmsArr = $this->getPopWmsByGoodsIds($goodsBnList);
        $wmsId = array_unique(array_values($wmsArr));
        $wmsId[] = 'all';

        $ruleList     = RuleInfoModel::Query('shopping', 'pop_wms', $ruleIdList, $wmsId);
        $ruleGoodsArr = array();
        foreach ($ruleList as $ruleInfo) {
            foreach ($wmsArr as $goodsBn => $wmsId) {
                if ($ruleInfo->filter_val == 'all' || $ruleInfo->filter_val == $wmsId) {
                    $ruleGoodsArr[$ruleInfo->rule_id][] = $goodsBn;
                }
            }
        }
        return $ruleGoodsArr;
    }

    /**
     * 通过商品ID获取ShopID
     */
    private function getShopIdByGoodsIds($goodsBnList)
    {
        $return = array();
        if (!$goodsBnList) {
            return $return;
        }
        $goodsBns = implode(',', array_filter($goodsBnList));
        $sql      = "select goods.bn, products.pop_shop_id from sdb_b2c_goods goods left join sdb_b2c_products products on goods.goods_id = products.goods_id and goods.bn IN ({$goodsBns})  where goods.bn IN ({$goodsBns})  group by goods.bn,products.pop_shop_id";
        $result   = $this->getStoreDB()->select($sql);
        if ($result) {
            foreach ($result as $v) {
                $return[$v->bn] = $v->pop_shop_id;
            }
        }
        return $return;
    }

    /**
     * 通过商品ID获取ShopID
     */
    private function getPopWmsByGoodsIds($goodsBnList)
    {
        $return = array();
        if (!$goodsBnList) {
            return $return;
        }
        $goodsBns = implode(',', array_filter($goodsBnList));
        $sql      = "select goods.bn, pop_owner.pop_wms_id from sdb_b2c_goods goods left join sdb_b2c_products products on goods.goods_id = products.goods_id and goods.bn IN ({$goodsBns}) left join sdb_b2c_pop_owner as pop_owner on products.pop_shop_id = pop_owner.pop_owner_id  where goods.bn IN ({$goodsBns})";
        $result   = $this->getStoreDB()->select($sql);
        if ($result) {
            foreach ($result as $v) {
                $return[$v->bn] = $v->pop_wms_id;
            }
        }
        return $return;
    }

    /**
     * 品牌
     */
    private function getBrandRule($ruleIdList, $goodsBnList)
    {
        $goodsBrandId = $this->getBrandIdByGoodsIds($goodsBnList);
        $brandArr     = array_unique(array_values($goodsBrandId));
        $brandArr[]   = 'all';

        $ruleList     = RuleInfoModel::Query('shopping', 'brand', $ruleIdList, $brandArr);
        $ruleGoodsArr = array();
        foreach ($ruleList as $ruleInfo) {
            foreach ($goodsBrandId as $goodsId => $brandId) {
                if ($ruleInfo->filter_val == 'all' || $ruleInfo->filter_val == $brandId) {
                    $ruleGoodsArr[$ruleInfo->rule_id][] = $goodsId;
                }
            }
        }
        return $ruleGoodsArr;
    }

    /**
     * 通过商品ID获取品牌ID
     */
    private function getBrandIdByGoodsIds($goodsBnList)
    {
        $return = array();
        if (!$goodsBnList) {
            return $return;
        }
        $goodsBns = implode(',', array_filter($goodsBnList));
        $sql      = "select bn, brand_id from sdb_b2c_goods where bn IN ({$goodsBns})";
        $result   = $this->getStoreDB()->select($sql);
        if ($result) {
            foreach ($result as $v) {
                $return[$v->bn] = $v->brand_id;
            }
        }
        return $return;
    }

    /**
     * 商城及类目
     */
    private function getMallCatRule($ruleIdList, $goodsBnList, $goods_bn_arr)
    {
        $goodsMallId = $this->getMallIdByGoodsIds($goodsBnList);
        $mallIdList  = array();
        foreach ($goodsMallId as $goodsBn => $mallIdArr) {
            $mallIdList = array_merge($mallIdList, $mallIdArr);
        }
        $mallIdList[] = 'all';

        $goodsMallCat = $this->getMallCatPathTreeByGoodsBns($goodsBnList);
        $catLayerArr  = array();
        foreach ($goodsMallCat as $goodsBn => $catTree) {
            foreach ($catTree as $index => $catId) {
                $catLayerArr[$index][$goodsBn] = $catId;
            }
        }
        $oneCat       = isset($catLayerArr[0]) ? array_unique(array_values($catLayerArr[0])) : array();
        $twoCat       = isset($catLayerArr[1]) ? array_unique(array_values($catLayerArr[1])) : array();
        $threeCat     = isset($catLayerArr[2]) ? array_unique(array_values($catLayerArr[2])) : array();
        $oneCat[]     = 'all';
        $twoCat[]     = 'all';
        $threeCat[]   = 'all';
        $ruleList     = RuleInfoModel::Query('shopping', 'mallcat', $ruleIdList, $mallIdList, $oneCat, $twoCat,
            $threeCat);
        $ruleGoodsArr = array();
        foreach ($ruleList as $ruleInfo) {
            if (!$goodsMallId && $ruleInfo->filter_val == 'all') {
                $ruleGoodsArr[$ruleInfo->rule_id] = array_values(array_map(function ($val) {
                    return trim($val, "'");
                }, $goodsBnList));
            } else {
                foreach ($goodsMallId as $goodsBn => $mallIdArr) {
                    if (
                        ($ruleInfo->filter_val == 'all' || in_array($ruleInfo->filter_val, $mallIdArr)) &&
                        ($ruleInfo->filter_val1 == 'all' || $ruleInfo->filter_val1 == $catLayerArr[0][$goodsBn]) &&
                        ($ruleInfo->filter_val2 == 'all' || $ruleInfo->filter_val2 == $catLayerArr[1][$goodsBn]) &&
                        ($ruleInfo->filter_val3 == 'all' || $ruleInfo->filter_val3 == $catLayerArr[2][$goodsBn])
                    ) {
                        $ruleGoodsArr[$ruleInfo->rule_id][] = $goodsBn;
                    }
                }
            }
        }

        $rule_info_all_list = RuleInfoModel::Query('shopping', 'mallcat', $ruleIdList, null, null, null, null, 'not_in')->toArray();
        if ($rule_info_all_list) {
            $rule_id_2_rule_info_list = array();
            foreach ($rule_info_all_list as $rule_info_all_info) {
                $rule_id_2_rule_info_list[$rule_info_all_info->rule_id][] = $rule_info_all_info;
            }
            foreach ($rule_id_2_rule_info_list as $rule_id => $rule_info_list) {
                foreach ($goods_bn_arr as $goods_bn) {
                    $mallIdArr = $goodsMallId[$goods_bn];
                    if ($mallIdArr &&  $goodsMallCat[$goods_bn]) {
                        $is_match = true;
                        foreach ($rule_info_list as $ruleInfo) {
                            if (
                                ($ruleInfo->filter_val == 'all' || in_array($ruleInfo->filter_val, $mallIdArr)) &&
                                in_array($ruleInfo->filter_val1, array($goodsMallCat[$goods_bn][0], 'all')) &&
                                in_array($ruleInfo->filter_val2, array($goodsMallCat[$goods_bn][1], 'all')) &&
                                in_array($ruleInfo->filter_val3, array($goodsMallCat[$goods_bn][2], 'all'))
                            ) {
                                $is_match = false;
                                break;
                            }
                        }

                    } else {
                        $is_match = true;
                    }
                    if ($is_match && $rule_id) {
                        $ruleGoodsArr[$rule_id][] = $goods_bn;
                    }
                }
            }
        }

        return $ruleGoodsArr;
    }

    /**
     * 通过商品ID获取商城ID
     */
    private function getMallIdByGoodsIds($goodsBnList)
    {
        $return = array();
        if (!$goodsBnList) {
            return $return;
        }
        $goodsBns      = implode(',', array_filter($goodsBnList));
        $sql           = "select bn,mall_id from mall_module_mall_goods where bn IN ({$goodsBns})";
        $mallGoodsList = $this->getStoreDB()->select($sql);
        if ($mallGoodsList) {
            foreach ($mallGoodsList as $mallGoodsItem) {
                $return[$mallGoodsItem->bn][] = $mallGoodsItem->mall_id;
            }
        }
        return $return;
    }

    /**
     * 通过商品ID获取商城分类路径树
     */
    private function getMallCatPathTreeByGoodsBns($goodsBnList)
    {
        $return = array();
        if (!$goodsBnList) {
            return $return;
        }
        //获取商城分类ID
        $goodsBns        = implode(',', array_filter($goodsBnList));
        $mallGoodsCatSql = "select bn,mall_goods_cat from sdb_b2c_goods where bn IN ({$goodsBns})";
        $mallGoodsCatRes = $this->getStoreDB()->select($mallGoodsCatSql);
        if (empty($mallGoodsCatRes)) {
            return $return;
        }

        foreach ($mallGoodsCatRes as $goodsInfo) {
            if ($goodsInfo->mall_goods_cat) {
                $return[$goodsInfo->bn] = $goodsInfo->mall_goods_cat;
            }
        }

        if (!$return) {
            return $return;
        }

        //获取分类的信息
        $mallCatIds   = implode(',', $return);
        $catInfoSql   = "select cat_id,cat_path from sdb_b2c_mall_goods_cat where cat_id IN ({$mallCatIds})";
        $catInfoRes   = $this->getStoreDB()->select($catInfoSql);
        $mallsCatPath = array();
        if (!empty($catInfoRes)) {
            foreach ($catInfoRes as $v) {
                $mallsCatPath[$v->cat_id] = $v->cat_path;
            }
        }

        //获取商品的商城一级分类
        foreach ($return as $goodsId => $catId) {
            if (!isset($mallsCatPath[$catId])) {
                unset($return[$goodsId]);
                continue;
            }
            $goodsMallCatPathTree   = array_values(array_filter(explode(',', $mallsCatPath[$catId])));
            $goodsMallCatPathTree[] = $catId;
            if (!empty($goodsMallCatPathTree)) {
                $return[$goodsId] = $goodsMallCatPathTree;
            }
        }
        return $return;
    }

}
