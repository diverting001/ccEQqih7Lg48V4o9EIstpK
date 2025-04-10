<?php
/**
 * neigou_service-stock
 *
 * @package     api
 * @author      xupeng
 * @since       Version
 * @filesource
 */

namespace App\Api\V1\Service\Operate;

use App\Api\Logic\Operate\LimitBuy\Platform;
use App\Api\Logic\Operate\LimitBuy\Supplier;

/**
 * 限制 Controller
 *
 * @package     api
 * @category    Controller
 * @author        xupeng
 */
class LimitBuy
{
    /**
     * 获取商品购买限制
     *
     * @param   $product    array       货品列表
     * @param   $companyId  string      公司ID
     * @param   $memberId   string      用户ID
     * @return  mixed
     */
    public function getGoodsLimitBuy($product, $companyId = null, $memberId = null)
    {
        $return = array();
        if (empty($product))
        {
            return false;
        }

        $limitBuyPlatformLogic = new Platform();

        // 获取商品平台限制
        $goodsPlatformData = $limitBuyPlatformLogic->getProductBuyLimit($product, $companyId, $memberId);
        if ( ! empty($goodsPlatformData))
        {
            foreach ($goodsPlatformData as $productBn => $v)
            {
                $return[$productBn] = $v;
            }
        }
        $limitBuySupplierLogic = new Supplier();
        // 获取供应商品限制
        $goodsSupplierData = $limitBuySupplierLogic->getProductBuyLimit($product, $companyId, $memberId);
        if ( ! empty($goodsSupplierData))
        {
            foreach ($goodsSupplierData as $productBn => $v)
            {
                if ( ! isset($return[$productBn]))
                {
                    $return[$productBn] = $v;
                    continue;
                }

                if ($v['min_buy'] !== null && $v['min_buy'] > $return[$productBn]['min_buy'])
                {
                    $return[$productBn]['min_buy'] = $v['min_buy'];
                }

                if ($v['max_buy'] !== null && $v['max_buy'] < $return[$productBn]['max_buy'])
                {
                    $return[$productBn]['max_buy'] = $v['max_buy'];
                }
            }
        }
        return $this->_formatLimitBuyData($return);
    }

    /**
     * 获取供应商品购买限制
     *
     * @param   $product    array       货品列表
     * @return  mixed
     */
    public function getSupplierGoodsLimitBuy($product)
    {
        $return = array();
        if (empty($product))
        {
            return false;
        }

        $limitBuySupplierLogic = new Supplier();
        // 获取供应商品限制
        $goodsSupplierData = $limitBuySupplierLogic->getProductBuyLimit($product);
        if ( ! empty($goodsSupplierData))
        {
            foreach ($goodsSupplierData as $productBn => $v)
            {
                $return[$productBn] = $v;
            }
        }
        return $this->_formatLimitBuyData($return);
    }

    /**
     * 格式化返回值
     *
     * @return  array
     */
    private function _formatLimitBuyData($data)
    {
        $return = array();
        if (empty($data))
        {
            return $return;
        }
        $keys = array('min_buy' => '最小购买数量', 'max_buy' => '最大购买数量', 'limit_type' => '限购维度', 'refresh_time' => '限购刷新时间','tips' => '提示信息');
        foreach ($data as $productBn => $v)
        {
            foreach ($keys as $key => $name)
            {
                if (isset($v[$key]) && $v[$key])
                {
                    $return[$productBn][] = array(
                        'name' => $name,
                        'desc' => $name,
                        'item_type' => $key,
                        'item_value' => $v[$key],
                    );
                }
            }
        }
        return $return;
    }

}
