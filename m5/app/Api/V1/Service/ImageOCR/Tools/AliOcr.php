<?php

namespace App\Api\V1\Service\ImageOCR\Tools;

/**
 * 阿里云Ocr服务
 */
class AliOcr
{
    //查询域名
    /**
     *
     * @param string $file 文件流
     * @return int[]
     * @throws \Exception
     */
    /**
     * 处理阿里云全文识别高精版
     * @param $file
     * @return string[]
     * @throws \Exception
     */
    public function RecognizeAdvancedApi($file): array
    {
        $param = $this->getCommonParams();
        $param['Action'] = 'RecognizeAdvanced';
        $param = ['NeedRotate' => "true"] + $param;//需要自动进行角度旋转
        $param['Signature'] = $this->getSignature($param);
        $url = config('neigou.ALI_OCR_ENDPOINT') . '?' . http_build_query($param);
        $res = $this->httpCurl($url, $file);
        $content = '';
        if ($res) {
            \Neigou\Logger::General('aliOcr.result.data', $res);
            $res_array = json_decode($res, true);
            if(isset($res_array['Data'])){
                $data = json_decode($res_array['Data'], true);
                $content = $data['content'];
                $content = str_replace(" ", "", $content);//去掉全部空格
            }
        }
        return ['content' => $content];
    }

    /**
     * 组合公共参数
     * @return array
     */
    private function getCommonParams()
    {
        return [
            'Format' => 'JSON',
            'Version' => '2021-07-07',
            'AccessKeyId' => config('neigou.ALI_OCR_ASSESS_KEY_ID'),
            'SignatureMethod' => 'HMAC-SHA1',
            'Timestamp' => gmdate('Y-m-d\\TH:i:s\\Z'),//'2022-08-24T10:27:00Z'
            'SignatureVersion' => '1.0',
            'SignatureNonce' => md5(microtime()),
        ];
    }

    private function percentEncode($str)
    {
        // 使用urlencode编码后，将"+","*","%7E"做替换即满足ECS API规定的编码规范
        $res = urlencode($str);
        $res = preg_replace('/\+/', '%20', $res);
        $res = preg_replace('/\*/', '%2A', $res);
        return preg_replace('/%7E/', '~', $res);
    }

    /**
     * 生成签名
     * @param $parameters
     * @return string
     */
    private function getSignature($parameters)
    {
        // 将参数Key按字典顺序排序
        ksort($parameters);
        // 生成规范化请求字符串
        $canonicalizedQueryString = '';
        foreach ($parameters as $key => $value) {
            $canonicalizedQueryString .= '&' . $this->percentEncode($key)
                . '=' . $this->percentEncode($value);
        }
        // 生成用于计算签名的字符串 stringToSign
        $stringToSign = "POST&%2F&" . $this->percentencode(substr($canonicalizedQueryString, 1));
        // 计算签名，注意accessKeySecret后面要加上字符'&'
        return base64_encode(hash_hmac('sha1', $stringToSign, config('neigou.ALI_OCR_ACCESS_KEY_SECRET') . '&', true));
    }

    /**
     * 模拟请求
     *
     * @param string $url
     * @param array $query POST内容
     * @param bool $withHeader 是否返回头信息
     * @param array $header 发送头信息
     * @throws \Exception
     */
    private function httpCurl($url, $file, $withHeader = false, $header = [])
    {

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

        if ($withHeader) {
            curl_setopt($ch, CURLOPT_HEADER, true);
        }

        if (!empty($file)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Accept: application/json',
                'Content-Type: application/octet-stream',
            ));

            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $file);
        }

        if (!empty($header)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        }

        $response = curl_exec($ch);
        $errorNo = curl_errno($ch);
        is_resource($ch) && curl_close($ch);

        if (0 !== $errorNo) {
            \Neigou\Logger::General('aliOcr.result.error', [$response, $errorNo]);
            return false;
        }
        return $response;
    }
}
