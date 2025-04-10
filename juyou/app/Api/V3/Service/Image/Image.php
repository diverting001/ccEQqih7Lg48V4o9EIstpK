<?php
/**
 * neigou_service-stock
 * @package     api
 * @author      xupeng
 * @since       Version
 * @filesource
 */

namespace App\Api\V3\Service\Image;

use App\Api\V3\Service\Image\Aliyun as AliyunService;

/**
 * 地址
 *
 * @package     api
 * @category    Service
 * @author        xupeng
 */
class Image
{
    private $_uploadErrMsg = '';

    /**
     * 获取子级地址
     *
     * @param   $name       string      文件名称
     * @param   $file       string      文件流
     * @param   $path       string      文件目录
     * @param   $platform   string      平台
     * @return  mixed
     */
    public function uploadImage($name, $file, $path = 'service_images', $platform = 'aliyun')
    {
        if (empty($file)) {
            $this->_uploadErrMsg = '文件内容为空';
            return false;
        }

        if ($platform == 'aliyun' || $platform == 'oss') {
            $aliyunService = new AliyunService();

            $fileUrl = $aliyunService->upload($name, $file, $path);

            if (!$fileUrl) {
                $this->_uploadErrMsg = '图片上传失败';
                return false;
            }
        } elseif ($platform == 'bos') {
            $bos = new Bos();

            $fileUrl = $bos->upload($name, $file, $path);

            if (!$fileUrl) {
                $this->_uploadErrMsg = '图片上传失败';
                return false;
            }
        } else {
            $this->_uploadErrMsg = '不支持的平台';
            return false;
        }

        return array('url' => $fileUrl);
    }

    // --------------------------------------------------------------------

    /**
     * 获取上传错误信息
     *
     * @return string
     */
    public function getUploadErrMsg()
    {
        return $this->_uploadErrMsg;
    }
}
