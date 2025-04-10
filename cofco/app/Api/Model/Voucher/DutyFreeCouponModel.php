<?php
/**
 * Created by PhpStorm.
 * User: chuanbin
 * Date: 2018/4/2
 * Time: 15:33
 */

namespace App\Api\Model\Voucher;

use App\Api\Logic\MessageCenter;
use App\Api\Logic\UserCenterMsg;
use App\Api\V1\Service\Voucher\Voucher as VoucherService;


class DutyFreeCouponModel
{
    private $_db;
    private $tableFreeshippingMember = "promotion_freeshipping_member";
    private $tableFreeshippingRules = "promotion_freeshipping_rules";
    private $tableFreeshippingOrder = "promotion_freeshipping_order";
    private $tableFreeshippingLimit = "promotion_freeshipping_limit";
    const MAX_LIMIT = 2;

    public function __construct()
    {
        $this->_db = app('api_db')->connection('neigou_store');
    }

    /**
     * 通过 goods ID 获取品牌 ID
     *
     * @param   $goodsIds   array   规则条件
     * @return  array
     */
    private function _getBrandIdByGoodsIds($goodsIds)
    {
        $return = array();

        if (empty($goodsIds)) {
            return $return;
        }

        $result = $this->_db->table('sdb_b2c_goods')->whereIn('goods_id', array_filter($goodsIds))->get()->all();
        if ($result) {
            foreach ($result as $v) {
                $tmp_gid = $v->goods_id;
                $return[$tmp_gid] = $v->brand_id;
            }
        }
        return $return;
    }

    /**
     * 免税券规则检测
     * @param $rule_id
     * @param $goods_list
     * @return array|bool
     * TODO 最大金额限制
     */
    public function _match_rule($rule_id, $goods_list, $max_limit = 0)
    {
        $rule_info = $this->getRule($rule_id);
        //判断当前RULE的作用规则
        $rule_condition = unserialize($rule_info->rule_condition);

        $ruleCondition = $rule_condition[0];

        if (empty($goods_list)) {
            return false;
        }

        $price = 0;
        foreach ($goods_list as $product) {
            $price += $product['price'] * $product['quantity'];
            $goodsList[] = $product['goods_id'];
        }
        $ruleCondition['max_cost'] = $max_limit;
        switch ($ruleCondition['processor']) {
            case 'CostReachedRuleProcessor':
                if (($price > $ruleCondition['filter_rule']['limit_cost'] OR abs($price - $ruleCondition['filter_rule']['limit_cost']) < 0.0001)) {
                    $price = 0;
                    $tax_fee = 0;
                    foreach ($goods_list as $product) {
                        $price += $product['price'] * $product['quantity'];
                        $tax_fee += $product['tax'];
                        $matchProductBnList[] = $product['bn'];

                    }
                    if ($tax_fee <= $ruleCondition['max_cost']) {
                        return array(
                            "product_bn_list" => $matchProductBnList,
                            "match_use_money" => $tax_fee
                        );
                    } else {
                        return array(
                            "product_bn_list" => $matchProductBnList,
                            "match_use_money" => $ruleCondition['max_cost']
                        );
                    }
                } else {
                    return false;
                }
                break;
            //验证商城类目是否符合
            case 'CostMallAndCatRuleProcessor':
                // 获取商城 ID
                $mallList = $this->_getMallIdbyGoodsIds($goodsList);
                // 获取商品一级分类
                $mallCatPathTree = $this->getMallCatPathTreeByGoodsIds($goodsList);
                $allMatched = true;
                $tax_fee = 0;
                foreach ($goods_list as $product) {
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

                    if ($mallCatIdMatch && $mallIdMatch) {
                        $tax_fee += $product['tax'];
                        $matchProductBnList[] = $product['bn'];
                    }
                }

                if (($price > $ruleCondition['filter_rule']['limit_cost'] OR abs($price - $ruleCondition['filter_rule']['limit_cost']) < 0.0001)) {
                    if (count($matchProductBnList) > 0) {
                        if ($tax_fee <= $ruleCondition['max_cost']) {
                            return array(
                                "product_bn_list" => $matchProductBnList,
                                "match_use_money" => $tax_fee
                            );
                        } else {
                            return array(
                                "product_bn_list" => $matchProductBnList,
                                "match_use_money" => $ruleCondition['max_cost']
                            );
                        }

                    } else {
                        return false;
                    }
                } else {
                    return false;
                }
                break;
            case 'CostBrandRuleProcessor'://指定品牌
                // 获取品牌数据
                $goodsBrand = $this->_getBrandIdByGoodsIds($goodsList);
                $tax_fee = 0;
                foreach ($goods_list as $product) {
//                        // 检验该商品能不能用内购券
//                        if ( ! $this->AuthProductBnBlackGoodsList($ruleCondition['ret_black_list'], $product['bn']))
//                        {
//                            continue;
//                        }

                    // 检查品牌条件
                    if (isset($goodsBrand[$product['goods_id']]) && in_array($goodsBrand[$product['goods_id']],
                            $ruleCondition['filter_rule']['brand'])) {
//                        echo 1;
                        $price += $product['price'] * $product['quantity'];
                        $tax_fee += $product['tax'];
                        $matchProductBnList[] = $product['bn'];
                    }
                }
//                print_r($matchProductBnList);

                $ruleCondition['filter_rule']['limit_cost'] = isset($ruleCondition['filter_rule']['limit_cost']) ? $ruleCondition['filter_rule']['limit_cost'] : 0;
                if (!empty($matchProductBnList) && ($price > $ruleCondition['filter_rule']['limit_cost'] OR abs($price - $ruleCondition['filter_rule']['limit_cost']) < 0.0001)) {
                    if ($tax_fee <= $ruleCondition['max_cost']) {
                        return array(
                            "product_bn_list" => $matchProductBnList,
                            "match_use_money" => $tax_fee
                        );
                    } else {
                        return array(
                            "product_bn_list" => $matchProductBnList,
                            "match_use_money" => $ruleCondition['max_cost']
                        );
                    }
                }
                break;
            case 'CostGoodsRuleProcessor'://指定商品
                if (empty($goods_list)) {
                    return false;
                }
                $price = 0;
                $tax_fee = 0;
                $matchProductBnList = array();
                foreach ($goods_list as $product) {
                    // 检验该商品能不能用内购券
//                        if ( ! $this->AuthProductBnBlackGoodsList($ruleCondition['ret_black_list'], $product['bn']))
//                        {
//                            continue;
//                        }

                    if (in_array($product['goods_id'], $ruleCondition['filter_rule']['goods'])) {
                        $price += $product['price'] * $product['quantity'];
                        $tax_fee += $product['tax'];
                        $matchProductBnList[] = $product['bn'];
                    }
                }
                $ruleCondition['filter_rule']['limit_cost'] = isset($ruleCondition['filter_rule']['limit_cost']) ? $ruleCondition['filter_rule']['limit_cost'] : 0;
                if (!empty($matchProductBnList) && ($price >= $ruleCondition['filter_rule']['limit_cost'] OR abs($price - $ruleCondition['filter_rule']['limit_cost']) < 0.0001)) {
                    if ($tax_fee <= $ruleCondition['max_cost']) {
                        return array(
                            "product_bn_list" => $matchProductBnList,
                            "match_use_money" => $tax_fee
                        );
                    } else {
                        return array(
                            "product_bn_list" => $matchProductBnList,
                            "match_use_money" => $ruleCondition['max_cost']
                        );
                    }
                }
                break;
            default:
                return false;
                break;
        }
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
            "start_time" => $start_time,
            "op_name" => $op_name,
            "rule_id" => $rule_id,
            "rule_name" => $rule_name,
            "money" => $money,
            "voucher_type" => 1,
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
            $message_center_mgr->dutyFreeSendMessage($member_list);


            $user_center_msg = new UserCenterMsg();
            $user_center_msg->dutyFreeSendUserCenterMsg($member_list);
            return $result;
        } else {
            $err_code = 20001;
            $err_msg = "创建券失败";
            return $err_msg;
        }
    }

    //查询券是否可用于当前商品 TODO 黑名单检测
    public function queryWithList($member_id, $company_id, $coupon_ids, $goods_list)
    {
        if (!isset($member_id) || !isset($company_id) || !isset($coupon_ids) || !isset($goods_list)) {
            \Neigou\Logger::General(
                'action.voucher',
                array(
                    'action' => 'useVoucherWithRule',
                    'success' => 0,
                    'reason' => 'invalid_params'
                )
            );
            return "参数不正确";
        }
        //获取券列表
        $coupon_list = $this->_db->table($this->tableFreeshippingMember)
            ->where('voucher_type', 1)
            ->whereIn('coupon_id', $coupon_ids)
            ->get()
            ->all();
        $return = false;
        foreach ($coupon_list as $key => $coupon_info) {
            //检测当前券是否针对商品列表可用
            $rzt = $this->_match_rule($coupon_info->rule_id, $goods_list, $coupon_info->money);
            if ($rzt['match_use_money'] > $coupon_info->money) {
                $rzt['match_use_money'] = $coupon_info->money;
            }
            $return[$coupon_info->coupon_id] = $rzt;
        }
        return $return;
    }

    //查询当前商品可用券 TODO 黑名单检测
    public function queryWithRule($member_id, $company_id, $goods_list)
    {
        if (!isset($member_id) || !isset($company_id) || !isset($goods_list)) {
            \Neigou\Logger::General(
                'action.voucher',
                array(
                    'action' => 'useVoucherWithRule',
                    'success' => 0,
                    'reason' => 'invalid_params'
                )
            );
            return "参数不正确";
        }
        //获取券列表
        $currentTime = time();
        $where = [
            ['member_id', $member_id],
            ['status', 0],
            ['voucher_type', 1],
            ['valid_time', '>=', $currentTime]
        ];

        $coupon_list = $this->_db->table($this->tableFreeshippingMember)->where($where)->get()->all();
        $return = false;
        $coupon_list = json_decode(json_encode($coupon_list), true);
        foreach ($coupon_list as $key => $coupon_info) {
            //检测当前券是否针对商品列表可用
            $rzt = $this->_match_rule($coupon_info['rule_id'], $goods_list, $coupon_info['money']);
            if (!$rzt) {
                unset($coupon_list[$key]);
            } else {
                $coupon_list[$key]['match_info'] = $rzt;
            }

        }
        return $coupon_list;
    }

    private function checkVoucher($couponInfo, $filterData, $member_id, $company_id)
    {
        if (empty($couponInfo)) {
            $ret['msg'] = $couponInfo->coupon_id . "错误的免税券";
            $ret['status'] = 0;
            return $ret;
        }
        if ($couponInfo->status != 0) {
            $ret['msg'] = $couponInfo->coupon_id . "已使用的免税券";
            $ret['status'] = 0;
            return $ret;
        }
        if ($couponInfo->valid_time < time()) {
            $ret['msg'] = $couponInfo->coupon_id . "已过期的免税券";
            $ret['status'] = 0;
            return $ret;
        }
        if ($couponInfo->start_time > time()) {
            $ret['msg'] = $couponInfo->coupon_id . "未开始的免税券";
            $ret['status'] = 0;
            return $ret;
        }
        // 检查免邮券使用限制
        if (!$this->_checkCouponLimit($member_id, $company_id)) {
            $ret['msg'] = "已超出使用限制";
            $ret['status'] = 0;
            return $ret;
        }
        //进行验证 如果返回false unset这张券
        $rule = $this->getRule($couponInfo->rule_id);
        $ruleCondition = unserialize($rule->rule_condition);
        $matchResult = $this->filter_parse($ruleCondition, $filterData, $couponInfo->money);
        if ($matchResult == false) {
            $condition = unserialize($rule->rule_condition);
            $ret['limit_cost'] = $condition[0]['filter_rule']['limit_cost'];
            $ret['match_use_money'] = 0;
            $ret['need_money'] = $ret['limit_cost'];
            $ret['product_bn_list'] = array();
            $ret['msg'] = $couponInfo->coupon_id . "订单无法使用免税券";
            $ret['status'] = 0;
            return $ret;
        }
        return $matchResult;
    }

    /**
     * 新购物车使用规则
     * @param $ruleCondition
     * @param $filter_data
     * @param $max_limit
     * @return bool
     */
    private function filter_parse($ruleCondition, $filter_data, $max_limit = 0)
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
            $time = time();
            $retBlackList = $this->_db->table('promotion_voucher_blacklist_rule')
                ->where([
                    ['type', 1],
                    ['start_time', '<=', $time],
                    ['end_time', '>=', $time]
                ])
                ->get()
                ->all();
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
                $tax = 0;
                foreach ($filter_data['products'] as $key => $good) {
                    foreach ($match_data['product_bn_list'] as $bn) {
                        if ($good['bn'] == $bn) {
                            $tax += $good['tax'] * $good['quantity'];
                        }
                    }
                }
                if ($match_data['match_use_money'] < $match_data['limit_cost']) {
                    $match_data['match_use_money'] = 0;//not match use price 0 to notice
                    $match_data['msg'] = '订单金额不满足，需凑单使用';
                    $match_data['status'] = 0;
                } else {
                    $match_data['msg'] = '当前可用';
                    $match_data['match_use_money'] = ($tax > $max_limit) ? $max_limit : $tax;
                    $match_data['need_money'] = 0;
                    $match_data['status'] = 1;
                }
                return $match_data;
            }
        }
    }

    public function queryCouponListWithRule($params)
    {
        foreach ($params['voucher_data'] as $key => $val) {
            $memberId = $val['member_id'];
            $couponId = $val['coupon_id'];
            $companyId = $val['company_id'];
            $where = [
                ['member_id', $memberId],
                ['coupon_id', $couponId],
                ['voucher_type', 1]
            ];
            $couponInfo = $this->_db->table($this->tableFreeshippingMember)->where($where)->first();
            $val['filter_data']['version'] = $params['version'];
            $val['filter_data']['newcart'] = $params['newcart'];
            $params['voucher_data'][$key]['result'] = $this->checkVoucher(
                $couponInfo,
                $val['filter_data'],
                $memberId,
                $companyId
            );
        }
        return $params;
    }

    //获取免税券列表
    public function queryMemberCoupon($memberId, $where, $limit)
    {
        if (empty($memberId)) {
            return false;
        }
        $sql = "select * from {$this->tableFreeshippingMember} fs_mem join promotion_freeshipping_rules rule on fs_mem.rule_id = rule.rule_id where member_id={$memberId} AND fs_mem.voucher_type=1 ";
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


    //获取券规则
    public function getRuleList($params_data_array)
    {
        if (!isset($params_data_array["rule_id_list"]) &&
            !is_array($params_data_array["rule_id_list"]) &&
            empty($params_data_array["rule_id_list"])) {
            \Neigou\Logger::General(
                'action.voucher',
                array(
                    'action' => 'getRuleList',
                    'success' => 0,
                    'reason' => 'invalid_params'
                )
            );
            return "免税券参数不正确";//err 1
        }
        $ruleIdListStr = implode(',', $params_data_array["rule_id_list"]);
        if (empty($ruleIdListStr)) {
            return "免税券参数不正确";//err 1
        }
        //$sql = "select * from {$this->tableFreeshippingRules} where rule_id in ({$ruleIdListStr})";
        //$result = $this->_db->select($sql);

        $placeholder = implode(',',array_fill(0,count($params_data_array["rule_id_list"]),'?'));
        $sql = "select * from {$this->tableFreeshippingRules} where rule_id in ({$placeholder})";
        $result = $this->_db->select($sql,$params_data_array["rule_id_list"]);
        if (!$result) {
            return "规则ID指向规则不存在";
        }
        $ruleInfoList = array();
        foreach ($result as $ruleInfoItem) {
            $ruleInfoList[$ruleInfoItem->rule_id] = $ruleInfoItem;
        }
        return $ruleInfoList;
    }

    //订单创建券
    public function createOrderForCoupon($couponId, $orderId, $memberId, $companyId, $filterData, &$err_code, &$err_msg)
    {
        if (empty($couponId) ||
            empty($orderId) ||
            empty($memberId)) {
            return false;
        }
        $currentTime = time();

        $findOrderCouponData = $this->_db->selectOne("select * from {$this->tableFreeshippingOrder} where order_id= :orderId and (status=0 or status=1) and voucher_type=1",array($orderId));
        if (!empty($findOrderCouponData)) {
            $err_code = 30002;
            $err_msg = "此订单已经使用过免税券";
            return $err_msg;
        }
        $this->_db->beginTransaction();
        $useSuccess = true;
        $where = [
            ['coupon_id', $couponId],
            ['voucher_type', 1],
            ['member_id', $memberId]
        ];
        $findCouponData = $this->_db->table($this->tableFreeshippingMember)->where($where)->first();
        if (empty($findCouponData)) {
            $err_code = 20001;
            $err_msg = "免税券不存在";
            $useSuccess = false;
        }
        if ($findCouponData->rule_id > 0) {
            //进行验证 如果返回false unset这张券
            $goods_list = $filterData['products'];
            $rzt = $this->_match_rule($findCouponData->rule_id, $goods_list, $findCouponData->money);
            if ($rzt['match_use_money'] > $findCouponData->money) {
                $rzt['match_use_money'] = $findCouponData->money;
            }
            if ($rzt == false) {
                $err_code = '20001';
                $err_msg = "订单无法使用免税券";
                return $err_msg;
            }
        }

        if ($findCouponData->status != 0) {
            $err_code = 30003;
            $err_msg = "免税券已使用";
            $useSuccess = false;
            return $err_msg;
        }

        // 检查免邮券使用限制
        if (!$this->_checkCouponLimit($memberId, $companyId)) {
            $err_code = 30004;
            $err_msg = "已超出使用限制";
            return $err_msg;
        }
        //检测是否可以使用券
        if (!empty($useSuccess)) {
            $couponOrderData = array(
                "member_id" => $memberId,
                "coupon_id" => $couponId,
                "order_id" => $orderId,
                "use_time" => $currentTime,
                "status" => 0,
                "voucher_type" => 1,
                "use_money" => $rzt['match_use_money'],
            );
            $couponOrderId = $this->_db->table($this->tableFreeshippingOrder)->insertGetId($couponOrderData);
            if (empty($couponOrderId)) {
                $err_code = 20002;
                $err_msg = "免税券使用失败";
                $useSuccess = false;
            }
        }

        if (!empty($useSuccess)) {
            $updateCouponData = array(
                "status" => 1,
                "last_modified" => $currentTime,
                "use_time" => $currentTime,
                "voucher_type" => 1,
            );
            $updateResult = $this->_db->table($this->tableFreeshippingMember)
                ->where('coupon_id', $couponId)
                ->update($updateCouponData);
            if ($updateResult === -1) {
                $err_code = 20003;
                $err_msg = "券状态更新失败";
                $useSuccess = false;
            }
        }
        if (!empty($useSuccess)) {
            $this->_db->commit();
            $return['coupon_id'] = $couponId;
            $return['match_info'] = $rzt;
            return $return;
        } else {
            $this->_db->rollback();
            return false;
        }
    }

    private function getCouponInfo($where)
    {
        return $this->_db->table($this->tableFreeshippingMember)->where($where)->first();
    }

    /**
     * 订单使用多张免税券
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
                ['voucher_type', 1],
                ['member_id', $memberId]
            ];
            $findCouponData = $this->getCouponInfo($where);
            if (empty($findCouponData)) {
                return $couponId . '免税券不存在';
            }
            if ($findCouponData->rule_id > 0) {
                //进行验证 如果返回false unset这张券
                $rule = $this->getRule($findCouponData->rule_id);
                $rzt = $this->filter_parse(unserialize($rule->rule_condition), $filterData, $findCouponData->money);
                if ($rzt == false) {
                    return $couponId . '订单无法使用免税券';
                }
            }
            if ($findCouponData->status != 0) {
                return $couponId . '免税券已使用';
            }
            // 检查免税券使用限制
            if (!$this->_checkCouponLimit($memberId, $companyId)) {
                return $couponId . '已超出使用限制';
            }
            $couponOrderData = array(
                "member_id" => $memberId,
                "coupon_id" => $couponId,
                "order_id" => $orderId,
                "use_money" => $rzt['match_use_money'],
                "use_time" => $currentTime,
                "voucher_type" => 1,
                "status" => 0
            );
            $couponOrderId = $this->_db->table($this->tableFreeshippingOrder)->insertGetId($couponOrderData);
            if (empty($couponOrderId)) {
                return $couponId . '免税券使用失败';
            }
            $updateCouponData = array(
                "status" => 1,
                "last_modified" => $currentTime,
                "voucher_type" => 1,
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
                return '免税券使用失败';
            }
        }
        $this->_db->commit();

        return $return;
    }

    //取消订单归还券
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
            ['status', 0],
            ['voucher_type', 1]
        ];
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
//        $findOrderCouponData = $this->storeSqllink->findOne("select * from {$this->tableFreeshippingOrder} where order_id={$orderId} and status=0");
        $where = [
            ['order_id', $orderId],
            ['status', 0],
            ['voucher_type', 1]
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

    //订单券查询
    public function queryOrderCoupon($orderId, &$err_code, &$err_msg)
    {
        if (empty($orderId)) {
            return false;
        }
//        $findOrderCouponData = $this->storeSqllink->findOne("select * from {$this->tableFreeshippingOrder} where order_id={$orderId} and status!=2");
        $where = [
            ['order_id', $orderId],
            ['voucher_type', 1],
            ['status', '!=', 2]
        ];
        $findOrderCouponData = $this->_db->table($this->tableFreeshippingOrder)->where($where)->first();
        if (empty($findOrderCouponData)) {
//            $findOrderCouponData = $this->storeSqllink->findOne("select * from {$this->tableFreeshippingOrder} where order_id={$orderId} order by use_time desc");
            $findOrderCouponData = $this->_db->table($this->tableFreeshippingOrder)->where('order_id',
                $orderId)->orderBy('use_time', 'desc')->first();
        }
        return $findOrderCouponData;
    }


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
                $usedResult = $this->_db->selectOne("SELECT COUNT(a.coupon_id) AS count FROM {$this->tableFreeshippingOrder} as a,{$this->tableFreeshippingMember} as b WHERE a.voucher_type=1 AND a.coupon_id=b.coupon_id AND a.member_id=:memberId AND a.use_time >= {$startTime} AND a.use_time < {$endTime} AND a.status IN (0, 1)",array($memberId));
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
    public function getRule($rule_id)
    {
        $result = $this->_db->table($this->tableFreeshippingRules)->where('rule_id', $rule_id)->first();
        if (!$result) {
            return "规则ID指向规则不存在";
        }
        return $result;
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

    public function saveRule($save_data_array)
    {
        if (empty($save_data_array) ||
            !isset($save_data_array['rule_type']) ||
            !isset($save_data_array['description']) ||
            !isset($save_data_array['op_name']) ||
            !isset($save_data_array['log_text']) ||
            !isset($save_data_array['rule_data'])) {
            \Neigou\Logger::General('action.voucher', array(
                'action' => 'saveRule',
                'success' => 0,
                'reason' => 'invalid_params'
            ));
            return "无效参数";
        }
        $str_generator = 'App\Api\Model\Voucher\Generate\\' . $save_data_array['rule_type'];
        $generator = new $str_generator;
        if (method_exists($generator, "create")) {
            $create_rule = $generator->create($save_data_array['rule_data']);
            if ($create_rule) {
                //设置最大可免税金额
                $create_rule['max_cost'] = $save_data_array['max_cost'];
                if (empty($save_data_array['rule_id'])) {
                    if (!isset($save_data_array['name'])) {
                        \Neigou\Logger::General('action.voucher', array(
                            'action' => 'saveRule',
                            'success' => 0,
                            'reason' => 'invalid_params'
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
                        'voucher_type' => 1,
                        'rule_condition' => serialize(array($create_rule)),
                        'extend_data' => $save_data_array['extend_data']
                    );
                    $result_rule_id = $this->_db->table($this->tableFreeshippingRules)->insertGetId($create_data);
                    $res = $this->sendEs($result_rule_id, $create_data['rule_condition'], array());
                    if (!$res) {
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
                        'voucher_type' => 1,
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
                    \Neigou\Logger::General('action.voucher',
                        array('action' => 'saveRule', 'success' => 1, 'rule_id' => $result_rule_id));
                    return $result_rule_id;
                } else {
                    \Neigou\Logger::General('action.voucher',
                        array('action' => 'saveRule', 'success' => 0, 'reason' => 'insert_failed'));
                    return "规则插入失败";
                }
            } else {
                \Neigou\Logger::General('action.voucher',
                    array('action' => 'saveRule', 'success' => 0, 'reason' => 'invalid_type'));
                return "传入创建规则不合理";
            }
        } else {
            \Neigou\Logger::General('action.voucher',
                array('action' => 'saveRule', 'success' => 0, 'reason' => 'invalid_type'));
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
        $res = $service->sendEsTask($rule_id, $condition, $old_condition, 'dutyfree');
        if ($res) {
            return true;
        } else {
            return false;
        }
    }

}
