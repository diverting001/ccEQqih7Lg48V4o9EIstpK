<?php
namespace App\Api\V1\Controllers\GuoMi;

use App\Api\Common\Controllers\BaseController;
use Illuminate\Http\Request;
use Rtgm\util\FormatSign;

use App\Api\V1\Service\Message\MessageLog;
require base_path() . '/vendor/PHP_GUOMI/autoload.php';
/**
 * 国密sm2 Controller
 *
 * @package     api
 * @category    Controller
 */
class Sm2Controller extends BaseController
{
    /**
     * Notes:国密sm2 签名
     * User: mazhenkang
     * Date: 2024/6/27 上午9:24
     * @param Request $request
     * @return array
     *  encryptStr 需要加密的字符串
     *  privateKey 私钥
     *  formatSign 输入输出的签名方式 16进制的还是base64
     *  randFixed 是否使用中间椭圆，使用中间椭圆的话，速度会快一些，但同样的数据的签名或加密的值就固定了
     */
    public function GetSign(Request $request)
    {
        $params = $this->getContentArray($request);

        if (empty($params['documentStr'])) {
            $this->setErrorMsg('待签名字符串不能为空');
            return $this->outputFormat(null, 400);
        }

        if (empty($params['privateKey'])) {
            $this->setErrorMsg('签名私钥不能为空');
            return $this->outputFormat(null, 401);
        }

        //签名方式 hex, base64
        $formatSign = $params['formatSign'] == 'base64' ? 'base64' : 'hex';
        $randFixed = !empty($params['randFixed']) ? true : false;

        $sm2 = new \Rtgm\sm\RtSm2($formatSign, $randFixed);
        $sign = $sm2->doSign($params['documentStr'], $params['privateKey']);

        $return = array(
            'sign' => $sign ? $sign : false
        );
        $this->setErrorMsg('success');
        return $this->outputFormat($return);
    }

    /**
     * Notes:国密sm2 验签
     * User: mazhenkang
     * Date: 2024/6/27 上午9:29
     * @param Request $request
     * @return array
     */
    public function CheckSign(Request $request)
    {
        $params = $this->getContentArray($request);
        if (empty($params['documentStr'])) {
            $this->setErrorMsg('加密字符串不能为空');
            return $this->outputFormat(null, 400);
        }

        if (empty($params['publicKey'])) {
            $this->setErrorMsg('验签公钥不能为空');
            return $this->outputFormat(null, 401);
        }

        if (empty($params['sign'])) {
            $this->setErrorMsg('延签sign不能为空');
            return $this->outputFormat(null, 402);
        }

        //签名方式 hex, base64
        $formatSign = $params['formatSign'] == 'base64' ? 'base64' : 'hex';
        $randFixed = !empty($params['randFixed']) ? true : false;

        $sm2 = new \Rtgm\sm\RtSm2($formatSign, $randFixed);
        if ( ! $sm2->verifySign($params['documentStr'], $params['sign'], $params['publicKey'])) {
            $this->setErrorMsg('签名错误');
            return $this->outputFormat('false', 4000);
        }

        $this->setErrorMsg('success');
        return $this->outputFormat('true');
    }

    /**
     * Notes:加密
     * User: mazhenkang
     * Date: 2024/6/28 上午10:51
     * @param Request $request
     * @return array
     */
    public function DoEncrypt(Request $request)
    {
        $params = $this->getContentArray($request);

        if (empty($params['documentStr'])) {
            $this->setErrorMsg('待加密字符串不能为空');
            return $this->outputFormat(null, 400);
        }

        if (empty($params['publicKey'])) {
            $this->setErrorMsg('加密公钥不能为空');
            return $this->outputFormat(null, 401);
        }

        //签名方式 hex, base64
        $formatSign = $params['formatSign'] == 'base64' ? 'base64' : 'hex';
        $randFixed = !empty($params['randFixed']) ? true : false;
        //加密数据是否04开头
        $start04 = $params['start04'] == 1 ? true : false;
        //返回数据 默认c1c3c2的hex字符串
        $outType = $params['outType'] == 'hex' ? 'hex' : 'base64';

        $sm2 = new \Rtgm\sm\RtSm2($formatSign, $randFixed);
        $doEncrypt = $sm2->doEncrypt($params['documentStr'], $params['publicKey']);
        if ($start04) {
            $doEncrypt = '04' . $doEncrypt;
        }
        //返回数据格式处理
        if ($outType == 'base64') {
            $doEncrypt = base64_encode(hex2bin($doEncrypt));
        }
        $this->setErrorMsg('success');
        //返回数据 默认c1c3c2的hex字符串
        $return = array(
            'encrypt' => $doEncrypt,
        );
        return $this->outputFormat($return);
    }

    /**
     * Notes:解密
     * User: mazhenkang
     * Date: 2024/6/27 上午10:18
     * @param Request $request
     */
    public function DoDecrypt(Request $request)
    {
        $params = $this->getContentArray($request);

        if (empty($params['documentStr'])) {
            $this->setErrorMsg('待解密字符串不能为空');
            return $this->outputFormat(null, 400);
        }

        if (empty($params['privateKey'])) {
            $this->setErrorMsg('解密公钥不能为空');
            return $this->outputFormat(null, 401);
        }

        //签名方式 hex, base64
        $formatSign = $params['formatSign'] == 'base64' ? 'base64' : 'hex';
        $randFixed = !empty($params['randFixed']) ? true : false;
        $trim04 = $params['trim04'] == 1 ? true : false;
        //返回数据 默认c1c3c2的hex字符串
        $inType = $params['inType'] == 'hex' ? 'hex' : 'base64';
        if ($inType == 'base64') {
            $params['documentStr'] = bin2hex(base64_decode($params['documentStr']));
        }
        $sm2 = new \Rtgm\sm\RtSm2($formatSign, $randFixed);
        try{
            $doEncrypt = $sm2->doDecrypt($params['documentStr'], $params['privateKey'], $trim04);
        }catch(\Exception $e){
            $this->setErrorMsg('解密异常');
            return $this->outputFormat(array(), 4000);
        }

        $this->setErrorMsg('success');
        $return = array(
            'decrypt' => $doEncrypt,
        );
        return $this->outputFormat($return);
    }
}
