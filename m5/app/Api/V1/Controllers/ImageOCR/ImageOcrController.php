<?php

namespace App\Api\V1\Controllers\ImageOCR;

use App\Api\Common\Controllers\BaseController;
use App\Api\V1\Service\ImageOCR\OcrApiService;
use Illuminate\Http\Request;

/**
 * 处理图片文字识别-OCR支持
 */
class ImageOcrController extends BaseController
{
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
        'image/x-png',
    );

    /**
     * @const   文件最大限制
     */
    const MAX_FILE_SIZE = 1024 * 1024 * 10;

    /**
     * 全文识别高精版
     */
    public function CardVoucherDistinguish(Request $request)
    {

        //获取上传的文件
        $file = $request->file('file');

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

        //获取上传图片的临时地址
        $tmppath = $file->getRealPath();
        //获取二进制文件
        $tmp =  file_get_contents($tmppath);

        $ocr_service = new OcrApiService();
        $data =  $ocr_service->getImageWord('ali_ocr',$tmp);
        if($data){
            $this->setErrorMsg('识别成功');
            return $this->outputFormat($data);
        }
        $this->setErrorMsg('识别失败');
        return $this->outputFormat(null, 400);
    }
}
