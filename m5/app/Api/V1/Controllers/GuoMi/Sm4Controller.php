<?php

namespace App\Api\V1\Controllers\GuoMi;

use App\Api\Common\Controllers\BaseController;
use Illuminate\Http\Request;

class Sm4Controller extends BaseController
{
    public function encrypt(Request $request)
    {
        $params = $this->getContentArray($request);

        if (empty($params['documentStr'])) {
            $this->setErrorMsg('待加密字符串不能为空');
            return $this->outputFormat(null, 400);
        }

        if (empty($params['secretKey'])) {
            $this->setErrorMsg('加密秘钥不能为空');
            return $this->outputFormat(null, 401);
        }

        if (strlen($params['secretKey']) > 16) {
            $this->setErrorMsg('加密秘钥长度不能大于16位');
            return $this->outputFormat(null, 402);
        }

        $ciphertext = openssl_encrypt($params['documentStr'], 'sm4-ecb', $params['secretKey'], OPENSSL_RAW_DATA);

        $this->setErrorMsg('success');

        //返回数据 默认c1c3c2的hex字符串
        $return = array(
            'encrypt' => isset($params['formatSign']) && $params['formatSign'] == 'base64'
                ? base64_encode($ciphertext)
                : bin2hex($ciphertext)
        );

        return $this->outputFormat($return);
    }

    public function decrypt(Request $request)
    {
        $params = $this->getContentArray($request);

        if (empty($params['documentStr'])) {
            $this->setErrorMsg('已加密字符串不能为空');
            return $this->outputFormat(null, 400);
        }

        if (empty($params['secretKey'])) {
            $this->setErrorMsg('加密秘钥不能为空');
            return $this->outputFormat(null, 401);
        }

        if (strlen($params['secretKey']) > 16) {
            $this->setErrorMsg('加密秘钥长度不能大于16位');
            return $this->outputFormat(null, 402);
        }

        $documentStr = isset($params['formatSign']) && $params['formatSign'] == 'base64'
                        ? base64_decode($params['documentStr'])
                        : hex2bin($params['documentStr']);

        $ciphertext = openssl_decrypt($documentStr, 'sm4-ecb', $params['secretKey'], OPENSSL_RAW_DATA);

        $this->setErrorMsg('success');

        //返回数据 默认c1c3c2的hex字符串
        $return = array(
            'decrypt' => $ciphertext
        );

        return $this->outputFormat($return);
    }
}
