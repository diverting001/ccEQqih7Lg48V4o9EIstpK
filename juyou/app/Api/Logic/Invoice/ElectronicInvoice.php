<?php
/**
 * Created by PhpStorm.
 * User: liuming
 * Date: 2018/11/16
 * Time: 7:33 PM
 */

namespace App\Api\Logic\Invoice;

use App\Api\Logic\Invoice\EInvoice\HangtianjinshuiEInvoice AS ThirdEInvoiceLogic;
use App\Api\Model\Invoice\EInvoice;
use OSS\OssClient;

class ElectronicInvoice
{
    const TYPE_NORMAL_INVOICE = 1; //正常发票
    const TYPE_RED_INVOICE = 2; //红冲发票

    /** 提交开具发票信息(*注意 此方法并没有验证发票信息是否正确, 在下载发票信息中才验证提交的发票信息是否正确)
     *
     * @param array $data
     * @param bool $isRetry 是否重试(如果重试提交, 那么不向数据库插入重复数据)
     * @return array
     */
    public function SubmitInvoiceInfo($data = array(), $isRetry = false)
    {
        $einvoiceModel = new EInvoice();
        if (false === $isRetry) {
            $modelRes = $einvoiceModel->getEInvoiceOne(array('swno' => $data['InvoInfo']['swno']));
            if ($modelRes) {
                return static:: setReturn(false, 400, $data['InvoInfo']['swno'] . '的流水号已存在!');
            }
            $modelRes = $einvoiceModel->createEInvoice($data['InvoInfo'], self::TYPE_NORMAL_INVOICE);
            if (!$modelRes) {
                return static:: setReturn(false, 500, $data['InvoInfo']['swno'] . '流水号发票录入数据库失败!');
            }

        }
        $thirdEInvoiceLogic = new ThirdEInvoiceLogic();
        $thirdRes = $thirdEInvoiceLogic->SubmitRedInvoiceInfo($data);

        if ($thirdRes['status'] == true && $isRetry) {
            $einvoiceModel->updateEInvoice(array('status' => 0, 'frequency' => 0),
                array('swno' => $data['InvoInfo']['swno']));
        }

        return $thirdRes;
    }

    /** 提交开具红冲发票信息(*注意 此方法并没有验证发票信息是否正确, 在下载发票信息中才验证提交的发票信息是否正确)
     *
     * @param array $data
     * @param bool $isRetry 是否重试(如果重试提交, 那么不向数据库插入重复数据)
     * @return array
     */
    public function SubmitRedInvoiceInfo($data = [], $isRetry = false)
    {
        $einvoiceModel = new EInvoice();
        if (false === $isRetry) {
            $modelRes = $einvoiceModel->getEInvoiceOne(array('swno' => $data['InvoInfo']['swno']));
            if ($modelRes) {
                return static:: setReturn(false, 400, $data['InvoInfo']['swno'] . '的流水号已存在!');
            }
            $modelRes = $einvoiceModel->createEInvoice($data['InvoInfo'], self::TYPE_RED_INVOICE);
            if (!$modelRes) {
                return static:: setReturn(false, 400, $data['InvoInfo']['swno'] . '流水号发票录入数据库失败!');
            }
        }

        $thirdEInvoiceLogic = new ThirdEInvoiceLogic();
        $thirdRes = $thirdEInvoiceLogic->SubmitRedInvoiceInfo($data);

        if ($thirdRes['status'] == true && $isRetry) {
            $einvoiceModel->updateEInvoice(array('status' => 0, 'frequency' => 0),
                array('swno' => $data['InvoInfo']['swno']));
        }

        return $thirdRes;

    }


    /** 获取发票下载信息
     *
     * @return array
     * @throws \Exception
     * @author liuming
     */
    public function GetDownloadInvoiceInfo($swnoList)
    {
        try {
            $einvoiceModel = new EInvoice();
            $modelRes = $einvoiceModel->getEInvoiceDown($swnoList);
            if ($modelRes) {
                return static:: setReturn(true, 0, '开票成功', $modelRes);
            } else {
                $modelRes = $einvoiceModel->getEInvoiceOne(['swno' => $swnoList]);
                if ($modelRes->remark) {
                    throw new \Exception($modelRes->remark, 400);
                }
                throw new \Exception('发票信息获取失败', 500);
            }
        } catch (\Exception $e) {
            return static:: setReturn(false, $e->getCode(), $e->getMessage());
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
        if (empty($params)) {
            return static:: setReturn(false, 400, '参数不能拿为空!');
        }

        $einvoiceModel = new EInvoice();
        try {

            $thirdEInvoiceLogic = new ThirdEInvoiceLogic();
            $thirdLogicRes = $thirdEInvoiceLogic->DownloadInvoice($params);

            if ($thirdLogicRes['code'] != 0) {
                if (!$params['frequency']) { //代表第一次处理失败
                    $upData = array('remark' => $thirdLogicRes['msg'], 'frequency' => $params['frequency'] + 1);
                } else {
                    $upData = array('status' => 2, 'remark' => $thirdLogicRes['msg']);
                }
                $modelRes = $einvoiceModel->updateEInvoice($upData, array('id' => $params['id']));
            } else {
                //todo 发票下载成功, 1 更新发票基本信息表状态, 2,插入下载发票表信息
                //处理成功插入表
                unset($thirdLogicRes['data']['returnMsg']);
                $invoiceInsertData['data'] = array('status' => 1);
                $invoiceInsertData['where'] = array('id' => $params['id']);
                $modelRes = $this->updateEinvoiceAndDownInfo($invoiceInsertData, $thirdLogicRes);
            }
            if (!$modelRes || $thirdLogicRes['code'] != 0) {
                throw new \Exception('获取发票存储信息失败,稍后重试!', 500);
            }
            return static:: setReturn(true);
        } catch (\Exception $e) {
            return static:: setReturn(false, 400, $e->getMessage());

        }
    }


    /** 简版提交红票
     *
     * @param array $params = array('swno' => '蓝票流水号')
     * @return array
     */
    public function SimpleSubmitRedInvoiceInfo($params = array())
    {
        if (empty($params['swno'])) {
            return static:: setReturn(false, 400, '参数不能拿为空!');
        }

        try {
            $einvoiceRes = $this->GetEinvoiceOne(array('swno' => $params['swno'], 'type' => self::TYPE_NORMAL_INVOICE));
            if (!$einvoiceRes) {
                throw new \Exception('流水号' . $params['swno'] . '不存在');
            }

            $downData = $this->getEinvoiceDownInfoByEinvoiceId($einvoiceRes['id']);
            $blueData = $this->getEinvoiceInfoByEinvoiceId($einvoiceRes['id']);

            $data = $this->setSimpleRedEinvoiceData($downData, $blueData);
            return static:: setReturn(true, 0, '', $data);

        } catch (\Exception $e) {
            return static:: setReturn(false, $e->getCode(), $e->getMessage());
        }
    }

    /** 获取发票状态信息
     *
     * @param array $where
     * @return array|bool
     */
    public function GetEinvoiceOne($where = array())
    {
        $einvoiceModel = new EInvoice();
        $einvoiceRes = $einvoiceModel->getEInvoiceOne($where);
        if (!$einvoiceRes) {
            return false;
        }
        return get_object_vars($einvoiceRes);
    }


    /** 获取下载到的发票信息
     *
     * @return bool
     * @throws \Exception
     */
    protected function getEinvoiceDownInfoByEinvoiceId($einvoiceId)
    {
        $einvoiceModel = new EInvoice();
        //获取原始发票信息
        $oldEinvoiceData = $einvoiceModel->getEInvoiceDownInfo(array('einvoice_id' => $einvoiceId));
        if (!$oldEinvoiceData) {
            throw new \Exception('原始发票下载信息不存在', 400);

        }
        return $oldEinvoiceData;
    }

    /** 设置简版红票提交数据
     *
     * @param array $downEinvoiceData
     * @param array $blueEinvoiceData
     * @return array
     */
    protected function setSimpleRedEinvoiceData($downEinvoiceData = array(), $einvoiceData = array())
    {
        $einvoiceData['yfpdm'] = $downEinvoiceData[0]['fpdm'];
        $einvoiceData['yfphm'] = $downEinvoiceData[0]['fphm'];
        return $einvoiceData;
    }

    /** 获取蓝票信息
     *
     * @param int $einvoiceId
     * @return array
     * @throws \Exception
     */
    public function getEinvoiceInfoByEinvoiceId($einvoiceId = 0)
    {

        $einvoiceModel = new EInvoice();
        $einvoiceBaseInfo = $einvoiceModel->getSubmitBaseInfo(array('einvoice_id' => $einvoiceId));

        if (empty($einvoiceBaseInfo)) {
            throw new \Exception('对应的提交基本信息不存在', 500);
        }
        $einvoiceBaseData = get_object_vars($einvoiceBaseInfo[0]);
        $eivoiceProductInfo = $einvoiceModel->getSubmitProductInfo(array('submit_id' => $einvoiceBaseData['id']));

        unset($einvoiceBaseData['id'], $einvoiceBaseData['swno']);
        $orderId = $einvoiceBaseData['order_id'];
        $einvoiceBaseData['order_items'][0]['order_id'] = $orderId;
        $einvoiceBaseData['order_items'][0]['product_items'] = $eivoiceProductInfo;
        if (strlen($einvoiceBaseData['company_type']) == 1) {
            $einvoiceBaseData['company_type'] = '0' . $einvoiceBaseData['company_type'];
        }
        return $einvoiceBaseData;
    }


    /** 更新发票基本状态和添加发票下载信息
     *
     * @param array $invoiceInfo
     * @param array $downInfo
     * @return bool
     * @throws \OSS\Core\OssException
     * @author liuming
     */
    protected function updateEinvoiceAndDownInfo($invoiceInfo = array(), $downInfo = array())
    {
        $content = base64_decode($downInfo['data']['pdfContent']);
        unset($downInfo['data']['pdfContent']);
        //todo 预留下载地址
        $downInfo['data']['pdf_path'] = $this->upLoadFile($content, $downInfo['data']['fphm']);
        $downInfo['data']['einvoice_id'] = $invoiceInfo['where']['id'];
        $einvoiceModel = new EInvoice();
        return $einvoiceModel->UpdateEinvoiceAndDownInfo($invoiceInfo, $downInfo);
    }

    /** 上传到阿里云
     *
     * @param $file
     * @param $name
     * @return bool
     * @throws \OSS\Core\OssException
     * @author liuming
     */
    protected function upLoadFile($file, $name)
    {
        $ossClient = new OssClient(config('neigou.OSS_ASSESS_KEY_ID'), config('neigou.OSS_ACCESS_KEY_SECRET'), config('neigou.OSS_ENDPOINT'));
        $orgPath = 'einvoice/' . date('Ymd', time()) . '/' . $name;
        $res = $ossClient->putObject(config('neigou.OSS_BUCKET'), $orgPath . '.pdf', $file);
        return $res['info']['url'];

    }

    public static function setReturn($status = false, $code = 0, $msg = '', $data = array())
    {
        return array('status' => $status, 'msg' => $msg, 'code' => $code, 'data' => $data);
    }

}
