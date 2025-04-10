<?php

namespace App\Api\V1\Service\ImageOCR;

use App\Api\V1\Service\ImageOCR\Tools\AliOcr;

class OcrApiService
{

    /**
     * 全文识别获取指定卡，券，密码值
     * @param $service_type
     * @param $file
     * @return int[]
     * @throws \Exception
     */
    public function getImageWord($service_type,$file): array
    {
        switch ($service_type){
            case 'ali_ocr':
                $Ocr = new AliOcr();
                break;
            default:
                return false;
                break;
        }
        return $Ocr->RecognizeAdvancedApi($file);
    }
}
