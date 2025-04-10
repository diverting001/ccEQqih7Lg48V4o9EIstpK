<?php

namespace App\Api\V3\Service\Image;

use Neigou\Logger;

class FuYouZhao implements UploadInterface
{
    /**
     * @param $name
     * @param $file string realpath
     * @param $path string path
     * @return array|false
     */
    public function upload($name, $file, $path)
    {
        $result = $this->apiUploadV2($file);
        if (!$result) {
            return false;
        }

        return $result['url'];
    }

    private function apiUploadV2($filePath)
    {
        $accessKey = config('neigou.FU_YOU_ZHAO_KEY');
        $secretKey = config('neigou.FU_YOU_ZHAO_SECRET');
        $bucketName = config('neigou.FU_YOU_ZHAO_BUCKET');
        $systemUrl = config('neigou.FU_YOU_ZHAO_ENDPOINT');
        $token = $this->getTokenV2($accessKey, $secretKey, $bucketName, $systemUrl);
        if (!$token) {
            return false;
        }

        $url = $systemUrl . '/cos-upload/v2/uploadFileByBackground';
        $CURLFile = new \CURLFile($filePath);
        $CURLFile->setPostFilename('test_fuxii.jpg');
        $data = [
            'file' => $CURLFile,
        ];
        $headers = [
            'url' => $systemUrl,
            'Authorization' => $token,
            'content-type' => 'multipart/form-data',
        ];
        $apiResult = $this->curlPost($url, $data, $headers);
//        cos-download/v2/dopf/{bucketName}/{resourceId}/{resourceName}(GET请求)
//        {
//            "code": "Y",
//            "body": {
//                "resourceId": "0791c75ed5458710bd1048a52c2ef0b8",
//                "resourceName": "fuxi.jpg",
//                "resourceUrl": "https://cos-cmht.cmft.com/cos-download/v2/dopb/flpt-prd-public-bucket/0791c75ed5458710bd1048a52c2ef0b8",
//                "bucketName": "flpt-prd-public-bucket",
//                "etag": "d0137ca1475b4f864866987acd2f055f",
//                "resourceSize": 7100,
//                "owner": "flpt-prd-public-bucket",
//                "modifiedTime": 1660703728703
//            },
//            "message": "资源上传成功"
//        }

        $result = json_decode($apiResult, true);
        if ($this->isApiSuccess($result)) {
            $apiReturnBody = $result['body'];
            $path = '/cos-download/v2/dopf/' . $bucketName . '/' . $apiReturnBody['resourceId'] . '/' . $apiReturnBody['resourceName'];
            $url = $systemUrl . $path;
            return [
                'url' => $url,
                'path' => $path,
                'id' => $apiReturnBody['resourceId'],
                'info' => $apiReturnBody['resourceUrl'],
                'name' => $apiReturnBody['resourceName'],
            ];
        }
        Logger::General('upload.fu_you_zhao.err', ['remark' => 'fu_you_zhao_up_err', 'data' => $data, 'header' => $headers, 'result' => $apiResult]);
        return false;
    }


    private function isApiSuccess($res)
    {
        return isset($res['code']) && $res['code'] === 'Y';
    }

    private function getTokenV2($accessKey, $secretKey, $bucketName, $systemUrl)
    {
        $url = $systemUrl . '/cos-upload/v2/uploadToken';
        $data = [
            'accessKey' => $accessKey,
            'secretKey' => $secretKey,
            'bucketName' => $bucketName,
            'systemUrl' => $systemUrl
        ];
        $apiResult = $this->curlPost($url, json_encode($data), [
            'content-type' => 'application/json',
        ]);

        $result = json_decode($apiResult, true);
        if ($this->isApiSuccess($result)) {
            return $result['body'];
        }
        Logger::General('upload.fu_you_zhao.err', ['remark' => 'fu_you_zhao_token_err', 'config' => $data, 'result' => $apiResult]);
        return false;
    }

    private function curlPost($url, $data = null, $headers = [])
    {
//        $file = array('file' => new \CURLFile(realpath('H:/1.jpg')))
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        if ($headers) {
            $headersRes = [];
            foreach ($headers as $k => $v) {
                $headersRes[] = $k . ': ' . $v;
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headersRes);
        }
        $res = curl_exec($ch);
//        $status = curl_getinfo($ch);
//        $errorNo = curl_errno($ch);
//        $error = curl_error($ch);
        curl_close($ch);
        return $res;
    }
}
