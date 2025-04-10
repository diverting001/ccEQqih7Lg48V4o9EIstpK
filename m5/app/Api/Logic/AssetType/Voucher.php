<?php

namespace App\Api\Logic\AssetType;

use App\Api\Logic\Service as Service;

// 优惠券
class Voucher extends Asset
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
        $result = $service_logic->ServiceCall('voucher_order_get', $assetObj['use_obj']);

        if ($result['error_code'] != 'SUCCESS' OR empty($result['data']))
        {
            return false;
        }

        // 状态(0:注册 1:锁定 2:取消 3:使用 4:异常）
        $status = 0;
        foreach ($result['data'] as $v)
        {
            if ($v['number'] == $assetInfo['asset_bn'])
            {
                // status` enum('normal','lock','finish','disabled') NOT NULL DEFAULT 'normal' COMMENT '代金券状态。''normal''：未使用,''lock''：已锁定,''finish''：已使用,''disabled''：已作废',
                switch ($v['status'])
                {
                    case 'normal':
                        $status = 0;
                        break;
                    case 'lock':
                        $status = 1;
                        break;
                    case 'finish':
                        $status = 3;
                        break;
                    case 'disabled':
                        $status = 2;
                        break;
                }
            }
        }

        return $status == 0 ? $assetObj['status'] : $status;
    }

}
