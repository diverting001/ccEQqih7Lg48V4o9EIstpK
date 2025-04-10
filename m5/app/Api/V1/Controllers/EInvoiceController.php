<?php
/** 航天金税获取电子发票接口
 *
 * Created by PhpStorm.
 * User: liuming
 * Date: 2018/11/13
 * Time: 3:22 PM
 */

namespace App\Api\V1\Controllers;

use App\Api\Logic\Invoice\ElectronicInvoice;
use Mockery\Exception;
use App\Api\Common\Controllers\BaseController;
use Illuminate\Http\Request;
use Neigou\Logger;

class EInvoiceController extends BaseController
{
    const TYPE_NORMAL_INVOICE   = 1; //正常发票
    const TYPE_RED_INVOICE      = 2; //红冲发票
    const OPERAT_RED_IONCODE    = 21; //红冲操作代码
    const OPERAT_NORMAL_IONCODE = 10; //正常操作代码

    const BIGGEST_PRODUCT_NUM = 999; //发票最大商品数量
    const BIGGEST_MONEY = 110000;    //发票最大金额


    const PRODUCT_LINE_TYPE = 0;//发票行性质
    const PRODUCT_YHZCBS    = 0; //税收优惠政策标志 0：不使用 1：使用
    const PRODUCT_YHZCNR    = '';//享受税收优惠政策内容
    const PRODUCT_LSLBS     = '';//零税率标识
    const PRODUCT_ZXBM      = '';//自行编码
    const PRODUCT_KCE       = '';//扣除额


    protected $invoice_config = array();
    //测试需要加上商品名称
    const PRODUCT_NAME = '';


    public function __construct()
    {
        //parent::__construct();

        $this->invoice_config = array(
            'saleTax' => config('neigou.EINVOICE_HANGTIANJINSHUI_SALE_TAX'),
            'invType' => '3', //发票类型(固定)
            'kpy'     => config('neigou.EINVOICE_HANGTIANJINSHUI_KPY'), //开票人, 如果传此参数值会被覆盖
            'sky'     => config('neigou.EINVOICE_HANGTIANJINSHUI_SKY'), //收款人, 如果传此参数值会被覆盖
            'fhr'     => config('neigou.EINVOICE_HANGTIANJINSHUI_FHR'), //复核人, 如果传此参数值会被覆盖
        );
    }


    /** 提交开具发票信息(*注意 此方法并没有验证发票信息是否正确, 在下载发票信息中才验证提交的发票信息是否正确)
     *
     * @return array
     * @throws \Exception
     * @author liuming
     */
    public function SubmitInvoiceInfo(Request $request)
    {
        $params = $this->getContentArray($request);
        return $this->RealSubmitBlueInvoice($params);
    }


    /** 正式提交蓝票信息
     *
     * @param array $params
     * @return array'
     */
    protected function RealSubmitBlueInvoice($params = array(),$isRetry = false){
        try{
            $data['InvoInfo']                  = $this->setSubmitInvoiceData($params, self::TYPE_NORMAL_INVOICE);
            $data['InvoInfo']['billType']      = self::TYPE_NORMAL_INVOICE; //开票类型
            $data['InvoInfo']['operationCode'] = self::OPERAT_NORMAL_IONCODE; //操作代码

            $einvoiceLogic = new ElectronicInvoice();
            $logicRes = $einvoiceLogic->SubmitInvoiceInfo($data,$isRetry);
            $this->setErrorMsg($logicRes['msg']);

            if ($logicRes['status'] != true){
                return $this->outputFormat($logicRes['data'], $logicRes['code']);
            }

            return $this->outputFormat($logicRes['data'], 0);
        }catch (\Exception $exception){
            $code = !empty($exception->getCode()) ? $exception->getCode() : 400;
            $this->setErrorMsg($exception->getMessage());
            return $this->outputFormat([], $code);
        }
    }

    /** 提交开具红冲发票信息(*注意 此方法并没有验证发票信息是否正确, 在下载发票信息中才验证提交的发票信息是否正确)
     *
     * @return array
     * @throws \Exception
     * @author liuming
     */
    public function SubmitRedInvoiceInfo(Request $request)
    {
        $params = $this->getContentArray($request);
        return $this->RealSubmitRedInvoice($params);

    }

    /** 正式提交红票信息
     *
     * @param array $params
     * @return array
     */
    protected function RealSubmitRedInvoice($params = array(),$isRetry = false){
        try{
            $data['InvoInfo']                  = $this->setSubmitInvoiceData($params, self::TYPE_RED_INVOICE);
            $data['InvoInfo']['billType']      = self::TYPE_RED_INVOICE; //开票类型
            $data['InvoInfo']['operationCode'] = self::OPERAT_RED_IONCODE; //操作代码
            $einvoiceLogic = new ElectronicInvoice();
            $logicRes = $einvoiceLogic->SubmitRedInvoiceInfo($data,$isRetry);
            $this->setErrorMsg($logicRes['msg']);
            if ($logicRes['status'] != true){
                return $this->outputFormat($logicRes['data'], $logicRes['code']);
            }
            return $this->outputFormat($logicRes['data'], 0);
        }catch (\Exception $exception){
            $code = !empty($exception->getCode()) ? $exception->getCode() : 400;
            $this->setErrorMsg($exception->getMessage());
            return $this->outputFormat([], $code);
        }
    }

    /** 获取发票下载信息
     *
     * @return array
     * @throws \Exception
     * @author liuming
     */
    public function GetDownloadInvoiceInfo(Request $request)
    {
        $params = $this->getContentArray($request);
        $swnoList = explode(',',$params['swno']);
        if (!$swnoList) {
            $this->setErrorMsg('swno不能为空');
            return $this->outputFormat([], 400);
        }
        $einvoiceLogic = new ElectronicInvoice();
        $logicRes = $einvoiceLogic->GetDownloadInvoiceInfo($swnoList);

        $this->setErrorMsg($logicRes['msg']);
        if ($logicRes['status'] != true){
            return $this->outputFormat($logicRes['data'], $logicRes['code']);
        }
        return $this->outputFormat($logicRes['data'], 0);
    }

    /** 设置开发数据
     *
     * @param array $data
     * @param int $type
     * @return array
     * @throws \Exception
     * @author liuming
     */
    protected function setSubmitInvoiceData($data = array(), $type = 1)
    {
        $invoiceData = array();
        //发票必要参数
        $mustKey = array(
            'swno', 'company_name', 'company_type', 'order_items',
        );
        //红冲发票附加必要参数
        $mustRedKey     = array(
            'thdh', 'yfpdm', 'yfphm', 'chyy',
        );
        $mustOrderKey   = array(
            'order_id',
        );
        $mustProductKey = array(
            'nums', 'amount', 'product_bn', 'price',
        );
        //可选发票信息
        $optionalKey = array(
            'cust_tax_no' => 'custTaxNo', 'cust_phone' => 'custPhone', 'cust_email' => 'custEmail', 'cust_bank_account' => 'custBankAccount', 'invo_memo' => 'invoMemo', 'company_addr' => 'custAddr', 'cust_telephone' => 'custTelephone',
        );

        //todo 参数检查
        $dataKey = array_keys($data);
        if ($type == self::TYPE_RED_INVOICE) {
            $mustKey = array_merge($mustKey, $mustRedKey);
        }
        //$allKey = array_merge($mustKey,$optionalKey);
        $intersectArr = array_intersect($mustKey, $dataKey);
        if (count($mustKey) !== count($intersectArr)) {
            $diffkey = array_diff($mustKey, $dataKey);
            $diffStr = implode(',', $diffkey);
            throw new \Exception('缺少请求参数: ' . $diffStr);
        }

        //todo 设置发票数据
        $orderItems = $data['order_items'];
        unset($data['order_items']);
        $invoiceData['swno']     = $data['swno'];
        $invoiceData['custName'] = $data['company_name'];
        $invoiceData['custType'] = $data['company_type'];
        $invoiceData['custAddr'] = $data['company_addr'];

        //todo 测试用
        //$invoiceData['specialRedFlag'] = 0;

        $productTotalMoney = 0.00;
        //设置订单和商品信息
        foreach ($orderItems as $orderK => $orderV) {

            //设置订单信息
            if (!$orderV['order_id']) throw new \Exception('order_id字段不能为空!');
            $orderInvoice[$orderK]['billNo'] = $orderV['order_id'];
            //商品相关数据

            //判断商品数量, 如果商品数量大于999个, 将返回错误
            if (count($orderV['product_items']) >= self::BIGGEST_PRODUCT_NUM) throw new \Exception('开票商品数量不能超过: '.self::BIGGEST_PRODUCT_NUM.'件!',400);
            foreach ($orderV['product_items'] as $productK => $productV) {
                if (!is_array($productV)) throw new \Exception('orderInfo字段值必须是数组!');
                foreach ($mustProductKey as $v1) {
                    if (empty($productV[$v1])) throw new \Exception('产品信息缺少请求字段: ' . $v1);
                }

                //如果为红冲, 将商品数量和总价设置为负数
                if ($type == self::TYPE_RED_INVOICE && $productV['nums'] > 0) {
                    $productV['nums']   *= -1;
                    $productV['amount'] *= -1;
                }

                $productTotalMoney +=  $productV['amount'];
                if ($productTotalMoney >= self::BIGGEST_MONEY) throw new \Exception('开票金额不能大于'.self::BIGGEST_MONEY.'元!',400);

                //设置产品信息
                $productInvoice [$productK]     = array(
                    'code'        => $productV['product_bn'], //商品id
                    'quantity'    => $productV['nums'], //数量
                    'taxPrice'    => $productV['price'], //单价
                    'totalAmount' => $productV['amount'], //含税总金额
                    'lineType'    => self::PRODUCT_LINE_TYPE, //发票行性质
                    'yhzcbs'      => self::PRODUCT_YHZCBS, //税收优惠政策标志
                    'yhzcnr'      => self::PRODUCT_YHZCNR, //税收优惠政策内容
                    'lslbs'       => self::PRODUCT_LSLBS, //预留字段
                    'zxbm'        => self::PRODUCT_ZXBM, //预留字段
                    'kce'         => self::PRODUCT_KCE, //预留字段
                    'name'        => self::PRODUCT_NAME, //商品名称
                    //todo 测试用
                    //'taxRate' => 0.16, //商品名称
                );
                $orderInvoice[$orderK]['Items'] = $productInvoice;
            }
        }

        //todo 设置可选参数默认值
        foreach ($optionalKey as $k => $v) {
            if (!empty($data[$k])) {
                $invoiceData[$v] = $data[$k];
            } else {
                $invoiceData[$v] = $data[$v];
            }
        }
        //$invoiceData = array_merge($invoiceData,self::$INVOICE_CONFIG);
        foreach ($this->invoice_config as $k => $v) {
            //if ($k == 'saleTax') continue;
            $invoiceData[$k] = $data[$k] ?? $v;
        }


        //todo 设置红冲inv
        if ($type == self::TYPE_RED_INVOICE) {
            //设置发票基本信息
            $invoiceData['thdh']     = $data['thdh']; //退款原因
            $invoiceData['yfpdm']    = $data['yfpdm'];//发票原始代码
            $invoiceData['yfphm']    = $data['yfphm'];//发票原始代码
            $invoiceData['chyy']     = $data['chyy']; //冲红原因
            $invoiceData['invoMemo'] = '对应正数发票代码:' . $invoiceData['yfpdm'] . '号码:' . $invoiceData['yfphm'];
        }

        $invoiceData['billDate'] = date('Y-m-d H:i:s', time()); //单据日期
        $invoiceData['Orders']   = $orderInvoice;
        return $invoiceData;
    }

    /** 简版开红票
     *
     * @return array
     */
    public function SimpleSubmitRedInvoiceInfo(Request $request){
        $params = $this->getContentArray($request);
        if (!$params['thdh'] || !$params['chyy']){
            $this->setErrorMsg('thdh或chyy不能为空');
            return $this->outputFormat('',400);
        }

        $logic = new ElectronicInvoice();
        $logicRes = $logic->SimpleSubmitRedInvoiceInfo(array('swno' => $params['blue_swno']));
        if ($logicRes['status'] != true){
            $this->setErrorMsg($logicRes['msg']);
            return $this->outputFormat($logicRes['data'], $logicRes['code']);
        }

        $logicRes['data']['swno'] = $params['swno'];
        $logicRes['data']['thdh'] = $params['thdh'];
        $logicRes['data']['chyy'] = $params['chyy'];

        return $this->RealSubmitRedInvoice($logicRes['data']);

    }

    /** 重新提交发票信息
     *
     */
    public function RetrySubmitEinvoiceInfo(Request $request){
        $params = $this->getContentArray($request);

        $einvoiceLogic = new ElectronicInvoice();

        if (empty($params['swno'])){
            $this->setErrorMsg('swno不能为空');
            return $this->outputFormat('',400);
        }

        foreach ($params['swno'] as $swnoV){
            //todo 1,获取原始发票信息. 2,重新提交数据
            $einvoiceBase = $einvoiceLogic->GetEinvoiceOne(array('swno' => $swnoV));

            if (empty($einvoiceBase)){
                $this->setErrorMsg('流水号['.$swnoV.']没有该发票信息');
                continue;
            }


            //获取之前提交的发票信息数据
            $einvoiceSubmitData = $einvoiceLogic->getEinvoiceInfoByEinvoiceId($einvoiceBase['id']);
            $einvoiceSubmitData['swno'] = $swnoV;

            //判断开蓝票还是红票
            if ($einvoiceBase['type'] == self::TYPE_NORMAL_INVOICE){//蓝票
                $this->RealSubmitBlueInvoice($einvoiceSubmitData,true);

            }else{//红票
                $this->RealSubmitRedInvoice($einvoiceSubmitData,true);
            }
        }

        return $this->outputFormat('',0);
    }


}
