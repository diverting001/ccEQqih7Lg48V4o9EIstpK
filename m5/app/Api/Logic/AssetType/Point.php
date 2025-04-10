<?php

namespace App\Api\Logic\AssetType;

use App\Api\Logic\Service as Service;

// 积分
class Point extends Asset
{
    /*
     * @todo 获取资产状态
     */
    public function getAssetStatus($assetInfo, $assetObj)
    {
        if (empty($assetInfo) OR empty($assetObj)) {
            return false;
        }

        // GetRecordByUse
        $service_logic = new Service();
        $data = array(
            'use_type'  => strtolower($assetObj['use_type']),
            'use_obj'   => $assetObj['use_obj'],
        );
        $result = $service_logic->ServiceCall('get_point_record_use', $data);

        if ($result['error_code'] != 'SUCCESS')
        {
            return false;
        }

        // 状态(0:注册 1:锁定 2:取消 3:使用 4:异常）
        $status = 0;

        // 状态 1.待锁定 2.已锁定 3.已使用 4.已取消 5:锁定失败
        switch ($result['data']['status'])
        {
            case 1:
                $status = 0;
                break;
            case 2:
                $status = 1;
                break;
            case 3:
                $status = 3;
                break;
            case 4:
                $status = 2;
                break;
            case 5:
                $status = 4;
                break;
        }

        return $status == 0 ? $assetObj['status'] : $status;
    }

}
