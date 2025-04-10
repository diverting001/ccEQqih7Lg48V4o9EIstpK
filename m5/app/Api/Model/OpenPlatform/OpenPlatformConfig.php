<?php
namespace App\Api\Model\OpenPlatform;

class OpenPlatformConfig
{
    public function getConfigInfoByPlatformType($app_id,$platform_type)
    {
        if(empty($app_id)){
            return;
        }

        $info = app('api_db')->table('service_open_platform_config')
            ->where('app_id', $app_id)
            ->where('platform_type', $platform_type)->first();
        return $info;
    }

    public function setExpiresTimeByAppId($app_id, $expires_time)
    {
        $data = array(
            'expires_time' => $expires_time
        );
        $status = app('api_db')->table('service_open_platform_config')->where(['app_id' => $app_id])->update($data);

        return $status;

    }

    public function getAdventList($time, $page = 1, $limit = 10)
    {
        $offset = ($page - 1) * $limit;
        $list = app('api_db')->table('service_open_platform_config')
            ->where('auto_refresh_token', 1)
            ->where('expires_time', '<=', $time)
            ->limit($limit)
            ->offset($offset)
            ->get()
            ->toArray();
        if (empty($list)) {
            return [];
        }

        foreach ($list as &$info) {
            $info = get_object_vars($info);
        }

        return $list;
    }

    public function addOpenPlatformConfig($data)
    {
        $insert_data = [];
        if (!empty($data['app_id'])) {
            $insert_data['app_id'] = $data['app_id'];
        }

        if (!empty($data['app_secret'])) {
            $insert_data['app_secret'] = $data['app_secret'];
        }

        if (!empty($data['platform_type'])) {
            $insert_data['platform_type'] = $data['platform_type'];
        }

        if (!empty($data['adapter_type'])) {
            $insert_data['adapter_type'] = $data['adapter_type'];
        }

        if (isset($data['auto_refresh_token']) && in_array($data['auto_refresh_token'], [0, 1])) {
            $insert_data['auto_refresh_token'] = $data['auto_refresh_token'];
        }

        if (!empty($data['memo'])) {
            $insert_data['memo'] = $data['memo'];
        }
        if (empty($insert_data)) {
            return false;
        }

        return app('api_db')->table('service_open_platform_config')->insertGetId($insert_data);
    }

    public function updateOpenPlatformConfigById($id, $data)
    {
        $update_data = [];

        if (!empty($data['adapter_type'])) {
            $update_data['adapter_type'] = $data['adapter_type'];
        }

        if (isset($data['auto_refresh_token']) && in_array($data['auto_refresh_token'], [0, 1])) {
            $update_data['auto_refresh_token'] = $data['auto_refresh_token'];
        }

        if (!empty($data['memo'])) {
            $update_data['memo'] = $data['memo'];
        }
        if (empty($update_data)) {
            return false;
        }
        $update_data['update_date'] = date('Y-m-d H:i:s');
        return app('api_db')->table('service_open_platform_config')->where(array('id' => $id))->update($update_data);
    }
}
