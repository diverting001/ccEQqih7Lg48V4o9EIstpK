<?php
/**
 * neigou_service-stock
 * @package     api
 * @author      xupeng
 * @since       Version
 * @filesource
 */

namespace App\Api\V3\Controllers\Image;


use App\Api\Common\Controllers\BaseController;
use App\Api\V3\Service\Image\Image as ImageService;
use Illuminate\Http\Request;

/**
 * 图片 Controller
 *
 * @package     api
 * @category    Controller
 * @author        xupeng
 */
class ImageController extends BaseController
{
    /**
     * @var
     */
    private $_imageService;

    /**
     * @var array   图片类型
     */
    private static $_imageTypes = array(
        'image/jpg',
        'image/jpeg',
        'image/png',
        'image/pjpeg',
        'image/gif',
        'image/bmp',
        'image/x-png'
    );

    /**
     * @const   文件最大限制
     */
    const MAX_FILE_SIZE = 1024 * 1024 * 10;


    /**
     * CompanyController constructor.
     */
    public function __construct()
    {
        $this->_imageService = new ImageService();
    }

    // --------------------------------------------------------------------

    /**
     * 图片上传
     *
     * @return array
     */
    public function UploadImage(Request $request)
    {
        $params = $request->post();
        // 文件
        $file = $request->file("file");
        if (is_null($file) || !$file->isFile()) {
            $this->setErrorMsg('请求参数错误');
            return $this->outputFormat(null, 400);
        }

        // 图片类型
        if (!in_array($file->getMimeType(), self::$_imageTypes)) {
            $this->setErrorMsg('图片类型不支持');
            return $this->outputFormat(null, 400);
        }

        // 检查图片大小
        if ($file->getSize() > self::MAX_FILE_SIZE) {
            $this->setErrorMsg('图片超出限制');
            return $this->outputFormat(null, 400);
        }

        $name = uniqid() . '.' . $file->extension();

        // 图片路径
        $path = $params['path'];

        //platform
        $config_platform = config('neigou.IMAGE_DRIVER');
        $platform = isset($params['platform'])? $params['platform']:strtolower($config_platform);

        // 获取子级地址
        $data = $this->_imageService->uploadImage($name, $file->getRealPath(), $path, $platform);

        if (!$data) {
            $errMsg = $this->_imageService->getUploadErrMsg();
            $errMsg OR $errMsg = '文件上传失败';

            $this->setErrorMsg($errMsg);
            return $this->outputFormat(null, 400);
        }

        $this->setErrorMsg('上传成功');
        return $this->outputFormat($data);
    }

}
