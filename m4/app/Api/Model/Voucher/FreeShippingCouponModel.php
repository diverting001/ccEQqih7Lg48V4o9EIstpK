<?php
/**
 * Created by PhpStorm.
 * User: chuanbin
 * Date: 2017/11/4
 * Time: 22:57
 */

namespace App\Api\Model\Voucher;


use App\Api\Logic\MessageCenter;
use App\Api\Logic\UserCenterMsg;
use App\Api\V1\Service\Voucher\Voucher as VoucherService;

class FreeShippingCouponModel
{
    private $_db;
    private $tableFreeshippingMember = "promotion_freeshipping_member";
    private $tableFreeshippingRules = "promotion_freeshipping_rules";
    private $tableFreeshippingOrder = "promotion_freeshipping_order";
    private $tableB2cGoodsCat = "sdb_b2c_goods_cat";
    private $tableFreeshippingLimit = "promotion_freeshipping_limit";
    const MAX_LIMIT = 2;


    public function __construct()
    {
        $this->_db = app('api_db')->connection('neigou_store');
    }

    public function createMemberCoupon(
        $memberId,
        $companyId,
        $couponName,
        $validTime,
        $op_name,
        $rule_id,
        $rule_name,
        $money,
        $start_time,
        &$err_code,
        &$err_msg
    ) {
        if (empty($memberId) ||
            empty($companyId) ||
            empty($couponName) ||
            empty($validTime)) {
            return false;
        }
        $validTime = strtotime(date("Y-m-d", $validTime) . " 23:59:59");
        $currentTime = time();
        $couponData = array(
            "member_id" => $memberId,
            "company_id" => $companyId,
            "coupon_name" => $couponName,
            "valid_time" => $validTime,
            "create_time" => $currentTime,
            "last_modified" => $currentTime,
//            "rule_id"=>0,
//            "rule_name"=>"京东模块可用",
            "op_name" => $op_name,
            "rule_id" => $rule_id,
            "rule_name" => $rule_name,
            "money" => $money,
            "start_time" => $start_time,
            "voucher_type" => 0,
        );
        $result = $this->_db->table($this->tableFreeshippingMember)->insertGetId($couponData);
        if (!empty($result)) {
            $member_list = array();
            $member_list[] = array(
                "member_id" => $memberId,
                "company_id" => $companyId,
                "valid_time" => $couponData['valid_time'],
                "rule_name" => $couponData['rule_name']
            );

            //1105
            $message_center_mgr = new MessageCenter();
            $message_center_mgr->freeshippingSendMessage($member_list);


            $user_center_msg = new UserCenterMsg();
            $user_center_msg->freeshippingSendUserCenterMsg($member_list);
            return $result;
        } else {
            $err_code = 20001;
            $err_msg = "创建券失败";
            return $err_msg;
        }
    }

    public function createOrderForCoupon($couponId, $orderId, $memberId, $companyId, $filterData, &$err_code, &$err_msg)
    {
        if (empty($couponId) ||
            empty($orderId) ||
            empty($memberId)) {
            return false;
        }
        $currentTime = time();
        $findOrderCouponData = $this->_db->selectOne("select * from {$this->tableFreeshippingOrder} where order_id=:orderId and (status=0 or status=1) and voucher_type = 0",array($orderId));
        if (!empty($findOrderCouponData)) {
            $err_code = 30002;
            $err_msg = "此订单已经使用过免邮券";
            return $err_msg;
        }
        $this->_db->beginTransaction();
        $useSuccess = true;
        $where = [
            ['coupon_id', $couponId],
            ['member_id', $memberId]
        ];
        $findCouponData = $this->_db->table($this->tableFreeshippingMember)->where($where)->first();
        if (empty($findCouponData)) {
            $err_code = 20001;
            $err_msg = "免邮券不存在";
            $useSuccess = false;
        }

        if ($findCouponData->rule_id > 0) {
            //进行验证 如果返回false unset这张券
            $rule = $this->getRule($findCouponData->rule_id);
            $rzt = $this->filter_rule(unserialize($rule->rule_condition), $filterData);
            if ($rzt == false) {
                $err_code = '20001';
                $err_msg = "订单无法使用免邮券";
                return $err_msg;
            }
        } else {
            $matchResult = $this->ruleMatch($filterData);
            if (empty($matchResult)) {
                $err_code = '20001';
                $err_msg = "订单无法使用免邮券";
                return $err_msg;
            }
        }

        if ($findCouponData->status != 0) {
            $err_code = 30003;
            $err_msg = "免邮券已使用";
            $useSuccess = false;
        }

        // 检查免邮券使用限制
        if (!$this->_checkCouponLimit($memberId, $companyId)) {
            $err_code = 30004;
            $err_msg = "已超出使用限制";
            return false;
        }


        if (!empty($useSuccess)) {
            $couponOrderData = array(
                "member_id" => $memberId,
                "coupon_id" => $couponId,
                "order_id" => $orderId,
                "use_time" => $currentTime,
                "status" => 0
            );
//            $couponOrderId = $this->storeSqllink->insert($couponOrderData, $this->tableFreeshippingOrder);
            $couponOrderId = $this->_db->table($this->tableFreeshippingOrder)->insertGetId($couponOrderData);
            if (empty($couponOrderId)) {
                $err_code = 20002;
                $err_msg = "免邮券使用失败";
                $useSuccess = false;
            }
        }

        if (!empty($useSuccess)) {
            $updateCouponData = array(
                "status" => 1,
                "last_modified" => $currentTime,
                "use_time" => $currentTime,
            );
//            $updateResult = $this->storeSqllink->update($updateCouponData, $this->tableFreeshippingMember, "coupon_id={$couponId}");
            $updateResult = $this->_db->table($this->tableFreeshippingMember)->where('coupon_id',
                $couponId)->update($updateCouponData);
            if ($updateResult === -1) {
                $err_code = 20003;
                $err_msg = "券状态更新失败";
                $useSuccess = false;
            }
        }
        if (!empty($useSuccess)) {
            $this->_db->commit();
            return $couponId;
        } else {
            $this->_db->rollback();
            return false;
        }
    }

    private function getCouponInfo($where)
    {
        return $this->_db->table($this->tableFreeshippingMember)->where($where)->first();
    }

    private function ret_msg($code, $msg, $status)
    {
        $data['code'] = $code;
        $data['msg'] = $msg;
        $data['status'] = $status;
        return $data;
    }


    /**
     * 订单使用多张免邮券
     * @param $couponInfo
     * @return bool|string
     */
    public function createOrderForCouponV2($couponInfo)
    {
        if (!is_array($couponInfo)) {
            return '参数错误';
        }
        $currentTime = time();
        $this->_db->beginTransaction();
        foreach ($couponInfo as $key => $value) {
            $couponId = $value['coupon_id'];
            $orderId = $value['order_id'];
            $memberId = $value['member_id'];
            $companyId = $value['company_id'];
            $filterData = $value['filter_data'];
            $where = [
                ['coupon_id', $couponId],
                ['member_id', $memberId]
            ];
            $findCouponData = $this->getCouponInfo($where);
            if (empty($findCouponData)) {
                return $couponId . '免邮券不存在';
            }
            if ($findCouponData->rule_id > 0) {
                //进行验证 如果返回false unset这张券
                $rule = $this->getRule($findCouponData->rule_id);
                $rzt = $this->filter_parse(unserialize($rule->rule_condition), $filterData, $findCouponData->money);
                if ($rzt == false) {
                    return $couponId . '订单无法使用免邮券';
                }
            } else {
                $matchResult = $this->ruleMatch($filterData);
                if (empty($matchResult)) {
                    return $couponId . '订单无法使用免邮券';
                }
            }
            if ($findCouponData->status != 0) {
                return $couponId . '免邮券已使用';
            }
            // 检查免邮券使用限制
            if (!$this->_checkCouponLimit($memberId, $companyId)) {
                return $couponId . '已超出使用限制';
            }
            $couponOrderData = array(
                "member_id" => $memberId,
                "coupon_id" => $couponId,
                "order_id" => $orderId,
                "use_time" => $currentTime,
                "use_money" => $rzt['match_use_money'],
                "status" => 0
            );
            $couponOrderId = $this->_db->table($this->tableFreeshippingOrder)->insertGetId($couponOrderData);
            if (empty($couponOrderId)) {
                return $couponId . '免邮券使用失败';
            }
            $updateCouponData = array(
                "status" => 1,
                "last_modified" => $currentTime,
                "use_time" => $currentTime,
            );
            $updateResult = $this->_db->table($this->tableFreeshippingMember)->where('coupon_id',
                $couponId)->update($updateCouponData);
            if ($updateResult === -1) {
                return $couponId . '券状态更新失败';
            }
            $couponInfo[$key]['status'] = true;
            $return[$key]['coupon_id'] = $couponId;
            $return[$key]['match_info'] = $rzt;
            $return[$key]['order_id'] = $orderId;
        }
        foreach ($couponInfo as $key => $info) {
            if ($info['status'] == false) {
                $this->_db->roolback();
                return '免邮券使用失败';
            }
        }
        $this->_db->commit();
        return $return;
    }

    /**
     * 订单取消归还券
     * @param $orderId
     * @param $err_code
     * @param $err_msg
     * @return bool
     */
    public function cancelOrderForCoupon($orderId, &$err_code, &$err_msg)
    {
        if (empty($orderId)) {
            return false;
        }
        $currentTime = time();
        $this->_db->beginTransaction();
        $useSuccess = true;
        $where = [
            ['order_id', $orderId],
            ['status', 0]
        ];
        //查询订单使用过的券
        $coupon_list = $this->_db->table($this->tableFreeshippingOrder)->where($where)->get()->all();
        if (count($coupon_list) <= 0) {
            return true;
        }
        $coupon_ids = array();
        foreach ($coupon_list as $coupon) {
            $coupon_ids[] = $coupon->coupon_id;
        }

        $updateCouponOrderData = array(
            "status" => 2,
        );
        //设置券和订单的关系
        $orderCouponId = $this->_db->table($this->tableFreeshippingOrder)->where($where)->update($updateCouponOrderData);
        if ($orderCouponId === false) {
            $err_code = 20002;
            $err_msg = "订单券状态更新失败";
            $useSuccess = false;
        }
        if (!empty($useSuccess)) {
            $updateCouponData = array(
                "status" => 0,
                "last_modified" => $currentTime,
                "use_time" => 0,
            );
            $updateResult = $this->_db->table($this->tableFreeshippingMember)->whereIn('coupon_id',
                $coupon_ids)->update($updateCouponData);
            if ($updateResult === -1) {
                $err_code = 20003;
                $err_msg = "券状态更新失败";
                $useSuccess = false;
            }
        }
        if (!empty($useSuccess)) {
            $this->_db->commit();
            return true;
        } else {
            $this->_db->rollback();
            return false;
        }
    }

    public function finishOrderForCoupon($orderId, &$err_code, &$err_msg)
    {
        if (empty($orderId)) {
            return false;
        }
        $currentTime = time();
        $this->_db->beginTransaction();
        $useSuccess = true;
        $where = [
            ['order_id', $orderId],
            ['voucher_type', 0],
            ['status', 0]
        ];
        $couponList = $this->_db->table($this->tableFreeshippingOrder)->where($where)->get()->all();
        if (count($couponList) <= 0) {
            return true;
        }

        $updateCouponOrderData = array(
            "status" => 1,
        );
        $orderCouponId = $this->_db->table($this->tableFreeshippingOrder)->where($where)->update($updateCouponOrderData);
        if ($orderCouponId === false) {
            $err_code = 20002;
            $err_msg = "订单券状态更新失败";
            $useSuccess = false;
        }
        if (!empty($useSuccess)) {
            $updateCouponData = array(
                "status" => 2,
                "last_modified" => $currentTime,
            );
            //查询订单用到的券
            foreach ($couponList as $coupon) {
                $couponIdArr[] = $coupon->coupon_id;
            }
            $updateResult = $this->_db->table($this->tableFreeshippingMember)->whereIn('coupon_id',
                $couponIdArr)->update($updateCouponData);
            if ($updateResult === -1) {
                $err_code = 20003;
                $err_msg = "券状态更新失败";
                $useSuccess = false;
            }
        }
        if (!empty($useSuccess)) {
            $this->_db->commit();
            return true;
        } else {
            $this->_db->rollback();
            return false;
        }
    }

    public function queryOrderCoupon($orderId, &$err_code, &$err_msg)
    {
        if (empty($orderId)) {
            return false;
        }
        $where = [
            ['order_id', $orderId],
            ['status', '!=', 2]
        ];
        $findOrderCouponData = $this->_db->table($this->tableFreeshippingOrder)->where($where)->first();
        if (empty($findOrderCouponData)) {
            $findOrderCouponData = $this->_db->table($this->tableFreeshippingOrder)->where('order_id',
                $orderId)->orderBy('use_time', 'desc')->first();
        }
        return $findOrderCouponData;
    }

    public function queryMemberCoupon($memberId, $where, $limit, &$err_code, &$err_msg)
    {
        if (empty($memberId)) {
            return false;
        }
        $sql = "select * from {$this->tableFreeshippingMember} fs_mem join promotion_freeshipping_rules rule on fs_mem.rule_id = rule.rule_id where member_id={$memberId} AND fs_mem.voucher_type=0 ";
        if (!empty($where)) {
            $sql .= " and {$where}";
        }
        if (!empty($limit)) {
            $sql .= " limit {$where}";
        }

        $sql .= " order by coupon_id desc";
        $findMemberCouponData = $this->_db->select($sql);
        return $findMemberCouponData;
    }

    public function queryMemberCouponWithRule($memberId, $companyId, $filterData, &$err_code, &$err_msg)
    {
        if (empty($memberId) || empty($filterData)) {
            return false;
        }
        // 检查免邮券使用限制
        if (!$this->_checkCouponLimit($memberId, $companyId)) {
            return true;
        }
        $currentTime = time();
        $where = [
            ['member_id', $memberId],
            ['status', 0],
            ['voucher_type', 0],
            ['valid_time', '>=', $currentTime]
        ];
        $findMemberValidCouponData = $this->_db->table($this->tableFreeshippingMember)->where($where)->orderBy('coupon_id',
            'desc')->get()->all();
        //检验有rule_id的券需要验证
        $findMemberValidCouponData = json_decode(json_encode($findMemberValidCouponData), true);
        foreach ($findMemberValidCouponData as $key => $coupon) {
            if ($coupon['rule_id'] > 0) {
                //进行验证 如果返回false unset这张券
                $rule = $this->getRule($coupon['rule_id']);
                $rzt = $this->filter_rule(unserialize($rule->rule_condition), $filterData);
                if ($rzt == false) {
                    unset($findMemberValidCouponData[$key]);
                }
            } else {
                $matchResult = $this->ruleMatch($filterData);
                if (empty($matchResult)) {
                    unset($findMemberValidCouponData[$key]);
                }
            }
        }
        return $findMemberValidCouponData;
    }

    public function filter_rule($ruleCondition, $filterData)
    {

        // 版本兼容
        if (!empty($filterData['version'])) {
            $funcName = '_newFilterRuleMatchV' . intval($filterData['version']);
            if (method_exists($this, $funcName)) {
                return $this->$funcName($ruleCondition, $filterData);
            }
            return false;
        }


        $ruleCondition = $ruleCondition[0];


        if (!is_array($filterData) || !isset($filterData['cart_objects']['object']['goods'])) {
            return false;
        }
        $price = 0;
        $goods_list = $filterData['cart_objects']['object']['goods'];

        foreach ($goods_list as $k => $goods) {
            $product_price = $goods['obj_items']['products'][0]['price']['price'];
            $number = $goods['quantity'];
            $goodsId = $goods['obj_items']['products'][0]['goods_id'];
            $price += $product_price * $number;
            $goodsList[] = $goodsId;
        }

        switch ($ruleCondition['processor']) {
            case 'CostReachedRuleProcessor':
                if (($price > $ruleCondition['filter_rule']['limit_cost'] OR abs($price - $ruleCondition['filter_rule']['limit_cost']) < 0.0001)) {
                    return true;
                } else {
                    return false;
                }
                break;
            case 'CostMallAndCatRuleProcessor':
                //验证商城类目是否符合
                // 获取商城 ID
                $mallList = $this->_getMallIdbyGoodsIds($goodsList);

                // 获取商品一级分类
                $mallCatPathTree = $this->getMallCatPathTreeByGoodsIds($goodsList);

                $allMatched = true;
                foreach ($filterData['products'] as $product) {
                    $mallIdMatch = false;
                    $mallCatIdMatch = false;
                    $mallIdIntersect = array_intersect($ruleCondition['filter_rule']['mall_id_list'],
                        !empty($mallList[$product['goods_id']]) ? $mallList[$product['goods_id']] : array());
                    if (in_array('all', $ruleCondition['filter_rule']['mall_id_list'])) {
                        $mallIdMatch = true;
                    } else {
                        if (!empty($mallIdIntersect)) {
                            $mallIdMatch = true;
                        }
                    }

                    if (in_array('all', $ruleCondition['filter_rule']['mall_cat_id_list'])) {
                        $mallCatIdMatch = true;
                    } else {
                        if (!empty($mallCatPathTree[$product['goods_id']])) {
                            foreach ($mallCatPathTree[$product['goods_id']] as $mallCatId) {
                                if (in_array($mallCatId, $ruleCondition['filter_rule']['mall_cat_id_list'])) {
                                    $mallCatIdMatch = true;
                                }
                            }
                        }
                    }

                    if (!$mallCatIdMatch || !$mallIdMatch) {
                        $allMatched = false;
                        break;
                    }
                }
                if (($price > $ruleCondition['filter_rule']['limit_cost'] OR abs($price - $ruleCondition['filter_rule']['limit_cost']) < 0.0001)
                    && $allMatched) {
                    return true;
                } else {
                    return false;
                }
                break;
            default:
                return false;
                break;
        }
    }

    /**
     * 通过商品 ID 获取商城 ID
     *
     * @param   $goodsIds   array   商品 ID
     * @return  array
     */
    private function _getMallIdByGoodsIds($goodsIds)
    {
        $return = array();

        if (empty($goodsIds)) {
            return $return;
        }

        $goodsIds = implode(',', array_filter($goodsIds));
        $sql = "select goods_id,mall_id from mall_module_mall_goods where goods_id IN ({$goodsIds})";
        $mallGoodsList = $this->_db->select($sql);

        if ($mallGoodsList) {
            foreach ($mallGoodsList as $mallGoodsItem) {
                $return[$mallGoodsItem->goods_id][] = $mallGoodsItem->mall_id;
            }
        }

        return $return;
    }

    /**
     * 通过商品 ID 获取商城分类路径树
     *
     * @param   $goodsIds   array   商品 ID
     * @return  array
     */
    private function getMallCatPathTreeByGoodsIds($goodsIds)
    {
        $return = array();

        if (empty($goodsIds)) {
            return $return;
        }

        // 获取商城分类 ID
        $goodsIds = implode(',', array_filter($goodsIds));
        $sql = "select goods_id,mall_goods_cat from sdb_b2c_goods where goods_id IN ({$goodsIds})";
        $result = $this->_db->select($sql);
        if (empty($result)) {
            return $return;
        }

        foreach ($result as $v) {
            if (!empty($v->mall_goods_cat)) {
                $return[$v->goods_id] = $v->mall_goods_cat;
            }
        }

        if (empty($return)) {
            return $return;
        }

        // 获取分类的信息
        $mallCatIds = implode(',', $return);
        $sql = "select cat_id,cat_path from sdb_b2c_mall_goods_cat where cat_id IN ({$mallCatIds})";
        $result = $this->_db->select($sql);
        $mallsCatPath = array();
        if (!empty($result)) {
            foreach ($result as $v) {
                $mallsCatPath[$v->cat_id] = $v->cat_path;
            }
        }

        // 获取商品的商城一级分类
        foreach ($return as $goodsId => $catId) {
            if (!isset($mallsCatPath[$catId])) {
                unset($return[$goodsId]);
                continue;
            }
            $goodsMallCatPathTree = array_values(array_filter(explode(',', $mallsCatPath[$catId])));
            $goodsMallCatPathTree[] = $catId;
            if (!empty($goodsMallCatPathTree)) {
                $return[$goodsId] = $goodsMallCatPathTree;
            }
        }

        return $return;
    }

    public function queryCouponWithRule($couponId, $memberId, $companyId, $filterData, &$err_code, &$err_msg)
    {
        if (empty($couponId) || empty($memberId) || empty($filterData)) {
            return false;
        }
        $currentTime = time();
        $where = [
            ['member_id', $memberId],
            ['coupon_id', $couponId],
            ['voucher_type', 0]
        ];
        $couponInfo = $this->_db->table($this->tableFreeshippingMember)->where($where)->first();

        if ($couponInfo->rule_id > 0) {
            //进行验证 如果返回false unset这张券
            $rule = $this->getRule($couponInfo->rule_id);
            $rzt = $this->filter_rule(unserialize($rule->rule_condition), $filterData);
            if ($rzt == false) {
                $err_code = '20001';
                $err_msg = "订单无法使用免邮券";
                return $err_msg;
            }
        } else {
            $matchResult = $this->ruleMatch($filterData);
            if (empty($matchResult)) {
                $err_code = '20001';
                $err_msg = "订单无法使用免邮券";
                return $err_msg;
            }
        }


        if (empty($couponInfo)) {
            $err_code = '20002';
            $err_msg = "错误的免邮券";
            return $err_msg;
        }

        if ($couponInfo->status != 0) {
            $err_code = '20003';
            $err_msg = "已使用的免邮券";
            return $err_msg;
        }

        if ($couponInfo->valid_time < $currentTime) {
            $err_code = '20003';
            $err_msg = "已过期的免邮券";
            return $err_msg;
        }
        if ($couponInfo->start_time > $currentTime) {
            $err_code = '20004';
            $err_msg = "未开始的免邮券";
            return false;
        }
        // 检查免邮券使用限制
        if (!$this->_checkCouponLimit($memberId, $companyId)) {
            $err_code = '20005';
            $err_msg = "已超出使用限制";
            return false;
        }

        return $couponInfo;
    }


    private function checkVoucher($couponInfo, $filterData, $member_id, $company_id)
    {
        if (empty($couponInfo)) {
            $ret['msg'] = $couponInfo->coupon_id . "错误的免邮券";
            $ret['status'] = 0;
            return $ret;
        }

        if ($couponInfo->status != 0) {
            $ret['msg'] = $couponInfo->coupon_id . "已使用的免邮券";
            $ret['status'] = 0;
            return $ret;
        }
        if ($couponInfo->valid_time < time()) {
            $ret['msg'] = $couponInfo->coupon_id . "已过期的免邮券";
            $ret['status'] = 0;
            return $ret;
        }
        if ($couponInfo->start_time > time()) {
            $ret['msg'] = $couponInfo->coupon_id . "未开始的免邮券";
            $ret['status'] = 0;
            return $ret;
        }
        // 检查免邮券使用限制
        if (!$this->_checkCouponLimit($member_id, $company_id)) {
            $ret['msg'] = "已超出使用限制";
            $ret['status'] = 0;
            return $ret;
        }
        if ($couponInfo->rule_id > 0) {
            //进行验证 如果返回false unset这张券
            $rule = $this->getRule($couponInfo->rule_id);
            $matchResult = $this->filter_parse(unserialize($rule->rule_condition), $filterData);
            if ($matchResult == false) {
                $condition = unserialize($rule->rule_condition);
                $ret['limit_cost'] = $condition[0]['filter_rule']['limit_cost'];
                $ret['match_use_money'] = 0;
                $ret['product_bn_list'] = array();
                $ret['need_money'] = $ret['limit_cost'];
                $ret['msg'] = $couponInfo->coupon_id . "订单无法使用免邮券";
                $ret['status'] = 0;
                return $ret;
            } else {
                $freight = 0;
                foreach ($filterData['products'] as $key => $good) {
                    foreach ($matchResult['product_bn_list'] as $bn) {
                        if ($good['bn'] == $bn) {
                            $freight += $good['freight'];
                        }
                    }
                }
//                $matchResult['msg'] = '当前可用';
                $matchResult['match_use_money'] = ($freight>$couponInfo->money && $couponInfo->money>0)?$couponInfo->money:$freight;
//                $matchResult['status'] = 1;
                return $matchResult;
            }
        } else {
            $matchResult = $this->ruleMatch($filterData);
            if (empty($matchResult)) {
                $ret['msg'] = $couponInfo->coupon_id . "订单无法使用免邮券";
                $ret['status'] = 0;
                return $ret;
            }
        }
        return $matchResult;
    }

    public function queryCouponListWithRule($params)
    {
        //param couponIds filter_data
        foreach ($params['voucher_data'] as $key => $val) {
            $memberId = $val['member_id'];
            $couponId = $val['coupon_id'];
            $companyId = $val['company_id'];
            $where = [
                ['member_id', $memberId],
                ['coupon_id', $couponId],
                ['voucher_type', 0]
            ];
            $couponInfo = $this->_db->table($this->tableFreeshippingMember)->where($where)->first();
            $val['filter_data']['version'] = $params['version'];
            $val['filter_data']['newcart'] = $params['newcart'];
            $params['voucher_data'][$key]['result'] = $this->checkVoucher($couponInfo, $val['filter_data'], $memberId,
                $companyId);
        }
        return $params;
    }

    private function ruleMatch($filterData)
    {
        // 版本兼容
        if (!empty($filterData['version'])) {
            $funcName = '_ruleMatchV' . intval($filterData['version']);
            if (method_exists($this, $funcName)) {
                return $this->$funcName($filterData);
            }
            return false;
        }
        if (!is_array($filterData) || !isset($filterData['cart_objects']['object']['goods'])) {
            return false;
        }
        $goods_list = $filterData['cart_objects']['object']['goods'];

        $cat_id_reflect_cache = array();
        foreach ($goods_list as $k => $goods) {
            $cat_id = $goods['obj_items']['products'][0]['cat_id'];
            if (empty($cat_id_reflect_cache[$cat_id])) {
                $rootCatId = $this->getRootCatIdByCatId($cat_id);
                $cat_id_reflect_cache[$cat_id] = $rootCatId;
            } else {
                $rootCatId = $cat_id_reflect_cache[$cat_id];
            }
            if ($rootCatId != config('neigou.JD_GOODS_CAT_ID', 2113)) {
                return false;
            }
        }

        return true;
    }

    // --------------------------------------------------------------------

    /**
     * 根据规则条件计算是否匹配
     *
     * @param   $filterData    array   订单数据
     * @return  boolean
     */
    private function _ruleMatchV6($filterData)
    {
        if (!is_array($filterData) OR empty($filterData['products'])) {
            return false;
        }

        $goodsList = array();
        foreach ($filterData['products'] as $product) {
            $goodsList[] = $product['goods_id'];
        }

        // 获取商品的根分类
        $goodsRootCat = $this->_getRootCatIdByGoodsIds($goodsList);

        foreach ($filterData['products'] as $product) {
            if (!isset($goodsRootCat[$product['goods_id']]) OR $goodsRootCat[$product['goods_id']] != config('neigou.JD_GOODS_CAT_ID',
                    1607)) {
                return false;
            }
        }

        return true;
    }

    private function _newFilterRuleMatchV6($ruleCondition, $filterData)
    {
        $ruleCondition = $ruleCondition[0];
        if (empty($filterData['products'])) {
            return false;
        }

        $price = 0;
        foreach ($filterData['products'] as $product) {
            $price += $product['price'] * $product['quantity'];
            $goodsList[] = $product['goods_id'];
        }


        switch ($ruleCondition['processor']) {
            case 'CostReachedRuleProcessor':
                if (($price > $ruleCondition['filter_rule']['limit_cost'] OR abs($price - $ruleCondition['filter_rule']['limit_cost']) < 0.0001)) {
                    return true;
                } else {
                    return false;
                }
                break;
            case 'CostMallAndCatRuleProcessor':
                //验证商城类目是否符合
                // 获取商城 ID
                $mallList = $this->_getMallIdbyGoodsIds($goodsList);

                // 获取商品一级分类
                $mallCatPathTree = $this->getMallCatPathTreeByGoodsIds($goodsList);

                $allMatched = true;
                foreach ($filterData['products'] as $product) {
                    $mallIdMatch = false;
                    $mallCatIdMatch = false;
                    $mallIdIntersect = array_intersect($ruleCondition['filter_rule']['mall_id_list'],
                        !empty($mallList[$product['goods_id']]) ? $mallList[$product['goods_id']] : array());
                    if (in_array('all', $ruleCondition['filter_rule']['mall_id_list'])) {
                        $mallIdMatch = true;
                    } else {
                        if (!empty($mallIdIntersect)) {
                            $mallIdMatch = true;
                        }
                    }

                    if (in_array('all', $ruleCondition['filter_rule']['mall_cat_id_list'])) {
                        $mallCatIdMatch = true;
                    } else {
                        if (!empty($mallCatPathTree[$product['goods_id']])) {
                            foreach ($mallCatPathTree[$product['goods_id']] as $mallCatId) {
                                if (in_array($mallCatId, $ruleCondition['filter_rule']['mall_cat_id_list'])) {
                                    $mallCatIdMatch = true;
                                }
                            }
                        }
                    }

                    if (!$mallCatIdMatch || !$mallIdMatch) {
                        $allMatched = false;
                        break;
                    }
                }
                if (($price > $ruleCondition['filter_rule']['limit_cost'] OR abs($price - $ruleCondition['filter_rule']['limit_cost']) < 0.0001)
                    && $allMatched) {
                    return true;
                } else {
                    return false;
                }
                break;
            default:
                return false;
                break;
        }
        return false;
    }

    private function getRootCatIdByCatId($cat_id = 0)
    {
//        $sql = "select * from {$this->tableB2cGoodsCat} where cat_id = $cat_id";
//        $result = $this -> storeSqllink -> findOne($sql);
        $result = $this->_db->table($this->tableB2cGoodsCat)->where('cat_id', $cat_id)->first();
        if ($result) {
            $catPath = $result->cat_path;
            if ($catPath == ',') {
                $rootCatId = $result->cat_id;
            } else {
                $rootCatId = explode(',', $catPath);
                $rootCatId = array_filter($rootCatId);
                $rootCatId = current($rootCatId);
            }
            return $rootCatId;
        }
        return false;
    }

    // --------------------------------------------------------------------

    /**
     * 通过商品 ID 分类根分类
     *
     * @param   $goodsIds   array   商品 ID
     * @return  array
     */
    private function _getRootCatIdByGoodsIds($goodsIds)
    {
        $return = array();

        if (empty($goodsIds)) {
            return $return;
        }

        // 获取商城分类 ID
        $goodsIds = implode(',', array_filter($goodsIds));

        // 查询商品 cat_id
        $sql = "select goods_id,cat_id from sdb_b2c_goods where goods_id IN ({$goodsIds})";
        $result = $this->_db->select($sql);
        if (empty($result)) {
            return $return;
        }

        $goodsCats = array();
        foreach ($result as $v) {
            $goodsCats[$v->goods_id] = $v->cat_id;
        }

        // 查询分类的 path

        $catIds = implode(',', $goodsCats);
        $sql = "select cat_id,cat_path from sdb_b2c_goods_cat where cat_id IN ({$catIds})";
        $result = $this->_db->select($sql);

        if (empty($result)) {
            return $return;
        }

        $catsRoot = array();
        foreach ($result as $v) {
            $catsRoot[$v->cat_id] = $v->cat_path == ',' ? $v->cat_id : current(array_filter(explode(',',
                $v->cat_path)));
        }
        // 获取商品的模块 ID
        foreach ($goodsCats as $goodsId => $catId) {
            if (isset($catsRoot[$catId])) {
                $return[$goodsId] = $catsRoot[$catId];
            }
        }
        return $return;
    }

    // --------------------------------------------------------------------

    /**
     * 检查免邮券的使用限制
     *
     * @param   $memberId       mixed     用户ID
     * @param   $companyId      mixed     公司ID
     * @return  boolean
     */
    private function _checkCouponLimit($memberId = null, $companyId = null)
    {
        // 公司下员工每日使用的次数限制
        if ($memberId !== null && $companyId !== null) {
            $now = time();
            $limitResult = $this->_db->selectOne("SELECT * FROM {$this->tableFreeshippingLimit} WHERE company_id= :companyId AND limit_type = 'MEMBER' AND status=1 AND (start_time <= {$now} OR start_time IS NULL) AND (end_time > {$now} OR end_time IS NULL) ORDER BY id DESC",array($companyId));
            if (!empty($limitResult) && $limitResult->max_use_count > 0) {
                $startTime = strtotime(date('Y-m-d', $now));
                $endTime = $startTime + 86400;
                // 查询本日用户已使用的数量
                $usedResult = $this->_db->selectOne("SELECT COUNT(a.coupon_id) AS count FROM {$this->tableFreeshippingOrder} as a,{$this->tableFreeshippingMember} as b WHERE a.voucher_type=0 AND a.coupon_id=b.coupon_id AND a.member_id=:memberId AND a.use_time >= {$startTime} AND a.use_time < {$endTime} AND a.status IN (0, 1)",array($memberId));
                if ($usedResult->count >= $limitResult->max_use_count) {
                    return false;
                }
            } else {
                $startTime = strtotime(date('Y-m-d', $now));
                $endTime = $startTime + 86400;
                // 查询本日用户已使用的数量
                $usedResult = $this->_db->selectOne("SELECT COUNT(a.coupon_id) AS count FROM {$this->tableFreeshippingOrder} as a,{$this->tableFreeshippingMember} as b WHERE a.coupon_id=b.coupon_id AND a.member_id=:memberId AND a.use_time >= {$startTime} AND a.use_time < {$endTime} AND a.status IN (0, 1)",array($memberId));
                if ($usedResult->count >= self::MAX_LIMIT) {
                    return false;
                }
            }
        }

        return true;
    }

    //获取券规则
    public function getRuleList($params_data_array)
    {
        if (!isset($params_data_array["rule_id_list"]) &&
            !is_array($params_data_array["rule_id_list"]) &&
            empty($params_data_array["rule_id_list"])) {
            \Neigou\Logger::General('action.voucher',
                array('action' => 'getRuleList', 'success' => 0, 'reason' => 'invalid_params'));
            return "免邮券参数不正确";//err 1
        }
        $ruleIdListStr = implode(',', $params_data_array["rule_id_list"]);
        if (empty($ruleIdListStr)) {
            return "免邮券参数不正确";//err 1
        }
        $sql = "select * from {$this->tableFreeshippingRules} where rule_id in ({$ruleIdListStr})";
        $result = $this->_db->select($sql);
        if (!$result) {
            return "规则ID指向规则不存在";//err 1
        }
        $ruleInfoList = array();
        foreach ($result as $ruleInfoItem) {
            $ruleInfoList[$ruleInfoItem->rule_id] = $ruleInfoItem;
        }
        return $ruleInfoList;
    }

    //获取券规则
    public function getRule($rule_id)
    {
        $result = $this->_db->table($this->tableFreeshippingRules)->where('rule_id', $rule_id)->first();
        if (!$result) {
            return "规则ID指向规则不存在";
        }
        return $result;
    }

    public function saveRule($save_data_array)
    {
        if (empty($save_data_array) ||
            !isset($save_data_array['rule_type']) ||
            !isset($save_data_array['description']) ||
            !isset($save_data_array['op_name']) ||
            !isset($save_data_array['log_text']) ||
            !isset($save_data_array['rule_data'])) {
            \Neigou\Logger::General('FreeShippingRule', array(
                'action' => 'saveRule',
                'success' => 0,
                'reason' => 'invalid_params',
                'params' => $save_data_array
            ));
            return "无效参数";
        }
        $str_generator = 'App\Api\Model\Voucher\Generate\\' . $save_data_array['rule_type'];
        $generator = new $str_generator;
        if (method_exists($generator, "create")) {
            $create_rule = $generator->create($save_data_array['rule_data']);
            if ($create_rule) {
                if (empty($save_data_array['rule_id'])) {
                    if (!isset($save_data_array['name'])) {
                        \Neigou\Logger::General('FreeShippingRule', array(
                            'action' => 'saveRule',
                            'success' => 0,
                            'reason' => 'require name'
                        ));
                        return "无效参数";
                    }
                    $create_data = array(
                        'name' => $save_data_array['name'],
                        'tag' => $save_data_array['tag'],
                        'description' => $save_data_array['description'],
                        'op_name' => $save_data_array['op_name'],
                        'log_text' => $save_data_array['log_text'],
                        'create_time' => time(),
                        'rule_condition' => serialize(array($create_rule)),
                        'extend_data' => $save_data_array['extend_data']
                    );
                    $result_rule_id = $this->_db->table($this->tableFreeshippingRules)->insertGetId($create_data);
                    $es_res = $this->sendEs($result_rule_id, $create_data['rule_condition'], array());
                    if (!$es_res) {
                        return 'ES商品对应关系任务创建失败';
                    }
                } else {
                    $save_data = array(
                        'description' => $save_data_array['description'],
                        'name' => $save_data_array['name'],
                        'tag' => $save_data_array['tag'],
                        'op_name' => $save_data_array['op_name'],
                        'log_text' => $save_data_array['log_text'],
                        'create_time' => time(),
                        'rule_condition' => serialize(array($create_rule)),
                        'extend_data' => $save_data_array['extend_data']
                    );
                    $where = [
                        ['rule_id', $save_data_array['rule_id']]
                    ];
                    $old_rule = $this->_db->table($this->tableFreeshippingRules)->Where($where)->first();
                    $es_res = $this->sendEs($save_data_array['rule_id'], $save_data['rule_condition'],
                        $old_rule->rule_condition);
                    if (!$es_res) {
                        return 'ES商品对应关系任务创建失败';
                    }
                    $rzt = $this->_db->table($this->tableFreeshippingRules)->where($where)->update($save_data);
                    if ($rzt > 0) {
                        $result_rule_id = $save_data_array['rule_id'];
                    }
                }
                if ($result_rule_id > 0) {
                    \Neigou\Logger::General('FreeShippingRule',
                        array('action' => 'saveRule', 'success' => 1, 'rule_id' => $result_rule_id));
                    return $result_rule_id;
                } else {
                    \Neigou\Logger::General('FreeShippingRule', array(
                        'action' => 'saveRule',
                        'success' => 0,
                        'reason' => 'insert_failed',
                        'param' => $save_data_array
                    ));
                    return "规则插入失败";
                }
            } else {
                \Neigou\Logger::General('FreeShippingRule', array(
                    'action' => 'saveRule',
                    'success' => 0,
                    'reason' => 'insert_type',
                    'param' => $save_data_array
                ));
                return "传入创建规则不合理";
            }
        } else {
            \Neigou\Logger::General('FreeShippingRule',
                array('action' => 'saveRule', 'success' => 0, 'reason' => 'type 不存在', 'param' => $save_data_array));
            return "传入的type不存在";
        }
    }

    /**
     * 创建ES商品对应关系更新任务
     * @param $rule_id
     * @param $condition
     * @param $old_condition
     * @return bool
     */
    public function sendEs($rule_id, $condition, $old_condition)
    {
        $service = new VoucherService();
        $res = $service->sendEsTask($rule_id, $condition, $old_condition, 'freeshipping');
        if ($res) {
            return true;
        } else {
            return false;
        }
    }

    private function filter_parse($ruleCondition, $filter_data,$money=0)
    {
        $condition_item = $ruleCondition[0];
        $str_processor = $condition_item['processor'];
        if (empty($str_processor)) {
            return false;
        }
        $str_processor = 'App\Api\Model\Voucher\Rule\\' . $str_processor;
        $processor = new $str_processor;
        $func_name = !empty($filter_data['version']) ? 'matchV' . intval($filter_data['version']) : 'match';
        if (method_exists($processor, $func_name)) {
            //
            $time = time();
            $retBlackList = $this->_db->table('promotion_voucher_blacklist_rule')->where([
                ['type', 1],
                ['start_time', '<=', $time],
                ['end_time', '>=', $time]
            ])->get()->all();

            $ruleBlackList = array();
            foreach ($retBlackList as $black_item) {
                if (!empty($black_item->rule)) {
                    $rule = json_decode($black_item->rule, true);
                    $ruleBlackList[$rule['bn']] = $rule['bn'];
                }
            }
            $condition_item['filter_rule']['ret_black_list'] = $ruleBlackList;
            $match_data = $processor->$func_name($condition_item['filter_rule'], $filter_data);
            if (empty($match_data)) {
                return false;
            } else {
                $freight = 0;
                foreach ($filter_data['products'] as $key => $good) {
                    foreach ($match_data['product_bn_list'] as $bn) {
                        if ($good['bn'] == $bn) {
                            $freight += $good['freight'];
                        }
                    }
                }
                if ($match_data['match_use_money'] < $match_data['limit_cost']) {
                    $match_data['match_use_money'] = 0;//not match use price 0 to notice
                    $match_data['msg'] = '订单金额不满足，需凑单使用';
                    $match_data['status'] = 0;
                } else {
                    $match_data['msg'] = '当前可用';
                    $match_data['match_use_money'] = ($freight>$money && $money>0)? $money:$freight;//not match use price 0 to notice
                    $match_data['status'] = 1;
                    $match_data['need_money'] = 0;
                }
                return $match_data;
            }
        }
    }

}
