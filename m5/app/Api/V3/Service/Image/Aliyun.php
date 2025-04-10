<?php
/**
 * neigou_service-stock
 * @package     api
 * @author      xupeng
 * @since       Version
 * @filesource
 */

namespace App\Api\V3\Service\Image;

use OSS\OssClient;

/**
 * 阿里云图片
 *
 * @package     api
 * @category    Service
 * @author        xupeng
 */
class Aliyun implements UploadInterface
{
    /**
     * 图片上传
     *
     * @param   $name       string      文件名称（包含后缀）
     * @param   $file       string      文件（文件流)
     * @param   $path       string      路径
     * @return  mixed
     * @throws
     */
    public function upload($name, $file, $path = 'default')
    {
        $ossClient = new OssClient(config('neigou.OSS_ASSESS_KEY_ID'), config('neigou.OSS_ACCESS_KEY_SECRET'), config('neigou.OSS_ENDPOINT'));

        $orgPath = $path . '/' . date('Ymd') . '/' . $name;

        $res = $ossClient->uploadFile(config('neigou.OSS_BUCKET'), $orgPath, $file);

        return $this->_convertUrl($res['info']['url']);
    }

    private function _convertUrl($url){
        if(empty(config('neigou.OSS_BUCKET_CDN'))){
            return $url;
        }
        $info = parse_url($url);
        $cdn_url = config('neigou.OSS_BUCKET_CDN').$info['path'];
        return $cdn_url;
    }

}
