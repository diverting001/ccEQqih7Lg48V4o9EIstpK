<?php

namespace App\Api\Logic\Promotion\Matcher;

use App\Api\Model\Goods\Promotion;

class CompanyGoodsLimitBuy extends BaseMatcher implements RuleMatcherInterface
{
    private $_processor_name = 'company_goods_limit_buy';

    private $_model;

    public function __construct()
    {
        $this->_model = new Promotion();

    }

    public function exec($times, $config = array(), $filter_data = array())
    {
        $detailLimit = $this->getProductLimit($filter_data, $config);
        if ($detailLimit['status'] !== true) {
            return $detailLimit;
        }
        $data['company_goods_limit_buy'] = $config;
        $data['class'] = $this->_processor_name;
        return $this->output(true, '满足限购条件', array(
            'company_goods_limit_buy' => $config,
            'class' => $this->_processor_name
        ));
    }

    /**
     * 检查购买量是否超出最大限制
     *
     * @param $filter_data
     * @param $config
     * @return mixed
     */
    private function getProductLimit($filter_data, $config)
    {
        foreach ($filter_data['goods_list'] as $value) {
            //已经售买的总数 当前商品的BN查询：（当前规则 + 当前用户 + 当前公司 + 当前产品）
            $todayHasSoldNums = $this->_model->getTodayCompanySoldNums(
                $value['bn'],
                $filter_data['scene_id'],
                $filter_data['company_id']
            );
            $addNums = $value['nums'] ?: $value['quantity'] ?: 1;
            if (!empty($config['max_buy'])) {
                if (bcadd($todayHasSoldNums, $addNums) > $config['max_buy']) {
                    return $this->output(false, '超过每日最大购买量', array(
                        'has_sold' => $todayHasSoldNums,
                        'max_sold' => $config['quota_nums']
                    ));
                }
            }
        }
        return $this->output(true, '', array());
    }
}
