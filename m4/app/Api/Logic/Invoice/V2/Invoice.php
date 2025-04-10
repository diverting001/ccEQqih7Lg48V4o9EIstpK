<?php
/**
 * neigou_service-stock
 * @package     api
 * @author      xupeng
 * @since       Version
 * @filesource
 */

namespace App\Api\Logic\Invoice\V2;

use App\Api\Model\Invoice\V2\Invoice as InvoiceModel;
use App\Api\Logic\Invoice\V2\InvoicePerform as invoicePerformLogic;
use OSS\OssClient;

/**
 * 发票V2 Logic
 *
 * @package     api
 * @category    Logic
 * @author        xupeng
 */
class Invoice
{
    /**
     * 类型
     */
    private static $_types = array('ORDER' => '订单');

    /**
     * 发票类型
     */
    private static $_invoice_types = array('INDIVIDUAL' => '个人', 'COMPANY' => '公司');

    /**
     * 发票种类
     */
    private static $_ship_types = array('ORDINARY' => '普通', 'SPECIAL' => '专用', 'ELECTRONIC' => '电子');

    /**
     * 申请类型申请类型(1:正常 2:换开 3:废弃）
     */
    private static $_apply_types = array(1 => '正常', 2 => '换开', 3 => '废弃');

    /**
     * 创建发票申请
     *
     * @param   $data   array   数据
     *          type                string  类型 (ORDER:订单)
     *          sn                  string  流水号
     *          bn                  string  编码
     *          apply_type          int     申请类型(1:正常 2:换开 3:废弃）
     *          invoice_type        string  发票类型(INDIVIDUAL:个人 COMPANY:公司)
     *          invoice_name        string  开票单位名称
     *          invoice_tax_id      string  税号
     *          invoice_addr        string  公司地址
     *          invoice_phone       string  公司电话
     *          invoice_bank        string  开户银行
     *          invoice_bank_no     string  开户银行卡号
     *          invoice_price       double  开票金额
     *          member_id           string  用户标识
     *          company_name        string  公司名称
     *          company_id          int     公司ID
     *          ship_type           string  发票种类(发票类型(ORDINARY:普通 SPECIAL:专用 ELECTRONIC:电子）
     *          ship_name           string  收件人姓名
     *          ship_tel            string  收件人电话
     *          ship_addr           string  收货人地址
     *          ship_email          string  收件人邮箱（电子票必填）
     *          perform             string  开票方（JIABAO:嘉宝 POP:店铺）
     *          extend_data         string  扩展信息
     *          remark              string  备注
     *          items               array   商品明细
     *          items.bn            string  商品编码
     *          item.name           string  商品名称
     *          item.tax_bn         string  税收编码
     *          item.spec           string  规格属性
     *          item.unit           string  单位
     *          item.price          double  价格
     *          item.quantity       int     数量
     *          item.amount         double  合计金额
     *          item.tax_rate       double  税率
     *          item.tax_amount     double  税额
     * @param   $errMsg   string   错误信息
     * @return  array|bool
     */
    public function createInvoiceApply($data, & $errMsg = '')
    {
        $invoiceModel = new InvoiceModel();

        $invoiceApplyInfo = $invoiceModel->getInvoiceApplyBySn($data['type'], $data['sn']);
        if (!empty($invoiceApplyInfo)) {
            return $invoiceApplyInfo;
        }

        if ($data['apply_id'] > 0) {
            // 获取原始申请信息
            $invoiceApplyInfo = $invoiceModel->getInvoiceApply($data['apply_id']);

            if (empty($invoiceApplyInfo)) {
                $errMsg = '申请不存在';
                return false;
            }

            if ($invoiceApplyInfo['apply_status'] != 3) {
                $errMsg = '申请未完成';
                return false;
            }
        }

        if (!isset(self::$_types[$data['type']])) {
            $errMsg = '类型不支持';
            return false;
        }

        if (!isset(self::$_invoice_types[$data['invoice_type']])) {
            $errMsg = '发票类型不支持';
            return false;
        }

        if (!isset(self::$_ship_types[$data['ship_type']])) {
            $errMsg = '发票种类不支持';
            return false;
        }

        // 个人发票
        if ($data['invoice_type'] == 'INDIVIDUAL') {
            if (empty($data['ship_name']) OR empty($data['ship_tel']) OR empty($data['ship_addr'])) {
                $errMsg = '收件人信息不能为空';
                return false;
            }
        } elseif ($data['invoice_type'] == 'COMPANY') {
            if (empty($data['invoice_name']) OR empty($data['invoice_tax_id'])) {
                $errMsg = '公司信息不能为空';
                return false;
            }
        }

        // 电子发票 邮箱不能为空
        if ($data['ship_type'] == 'ELECTRONIC' && empty($data['ship_email'])) {
            $errMsg = '电子发票收件人邮箱不能为空';
            return false;
        }

        if (empty($data['items'])) {
            $errMsg = '商品明细缺失';
            return false;
        }

        if ($data['invoice_price'] <= 0.00) {
            $errMsg = '发票金额不能为空';
            return false;
        }

        // 发票金额
        $data['invoice_price'] = number_format($data['invoice_price'], 2, '.', '');

        // 申请类型
        $data['apply_type'] OR $data['apply_type'] = 1;
        if (!isset(self::$_apply_types[$data['apply_type']])) {
            $errMsg = '申请类型不支持';
            return false;
        }
        $now = time();

        $addData = array(
            'type' => $data['type'], // 类型
            'sn' => $data['sn'], // 流水号
            'bn' => $data['bn'], // 编码
            'apply_type' => $data['apply_type'], // 申请类型(1:正常 2:换开 3:废弃）
            'invoice_type' => $data['invoice_type'], // 发票类型
            'invoice_name' => $data['invoice_name'] ? $data['invoice_name'] : '', // 开票单位名称
            'invoice_tax_id' => $data['invoice_tax_id'] ? $data['invoice_tax_id'] : '', // 税号
            'invoice_addr' => $data['invoice_addr'] ? $data['invoice_addr'] : '', // 公司地址
            'invoice_phone' => $data['invoice_phone'] ? $data['invoice_phone'] : '', // 公司电话
            'invoice_bank' => $data['invoice_bank'] ? $data['invoice_bank'] : '', // 开户银行
            'invoice_bank_no' => $data['invoice_bank_no'] ? $data['invoice_bank_no'] : '', // 开户银行卡号
            'invoice_price' => $data['invoice_price'], // 发票金额
            'member_id' => $data['member_id'] ? $data['member_id'] : '', // 用户 ID
            'company_name' => $data['company_name'] ? $data['company_name'] : '', // 公司名称
            'company_id' => intval($data['company_id']), // 公司ID
            'ship_type' => $data['ship_type'], // 发票种类
            'ship_name' => $data['ship_name'] ? $data['ship_name'] : '', // ship_name
            'ship_tel' => $data['ship_tel'] ? $data['ship_tel'] : '', // 收件人电话
            'ship_addr' => $data['ship_addr'] ? $data['ship_addr'] : '', //收货人地址
            'ship_email' => $data['ship_email'] ? $data['ship_email'] : '', // 收件人邮箱（电子票必填）
            'perform' => $data['perform'] ? $data['perform'] : '', // 开票方（JIABAO:嘉宝 POP:店铺）
            'extend_data' => $data['extend_data'] ? $data['extend_data'] : '',
            'remark' => $data['remark'] ? $data['remark'] : '', // 备注
            'create_time' => $now, // 创建时间
            'update_time' => $now, // 更新时间
            'origin_apply_id' => $data['apply_id'] ? $data['apply_id'] : 0, // 原始申请ID
            'review_status' => 1, // 审核状态 1待审核 2审核中  3通过 4拒绝
        );

        app('db')->beginTransaction();

        $applyId = $invoiceModel->addInvoiceApply($addData);

        if ($applyId <= 0) {
            app('db')->rollBack();
            return false;
        }

        $items = array();

        foreach ($data['items'] as $item) {
            $item['price'] = number_format($item['price'], 2, '.', '');
            $item['amount'] = number_format($item['amount'], 2, '.', '');
            $item['tax_rate'] = number_format($item['tax_rate'], 2, '.', '');
            $items[] = [
                'bn' => $item['bn'] ? $item['bn'] : '', // 商品编码
                'name' => $item['name'] ? $item['name'] : '', // 商品名称
                'tax_bn' => $item['tax_bn'] ? $item['tax_bn'] : '', // 税收编码
                'spec' => $item['spec'] ? $item['spec'] : '', // 规格属性
                'unit' => $item['unit'] ? $item['unit'] : '', // 单位
                'price' => $item['price'], // 价格
                'quantity' => intval($item['quantity']), // 数量
                'amount' => $item['amount'], // 合计金额
                'tax_rate' => $item['tax_rate'], // 税率
                'tax_amount' => round($item['amount'] * $item['tax_rate'], 2),// 税额
                'pmt_amount' => $item['pmt_amount'], // 优惠金额
            ];
        }

        if (!$invoiceModel->addInvoiceApplyItems($applyId, $items)) {
            $errMsg = '添加发票申请明细失败';
            app('db')->rollBack();
            return false;
        }

        app('db')->commit();

        $invoiceModel->addInvoiceRecord($applyId, $data['apply_type'], $data);

        return array('apply_id' => $applyId);
    }

    // --------------------------------------------------------------------

    /**
     * 发票申请作废
     *
     * @param   $data   array   数据
     *          type                string  类型 (ORDER:订单)
     *          sn                  string  流水号
     *          bn                  string  编码
     *          extend_data         string  扩展信息
     *          remark              string  备注
     * @param   string $errMsg 错误信息
     * @return  array|bool
     */
    public function createInvoiceApplyCancel($data, & $errMsg = '')
    {
        $invoiceModel = new InvoiceModel();

        $invoiceApplyInfo = $invoiceModel->getInvoiceApplyBySn($data['type'], $data['sn']);
        if (!empty($invoiceApplyInfo)) {
            return $invoiceApplyInfo;
        }

        // 获取原始申请信息
        $invoiceApplyInfo = $invoiceModel->getInvoiceApply($data['apply_id']);

        if (empty($invoiceApplyInfo)) {
            $errMsg = '申请取消的申请记录不存在';
            return false;
        }

        if ($invoiceApplyInfo['apply_status'] != 3) {
            $errMsg = '申请为完成无法作废';
            return false;
        }

        $now = time();

        $addData = array(
            'type' => $data['type'], // 类型
            'sn' => $data['sn'], // 流水号
            'bn' => $data['bn'], // 编码
            'apply_type' => 3, // 申请类型(1:正常 2:换开 3:废弃）
            'invoice_type' => $invoiceApplyInfo['invoice_type'], // 发票类型
            'invoice_name' => $invoiceApplyInfo['invoice_name'], // 开票单位名称
            'invoice_tax_id' => $invoiceApplyInfo['invoice_tax_id'], // 税号
            'invoice_addr' => $invoiceApplyInfo['invoice_addr'], // 公司地址
            'invoice_phone' => $invoiceApplyInfo['invoice_phone'], // 公司电话
            'invoice_bank' => $invoiceApplyInfo['invoice_bank'], // 开户银行
            'invoice_bank_no' => $invoiceApplyInfo['invoice_bank_no'], // 开户银行卡号
            'invoice_price' => $invoiceApplyInfo['invoice_price'], // 发票金额
            'member_id' => $invoiceApplyInfo['member_id'], // 用户 ID
            'company_name' => $invoiceApplyInfo['company_name'], // 公司名称
            'company_id' => $invoiceApplyInfo['company_id'], // 公司ID
            'ship_type' => $invoiceApplyInfo['ship_type'], // 发票种类
            'ship_name' => $invoiceApplyInfo['ship_name'], // ship_name
            'ship_tel' => $invoiceApplyInfo['ship_tel'], // 收件人电话
            'ship_addr' => $invoiceApplyInfo['ship_addr'], //收货人地址
            'ship_email' => $invoiceApplyInfo['ship_email'], // 收件人邮箱（电子票必填）
            'perform' => $invoiceApplyInfo['perform'], // 开票方（JIABAO:嘉宝 POP:店铺）
            'extend_data' => $data['extend_data'] ? $data['extend_data'] : '',
            'remark' => $data['remark'] ? $data['remark'] : '', // 备注
            'create_time' => $now, // 创建时间
            'update_time' => $now, // 更新时间
            'origin_apply_id' => $data['apply_id'], // 原始申请ID
            'review_status' => 1, // 审核状态 1待审核 2审核中  3通过 4拒绝
        );

        $applyId = $invoiceModel->addInvoiceApply($addData);

        if ($applyId <= 0) {
            return false;
        }

        // 保存记录
        $invoiceModel->addInvoiceRecord($applyId, 3, $data);

        return array('apply_id' => $applyId);
    }

    // --------------------------------------------------------------------

    /**
     * 审核操作
     *
     * @param   $applyId    int     申请ID
     * @param   $status     int     状态(3:通过 4:拒绝）
     * @param   $reviewMsg  string  审核描述
     * @param   $errMsg     string  错误信息
     * @return  boolean
     */
    public function applyReview($applyId, $status, $reviewMsg = '', & $errMsg = '')
    {
        if ($applyId <= 0 OR !in_array($status, array(3, 4))) {
            return false;
        }

        $invoiceModel = new InvoiceModel();
        // 获取申请信息
        $invoiceApplyInfo = $invoiceModel->getInvoiceApply($applyId);

        if (empty($invoiceApplyInfo)) {
            $errMsg = '申请记录不存在';
            return false;
        }

        if ($invoiceApplyInfo['review_status'] != 1) {
            $errMsg = '申请已审核';
            return false;
        }

        // 更新审核状态
        if (!$invoiceModel->updateReviewStatus($applyId, $status, $reviewMsg)) {
            $errMsg = '更新审核状态失败';
            return false;
        }

        return true;
    }

    // --------------------------------------------------------------------

    /**
     * 发票申请自动审核
     *
     * @param   $applyId    int     申请ID
     * @param   $errMsg     string  错误信息
     * @return  boolean
     */
    public function applyAutoReview($applyId, & $errMsg = '')
    {
        return false;
    }

    // --------------------------------------------------------------------

    /**
     * 发票平台申请
     *
     * @param   $applyId    int     申请ID
     * @param   $errMsg     string  错误信息
     * @return  boolean
     */
    public function invoicePerformApply($applyId, & $errMsg = '')
    {
        if ($applyId <= 0) {
            return false;
        }

        $invoiceModel = new InvoiceModel();

        // 获取申请信息
        $invoiceApplyInfo = $invoiceModel->getInvoiceApply($applyId);

        if (empty($invoiceApplyInfo)) {
            $errMsg = '申请记录不存在';
            return false;
        }

        // 审核状态(1:待审核 2:审核中 3:通过 4:拒绝)
        if ($invoiceApplyInfo['review_status'] != 3) {
            $errMsg = '审核未通过';
            return false;
        }

        // 申请状态(1:待处理  2:进行中 3:完成 4:异常 5:已提交待返回 6.换开已作废待重新提交 7.换开已提交待返回)
        if (!in_array($invoiceApplyInfo['apply_status'], array(2, 6))) {
            $errMsg = '申请状态错误';
            return false;
        }

        $invoiceApplyItems = $invoiceModel->getInvoiceApplyItems($applyId);

        if (empty($invoiceApplyItems)) {
            $errMsg = '申请明细不存在';
            return false;
        }

        $invoiceApplyInfo['items'] = $invoiceApplyItems;

        $invoicePerformLogic = new invoicePerformLogic();

        // 发票平台申请
        $result = $invoicePerformLogic->apply($invoiceApplyInfo, $errMsg);

        if (empty($result)) {
            return false;
        }

        // 更新状态
        if (!$invoiceModel->updateApplyStatus($applyId, 5)) {
            echo '更新审核申请状态失败';
            return false;
        }

        return true;
    }

    // --------------------------------------------------------------------

    /**
     * 发票平台申请作废
     *
     * @param   $applyId    int     申请ID
     * @param   $errMsg     string  错误信息
     * @return  boolean
     */
    public function invoicePerformCancel($applyId, & $errMsg = '')
    {
        if ($applyId <= 0) {
            return false;
        }

        $invoiceModel = new InvoiceModel();

        // 获取申请信息
        $invoiceApplyInfo = $invoiceModel->getInvoiceApply($applyId);

        if (empty($invoiceApplyInfo)) {
            $errMsg = '申请记录不存在';
            return false;
        }

        // 审核状态(1:待审核 2:审核中 3:通过 4:拒绝)
        if ($invoiceApplyInfo['review_status'] != 3) {
            $errMsg = '审核未通过';
            return false;
        }

        // 申请状态(1:待处理  2:进行中 3:完成 4:异常 5:已提交待返回 6.换开已作废待重新提交 7.换开已提交待返回)
        if ($invoiceApplyInfo['apply_status'] != 2) {
            $errMsg = '申请状态错误';
            return false;
        }

        // 获取原申请记录
        $originInvoiceApply = $invoiceModel->getInvoiceApply($invoiceApplyInfo['origin_apply_id']);
        if (empty($originInvoiceApply)) {
            $errMsg = '原申请记录不存在';
            return false;
        }
        $invoiceApplyItems = $invoiceModel->getInvoiceApplyItems($originInvoiceApply['apply_id']);

        if (empty($invoiceApplyItems)) {
            $errMsg = '申请明细不存在';
            return false;
        }

        $originInvoiceApply['items'] = $invoiceApplyItems;

        // 获取发票详情
        $originInvoiceApply['detail'] = $this->getInvoiceDetail($invoiceApplyInfo['origin_apply_id']);

        $invoicePerformLogic = new invoicePerformLogic();

        $originInvoiceApply['apply_id'] = $applyId;
        $originInvoiceApply['origin_apply_id'] = $invoiceApplyInfo['origin_apply_id'];

        // 发票平台申请
        $result = $invoicePerformLogic->cancel($originInvoiceApply, $errMsg);

        if (empty($result)) {
            return false;
        }

        // 更新状态
        if (!$invoiceModel->updateApplyStatus($applyId, 5)) {
            echo '更新审核申请状态失败';
            return false;
        }

        return true;
    }

    // --------------------------------------------------------------------

    /**
     * 获取申请详情
     *
     * @param   $applyId        mixed       申请ID
     * @param   $type           mixed       类型
     * @param   $sn             mixed       流水号
     * @param   $bn             mixed       编码
     * @param   $applyStatus    mixed       申请状态
     * @return  array
     */
    public function getApplyDetail($applyId = null, $type = null, $sn = null, $bn = null, $applyStatus = null)
    {
        $return = array();

        $invoiceModel = new InvoiceModel();

        $applyList = array();
        $applyIds = array();
        if (!empty($applyId)) {
            // 获取申请信息
            $applyList = $invoiceModel->getInvoiceApply($applyId);

            if (empty($applyList)) {
                return $return;
            }
        } elseif (!empty($type) && !empty($sn)) {
            // 获取申请信息
            $applyInfo = $invoiceModel->getInvoiceApplyBySn($type, $sn);

            if (empty($applyInfo)) {
                return $return;
            }

            $applyList[$applyInfo['apply_id']] = $applyInfo;
        } elseif (!empty($type) && !empty($bn)) {
            // 获取申请信息
            $applyList = $invoiceModel->getInvoiceApplyByBn($type, $bn);

            if (empty($applyList)) {
                return $return;
            }
        }

        if (empty($applyList)) {
            return $return;
        }

        $invalidApplyIds[] = array();
        foreach ($applyList as $key => $applyInfo) {
            // 过滤换开发票
            if ($applyStatus) {
                is_array($applyStatus) OR $applyStatus = array($applyStatus);

                if (!in_array($applyInfo['apply_status'], $applyStatus)) {
                    unset($applyList[$key]);
                    continue;
                }
            }

            $applyIds[] = $applyInfo['apply_id'];
        }

        if (empty($applyList)) {
            return $return;
        }

        // 获取发票申请明细
        $invoiceApplyItems = $invoiceModel->getInvoiceApplyItems($applyIds);

        // 获取发票信息
        $invoiceDetail = $this->getInvoiceDetail($applyIds);

        foreach ($applyList as $applyInfo) {
            $applyInfo['items'] = isset($invoiceApplyItems[$applyInfo['apply_id']]) ? $invoiceApplyItems[$applyInfo['apply_id']] : array();
            $applyInfo['detail'] = isset($invoiceDetail[$applyInfo['apply_id']]) ? $invoiceDetail[$applyInfo['apply_id']] : array();
            $return[] = $applyInfo;
        }

        return $return;
    }

    // --------------------------------------------------------------------

    /**
     * 获取申请列表
     *
     * @param   $type       mixed       类型
     * @param   $filter     mixed       过滤条件
     * @param   $pageInfo   mixed       分页数据
     * @return  array
     */
    public function getApplyList($type, $filter = array(), & $pageInfo = array())
    {
        $return = array();

        $invoiceModel = new InvoiceModel();

        $applyIds = array();

        $limit = null;

        $offset = null;

        if (!empty($pageInfo)) {
            // 获取申请列表总数量
            $count = $invoiceModel->getApplyListCount($type, $filter);

            $pageSize = $pageInfo['page_size'] > 0 ? $pageInfo['page_size'] : 30;
            $maxPage = ceil($count / $pageSize);
            $page = $pageInfo['page'] > $maxPage ? $maxPage : $pageInfo['page'];
            $limit = $pageSize;
            $offset = ($page - 1) * $pageSize;

            $pageInfo = array(
                'page' => $page,
                'max_page' => $maxPage,
                'page_size' => $pageSize,
                'total' => $count,
            );
        }

        // 获取申请信息
        $applyList = $invoiceModel->getApplyList($type, $filter, $limit, $offset);

        if (empty($applyList)) {
            return $return;
        }

        $invalidApplyIds[] = array();
        foreach ($applyList as $key => $applyInfo) {
            $applyIds[] = $applyInfo['apply_id'];
        }

        if (empty($applyList)) {
            return $return;
        }

        // 获取发票申请明细
        $invoiceApplyItems = $invoiceModel->getInvoiceApplyItems($applyIds);

        // 获取发票信息
        $invoiceDetail = $this->getInvoiceDetail($applyIds);

        foreach ($applyList as $applyInfo) {
            $applyInfo['items'] = isset($invoiceApplyItems[$applyInfo['apply_id']]) ? $invoiceApplyItems[$applyInfo['apply_id']] : array();
            $applyInfo['detail'] = isset($invoiceDetail[$applyInfo['apply_id']]) ? $invoiceDetail[$applyInfo['apply_id']] : array();
            $return[] = $applyInfo;
        }

        return $return;
    }

    // --------------------------------------------------------------------

    /**
     * 获取发票内容
     *
     * @param   $applyId        mixed       申请ID
     * @return  array
     */
    public function getInvoiceDetail($applyId)
    {
        $return = array();

        if (empty($applyId)) {
            return $return;
        }

        $invoiceModel = new InvoiceModel();

        $applyIds = is_array($applyId) ? $applyId : array($applyId);

        // 获取发票信息
        $invoiceList = $invoiceModel->getInvoiceDetail($applyIds);

        if (empty($invoiceList)) {
            return $return;
        }

        $invoiceIds = array();
        foreach ($invoiceList as $detail) {
            $invoiceIds[] = $detail['invoice_id'];
        }

        $invoiceItems = $invoiceModel->getInvoiceDetailItems($invoiceIds);

        foreach ($invoiceList as $detail) {
            if (!empty($invoiceItems[$detail['invoice_id']])) {
                $detail['items'] = $invoiceItems[$detail['invoice_id']];
            }

            $return[$detail['apply_id']][] = $detail;
        }

        return is_array($applyId) ? $return : current($return);
    }

    // --------------------------------------------------------------------

    /**
     * 获取发票内容
     *
     * @param   $applyId        mixed       申请ID
     * @param   $type           int         类型(1:申请 2:作废)
     * @param   $invoiceDetail  array       发票详情
     * @param   $errMsg         string      错误信息
     * @return  boolean
     */
    public function saveInvoiceDetail($applyId, $type, $invoiceDetail, & $errMsg = '')
    {
        if ($applyId <= 0 OR empty($invoiceDetail)) {
            $errMsg = '信息缺失';
            return false;
        }

        $invoiceModel = new InvoiceModel();

        // 获取申请信息
        $invoiceApplyInfo = $invoiceModel->getInvoiceApply($applyId);

        if (empty($invoiceApplyInfo)) {
            $errMsg = '申请记录不存在';
            return false;
        }

        // 申请状态(1:待处理  2:进行中 3:完成 4:异常 5:已提交待返回 6.换开已作废待重新提交 7.换开已提交待返回)
        if ($invoiceApplyInfo['apply_status'] == 3) {
            return true;
        }

        $now = time();

        // 生成发票信息
        $invoiceBaseInfo = array(
            'apply_id' => $applyId, // 申请ID
            'invoice_type' => $invoiceApplyInfo['invoice_type'], // 发票类型
            'invoice_name' => $invoiceApplyInfo['invoice_name'], // 开票单位名称
            'invoice_tax_id' => $invoiceApplyInfo['invoice_tax_id'], // 税号
            'invoice_addr' => $invoiceApplyInfo['invoice_addr'], // 公司地址
            'invoice_phone' => $invoiceApplyInfo['invoice_phone'], // 公司电话
            'invoice_bank' => $invoiceApplyInfo['invoice_bank'], // 开户银行
            'invoice_bank_no' => $invoiceApplyInfo['invoice_bank_no'], // 开户银行卡号
            'invoice_price' => $invoiceApplyInfo['invoice_price'], // 发票金额
            'ship_type' => $invoiceApplyInfo['ship_type'], // 发票种类
            'ship_name' => $invoiceApplyInfo['ship_name'], // 收货人姓名
            'ship_tel' => $invoiceApplyInfo['ship_tel'], // 收件人电话
            'ship_addr' => $invoiceApplyInfo['ship_addr'], //收货人地址
            'ship_email' => $invoiceApplyInfo['ship_email'], // 收件人邮箱（电子票必填）
            'extend_data' => $invoiceApplyInfo['extend_data'] ? $invoiceApplyInfo['extend_data'] : '',
            'remark' => $invoiceApplyInfo['remark'] ? $invoiceApplyInfo['remark'] : '', // 备注
            'create_time' => $now, // 创建时间
            'update_time' => $now, // 更新时间
        );

        // 批量插入发票单
        app('db')->beginTransaction();

        $disableInvoiceBn = array();
        foreach ($invoiceDetail as $v) {
            $invoiceBaseInfo['invoice_color'] = $v['type']; // 发票种类：1 蓝票 2 红票
            $invoiceBaseInfo['third_bn'] = $v['bn'];
            $invoiceBaseInfo['extend_data'] = json_encode(array(
                'code' => $v['code'], //发票代码
                'number' => $v['number'], // 发票号码
                'content' => $v['content'],  // 发票内容（密文）
                'check_code' => $v['check_code'], // 检查码
                'invoice_url' => $v['url'], // 发票地址
                'origin_third_bn' => $v['origin_bn'],
            ));

            // 作废发票
            if ($v['type'] == 2) {
                $disableInvoiceBn[] = $v['origin_bn'];
            }

            // 添加发票详情
            $invoiceId = $invoiceModel->addInvoiceDetail($invoiceBaseInfo);

            if ($invoiceId <= 0) {
                $errMsg = '添加发票明细失败';
                app('db')->rollBack();
                return false;
            }

            $items = array();
            foreach ($v['items'] as $item) {
                // 添加发票明细
                $item['price'] = number_format($item['price'], 2, '.', '');
                $item['amount'] = number_format($item['amount'], 2, '.', '');
                $item['tax_rate'] = number_format($item['tax_rate'], 2, '.', '');
                $item['tax_amount'] = number_format($item['tax_amount'], 2, '.', '');
                $items[] = [
                    'bn' => $item['bn'], // 商品编码
                    'name' => $item['name'], // 商品名称
                    'tax_bn' => $item['tax_bn'], // 税收编码
                    'spec' => $item['spec'], // 规格属性
                    'unit' => $item['unit'], // 单位
                    'price' => $item['price'], // 价格
                    'quantity' => intval($item['quantity']), // 数量
                    'amount' => $item['amount'], // 合计金额
                    'tax_rate' => $item['tax_rate'], // 税率
                    'tax_amount' => $item['tax_amount'], // 税额
                ];
            }

            // 添加发票详情明细
            if (!$invoiceModel->addInvoiceDetailItems($invoiceId, $items)) {
                $errMsg = '添加发票明细失败';
                app('db')->rollBack();
                return false;
            }
        }

        // 换开作废状态
        $status = $invoiceApplyInfo['apply_type'] == 2 && $type == 3 ? 6 : 3;

        // 更新发票申请状态
        if (!$invoiceModel->updateApplyStatus($applyId, $status)) {
            $errMsg = '更新发票申请状态失败';
            app('db')->rollBack();
            return false;
        }

        // 更新发票状态
        if (!empty($disableInvoiceBn) && !$invoiceModel->updateInvoiceStatusByBn($invoiceApplyInfo['origin_apply_id'],
                $disableInvoiceBn, 2)) {
            $errMsg = '更新发票状态失败';
            app('db')->rollBack();
            return false;
        }

        // 更新发票
        app('db')->commit();

        return true;
    }

    // --------------------------------------------------------------------

    /**
     * 修复申请的异常数据
     *
     * @param   $applyId        mixed       申请ID
     * @param   $items          array       项目
     * @param   $errMsg         string      错误信息
     * @return  boolean
     */
    public function fixApplyData($applyId, $items, & $errMsg = '')
    {
        if ($applyId <= 0 OR empty($items)) {
            $errMsg = '信息缺失';
            return false;
        }

        $invoiceModel = new InvoiceModel();

        // 获取申请信息
        $invoiceApplyInfo = $invoiceModel->getInvoiceApply($applyId);

        if (empty($invoiceApplyInfo)) {
            $errMsg = '申请记录不存在';
            return false;
        }

        // 申请状态(1:待处理  2:进行中 3:完成 4:异常 5:已提交待返回 6.换开已作废待重新提交 7.换开已提交待返回)
        if (!in_array($invoiceApplyInfo['apply_status'], array(1, 2, 4))) {
            $errMsg = '状态不允许修改';
            return false;
        }

        $fixStatus = null;

        // 明细修复
        if (!empty($items['items']) && is_array($items['items'])) {
            $updateItems = array();
            $itemsFields = array('name', 'tax_bn', 'spec', 'unit', 'tax_rate');

            foreach ($items['items'] as $item) {
                if (empty($item['bn'])) {
                    continue;
                }

                // 商品名称，规格、税率、税收编码
                foreach ($item as $key => $val) {
                    if (in_array($key, $itemsFields)) {
                        $updateItems[$item['bn']][$key] = $val;
                    }
                }
            }

            foreach ($updateItems as $bn => $data) {
                $invoiceModel->updateApplyItemInfo($applyId, $bn, $data);
                $fixStatus = true;
            }
        }

        if ($fixStatus == true) {
            // 更新状态
            if (!$invoiceModel->updateApplyInfo($applyId, array('apply_status' => 2, 'failed_count' => 0))) {
                echo '更新状态失败';
                return false;
            }
        }

        return true;
    }

    // --------------------------------------------------------------------

    /**
     * 下单发票文件
     *
     * @param   $invoiceDetail  array   发票详情
     * @return  boolean
     */
    public function downloadInvoiceFile($invoiceDetail)
    {
        if (empty($invoiceDetail)) {
            return false;
        }

        $extendData = json_decode($invoiceDetail['extend_data'], true);

        if (empty($extendData) OR empty($extendData['invoice_url'])) {
            return false;
        }

        // 上传到阿里云
        $invoiceUrl = $this->_upLoadFile($extendData['invoice_url'], md5($extendData['invoice_url']));

        if ($invoiceUrl) {
            $invoiceModel = new InvoiceModel();

            // 更新发票地址
            if (!$invoiceModel->updateInvoiceInfo($invoiceDetail['invoice_id'], array('invoice_url' => $invoiceUrl))) {
                return false;
            }
        }

        return true;
    }

    // --------------------------------------------------------------------

    /**
     * 文件上传
     *
     * @param   $file   string  文件路径
     * @param   $name   string  文件名称
     * @return  boolean
     */
    private function _upLoadFile($file, $name)
    {
        $fileContent = file_get_contents($file);
        if (empty($fileContent)) {
            return false;
        }

        $ossClient = new OssClient(config('neigou.OSS_ASSESS_KEY_ID'), config('neigou.OSS_ACCESS_KEY_SECRET'), config('neigou.OSS_ENDPOINT'));
        $orgPath = 'einvoice/' . date('Ymd', time()) . '/' . $name;
        $res = $ossClient->putObject(config('neigou.OSS_BUCKET'), $orgPath . '.pdf', $fileContent);

        return $res['info']['url'];
    }

}
