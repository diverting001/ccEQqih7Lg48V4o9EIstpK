<?php

namespace App\Api\V1\Service\Operate;

use App\Api\Logic\Operate\LimitMoney\Platform;

class LimitMoney
{
    public function getGoodsLimitMoney($product, $companyId = null, $memberId = null)
    {
        $return = array();

        if (empty($product))
        {
            return false;
        }

        $limitMoneyPlatformLogic = new Platform();

        // 获取商品平台限制
        $goodsPlatformData = $limitMoneyPlatformLogic->getProductMoneyLimit($product, $companyId, $memberId);
        if ( ! empty($goodsPlatformData))
        {
            foreach ($goodsPlatformData as $productBn => $v)
            {
                $return[$productBn] = $v;
            }
        }

        return $return;
    }
}
