<?php

namespace App\Api\V1\Service\BasicBusinessLimit;

use App\Api\V3\Service\ServiceTrait;
use App\Api\Model\BasicBusinessLimit\BasicBusinessLimit as BasicBusinessLimitModel;
use App\Api\Model\BasicBusinessLimit\BasicBusinessLimitRelation as BasicBusinessLimitRelationModel;

class BasicBusinessLimit
{

    use ServiceTrait;

    public function create($basicBusinessLimitData)
    {
        $basicBusinessLimitData['create_time'] = time();
        $basicBusinessLimitData['update_time'] = time();

        $relationIds = $basicBusinessLimitData['related_id'];
        unset($basicBusinessLimitData['related_id']);

        app('db')->beginTransaction();

        $basicBusinessLimitId = BasicBusinessLimitModel::create($basicBusinessLimitData);
        if (!$basicBusinessLimitId) {
            app('db')->rollBack();

            return $this->Response(false, "创建失败");
        }

        $basicBusinessLimitRelationList = [];
        foreach ($relationIds as $relationId) {
            $basicBusinessLimitRelation['limit_id']    = $basicBusinessLimitId;
            $basicBusinessLimitRelation['related_id'] = $relationId;
            $basicBusinessLimitRelation['create_time'] = time();
            $basicBusinessLimitRelation['update_time'] = time();
            $basicBusinessLimitRelationList[]          = $basicBusinessLimitRelation;
        }

        $status = BasicBusinessLimitRelationModel::create($basicBusinessLimitRelationList);
        if (!$status) {
            app('db')->rollBack();

            return $this->Response(false, "创建失败");
        }

        app('db')->commit();

        return $this->Response(true, "创建成功", ['id' => $basicBusinessLimitId]);
    }

    public function update($id, $basicBusinessLimitData)
    {
        $basicBusinessLimitData['update_time'] = time();

        $relatedIds = array_unique($basicBusinessLimitData['related_id']);
        arsort($relatedIds);

        unset($basicBusinessLimitData['related_id']);
        unset($basicBusinessLimitData['id']);


        app('db')->beginTransaction();

        $status = BasicBusinessLimitModel::update($id, $basicBusinessLimitData);
        if (!$status) {
            app('db')->rollBack();
        }

        $condition['limit_id']          = $id;
        $basicBusinessLimitRelationList = BasicBusinessLimitRelationModel::getList($condition);
        $originRelatedIds = [];
        foreach ($basicBusinessLimitRelationList as $item) {
            $originRelatedIds[] = $item->related_id;
        }

        if($originRelatedIds) {
            $originRelatedIds = array_unique($originRelatedIds);
            asort($originRelatedIds);
        }


        if ($originRelatedIds != $relatedIds) {
            $status = BasicBusinessLimitRelationModel::delete(['limit_id' => $id]);
            if (!empty($originRelatedIds) && !$status) {
                app('db')->rollBack();
                return $this->Response(false, "更新失败");
            }

            if (!empty($relatedIds)) {
                $basicBusinessLimitRelationList = [];
                foreach ($relatedIds as $originRelatedIds) {
                    $basicBusinessLimitRelation['limit_id']    = $id;
                    $basicBusinessLimitRelation['related_id']  = $originRelatedIds;
                    $basicBusinessLimitRelation['create_time'] = time();
                    $basicBusinessLimitRelation['update_time'] = time();
                    $basicBusinessLimitRelationList[]          = $basicBusinessLimitRelation;
                }

                $status = BasicBusinessLimitRelationModel::Create($basicBusinessLimitRelationList);
                if (!$status) {
                    app('db')->rollBack();
                    return $this->Response(false, "更新失败");
                }
            }

        }

        app('db')->commit();

        return $this->Response(true, "更新成功");
    }

    /**
     * @param $id
     * @param $state
     */
    public function updateState($id, $state)
    {

        $status = BasicBusinessLimitModel::updateState($id, $state);
        if ($status) {
            return $this->Response(true, "修改成功");
        }

        return $this->Response(false, "修改成功");
    }

    /**
     * @param $params
     *
     * @return array
     */
    public function getList($params)
    {
        $condition = [];
        $option    = [];

        if (isset($params['filter'])) {
            if (isset($params['filter']['name']) && empty($params['filter']['name'])) {
                $condition['name'] = $params['filter']['name'];
            }
        }

        if (!isset($params['page'])) {
            $page = 1;
        } else {
            $page = (int)$params['page'];
        }


        if (!isset($params['page_size'])) {
            $pageSize = 20;
        } else {
            $pageSize = (int)$params['page_size'];
        }


        $offset           = ($page - 1) * $pageSize;
        $option['offset'] = $offset;
        $option['limit']  = $pageSize;

        $basicBusinessLimitList = BasicBusinessLimitModel::getList($condition, $option);

        $count = BasicBusinessLimitModel::getCount($condition);
        if ($count == 0) {
            return $this->Response(true, "成功",
                ['list' => [], 'page' => 0, 'page_size' => 0, 'total_count' => 0, 'total_page' => 0]);
        }

        return $this->Response(true, "成功", [
            'list'        => $basicBusinessLimitList,
            'page'        => $page,
            'page_size'   => $pageSize,
            'total_count' => $count,
            'total_page'  => ceil($count / $page)
        ]);

    }

    /**
     * @param $id
     *
     * @return array
     */
    public function getInfo($id)
    {
        $basicBusinessLimitInfo = BasicBusinessLimitModel::getInfo($id);

        $relationCondition             = [];
        $relationCondition['limit_id'] = $id;

        $basicBusinessLimitRelationList = BasicBusinessLimitRelationModel::getList($relationCondition);

        foreach ($basicBusinessLimitInfo as $item) {
            $item->list = $basicBusinessLimitRelationList;
        }

        return $this->Response(true, "成功", $basicBusinessLimitInfo);

    }

    /**
     * @param $orderData [['pop_shop_id' => 1, 'price' => 10, 'goods_bn' => 'shop_****', 'product_bn' => 'shop_****', 'quantity' => '10' ]]
     * @param $firstErrorTips
     * @return array
     */
    public function validate($orderData, &$firstErrorTips): array
    {
        $relatedIds  = array_column($orderData, "pop_shop_id");

        $matchedBasicBusinessLimitList = $this->getMatchedBasicBusinessLimit($relatedIds);

        $result = $this->validateNum($orderData, $matchedBasicBusinessLimitList);

        $result2 = $this->validateAmount($orderData, $matchedBasicBusinessLimitList);

        if ($result && $result2) {
            return $this->Response(true, "成功", $orderData);
        }

        foreach ($orderData as $item) {
            if ($item['num_limit_result'] == "failed" || $item['amount_limit_result'] == "failed") {

                if ($item['num_limit_result'] == "failed") {
                    if ($firstErrorTips) {
                        $firstErrorTips .= ' ' . $item['num_limit_tips'];
                    } else {
                        $firstErrorTips = $item['num_limit_tips'];
                    }
                }
                if ($item['amount_limit_result'] == "failed") {
                    if ($firstErrorTips) {
                        $firstErrorTips .= ' ' . $item['amount_limit_tips'];
                    } else {
                        $firstErrorTips = $item['amount_limit_tips'];
                    }
                }
                break;
            }
        }

        return $this->Response(false, "失败", $orderData);
    }

    /**
     * @param $orderData
     * @param $matchedBasicBusinessLimitList
     *
     * @return bool
     */
    public function validateNum(&$orderData, $matchedBasicBusinessLimitList): bool
    {
        $checked = true;
        foreach ($orderData as $k => $item) {
            if (isset($matchedBasicBusinessLimitList[$item['pop_shop_id']]['num_limit'])) {
                if ($item['quantity'] > $matchedBasicBusinessLimitList[$item['pop_shop_id']]['num_limit']['limit']) {
                    $checked  = false;
                    $orderData[$k]['num_limit_result'] = "failed";
                    $orderData[$k]['num_limit_tips'] = $matchedBasicBusinessLimitList[$item['pop_shop_id']]['num_limit']['tips'];
                } else {
                    $orderData[$k]['num_limit_result'] = "success";
                    $orderData[$k]['num_limit_tips'] = "";
                }
            }  else {
                $orderData[$k]['num_limit_result'] = "success";
                $orderData[$k]['num_limit_tips'] = "";
            }
        }

        return $checked;
    }

    /**
     * @param $orderData
     * @param $matchedBasicBusinessLimitList
     *
     * @return bool
     */
    public function validateAmount(&$orderData, $matchedBasicBusinessLimitList): bool
    {
        $originOrderData            = $orderData;
        $orderAmountIndexShopIdList = [];
        foreach ($originOrderData as $item) {
            if (isset($orderAmountIndexShopIdList[$item['pop_shop_id']]['amount'])) {
                $orderAmountIndexShopIdList[$item['pop_shop_id']]['amount'] = $orderAmountIndexShopIdList[$item['pop_shop_id']]['amount'] + $item['price'] * $item['quantity'];
            } else {
                $orderAmountIndexShopIdList[$item['pop_shop_id']]['amount'] = 0;
                $orderAmountIndexShopIdList[$item['pop_shop_id']]['amount'] = $orderAmountIndexShopIdList[$item['pop_shop_id']]['amount'] + $item['price'] * $item['quantity'];
            }
        }

        foreach ($orderAmountIndexShopIdList as $shopId => &$shopOrderAmountData) {
            if (isset($matchedBasicBusinessLimitList[$shopId]['amount_limit']['limit']) && $shopOrderAmountData['amount'] > $matchedBasicBusinessLimitList[$shopId]['amount_limit']['limit']) {
                $shopOrderAmountData['amount_limit_result'] = "failed";
                $shopOrderAmountData['amount_limit_tips'] = $matchedBasicBusinessLimitList[$shopId]['amount_limit']['tips'];
            } else {
                $shopOrderAmountData['amount_limit_result'] = "success";
                $shopOrderAmountData['amount_limit_tips'] = "";
            }
        }
        unset($shopOrderAmountData);

        $checked = true;
        foreach ($orderData as $k => $item) {
            if (isset($orderAmountIndexShopIdList[$item['pop_shop_id']]['amount_limit_result']) && $orderAmountIndexShopIdList[$item['pop_shop_id']]['amount_limit_result'] == "failed") {
                $checked = false;
                $orderData[$k]['amount_limit_result'] = "failed";
                $orderData[$k]['amount_limit_tips'] = $orderAmountIndexShopIdList[$item['pop_shop_id']]['amount_limit_tips'];
            } else {
                $orderData[$k]['amount_limit_result'] = "success";
                $orderData[$k]['amount_limit_tips'] = "";
            }
        }

        return $checked;
    }

    /**
     * @param $shopIds
     *
     * @return array
     */
    public function getMatchedBasicBusinessLimit($relatedIds): array
    {
        $condition               = [];
        $condition['related_id'] = $relatedIds;
        $basicBusinessLimitList  = BasicBusinessLimitModel::getBasicBusinessLimitList($condition);

        $matchedBasicBusinessLimitList = [];
        foreach ($relatedIds as $shopId) {
            $matchedBasicBusinessLimitList[$shopId] = [];
            if (isset($basicBusinessLimitList[$shopId])) {
                if (isset($basicBusinessLimitList[$shopId]['num_limit'])) {
                    $basicBusinessNumLimit = $basicBusinessLimitList[$shopId]['num_limit'];
                    array_multisort(array_column($basicBusinessNumLimit, 'limit'), SORT_ASC, $basicBusinessNumLimit);
                    $matchedBasicBusinessLimitList[$shopId]['num_limit'] = $basicBusinessNumLimit[0];
                }
                if (isset($basicBusinessLimitList[$shopId]['amount_limit'])) {
                    $basicBusinessAmountLimit = $basicBusinessLimitList[$shopId]['amount_limit'];
                    array_multisort(array_column($basicBusinessAmountLimit, 'limit'), SORT_ASC, $basicBusinessAmountLimit);
                    $matchedBasicBusinessLimitList[$shopId]['amount_limit'] = $basicBusinessAmountLimit[0];
                }
            }
        }

        return $matchedBasicBusinessLimitList;
    }

}


