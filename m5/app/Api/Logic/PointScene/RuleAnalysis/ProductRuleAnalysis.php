<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-01-31
 * Time: 15:31
 */

namespace App\Api\Logic\PointScene\RuleAnalysis;


use App\Api\Model\PointScene\RuleInfo as RuleInfoModel;
use App\Api\Model\PointScene\Scene as SceneModel;

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

    /**
     * 商城使用积分规则解析
     */
    public function WithRule($ruleIdArr, $sceneIdArr, $filterData)
    {
        $sceneIdList = array_keys($sceneIdArr);
        $ruleIdList  = array_values($ruleIdArr);

        $goodsBnList = array();
        $goodsArr    = array();
        $productBnList = $productArr = $goods2ProductBn = [];

        foreach ($filterData as $goods) {
            if (!isset($goods['goods_bn']) || !$goods['goods_bn']) {
                return $this->Response(false, '商品错误', array());
            }
            $goodsBnList[] = "'" . $goods['goods_bn'] . "'";

            $goodsArr[$goods['goods_bn']] = $goods;

            if ( ! empty($goods['product_bn'])) {
                $productBnList[]                       = "'".$goods['product_bn']."'";
                $productArr[$goods['product_bn']]      = $goods;
                $goods2ProductBn[$goods['goods_bn']][] = $goods['product_bn'];
            }
        }

        $ruleRes      = $this->getMallCatRule($ruleIdList, $goodsBnList);
        $brandRuleRes = $this->getBrandRule($ruleIdList, $goodsBnList);
        if ($brandRuleRes) {
            foreach ($brandRuleRes as $ruleId => $ruleGoodsBnList) {
                if (isset($ruleRes[$ruleId])) {
                    $ruleRes[$ruleId] = array_merge($ruleRes[$ruleId], $ruleGoodsBnList);
                } else {
                    $ruleRes[$ruleId] = $ruleGoodsBnList;
                }
            }
        }

        $shopRuleRes = $this->getShopRule($ruleIdList, $goodsBnList);
        if ($shopRuleRes) {
            foreach ($shopRuleRes as $ruleId => $ruleGoodsBnList) {
                if (isset($ruleRes[$ruleId])) {
                    $ruleRes[$ruleId] = array_merge($ruleRes[$ruleId], $ruleGoodsBnList);
                } else {
                    $ruleRes[$ruleId] = $ruleGoodsBnList;
                }
            }
        }

        $goodsRuleRes = $this->getGoodsRule($ruleIdList, $goodsBnList);
        if ($goodsRuleRes) {
            foreach ($goodsRuleRes as $ruleId => $ruleGoodsBnList) {
                if (isset($ruleRes[$ruleId])) {
                    $ruleRes[$ruleId] = array_merge($ruleRes[$ruleId], $ruleGoodsBnList);
                } else {
                    $ruleRes[$ruleId] = $ruleGoodsBnList;
                }
            }
        }

        $returnArr = array();
        foreach ($ruleRes as $ruleId => $goodsBnList) {
            foreach ($goodsBnList as $goodsBn) {
                foreach ($sceneIdList as $sceneId) {
                    $curSceneRuleIdArr = [];
                    foreach ($sceneIdArr[$sceneId] as $ruleBn) {
                        $curSceneRuleIdArr[] = $ruleIdArr[$ruleBn];
                    }

                    if (!in_array($ruleId, $curSceneRuleIdArr)) {
                        continue;
                    }
                    if (!isset($returnArr[$sceneId])) {
                        $returnArr[$sceneId] = array(
                            "rule_id"    => $ruleId,
                            "scene_id"   => $sceneId,
                            "goods_list" => array()
                        );
                    }
                    if ($goodsBn == 'all') {
                        foreach ($goodsArr as $lGoods) {
                            $returnArr[$sceneId]['goods_list'][$lGoods['goods_bn']] = $lGoods;
                        }
                    } else {
                        $returnArr[$sceneId]['goods_list'][$goodsBn] = $goodsArr[$goodsBn];
                    }
                }
            }
        }

        if ( ! empty($productBnList)) {

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

            if ($productRuleRes) {
                foreach ($productRuleRes as $ruleId => $ruleProductBnList) {

                    if (isset($ruleProductBnRes[$ruleId])) {

                        $ruleProductBnRes[$ruleId] = array_merge($ruleProductBnRes[$ruleId], $ruleProductBnList);
                    } else {
                        $ruleProductBnRes[$ruleId] = $ruleProductBnList;
                    }
                }
            }

            foreach ($ruleProductBnRes as $ruleId => $productBnList) {
                foreach ($productBnList as $bn) {
                    foreach ($sceneIdList as $sceneId) {
                        $curSceneRuleIdArr = [];
                        foreach ($sceneIdArr[$sceneId] as $ruleBn) {
                            $curSceneRuleIdArr[] = $ruleIdArr[$ruleBn];
                        }

                        if (!in_array($ruleId, $curSceneRuleIdArr)) {
                            continue;
                        }
                        if (!isset($returnArr[$sceneId])) {
                            $returnArr[$sceneId] = array(
                                "rule_id"    => $ruleId,
                                "scene_id"   => $sceneId,
                                "goods_list" => array()
                            );
                        }
                        if ($bn == 'all') {
                            foreach ($productArr as $product) {
                                $returnArr[$ruleId]['product_list'][$product['product_bn']] = $product;
                            }
                        } else {
                            $returnArr[$ruleId]['product_list'][$bn] = $productArr[$bn];
                        }
                    }
                }
            }
        }

        return $this->Response(true, '获取成功', $returnArr);
    }

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
    private function getMallCatRule($ruleIdList, $goodsBnList)
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
