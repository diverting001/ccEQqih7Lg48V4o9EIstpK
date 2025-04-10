<?php
/**
 * Created by PhpStorm.
 * User: chuanbin
 * Date: 2017/9/18
 * Time: 15:38
 */

namespace App\Api\V1\Controllers;

use App\Api\Common\Controllers\BaseController;
use App\Api\Model\Goods\Promotion;
use Illuminate\Http\Request;

class GoodsController extends BaseController
{
    /**
     * 中间计算 商品价格
     * @param $good 商品信息
     * @param $discount 折扣金额
     * @return mixed
     */
    private function calc_price_info($good, $discount)
    {
        $arr['price'] = $good['price'] * $good['nums'];
        $arr['discount'] = $discount;
        $arr['total'] = $arr['price'] - $discount;
        return $arr;
    }

    /**
     * 计算总价格
     * @param $gids
     * @param $goods
     * @return int
     */
    private function calc_amount($gids, $goods)
    {
        $amount = 0;
        foreach ($gids as $gid) {
            $amount += $goods[$gid]['price'] * $goods[$gid]['nums'];
        }
        return $amount;
    }

    /**
     * 判定规则可否使用
     * @param $rule
     * @param $amount
     * @param $good
     * @return bool
     */
    private function check_rule($rule, $amount, $good)
    {
        if (!empty($rule['min_price']) || !empty($rule['min_num'])) {
            if ($amount >= $rule['min_price']) {
                return true;
            } elseif ($rule['affect_type'] == 'limit_buy') {
                if ($good['nums'] >= $rule['min_num']) {
                    return true;
                }
            }

        }
        return false;
    }

    /**
     * 减价 计算
     * @param $good
     * @param $rule
     * @param $amount
     * @return mixed
     */
    private function calc_price($good, $rule, $amount)
    {
        //按比例计算打折信息
        $price = $good['price'] * $good['nums'] / $amount * $rule['discount'];
        //获取金额详情
        $arr = $this->calc_price_info($good, $price);
        $arr['desc'] = '减价';
        $arr['type'] = 'price';
        return $arr;
    }

    /**
     * 限购 计算
     * @param $good
     * @param $rule
     * @param int $member_id
     * @return mixed
     */
    private function calc_limit_buy($good, $rule, $member_id = 0)
    {
        $arr = $this->calc_price_info($good, 0);
        $arr['desc'] = '限购';
        $arr['type'] = 'limit_buy';
        $arr['max_buy'] = $rule['max_buy'];
        $arr['min_num'] = $rule['min_num'];//新增起订量
        $arr['max_sale'] = $rule['max_sale'];
        //TODO 已经售买的总数 当前商品的BN查询
        $obj = new Promotion();
        $arr['has_sold'] = $obj->GetSoldByBn($good['bn'], $rule['id']);
        //TODO 当前用户已经购买的数量 当前用户和BN组合查询
        $arr['has_buy'] = $obj->GetSoldByUid($good['bn'], $rule['id'], $member_id);
        return $arr;
    }

    /**
     * 打折 计算
     * @param $good
     * @param $rule
     * @param $amount
     * @return mixed
     */
    private function calc_discount($good, $rule, $amount)
    {
        $percent = 1 - ($rule['discount'] / 100);
        $discount = $amount * $percent;//打折了多少价格
        $price = $good['price'] * $good['nums'] / $amount * $discount;
        $arr = $this->calc_price_info($good, $price);
        $arr['desc'] = '折扣';
        $arr['select'] = 1;
        $arr['type'] = 'discount';
        return $arr;

    }

    /**
     * 定价 计算
     * @param $good
     * @param $rule
     * @return mixed
     */
    private function calc_regular_price($good, $rule)
    {
        $arr['desc'] = '定价';
        $arr['select'] = 1;
        $arr['type'] = 'regular_price';
        //满足参加活动定条件 计算比例价格
        $price = $rule['discount'];
        //最终展示
        $arr['price'] = $good['price'] * $good['nums'];
        $arr['discount'] = $price;
        $arr['total'] = $price * $good['nums'];
        return $arr;

    }

    /**
     * 免邮 计算
     * @param $good
     * @return mixed
     */
    private function calc_free_shipping($good)
    {
        $arr = $this->calc_price_info($good, 0);
        $arr['type'] = 'free_shipping';
        $arr['desc'] = '免邮';
        return $arr;
    }

    /**
     * 赠品 计算
     * @param $good
     * @param $rule
     * @return mixed
     */
    private function calc_present($good, $rule)
    {
        $arr = $this->calc_price_info($good, 0);
        $arr['type'] = 'present';
        $arr['desc'] = '赠品';
        $arr['present'] = json_decode($rule['present_list'], true);
        return $arr;
    }

    public function GetPromotion(Request $request)
    {
        $post_data = $this->getContentArray($request);

        $goods = $post_data['product'];
        $company_id = $post_data['company_id'];
        $member_id = $post_data['member_id'];


        $rule_obj = new Promotion();

        foreach ($goods as $key => $good) {
            //获取商品Bn所参加的活动
            $rules = $rule_obj->getRule($good);
//            $rules->gid = $good['id'];
            $goods[$key]['promotion'] = $rules;
            $tmp_good[$good['id']] = $good;
            $tmp_good[$good['id']]['promotion'] = $rules;
        }
        $goods = $tmp_good;

        //组合商品参加活动的所有规则
        foreach ($goods as $key => $good) {
            foreach ($good['promotion'] as $rid => $rule) {
                //对商品对促销规则进行筛选 判定公司是否可以参与活动 如果没有参与 执行unset
                $r = $rule_obj->CheckCompany($rid, $company_id);
                if ($r) {
                    $rule_tmp[$rid] = $rule;
                    $tmp[$rid][] = $good['id'];
                } else {
                    unset($goods[$key]['promotion'][$rid]);
                }

            }
        }

        //组合商品可以共同参加的活动规则
        foreach ($goods as $key => $good) {
            foreach ($good['promotion'] as $rid => $rule) {
                $goods[$key]['promotion'][$rid]['gid'] = $tmp[$rid];
            }
        }

        foreach ($goods as $key => $good) {
            $auto = reset($good['promotion']);
//            $goods[$key]['auto_promotion'] = $auto;
            //判断第一个是否是共享的规则
            if ($auto['is_share'] == 'true') {
                //计算所有 is_share为true的规则说影响的内容
                foreach ($good['promotion'] as $rid => $rule) {
                    if ($rule['is_share'] == 'true') {
                        //可共享的规则信息
                        $amount = $this->calc_amount($rule['gid'], $goods);
                        if ($this->check_rule($rule, $amount, $good)) {
                            $rule_id = $rule['id'];
                            $rule_sort = $rule['sort'];
                            switch ($rule['affect_type']) {
                                case 'price'://TODO 价格应该单计 参加此类活动的价格总数
                                    $first_select[$key][$rule_sort] = $rule_id;
                                    $goods[$key]['rule'][$rid] = $this->calc_price($good, $rule, $amount);
                                    break;
                                case 'limit_buy':
                                    $limit_buy[$key][$rule_sort] = $rule_id;
                                    $goods[$key]['rule'][$rid] = $this->calc_limit_buy($good, $rule, $member_id);
                                    break;
                                case 'discount':
                                    $goods[$key]['rule'][$rid] = $this->calc_discount($good, $rule, $amount);
                                    $first_select[$key][$rule_sort] = $rule_id;
                                    break;
                                case 'regular_price':
                                    $first_select[$key][$rule_sort] = $rule_id;
                                    $goods[$key]['rule'][$rid] = $this->calc_regular_price($good, $rule);
                                    break;
                                case 'free_shipping':
                                    $goods[$key]['rule'][$rid] = $this->calc_free_shipping($good);
                                    break;
                                case 'present':
                                    $goods[$key]['rule'][$rid] = $this->calc_present($good, $rule);
                                    break;
                            }
                            $goods[$key]['rule'][$rid]['name'] = $rule['name'];
                            $goods[$key]['rule'][$rid]['product_id'] = $rule['gid'];
                            $goods[$key]['rule'][$rid]['start_time'] = $rule['start_time'];
                            $goods[$key]['rule'][$rid]['end_time'] = $rule['end_time'];
                        }
                    }

                }
            } else {
                $rid = $auto['id'];
                $amount = $this->calc_amount($auto['gid'], $goods);
                if ($this->check_rule($auto, $amount, $good)) {
                    $auto_id = $auto['id'];
                    $auto_sort = $auto['sort'];
                    switch ($auto['affect_type']) {
                        case 'price':
                            $first_select[$key][$auto_sort] = $auto_id;
                            $goods[$key]['rule'][$rid] = $this->calc_price($good, $auto, $amount);
                            break;
                        case 'limit_buy':
                            $limit_buy[$key][$auto_sort] = $auto_id;
                            $goods[$key]['rule'][$rid] = $this->calc_limit_buy($good, $auto);
                            break;
                        case 'discount':
                            $goods[$key]['rule'][$rid] = $this->calc_discount($good, $auto, $amount);
                            $first_select[$key][$auto_sort] = $auto_id;
                            break;
                        case 'regular_price':
                            $first_select[$key][$auto_sort] = $auto_id;
                            $goods[$key]['rule'][$rid] = $this->calc_regular_price($good, $auto);
                            break;
                        case 'free_shipping':
                            $goods[$key]['rule'][$rid] = $this->calc_free_shipping($good);
                            break;
                        case 'present':
                            $goods[$key]['rule'][$rid] = $this->calc_present($good, $auto);
                            break;
                    }
                    $goods[$key]['rule'][$rid]['name'] = $auto['name'];
                    $goods[$key]['rule'][$rid]['product_id'] = $auto['gid'];
                    $goods[$key]['rule'][$rid]['start_time'] = $auto['start_time'];
                    $goods[$key]['rule'][$rid]['end_time'] = $auto['end_time'];
                }

            }

        }


        $amount = 0;
        $discount = 0;

        foreach ($goods as $key => $val) {
            unset($goods[$key]['promotion']);

            //循环所有规则 如果有免邮 设置免邮为true 如果有定价 打折 减价 选择优先级最高的一个 如果有限购 有体现
            ksort($first_select[$key]);
            $price_rule_id = end($first_select[$key]);

            ksort($limit_buy[$key]);
            $limit_buy_rule = end($limit_buy[$key]);


            $use_rule = array();
            $free_shipping = 0;
            if (!empty($val['rule'])) {
                $i = 0;
                foreach ($val['rule'] as $rid => $rule) {
                    if ($rule['type'] == 'free_shipping') {
                        ++$free_shipping;
                        $free_shipping_rule_id = $rid;//Log use only
                    }
                    $j[$i] = $rid;
                    $i++;
                }
                if (empty($price_rule_id)) {
                    $price_rule_id = $j[0];
                }
                if (empty($limit_buy_rule)) {
                    $limit_buy_rule = $j[0];
                }

                $calc[$key]['free_shipping'] = ($free_shipping > 0) ? true : false;
                $calc[$key]['rule_id'] = $price_rule_id;
                $calc[$key]['price'] = $val['rule'][$price_rule_id]['price'];
                $calc[$key]['discount'] = $val['rule'][$price_rule_id]['discount'];
                $calc[$key]['total'] = $val['rule'][$price_rule_id]['total'];
                $calc[$key]['max_buy'] = $val['rule'][$limit_buy_rule]['max_buy'];
                $calc[$key]['max_sale'] = $val['rule'][$limit_buy_rule]['max_sale'];
                $calc[$key]['min_num'] = $val['rule'][$limit_buy_rule]['min_num'];//新增起订量
                $calc[$key]['has_sold'] = $val['rule'][$limit_buy_rule]['has_sold'];//新增起订量
                $calc[$key]['has_buy'] = $val['rule'][$limit_buy_rule]['has_buy'];//新增起订量

                $calc[$key]['start_time'] = $val['rule'][$price_rule_id]['start_time'];
                $calc[$key]['end_time'] = $val['rule'][$price_rule_id]['end_time'];
                $calc[$key]['product_bn'] = $val['bn'];

                if ($val['rule'][$price_rule_id]['type'] == 'free_shipping') {
                    unset($price_rule_id);
                }
                if ($val['rule'][$limit_buy_rule]['type'] != 'limit_buy') {
                    unset($limit_buy_rule);
                }
                $use_rule[] = $free_shipping_rule_id;
                $use_rule[] = $price_rule_id;
                $use_rule[] = $limit_buy_rule;
                $use_rule = array_unique($use_rule);
                $goods[$key]['use_rule'] = array_values(array_filter($use_rule));
            } else {
                $calc[$key]['free_shipping'] = false;
                $calc[$key]['rule_id'] = null;
                $calc[$key]['price'] = $val['price'] * $val['nums'];
                $calc[$key]['discount'] = 0;
                $calc[$key]['total'] = $val['price'] * $val['nums'];
                $calc[$key]['max_buy'] = 0;
                $calc[$key]['max_sale'] = 0;
                $calc[$key]['product_bn'] = $val['bn'];
            }
            $amount += $calc[$key]['total'];
            $discount += $calc[$key]['discount'];

            $log_data['good'] = $val;
            $log_data['rule_ids'] = $use_rule;
            unset($price_rule_id);
            unset($limit_buy_rule);
            unset($free_shipping_rule_id);
            $log_data['bn'] = $val['bn'];
            \Neigou\Logger::Debug('server.promotion.getPromotion', $log_data);
            unset($use_rule);
        }

        $out['product'] = $goods;
        $out['calc'] = $calc;
        $out['all']['amount'] = $amount;
        $out['all']['discount'] = $discount;
        $out['rules'] = $rule_tmp;

        $log_data['data'] = array(
            'post_data' => $post_data,
            'out' => $out
        );
        $log_data['remark'] = 'succ';
        \Neigou\Logger::Debug('server.GetPromotionV2', $log_data);

        $this->setErrorMsg('请求成功');
        return $this->outputFormat($out);


    }

    public function LockStock(Request $request)
    {
        $post_data = $this->getContentArray($request);

        $obj = new Promotion();
        foreach ($post_data['product'] as $key => $val) {
            $val['order_id'] = $post_data['order_id'];
            $val['member_id'] = $post_data['member_id'];
            $val['company_id'] = $post_data['company_id'];
            $data[$key]['status'] = $obj->LockStock($val);
            $data[$key]['product_bn'] = $val['bn'];
        }
        $this->setErrorMsg('请求成功');
        return $this->outputFormat($data);
    }

    public function checkStock(Request $request)
    {
        $post_data = $this->getContentArray($request);
        $goods = $post_data['product'];
        $company_id = $post_data['company_id'];
        $member_id = $post_data['member_id'];
        $rule_obj = new Promotion();
        foreach ($goods as $key => $good) {
            //获取商品Bn所参加的活动
            $rules = $rule_obj->getRule($good);
            $status = true;
            $msg = 'succ';
            $info = null;
            $arr['status'] = $status;
            $arr['msg'] = $msg;
            $arr['rule'] = $info;
            $goods[$key]['check'] = $arr;
            foreach ($rules as $rid => $rule) {
                $r = $rule_obj->CheckCompany($rid, $company_id);
                if ($r) {
                    if ($rule['affect_type'] == 'limit_buy') {
                        //获取用户限购的信息
                        $info = $this->calc_limit_buy($good, $rule, $member_id);
                        if ($info['max_sale'] >= $info['has_sold']) {
                            if ($good['nums'] + $info['has_sold'] > $info['max_sale']) {
                                //如果用户购买的数量+已经卖出的数量 > 最大售卖数
                                $msg = '超过最大售出量';
                                $status = false;
                            } else {
                                $all_nums = $good['nums'] + $info['has_buy'];//用户中购买数
                                if ($all_nums > $info['max_buy']) {
                                    $msg = '超过最大购买限制';
                                    $status = false;
                                } elseif ($good['nums'] < $info['min_num']) {
                                    $msg = '低于最小起订量限制';
                                    $status = false;
                                }
                            }
                        } else {
                            $msg = '超过最大卖出限制';
                            $status = false;
                        }
                    }
                }
                $arr['status'] = $status;
                $arr['msg'] = $msg;
                $arr['rule'] = $info;
                $goods[$key]['check'] = $arr;
            }
        }
        $log_data['data'] = $goods;
        $log_data['remark'] = 'succ';
        \Neigou\Logger::General('server.promotion.check', $log_data);
        $this->setErrorMsg('请求成功');
        return $this->outputFormat($goods);
    }

    public function UnLockStock(Request $request)
    {
        $order_id = $this->getContentArray($request);
        $obj = new Promotion();
        $res = $obj->UnLockStock($order_id);
        if ($res) {
            $this->setErrorMsg('请求成功');
            return $this->outputFormat($res);
        } else {
            $this->setErrorMsg('解锁失败');
            return $this->outputFormat($res, 505);
        }

    }
}
