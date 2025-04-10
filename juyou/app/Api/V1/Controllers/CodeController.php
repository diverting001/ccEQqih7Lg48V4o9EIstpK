<?php

namespace App\Api\V1\Controllers;

use App\Api\Common\Controllers\BaseController;
use Illuminate\Http\Request;

/**
 * 语音验证码
 * Class CodeController
 * @package App\Api\V1\Controllers
 */
class CodeController extends BaseController
{
    /**
     *  梦网语音
     */
    const API_URL = 'http://61.145.229.28:5001/voiceprepose/MongateSendSubmit';
    const SMS_USER = 'YY0276';
    const SMS_PWD = '512693';
    /**
     *  模板
     */
    const TEMPLATE_ID = '200236';

    /**
     * 发送语音验证码
     *
     * @return array
     */
    public function voice(Request $request)
    {
        $params = $this->getContentArray($request);
        $mobile = $params['mobile'];
        $code = $params['code'];

        \Neigou\Logger::General(
            'action.Code',
            array(
                'action' => 'voice',
                'tip' => '语音验证码请求参数',
                'params' => array(
                    'mobile' => $mobile,
                    'code' => $code
                )
            )
        );

        // 验证手机号
        if (!$mobile || !is_numeric($mobile) || (strlen($mobile) != 11)) {
            \Neigou\Logger::General('action.Code',
                array('action' => 'voice', 'success' => 0, 'reason' => 'invalid_params'));
            $this->setErrorMsg('手机号码参数错误');
            return $this->outputFormat(null, 404);
        }

        // 验证验证码格式
        if (!preg_match('/^[0-9a-z]{4,8}$/i', $code)) {
            \Neigou\Logger::General('action.Code',
                array('action' => 'voice', 'success' => 0, 'reason' => 'invalid_params'));
            $this->setErrorMsg('验证码参数错误');
            return $this->outputFormat(null, 404);
        }

        $result = self::activeAndSend($params);
        if ($result) {
            $this->setErrorMsg('请求成功');
            return $this->outputFormat($result);
        } else {
            $this->setErrorMsg('请求失败');
            return $this->outputFormat($result, 404);
        }
    }

    /**
     * @param array $params
     * @return bool|mixed
     */
    private static function activeAndSend($params = array())
    {
        $url = self::API_URL;
        $data = array(
            'userId' => self::SMS_USER,
            'password' => self::SMS_PWD,
            'pszMobis' => $params['mobile'],
            'pszMsg' => $params['code'],
            'iMobiCount' => 1,
//            'pszSubPort'=>'*',
            'MsgId' => '123456',
            'PtTmplId' => self::TEMPLATE_ID,
            'msgType' => 1,
        );

        $result = self::sendSMS($data, $url, 'post');
        if ($result['res_code'] == 0) {
            return $result['idertifier'];
        } else {
            return false;
        }
    }

    /**
     * @param $params
     * @param $url
     * @param string $method
     * @return array|bool
     */
    private static function sendSMS($params, $url, $method = 'post')
    {
        if (!in_array(strtolower($method), array('post', 'get'))) {
            trigger_error('error method', E_ERROR);
            return false;
        }
        $return = self::actionPost($url, $params);

        $value = array();
        $p = xml_parser_create();
        xml_parse_into_struct($p, $return, $value, $index);
        xml_parser_free($p);

        $result = $value[0]['value'];
        $len = strlen($result);
        $msg = 'Success';
        $status = 0;

        if ($len < 10) {
            $status = 1;
            $msg = 'Fail';
        }

        $data = array(
            'res_code' => $status,
            'res_message' => $msg,
            'idertifier' => $result,
        );

        return $data;
    }

    /**
     * @param $http_url
     * @param $postdata
     * @return mixed
     */
    private static function actionPost($http_url, $postdata)
    {
        $ch = curl_init();//初始化curl
        curl_setopt($ch, CURLOPT_URL, $http_url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postdata));
        curl_setopt($ch, CURLOPT_HTTP_VERSION, '1.1');
        $result = curl_exec($ch);
        \Neigou\Logger::General(
            'action.Code',
            array(
                'action' => 'voice',
                'tip' => '语音验证码请求手机号：' . $postdata['pszMobis'] . '，请求结果',
                'params' => $result
            )
        );

        if (curl_errno($ch)) {
            echo 'Errno' . curl_error($ch);
        }
        curl_close($ch);
        return $result;
    }
}
