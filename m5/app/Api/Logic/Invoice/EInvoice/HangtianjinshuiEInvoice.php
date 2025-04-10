<?php
/**
 * Created by PhpStorm.
 * User: liuming
 * Date: 2018/11/16
 * Time: 7:33 PM
 */

namespace App\Api\Logic\Invoice\EInvoice;

use Neigou\Curl;
use Neigou\Logger;

class HangtianjinshuiEInvoice
{
    const TYPE_NORMAL_INVOICE   = 1; //正常发票
    const TYPE_RED_INVOICE      = 2; //红冲发票

    protected $request_config = array();

    public function __construct()
    {
        $this->request_config = array(
            'domain' => config('neigou.EINVOICE_HANGTIANJINSHUI_PATH') . '?wsdl',
            'post'   => config('neigou.EINVOICE_HANGTIANJINSHUI_PATH') . ' HTTP/1.1',
            'host'   => config('neigou.EINVOICE_HANGTIANJINSHUI_HOST'),
        );
    }


    /** 提交开具发票信息(*注意 此方法并没有验证发票信息是否正确, 在下载发票信息中才验证提交的发票信息是否正确)
     *
     * @return mixed
     * @throws \Exception
     * @author liuming
     */
    public function SubmitInvoiceInfo($data = array())
    {
        try {
            $soapInfo = $this->setSoapInfo($data, 'submitEInvoiceInfo');
            $result   = $this->request($soapInfo['header'], $soapInfo['content']);

            //设置返回信息
            $msgTmp = static:: getMsgByCode($result['msgCode']);
            $msg    = empty($msgTmp) ? $result['msg'] : $msgTmp;

            if ($result['msgCode'] != 0) {
                return static:: setReturn(false, $result['msgCode'],$msg);
            } else {
                return static:: setReturn(true, $result['msgCode'],$msg);
            }
        } catch (\Exception $e) {
            return static:: setReturn(false, $e->getCode(),$e->getMessage());
        }
    }

    /** 提交开具红冲发票信息(*注意 此方法并没有验证发票信息是否正确, 在下载发票信息中才验证提交的发票信息是否正确)
     *
     * @return array
     * @throws \Exception
     * @author liuming
     */
    public function SubmitRedInvoiceInfo($data = [])
    {
        try {
            $soapInfo = $this->setSoapInfo($data, 'submitEInvoiceInfo');
            $result   = $this->request($soapInfo['header'], $soapInfo['content']);

            //设置返回信息
            $msgTmp = static:: getMsgByCode($result['msgCode']);
            $msg    = empty($msgTmp) ? $result['msg'] : $msgTmp;
            if ($result['msgCode'] != 0) {
                return static:: setReturn(false, $result['msgCode'],$msg);
            } else {
                return static:: setReturn(true, $result['msgCode'],$msg);
            }
        } catch (\Exception $e) {
            return static:: setReturn(false, $e->getCode(),$e->getMessage());
        }
    }


    /** 脚本--获取发票下载
     *
     * @return array
     * @throws \Exception
     * @author liuming
     */
    public function DownloadInvoice($params = array())
    {
        if (empty($params)){
            return static :: setReturn(false,400,'参数不能拿为空!');
        }

        try {
            $data['InvoInfo']['swno']    = $params['swno'];
            $data['InvoInfo']['saleTax'] = config('neigou.EINVOICE_HANGTIANJINSHUI_SALE_TAX');
            $soapInfo = $this->setSoapInfo($data, 'downloadEInvoiceInfo');
            $result   = $this->request($soapInfo['header'], $soapInfo['content']);

            $msgTmp = static:: getMsgByCode($result['returnMsg']['msgCode']);
            $msg    = empty($msgTmp) ? $result['returnMsg']['msg'] : $msgTmp;
            if ($result['returnMsg']['msgCode'] != 0) {
                throw new \Exception($msg,$result['returnMsg']['msgCode']);
            }
            return static :: setReturn(true,$result['returnMsg']['msgCode'],'',$result);
        } catch (\Exception $e) {
            return static :: setReturn(false,400,$e->getMessage());

        }
    }


    /** 设置soap信息(包含header头和content内容)
     *
     * @param array $data
     * @param $method
     * @return mixed
     * @throws \Exception
     * @author liuming
     */
    protected function setSoapInfo($data = array(), $method)
    {
        if (empty($data) || !is_array($data)) throw new \Exception('转换xml参数不正确',400);
        //todo 将数组转为xml
        $xml = static:: arr2xml($data);

        //todo 请求soap
        $sopa['content'] = $this->setSoapContent($xml, $method);

        //todo 获取soapHeader头信息
        $sopa['header'] = $this->setSoapHeader($sopa['content']);

        return $sopa;
    }

    /** 设置soap header头信息
     *
     * @param string $content
     * @return array
     * @author liuming
     */
    protected function setSoapHeader($content = '')
    {
        $headerArr = array(
            0 => array(
                'POST'            => $this->request_config['post'],
                'Accept-Encoding' => 'gzip,deflate',
                'Content-Type'    => 'text/xml;charset=UTF-8',
                'SOAPAction'      => '',
                'Content-Length'  => strlen($content),
                'Host'            => $this->request_config['host'],
                'Connection'      => 'Keep-Alive',
                'User-Agent'      => 'Apache-HttpClient/4.1.1 (java 1.5)',
            ),
        );

        return $headerArr;
    }

    /** 设置soap content内容信息
     *
     * @param string $content
     * @param string $method
     * @return string
     * @throws \Exception
     * @author liuming
     */
    protected function setSoapContent($content = '', $method = '')
    {
        $ser = array(
            'submitEInvoiceInfo',
            'downloadEInvoiceInfo',
            'getKPYL',
        );

        if (!in_array($method, $ser)) throw new \Exception($method . ' 方法不存在!');
        //soap头部
        $begin = '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ser="http://service.ejinshui.com/"><soapenv:Header/><soapenv:Body><ser:' . $method . '><arg0><![CDATA[';

        //soap尾部
        $end = ']]></arg0></ser:' . $method . '></soapenv:Body></soapenv:Envelope>';

        return $begin . $content . $end;
    }

    /** 向航天金税发送请求信息
     *
     * @param array $header
     * @param string $str
     * @return string
     * @throws \Exception
     * @author liuming
     */
    protected function request($header = array(), $str = '')
    {
        $curl = new Curl();
        $curl->SetHeader($header);
        $_result = $curl->Post($this->request_config['domain'], $str);

        //todo 将&lt;等实体对象转换为html标签
        $_result = htmlspecialchars_decode($_result);

        Logger::General('einvoice_hangtianjinshui', array('request_header' => $header,'domain' => $this->request_config['domain'],'httpCode' =>$curl->GetHttpCode(),'error' => $curl->GetError(),'request_xml_content' => $str, 'request' => $_result));

        $tmpArr1 = explode('<return>', $_result);
        if (!$tmpArr1[1]) throw new \Exception('请求第三方远程信息失败!',500);
        $tmpArr2 = explode('</return>', $tmpArr1[1]);
        if (!$tmpArr2[0]) throw new \Exception('请求第三方远程信息失败!',500);

        return json_decode(json_encode(simplexml_load_string($tmpArr2[0])), true);

    }


    /** 将array转xml
     *
     * @param $arr
     * @param int $dom
     * @param int $item
     * @return mixed
     * @author liuming
     */
    protected static function arr2xml2($arr, $dom = 0, $item = 0)
    {
        if (!$dom) {
            $dom = new \DOMDocument("1.0", "UTF-8");
        }
        if (!$item) {
            $root = key($arr);
            $arr  = $arr[$root];
            $item = $dom->createElement($root);
            $dom->appendChild($item);
        }
        foreach ($arr as $key => $val) {
            $itemx = $dom->createElement(is_string($key) ? $key : "item");
            $item->appendChild($itemx);
            if (!is_array($val)) {
                $text = $dom->createTextNode($val);
                $itemx->appendChild($text);
            } else {
                static:: arr2xml($val, $dom, $itemx);
            }
        }
        return $dom->saveXML();
    }

    protected static function arr2xml($arr, $dom = 0, $item = 0, $oldKey = '')
    {
        if (!$dom) {
            $dom = new \DOMDocument("1.0", "UTF-8");
        }
        if (!$item) {
            $root = key($arr);
            $arr  = $arr[$root];
            $item = $dom->createElement($root);
            $dom->appendChild($item);
        }

        foreach ($arr as $key => $val) {
            if ($val['billNo']) {
                $oldKey = 'Order';
            } else {
                $oldKey = 'item';
            }
            //echo $key;
            $itemx = $dom->createElement(is_string($key) ? $key : $oldKey);
            $item->appendChild($itemx);
            if (!is_array($val)) {
                $text = $dom->createTextNode($val);
                $itemx->appendChild($text);
            } else {
                static:: arr2xml($val, $dom, $itemx, $oldKey);
            }
        }
        return $dom->saveXML();
    }

    protected static function getMsgByCode($code = 0000)
    {
        $msgData = array(
            0000 => '发票开具数据保存成功',
            //9999 => '发票开具失败',
            1001 => '专用发票购方税号、购方地址、购方开户行和账号不能为空',
            1002 => '红字发票特殊冲红标志不能为空',
            1003 => '红字发票操作代码必须为“21”',
            1004 => '红字发票原始发票代码和原始发票号码不能为空',
            1005 => '蓝字发票冲红原因必须为空',
            1006 => '单价、数量和计数单位必须同时存在',
            1007 => '商品行误差过大',
            1008 => '红字发票备注格式不正确',
            1009 => '开票类型超出范围',
            1010 => '发票类型超出范围',
            1011 => '红字发票退货单号不能为空',
            1012 => '购方企业类型超出范围',
            1013 => '单据号不合法',
            1014 => '销方税号不能为空',
            1015 => '购方邮箱不合法',
            1016 => '流水号不合法',
            1017 => '开票员不合法',
            1018 => '税率不能为空',
            1019 => '发票数据全部下载失败',
            1020 => '发票数据部分下载成功',
            //外层协议代码错误
            9001 => '企业ERP唯一编码错误',
            9002 => '接口编码错误',
            9003 => '企业ERP唯一编码错误',
            9004 => '密码错误',
            9005 => '数据交换请求发起方代码错误',
            9006 => '数据交换请求发出时间错误',
            9007 => '数据交换流水号错误',
            9008 => '应用标识错误',
            9009 => '平台已经停用',
            9010 => '获取CA密钥证书信息异常',
            //业务层协议错误代码
            9101 => '开票类型错误',
            9102 => '操作代码错误',
            9103 => '购货方企业类型错误',
            9104 => '纳税人识别号错误',
            9105 => '纳税人名称错误',
            9106 => '重复开具发票错误',
            9107 => '代开标志错误',
            9108 => '开票项目错误',
            9109 => '购货方名错误',
            9110 => '销货方名称错误',
            9111 => '购货方纳税人名称错误',
            9112 => '开票合计金额错误',
            9113 => '开票员错误',
            9114 => '订单号错误',
            9115 => '购货方手机号错误',
            9116 => '操作代码错误',
            9117 => '原发票代码错误',
            9118 => '原发票号码错误',
            9119 => '特殊冲红标志错误',
            9120 => '冲红对应的蓝字发票错误',
            9121 => '发票请求流水号错误',
            9122 => '发票明细信息为空',
            9123 => '纳税人电子档案号码错误',
            9124 => '税务机关代码错误',
            9125 => '票样代码错误',
            9126 => '购货方纳税人识别号码错误',
            9127 => '购货方邮件错误',
            9128 => '购货方固定电话错误',
            9129 => '购货方地址错误',
            9130 => '购货方省份错误',
            9131 => '行业代码错误',
            9132 => '收款员错误',
            9133 => '冲红原因错误',
            9134 => '备注错误',
            9135 => '冲红金额过小',
            9136 => 'PDF电子发票正在生产或PDF电子发票请求数据错误',
            //明细错误代码
            9201 => '开票项目错误',
            9202 => '开票数量错误',
            9203 => '开票单价错误',
            9204 => '开票金额错误',
            //CA加解密错误代码
            1101 => '参数错误',
            1102 => '信任链错误',
            1103 => '证书错误',
            1104 => '证书验证错误（包括过期)',
            1105 => '内存错误',
            1106 => '密文错误',
            1107 => '没有初始化',
            1108 => '密钥错误',
            1109 => 'PFX文件错误',
            1110 => '加密错误',
            1111 => '解密错误',
            1112 => '签名错误',
            1113 => '验证错误',
        );

        if (isset($msgData[$code])) {
            return $msgData[$code];
        }
        return null;
    }

    public static function setReturn($status = false, $code = 0,$msg = '',$data = array())
    {
        return array('status' => $status, 'msg' => $msg, 'code' => $code, 'data' => $data);
    }

}
