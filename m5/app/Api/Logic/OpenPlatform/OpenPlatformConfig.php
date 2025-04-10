<?php
namespace App\Api\Logic\OpenPlatform;

use App\Api\Model\OpenPlatform\OpenPlatformConfig as OpenPlatformConfigModel;

class OpenPlatformConfig
{
    /**
     * Notes:新增或编辑
     * User: mazhenkang
     * Date: 2024/8/16 下午4:19
     */
    public function saveConfig($params, &$msg = '')
    {
        $app_id = $params['app_id']; //应用id
        $app_secret = $params['app_secret']; //应用秘钥
        $platform_type = $params['platform_type']; //平台类型： 1、微信公众号 2、微信小程序
        $adapter_type = $params['adapter_type'] ?: 1; //处理类型： 1、公众号 2、小程序 3、java公众号
        $auto_refresh_token = $params['auto_refresh_token'] ?: 0; //自动刷新临时token 0、不刷新 1、刷新
        $memo = $params['memo'] ?: ''; //备注

        $model = new OpenPlatformConfigModel();
        $info = $model->getConfigInfoByPlatformType($app_id, $platform_type);
        if (empty($info)) {
            $insert_data = [
                'app_id' => $app_id,
                'app_secret' => $app_secret,
                'platform_type' => $platform_type,
                'adapter_type' => $adapter_type,
                'auto_refresh_token' => $auto_refresh_token,
                'memo' => $memo,
            ];
            $id = $model->addOpenPlatformConfig($insert_data);
            if (!$id) {
                $msg = '数据插入失败';
                return false;
            }

            return true;
        }

        $update_data = [
            'app_secret' => $app_secret,
            'adapter_type' => $adapter_type,
            'auto_refresh_token' => $auto_refresh_token,
            'memo' => $memo,
        ];

        $set = $model->updateOpenPlatformConfigById($info->id, $update_data);
        if(!$set){
            $msg = '修改失败';
            return false;
        }

        return true;
    }
}
