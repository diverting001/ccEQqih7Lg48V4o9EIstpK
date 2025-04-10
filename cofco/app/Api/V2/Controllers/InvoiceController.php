<?php
/**
 * neigou_service-stock
 * @package     api
 * @author      xupeng
 * @since       Version
 * @filesource
 */

namespace App\Api\V2\Controllers;

use App\Api\Common\Controllers\BaseController;
use App\Api\Logic\Invoice\V2\Invoice as InvoiceLogic;
use App\Api\Model\Invoice\V2\Invoice as InvoiceModel;
use Illuminate\Http\Request;

/**
 * 发票V2 Controller
 *
 * @package     api
 * @category    Controller
 * @author        xupeng
 */
class InvoiceController extends BaseController
{
    /**
     * 发票申请
     *
     * @return string
     */
    public function Apply(Request $request)
    {
        $data = $this->getContentArray($request);
        /*
         * @var $data               array   请求参数
         *      type                string  类型 (ORDER:订单)
         *      sn                  string  流水号
         *      bn                  string  编码
         *      invoice_type        string  发票类型(INDIVIDUAL:个人 COMPANY:公司)
         *      invoice_name        string  开票单位名称
         *      invoice_tax_id      string  税号
         *      invoice_addr        string  公司地址
         *      invoice_phone       string  公司电话
         *      invoice_bank        string  开户银行
         *      invoice_bank_no     string  开户银行卡号
         *      invoice_price       double  开票金额
         *      member_id           string  用户标识
         *      company_name        string  公司名称
         *      company_id          int     公司ID
         *      ship_type           string  发票种类(发票类型(ORDINARY:普通 SPECIAL:专用 ELECTRONIC:电子）
         *      ship_name           string  收件人姓名
         *      ship_tel            string  收件人电话
         *      ship_addr           string  收货人地址
         *      ship_email          string  收件人邮箱（电子票必填）
         *      perform             string  开票方（JIABAO:嘉宝 POP:店铺）
         *      extend_data         string  扩展信息
         *      remark              string  备注
         *      items               array   商品明细
         *      items.bn            string  商品编码
         *      item.name           string  商品名称
         *      item.tax_bn         string  税收编码
         *      item.spec           string  规格属性
         *      item.unit           string  单位
         *      item.price          double  价格
         *      item.quantity       int     数量
         *      item.amount         double  合计金额
         *      item.tax_rate       double  税率
         *      item.tax_amount     double  税额
         */


        $errMsg = '';

        // 检查请求参数
        if (!self::_checkArrayEmpty($data, array('type', 'sn', 'bn', 'invoice_type', 'ship_name', 'perform', 'items'),
            $errMsg)) {
            $errMsg OR $errMsg = '请求参数错误';
            \Neigou\Logger::General('service_invoice_v2_apply_failed', array('errMsg' => $errMsg, 'data' => $data));
            $this->setErrorMsg($errMsg);
            return $this->outputFormat(null, 404);
        }

        $invoiceLogic = new InvoiceLogic();

        // 新增保存申请
        $applyInfo = $invoiceLogic->createInvoiceApply($data, $errMsg);

        if (empty($applyInfo) OR $applyInfo === false) {
            $errMsg OR $errMsg = '申请发票失败';
            \Neigou\Logger::General('service_invoice_v2_apply_failed', array('errMsg' => $errMsg, 'data' => $data));
            $this->setErrorMsg($errMsg);
            return $this->outputFormat(null, 404);
        }

        $this->setErrorMsg('请求成功');

        $data = array('apply_id' => $applyInfo['apply_id']);
        return $this->outputFormat($data);
    }

    // --------------------------------------------------------------------

    /**
     * 申请发票换开
     *
     * @return  string
     */
    public function ApplyChange(Request $request)
    {
        $data = $this->getContentArray($request);
        /*
         * @var $data               array   请求参数
         *      type                string  类型 (ORDER:订单)
         *      sn                  string  流水号
         *      bn                  string  编码
         *      invoice_type        string  发票类型(INDIVIDUAL:个人 COMPANY:公司)
         *      invoice_name        string  开票单位名称
         *      invoice_tax_id      string  税号
         *      invoice_addr        string  公司地址
         *      invoice_phone       string  公司电话
         *      invoice_bank        string  开户银行
         *      invoice_bank_no     string  开户银行卡号
         *      invoice_price       double  开票金额
         *      member_id           string  用户标识
         *      company_name        string  公司名称
         *      company_id          int     公司ID
         *      ship_type           string  发票种类(发票类型(ORDINARY:普通 SPECIAL:专用 ELECTRONIC:电子）
         *      ship_name           string  收件人姓名
         *      ship_tel            string  收件人电话
         *      ship_addr           string  收货人地址
         *      ship_email          string  收件人邮箱（电子票必填）
         *      perform             string  开票方（JIABAO:嘉宝 POP:店铺）
         *      apply_id            int     原申请ID
         *      extend_data         string  扩展信息
         *      remark              string  备注
         *      items               array   商品明细
         *      items.bn            string  商品编码
         *      item.name           string  商品名称
         *      item.tax_bn         string  税收编码
         *      item.spec           string  规格属性
         *      item.unit           string  单位
         *      item.price          double  价格
         *      item.quantity       int     数量
         *      item.amount         double  合计金额
         *      item.tax_rate       double  税率
         *      item.tax_amount     double  税额
         */

        $errMsg = '';

        // 检查请求参数
        if (!self::_checkArrayEmpty($data,
            array('type', 'sn', 'bn', 'invoice_type', 'ship_name', 'perform', 'items', 'apply_id'), $errMsg)) {
            $errMsg OR $errMsg = '请求参数错误';
            \Neigou\Logger::General('service_invoice_v2_change_failed', array('errMsg' => $errMsg, 'data' => $data));
            $this->setErrorMsg($errMsg);
            return $this->outputFormat(null, 404);
        }
        $invoiceLogic = new InvoiceLogic();

        // 换开
        $data['apply_type'] = 2;

        // 新增保存申请
        $applyInfo = $invoiceLogic->createInvoiceApply($data, $errMsg);

        if (empty($applyInfo) OR $applyInfo === false) {
            $errMsg OR $errMsg = '申请发票换开失败';
            \Neigou\Logger::General('service_invoice_v2_change_failed', array('errMsg' => $errMsg, 'data' => $data));
            $this->setErrorMsg($errMsg);
            return $this->outputFormat(null, 404);
        }

        $invoiceModel = new InvoiceModel();

        // 更新原发票申请状态
        $updateData = array(
            'status' => 2,
        );
        if (!$invoiceModel->updateApplyInfo($data['apply_id'], $updateData)) {
            $this->setErrorMsg('更新原发票状态失败');
            \Neigou\Logger::General('service_invoice_v2_change_failed',
                array('errMsg' => '更新原发票状态失败', 'data' => $data));
            return $this->outputFormat(null, 404);
        }

        $this->setErrorMsg('请求成功');

        $data = array('apply_id' => $applyInfo['apply_id']);
        return $this->outputFormat($data);
    }

    // --------------------------------------------------------------------

    /**
     * 申请发票作废
     *
     * @return  string
     */
    public function ApplyCancel(Request $request)
    {
        $data = $this->getContentArray($request);
        /*
         * @var $data       array   请求参数
         *      type        string  类型 (ORDER:订单)
         *      sn          string  流水号
         *      apply_id    string  申请ID
         */

        // 检查请求参数
        if (!self::_checkArrayEmpty($data, array('type', 'sn', 'apply_id'), $errMsg)) {
            $errMsg OR $errMsg = '请求参数错误';
            \Neigou\Logger::General('service_invoice_v2_cancel_failed', array('errMsg' => $errMsg, 'data' => $data));
            $this->setErrorMsg($errMsg);
            return $this->outputFormat(null, 404);
        }

        $invoiceLogic = new InvoiceLogic();

        // 新增保存申请
        $applyInfo = $invoiceLogic->createInvoiceApplyCancel($data, $errMsg);

        if (empty($applyInfo) OR $applyInfo === false) {
            $errMsg OR $errMsg = '申请发票作废失败';
            \Neigou\Logger::General('service_invoice_v2_cancel_failed', array('errMsg' => $errMsg, 'data' => $data));
            $this->setErrorMsg($errMsg);
            return $this->outputFormat(null, 404);
        }

        $invoiceModel = new InvoiceModel();

        // 更新原发票申请状态
        $updateData = array(
            'status' => 2,
        );
        if (!$invoiceModel->updateApplyInfo($data['apply_id'], $updateData)) {
            $this->setErrorMsg('更新原发票状态失败');
            \Neigou\Logger::General('service_invoice_v2_cancel_failed',
                array('errMsg' => '更新原发票状态失败', 'data' => $data));
            return $this->outputFormat(null, 404);
        }

        $this->setErrorMsg('请求成功');

        $data = array('apply_id' => $applyInfo['apply_id']);
        return $this->outputFormat($data);
    }

    // --------------------------------------------------------------------

    /**
     * 申请发票撤回
     *
     * @return  string
     */
    public function ApplyRevoke(Request $request)
    {
        $data = $this->getContentArray($request);

        /*
         * @var $data       array   请求参数
         *      apply_id    mixed  申请ID
         */
        // 检查请求参数
        if (!self::_checkArrayEmpty($data, array('apply_id'), $errMsg)) {
            $errMsg OR $errMsg = '请求参数错误';
            \Neigou\Logger::General('service_invoice_v2_revoke_failed', array('errMsg' => $errMsg, 'data' => $data));
            $this->setErrorMsg($errMsg);
            return $this->outputFormat(null, 404);
        }

        $invoiceModel = new InvoiceModel();

        $applyIds = is_array($data['apply_id']) ?  $data['apply_id'] : array($data['apply_id']);

        // 获取申请列表
        $invoiceList = $invoiceModel->getInvoiceApply($applyIds);

        $return = array();
        if ( ! empty($invoiceList))
        {
            foreach ($invoiceList as $invoice)
            {
                //申请状态(0:取消 1:待处理  2:进行中 3:完成 4:异常 5:已提交待返回 6.换开已作废待重新提交 7.换开已提交待返回)
                if ( ! in_array($invoice['apply_status'], array(0, 3)))
                {
                    if ( ! $invoiceModel->updateApplyInfo($invoice['apply_id'], array('apply_status' => 0)))
                    {
                        \Neigou\Logger::General('service_invoice_v2_revoke_failed', array('errMsg' => '更新原发票状态失败', 'data' => $data));
                    }
                    else
                    {
                        $return[] = $invoice['apply_id'];
                    }
                }
            }
        }

        $this->setErrorMsg('请求成功');
        
        return $this->outputFormat($return);
    }

    // --------------------------------------------------------------------

    /**
     * 获取发票申请详情
     *
     * @return  string
     */
    public function GetApplyDetail(Request $request)
    {
        $data = $this->getContentArray($request);
        /*
         * @var $data       array   请求参数
         *      type        string  类型 (ORDER:订单)
         *      sn          string  流水号
         *      apply_id    string  申请ID
         *      bn          string  编码
         */

        $invoiceLogic = new InvoiceLogic();

        // 获取发票申请信息
        $applyInfo = $invoiceLogic->getApplyDetail($data['apply_id'], $data['type'], $data['sn'], $data['bn']);

        $this->setErrorMsg('请求成功');

        return $this->outputFormat($applyInfo);
    }

    // --------------------------------------------------------------------

    /**
     * 获取申请列表
     *
     * @return  string
     */
    public function GetApplyList(Request $request)
    {
        $data = $this->getContentArray($request);
        /*
         * @var $data           array   请求参数
         *      page            int     访问页数
         *      page_size       int     每页数量
         *      review_status   mixed   审核状态(2待审核 3审核通过 4拒绝)
         *      member_id       mixed   用户ID
         *      company_id      mixed   公司ID
         *      company_name    mixed   公司名称
         */

        // 检查请求参数
        if (!self::_checkArrayEmpty($data, array('type'), $errMsg)) {
            $errMsg OR $errMsg = '请求参数错误';
            $this->setErrorMsg($errMsg);

            return $this->outputFormat(null, 404);
        }

        $pageInfo = array(
            'page' => !empty($data['page']) ? $data['page'] : 1,
            'page_size' => !empty($data['page_size']) && $data['page_size'] < 100 ? $data['page_size'] : 1,
        );

        // 类型
        $type = $data['type'];

        $invoiceLogic = new InvoiceLogic();

        // 获取发票申请信息
        $applyList = $invoiceLogic->getApplyList($type, $data, $pageInfo);

        return $this->outputFormat(array('apply_list' => $applyList, 'page' => $pageInfo));
    }

    // --------------------------------------------------------------------

    /**
     * 通知
     *
     * @return  string
     */
    public function Notify(Request $request)
    {
        $data = $this->getContentArray($request);
        /*
         * @var $data           array   请求参数
         *      method          string  方法
         */

        // 检查请求参数
        if (!self::_checkArrayEmpty($data, array('apply_id', 'invoice_detail', 'apply_type'), $errMsg)) {
            $errMsg OR $errMsg = '请求参数错误';
            $this->setErrorMsg($errMsg);
            return $this->outputFormat(null, 404);
        }

        $invoiceLogic = new InvoiceLogic();

        // 保存发票信息
        $errMsg = '';
        if (!$invoiceLogic->saveInvoiceDetail($data['apply_id'], $data['apply_type'], $data['invoice_detail'],
            $errMsg)) {
            $this->setErrorMsg($errMsg);
            return $this->outputFormat(null, 404);
        }

        $this->setErrorMsg('请求成功');

        $data = array();
        return $this->outputFormat($data);
    }

    // --------------------------------------------------------------------

    /**
     * 审核操作
     *
     * @return  string
     */
    public function ApplyReview(Request $request)
    {
        $data = $this->getContentArray($request);
        /*
         * @var $data           array   请求参数
         *      method          string  方法
         */

        // 检查请求参数
        if (!self::_checkArrayEmpty($data, array('apply_id', 'status', 'review_msg'), $errMsg)) {
            $errMsg OR $errMsg = '请求参数错误';
            $this->setErrorMsg($errMsg);
            return $this->outputFormat(null, 404);
        }

        $invoiceLogic = new InvoiceLogic();

        // 审核
        $errMsg = '';
        if (!$invoiceLogic->applyReview($data['apply_id'], $data['status'], $data['review_msg'], $errMsg)) {
            $this->setErrorMsg($errMsg);
            return $this->outputFormat(null, 404);
        }

        $this->setErrorMsg('请求成功');

        $data = array();
        return $this->outputFormat($data);
    }

    // --------------------------------------------------------------------

    /**
     * 修复申请数据异常问题
     *
     * @return  string
     */
    public function FixApplyDataException(Request $request)
    {
        $data = $this->getContentArray($request);
        /*
         * @var $data           array   请求参数
         *      method          string  方法
         */

        // 检查请求参数
        if (!self::_checkArrayEmpty($data, array('apply_id', 'items'), $errMsg)) {
            $errMsg OR $errMsg = '请求参数错误';
            $this->setErrorMsg($errMsg);
            return $this->outputFormat(null, 404);
        }

        $invoiceLogic = new InvoiceLogic();

        // 修复异常数据
        $errMsg = '';
        if (!$invoiceLogic->fixApplyData($data['apply_id'], $data['items'], $errMsg)) {
            $this->setErrorMsg($errMsg);
            return $this->outputFormat(null, 404);
        }

        $this->setErrorMsg('请求成功');

        $data = array();
        return $this->outputFormat($data);
    }

    // --------------------------------------------------------------------

    /**
     * 检查数据是否为空
     *
     * @param   $data   array   数据
     * @param   $fields array   检查字段信息
     * @param   $errMsg string  错误信息
     * @return  boolean
     */
    private function _checkArrayEmpty($data, $fields, & $errMsg = '')
    {
        if (empty($data) OR empty($fields)) {
            return true;
        }

        foreach ($fields as $field) {
            if (empty($data[$field])) {
                $errMsg = $field . '不能为空';
                return false;
            }
        }

        return true;
    }

}
