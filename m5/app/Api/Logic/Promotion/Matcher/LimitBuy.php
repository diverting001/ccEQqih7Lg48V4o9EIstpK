<?php
/**
 * Created by PhpStorm.
 * User: chuanbin
 * Date: 2020-06-09
 * Time: 18:16
 */

namespace App\Api\Logic\Promotion\Matcher;


use App\Api\Model\Goods\Promotion;

class LimitBuy extends BaseMatcher implements RuleMatcherInterface
{
    private $_processor_name = 'limit_buy';

    private $_model;

    public function __construct()
    {
        $this->_model = new Promotion();

    }

    public function isBatchLimit($config): bool
    {
        switch ($config['limit_type']) {
            case 'product_chosen':
                $limit = true;
                break;

            default:
                $limit = false;
                break;
        }

        return $limit;
    }

    public function exec($times, $config = array(), $filter_data = array())
    {
        // 初始化limit_type
        $config['limit_type'] = $config['limit_type'] ?: 'product'; // 默认product
        switch ($config['limit_type']) {
            case 'product':
                $detailLimit = $this->getProductLimit($times, $filter_data, $config);
                break;
            case 'goods':
                $detailLimit = $this->getGoodsLimit($times, $filter_data, $config);
                break;
            case 'product_chosen':
                $detailLimit = $this->getProductChosenLimit($filter_data, $config);
                break;
            default:
                return $this->output(false, '未适配的限购类型', $config);
        }
        if ($detailLimit['status'] !== true) {
            return $detailLimit;
        }

        $data['limit_buy'] = $config;
        $data['class'] = $this->_processor_name;
        return $this->output(true,'满足限购条件',$config);
    }


    /**
     * @param $times
     * @param $filter_data
     * @param $config
     * @return mixed
     */
    private function getProductLimit($times, $filter_data, $config)
    {
        foreach ($filter_data['goods_list'] as $value) {
            //如果配置了按天刷新 获取售出和已买数量需要按照自然日计数
            //已经售买的总数 当前商品的BN查询
            $config['has_sold'] = $this->_model->GetSoldByBn($value['bn'], $filter_data['scene_id']);

            if(!empty($config['everyday_goods_stock'])) {
                //当天已经售买的总数 当前商品的BN查询
                $config['has_today_sold'] = $this->_model->GetTodaySoldByBn($value['bn'], $filter_data['scene_id'], $config['daily_refresh_start_time']);
            }

            //当前用户已经购买的数量 当前用户和BN组合查询
            $config['has_buy'] = $this->_model->GetSoldByUid($value['bn'], $filter_data['scene_id'], $filter_data['member_id'], $config['refresh_time'], $config['daily_refresh_start_time']);
            $config['add_num'] = $value['nums'] ?: $value['quantity'] ?: 1;
            $check = $this->checkBuyCount($config);
            if ($check !== true) {
                if(!empty($check == '超过每日最大购买量')) {
                    $config['has_sold'] = $config['has_today_sold'];
                    $config['max_sold'] = $config['everyday_goods_stock'];
                }
                return $this->output(false, $check, $config);
            }
        }
        return $this->output(true, '', array());
    }

    /**
     * @param $times
     * @param $filter_data
     * @param $config
     * @return mixed
     */
    private function getGoodsLimit($times, $filter_data, $config)
    {
        $goodsList = array();
        foreach ($filter_data['goods_list'] as $value) {
            $addNum = $value['goods_nums'] ?: $value['goods_quantity'] ?: 1;
            if (!isset($goodsList[$value['goods_id']])) {
                //如果配置了按天刷新 获取售出和已买数量需要按照自然日计数
                //已经售买的总数 当前商品的BN查询
                $config['has_sold'] = $this->_model->getSoldByGoodsIdRule($value['goods_id'], $filter_data['scene_id'], $filter_data['order_id']);

                if(!empty($config['everyday_goods_stock'])) {
                    //当天已经售买的总数 当前商品的BN查询
                    $config['has_today_sold'] = $this->_model->getTodaySoldByGoodsIdRule($value['goods_id'], $filter_data['scene_id'], $filter_data['order_id'], $config['daily_refresh_start_time']);
                }

                //当前用户已经购买的数量 当前用户和BN组合查询
                $config['has_buy'] = $this->_model->getSoldByGoodsIdMemberRule($value['goods_id'], $filter_data['scene_id'], $filter_data['member_id'], $config['refresh_time'], $filter_data['order_id'], $config['daily_refresh_start_time']);
                $config['add_num'] = $addNum;
                $goodsList[$value['goods_id']] = $config;
                $check = $this->checkBuyCount($config);
                if ($check !== true) {
                    if(!empty($check == '超过每日最大购买量')) {
                        $config['has_sold'] = $config['has_today_sold'];
                        $config['max_sold'] = $config['everyday_goods_stock'];
                    }
                    return $this->output(false, $check, $config);
                }
            } else if (bccomp($goodsList[$value['goods_id']]['add_num'], $addNum, 0) != 0) {
                return $this->output(false, 'spu数量异常', $config);
            }
        }
        return $this->output(true, '', array());
    }

    public function getProductChosenLimit( $filter_data, $config)
    {
        $hasSoldNums = $this->_model->getSoldByMemberAndRule($filter_data['scene_id'], $filter_data['member_id'], $config['refresh_time'], $config['daily_refresh_start_time']);
        if ($hasSoldNums > $config['max_buy']) {
            return $this->output(false, $config['tips'], $config);
        }

        $totalSkuBuyNum = 0;
        foreach ($filter_data['goods_list'] as $value) {
            $totalSkuBuyNum += $value['nums'] ?: $value['quantity'] ?: 1;
        }

       if (bcadd($hasSoldNums, $totalSkuBuyNum) > $config['max_buy']) {
           return $this->output(false, $config['tips'], $config);
       }

       return $this->output(true, '', array());
    }

    private function checkBuyCount($config)
    {
        if ($config['add_num'] + $config['has_buy'] > $config['max_buy']) {
            return '超过单人允许购买量';
        }
        if(!empty($config['everyday_goods_stock'])){
            if ($config['add_num'] + $config['has_today_sold'] > $config['everyday_goods_stock']) {
                return '超过每日最大购买量';
            }
        }
        if ($config['add_num'] + $config['has_sold'] > $config['max_sold']) {
            return '超过最大购买量';
        }
        if ($config['add_num'] < $config['min_buy']) {
            return '小于起订量';
        }
        return true;
    }

}
