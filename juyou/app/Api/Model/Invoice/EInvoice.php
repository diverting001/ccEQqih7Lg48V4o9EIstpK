<?php
/**
 * Created by PhpStorm.
 * User: liuming
 * Date: 2018/11/15
 * Time: 6:34 PM
 */

namespace App\Api\Model\Invoice;


use Illuminate\Support\Facades\Schema;
use Mockery\Exception;
use Neigou\Logger;

class EInvoice
{
    const TYPE_NORMAL_INVOICE = 1; //正常发票
    const TYPE_RED_INVOICE = 2; //红冲发票
    private $server_einvoice = 'server_einvoice';
    private $server_einvoice_submit = 'server_einvoice_submit';
    private $server_einvoice_submit_product = 'server_einvoice_submit_product';
    private $server_einvoice_down = 'server_einvoice_down';
    private $_db;

    public function __construct($db = '')
    {
        $this->_db = app('api_db');
    }

    /** 获取单个发票信息
     *
     * @param $whereArr
     * @return bool
     * @author liuming
     */
    public function getEInvoiceOne($whereArr)
    {
        if (!$whereArr) {
            return false;
        }
        return $this->_db->table($this->server_einvoice)->where($whereArr)->first();
    }

    /** 获取所有发票信息
     *
     * @return mixed
     * @author liuming
     */
    public function getEInoiceAll($whereArr = array())
    {
        return $this->_db->table($this->server_einvoice)->where($whereArr)->get()->toArray();
    }

    /** 更新发票信息
     *
     * @param $data
     * @param array $whereArr
     * @return bool
     * @author liuming
     */
    public function updateEInvoice($data, $whereArr = array())
    {
        if (!$whereArr) {
            return false;
        }
        return $this->_db->table($this->server_einvoice)->where($whereArr)->update($data);
    }

    /** 创建发票信息
     *
     * @param array $data
     * @param int $type
     * @return bool
     * @author liuming
     */
    public function createEInvoice($data = array(), $type = 1)
    {
        try {
            $this->_db->beginTransaction();
            //todo 插如表数据server_einvoice,server_einvoice_submit,server_einvoice_submit_product
            $orderData = $data['Orders'][0];
            unset($data['Orders']);
            //先插入server_einvoice
            $baseData = array(
                'swno' => $data['swno'],
                'type' => $type,
                'status' => 0,
            );
            $einvoiceId = $this->_db->table($this->server_einvoice)->insertGetId($baseData);
            //
            if (!$einvoiceId) {
                throw new Exception(json_encode($baseData));
            }
            //插入server_einvoice_submit数据
            $submitData = array(
                'einvoice_id' => $einvoiceId,
                'swno' => $data['swno'],
                'company_name' => $data['custName'],
                'company_type' => $data['custType'],
                'cust_tax_no' => $data['custTaxNo'],
                'cust_phone' => $data['custPhone'],
                'cust_email' => $data['custEmail'],
                'cust_bank_account' => $data['custBankAccount'],
                'cust_addr' => $data['custAddr'],
                'remark' => $data['invoMemo'],
                'sale_tax' => $data['saleTax'],
                'kpy' => $data['kpy'],
                'sky' => $data['sky'],
                'fhr' => $data['fhr'],
                'thdh' => $data['thdh'],
                'yfpdm' => $data['yfpdm'],
                'yfphm' => $data['yfphm'],
                'chyy' => $data['chyy'],
                'bill_date' => $data['billDate'],
                'bill_type' => $data['bill_type'],
                'operation_code' => $data['operationCode'],
                'order_id' => $orderData['billNo'],
            );
            $submitId = $this->_db->table($this->server_einvoice_submit)->insertGetId($submitData);
            if (!$submitId) {
                throw new Exception(json_encode($submitData));
            }
            //插入server_einvoice_submit_product
            $this->_db->enableQueryLog();
            foreach ($orderData['Items'] as $product) {
                $productData['product_bn'] = $product['code'];
                $productData['einvoice_id'] = $einvoiceId;
                $productData['submit_id'] = $submitId;
                $productData['swno'] = $data['swno'];
                $productData['price'] = $product['taxPrice'];
                $productData['nums'] = $product['quantity'];
                $productData['amount'] = $product['totalAmount'];
                $productId = $this->_db->table($this->server_einvoice_submit_product)->insertGetId($productData);
                if (!$productId) {
                    throw new Exception(json_encode($productData));
                }
            }
            $this->_db->commit();
            return true;
        } catch (\Exception $exception) {
            Logger::General('service.einvoice.create',
                array('remark' => '添加信息失败', 'data' => json_decode($exception->getMessage(), true)));
            $this->_db->rollback();
            return false;
        }
    }

    /** 创建发票信息表
     *
     * @param array $data
     * @return bool
     * @author liuming
     */
    public function createEInvoiceDown($data = array())
    {
        if (!$data) {
            return false;
        }
        $colums = Schema::getColumnListing($this->server_einvoice_down);
        foreach ($colums as $v) {
            $insertData[$v] = $data[$v];
        }
        $insertData = array_filter($insertData);
        return $this->_db->table($this->server_einvoice_down)->insertGetId($insertData);
    }

    /** 更新发票信息表状态和添加下载文件
     *
     * @param array $invoice
     * @param array $down
     * @return bool
     * @author liuming
     */
    public function UpdateEinvoiceAndDownInfo($invoice = array(), $down = array())
    {
        if (empty($invoice) || empty($down)) {
            return false;
        }
        $this->_db->beginTransaction();
        $invoiceRes = $this->updateEInvoice($invoice['data'], $invoice['where']);
        if (!$invoiceRes) {
            $this->_db->rollback();
            return false;
        }

        $downRes = $this->createEInvoiceDown($down['data']);
        if (!$downRes) {
            $this->_db->rollback();
            return false;
        }
        $this->_db->commit();
        return true;
    }


    /** 获取下载发票信息
     *
     * @param array $whereArr
     * @return bool
     * @author liuming
     */
    public function getEInvoiceDown($whereArr = array())
    {
        if (!$whereArr) {
            return false;
        }
        $res = $this->_db->table($this->server_einvoice_down)->whereIn('swno', $whereArr)->get();
        $res = $res->map(function ($item) {
            $item = (array)$item;
            unset($item['id'], $item['einvoice_id'], $item['pdf_url']);
            return $item;
        });
        return $res->toArray();
    }

    /** 获取发票基本信息
     *
     * @param array $whereArr
     * @return bool
     */
    public function getSubmitBaseInfo($whereArr = array())
    {
        if (!$whereArr) {
            return false;
        }
        return $this->_db->table($this->server_einvoice_submit)->select('swno', 'id', 'swno', 'company_name',
            'company_type', 'cust_tax_no', 'cust_bank_account', 'cust_phone', 'cust_addr as company_addr', 'cust_email',
            'order_id', 'yfpdm', 'yfphm', 'chyy', 'thdh')->where($whereArr)->get()->toArray();
    }

    /** 获取发票产品信息
     *
     * @param array $whereArr
     * @return bool
     */
    public function getSubmitProductInfo($whereArr = array())
    {
        if (!$whereArr) {
            return false;
        }
        $res = $this->_db->table($this->server_einvoice_submit_product)->where($whereArr)->get();
        $res = $res->map(function ($item) {
            $item = (array)$item;
            unset($item['id'], $item['einvoice_id'], $item['submit_id'], $item['swno']);
            return $item;
        });
        return $res->toArray();
    }

    public function getEInvoiceDownInfo($whereArr = array())
    {
        if (!$whereArr) {
            return false;
        }
        $res = $this->_db->table($this->server_einvoice_down)->where($whereArr)->get();
        $res = $res->map(function ($item) {
            $item = (array)$item;
            return $item;
        });
        return $res->toArray();
    }

}
