<?php

namespace App\Api\Logic\AssetType;

use App\Api\Logic\Service as Service;

// 免邮券
class Freeshipping extends Asset
{
    /*
     * @todo 获取资产状态
     */
    public function getAssetStatus($assetInfo, $assetObj)
    {
        if (empty($assetInfo) OR empty($assetObj)) {
            return false;
        }

        $service_logic = new Service();
        $result = $service_logic->ServiceCall('voucher_get', $assetInfo['asset_bn']);
        if ($result['error_code'] != 'SUCCESS' OR empty($result['data']))
        {
            return false;
        }
        // 券码ID
        $couponId = $result['data']['voucher_id'];
        $result = $service_logic->ServiceCall('freeshipping_order_get', array('order_id' => $assetObj['use_obj']));

        if ($result['error_code'] != 'SUCCESS' OR empty($result['data']))
        {
            return false;
        }

        // 状态(0:注册 1:锁定 2:取消 3:使用 4:异常）
        $status = 0;

        if ($result['data']['coupon_id'] && $result['data']['coupon_id'] == $couponId)
        {
            // 0:锁定，1：完成，2：订单取消
            switch ($result['data']['status'])
            {
                case 0:
                    $status = 1;
                    break;
                case 1:
                    $status = 3;
                    break;
                case 2:
                    $status = 2;
                    break;
            }
        }

        return $status == 0 ? $assetObj['status'] : $status;
    }

}
