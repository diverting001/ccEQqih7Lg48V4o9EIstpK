<?php

namespace App\Api\V3\Controllers\File;

use App\Api\Common\Controllers\BaseController;
use App\Api\V3\Service\Image\Image as ImageService;
use Illuminate\Http\Request;

/**
 * 文件 Controller
 *
 * @package     api
 * @category    Controller
 * @author        xupeng
 */
class FileController extends BaseController
{
    /**
     * @var
     */
    private $_fileService;

    /**
     * @var array   文件类型
     */
    private static $_fileTypes = array(
        'application/msword',   //doc
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',  //docx
        'application/vnd.ms-excel', //xls
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',    //xlsx
        'application/pdf',  //pdf
        'application/vnd.ms-powerpoint',    //ppt
        'application/vnd.openxmlformats-officedocument.presentationml.presentation', //pptx
        'text/plain', //txt csv
        'text/csv', // csv
        'application/zip', //zip
    );

    /**
     * @const   文件最大限制
     */
    const MAX_FILE_SIZE = 1024 * 1024 * 50;

    /**
     * CompanyController constructor.
     */
    public function __construct()
    {
        $this->_fileService = new ImageService();
    }

    // --------------------------------------------------------------------

    /**
     * 文件上传
     *
     * @return array
     */
    public function Upload(Request $request)
    {
        $params = $request->post();
        // 文件
        $file = $request->file("file");
        if (is_null($file) || !$file->isFile()) {
            $this->setErrorMsg('请求参数错误');
            return $this->outputFormat(null, 400);
        }

        // 文件类型
        if (!in_array($file->getClientMimeType(), self::$_fileTypes)) {
            $this->setErrorMsg('文件类型不支持');
            return $this->outputFormat(null, 400);
        }

        // 检查文件大小
        if ($file->getSize() > self::MAX_FILE_SIZE) {
            $this->setErrorMsg('文件超出限制');
            return $this->outputFormat(null, 400);
        }

        if (!empty($params['name'])) {
            $name = $params['name'];
        } else {
            $name = uniqid() . '.' . $file->getClientOriginalExtension();
        }

        $path = $params['path'] ? ('file/'.$params['path']) : 'file';

        //platform
        $config_platform = config('neigou.IMAGE_DRIVER');
        $platform = $params['platform'] ?? $config_platform;

        // 获取子级地址
        $data = $this->_fileService->uploadImage($name, $file->getRealPath(), $path, strtolower($platform));

        if (!$data) {
            $errMsg = $this->_fileService->getUploadErrMsg();
            $errMsg OR $errMsg = '文件上传失败';

            $this->setErrorMsg($errMsg);
            return $this->outputFormat(null, 400);
        }

        $this->setErrorMsg('上传成功');
        return $this->outputFormat($data);
    }
}
