<?php
/**
 * neigou_service-stock
 * @package     api
 * @author      xupeng
 * @since       Version
 * @filesource
 */

namespace App\Api\V3\Service\Sms;

/**
 * 梦网
 *
 * @package     api
 * @category    Service
 * @author        xupeng
 */
class SmsMengWang
{
    /**
     * 配置
     */
    private static $_config = array(
        'NEIGOU' => array(
            'CAPTCHA' => array(
                'url' => 'http://61.135.198.131:8023/MWGate/wmgw.asmx/MongateSendSubmit',
                'user' => 'J70162',
                'pwd' => '646546',
                'tmp_id' => '91002094',
            ),
            'NORMAL' => array(
                'url' => 'http://61.145.229.28:7902/MWGate/wmgw.asmx/MongateSendSubmit',
                'user' => 'js1632',
                'pwd' => '565287',
                'tmp_id' => '91002094',
            ),
        ),
        'DIANDI' => array(
            'CAPTCHA' => array(
                'url' => 'http://61.135.198.131:8023/MWGate/wmgw.asmx/MongateSendSubmit',
                'user' => 'J71557',
                'pwd' => '269852',
                'tmp_id' => '91002094',
            ),
            'NORMAL' => array(
                'url' => 'http://61.145.229.28:7902/MWGate/wmgw.asmx/MongateSendSubmit',
                'user' => 'JS5103',
                'pwd' => '395620',
                'tmp_id' => '91002094',
            ),
        ),

    );

    /**
     * 基础配置
     */
    private static $_baseConfig = array(
        'ecos.leho.sms189.appid' => '392135610000043184',
        'ecos.leho.sms189.template_id' => '91005518',
        'ecos.leho.sms189.appsecret' => '69dccb6d5fc6264375b7ca13fea5e282',
        'sms189.app.leho.api.test.status' => 'false',
        'sms189.app.leho.api.test.value' => 'LEHO_YES_API_TEST',
        'ecos.leho.sms189.tokeninfo' => '',
    );

    /**
     * 获取物流详情
     *
     * @param   $mobile     string  手机号
     * @param   $content    string  内容
     * @param   $com        string  公司
     * @param   $type       string  类型
     * @param   $errMsg     string  错误信息
     * @return  boolean
     */
    public function send($mobile, $content, $com, $type, & $errMsg = '')
    {
        if (!isset(self::$_config[$com][$type])) {
            $errMsg = '配置错误';
            return false;
        }

        if ($type !== 'CAPTCHA' && strpos($content, '回复TD退订') === false) {
            $content .= '回复TD退订';
        }

        $config = self::$_config[$com][$type];

        $data = array(
            'userId' => $config['user'],
            'password' => $config['pwd'],
            'pszMobis' => $mobile,
            'pszMsg' => $content,
            'iMobiCount' => 1,
            'pszSubPort' => '*',
            'MsgId' => '123456',
        );

        $data['sign'] = self::_createSign($data);

        $result = self::_request($config['url'], $data);

        if ($result === false) {
            return false;
        }

        return $result;
    }

    // --------------------------------------------------------------------

    /**
     * 生成签名
     *
     * @param   $params     array   参数
     * @return  string
     */
    private static function _createSign($params)
    {
        ksort($params);
        $string = '';
        foreach ($params as $k => $v) {
            $string .= $k . '=' . $v . '&';
        }

        $string = substr($string, 0, -1);

        $appSecret = self::$_baseConfig['ecos.leho.sms189.appsecret'];
        if (function_exists('hash_hmac')) {
            $signature = base64_encode(hash_hmac("sha1", $string, $appSecret, true));
        } else {
            $blocksize = 64;
            $hashfunc = 'sha1';
            if (strlen($appSecret) > $blocksize) {
                $appSecret = pack('H*', $hashfunc($appSecret));
            }
            $appSecret = str_pad($appSecret, $blocksize, chr(0x00));
            $ipad = str_repeat(chr(0x36), $blocksize);
            $opad = str_repeat(chr(0x5c), $blocksize);
            $hmac = pack(
                'H*', $hashfunc(
                    ($appSecret ^ $opad) . pack(
                        'H*', $hashfunc(
                            ($appSecret ^ $ipad) . $string
                        )
                    )
                )
            );
            $signature = base64_encode($hmac);
        }

        return $signature;
    }

    // --------------------------------------------------------------------

    /**
     * 请求接口
     *
     * @param   $url        string  请求地址
     * @param   $params     array   请求数据
     * @return string
     */
    private static function _request($url, $params)
    {
        $ch = curl_init();//初始化curl
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch, CURLOPT_HTTP_VERSION, '1.1');
        $result = curl_exec($ch);

        $value = array();
        $p = xml_parser_create();
        xml_parse_into_struct($p, $result, $value, $index);
        xml_parser_free($p);
        $result = $value[0]['value'];
        $len = strlen($result);

        if ($len < 10) {
            return false;
        }

        return $result;
    }

}
