<?php

namespace App\Api\Logic\AssetType;

use App\Api\Model\Asset\Asset as AssetModel;

// 积分
class Cash extends Asset
{
    /*
     * @todo 获取资产状态
     */
    public function getAssetStatus($assetInfo, $assetObj)
    {
        if (empty($assetInfo) OR empty($assetObj)) {
            return false;
        }

        // 状态(0:注册 1:锁定 2:取消 3:使用 4:异常）
        $status = 0;

        $result = $this->_request($assetObj['use_obj']);
        if ( ! empty($result['Data']))
        {
            $data = current($result['Data']);
            // 更新 asset_bn
            if ( ! $assetInfo['asset_bn'] && $data['pay_app_id'])
            {
                $assetModel = new AssetModel();
                $assetModel->updateAssetData($assetInfo['asset_id'], array('asset_bn' => $data['pay_app_id']));
            }
            // 'succ','failed','cancel','error','invalid','progress','timeout','ready'
            switch ($data['status'])
            {
                case 'ready':
                    $status = 0;
                    break;
                case 'succ':
                    $status = 3;
                    break;
                case 'cancel':
                    $status = 2;
                    break;
                case 'failed':
                    $status = 2;
                    break;
                case 'error':
                    $status = 2;
                    break;
                case 'timeout':
                    $status = 2;
                    break;
            }
        }

        return $status == 0 ? $assetObj['status'] : $status;
    }

    private function _request($orderId)
    {
        $data = array(
            'order_id' => $orderId
        );
        $send_data = array('data' => base64_encode(json_encode($data)));

        $send_data['token'] = \App\Api\Common\Common::GetEcStoreSign($send_data);

        $curl       = new \Neigou\Curl();
        $result = $curl->Post(config('neigou.STORE_DOMIN') . '/openapi/payment/getPaymentInfoByOrder', $send_data);

        $result = json_decode($result, true);

        return $result;
    }

}
