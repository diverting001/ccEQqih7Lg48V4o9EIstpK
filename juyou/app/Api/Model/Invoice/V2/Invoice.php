<?php
/**
 * Created by PhpStorm.
 * User: chuanbin
 * Date: 2018/9/26
 * Time: 11:35
 */

namespace App\Api\Model\Invoice\V2;

class Invoice
{
    /**
     * 发票申请表
     */
    const TABLE_INVOICE_APPLY = 'server_new_invoice_apply';

    /**
     * 发票申请明细表
     */
    const TABLE_INVOICE_APPLY_ITEMS = 'server_new_invoice_apply_items';

    /**
     * 发票记录表
     */
    const TABLE_INVOICE_RECORD = 'server_new_invoice_record';

    /**
     * 发票表
     */
    const TABLE_INVOICES = 'server_new_invoices';

    /**
     * 发票明细表
     */
    const TABLE_INVOICE_ITEMS = 'server_new_invoice_items';

    /**
     * Invoice constructor.
     *
     * @param   string $db
     */
    public function __construct($db = '')
    {
        $this->_db = app('api_db');
    }

    /**
     * 新增发票申请
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
     *          create_time         int     创建时间
     *          update_time         int     更新时间
     *          origin_apply_id     int     原始申请ID
     *          review_status       int     审核状态 1待审核 2审核中  3通过 4拒绝
     *          apply_type          int     申请类型(1:正常 2:换开 3:废弃）
     * @return  mixed
     */
    public function addInvoiceApply($data)
    {
        return app('api_db')->table(self::TABLE_INVOICE_APPLY)->insertGetId($data);
    }

    // --------------------------------------------------------------------

    /**
     * 获取发票申请详情（流水号）
     *
     * @param   $type   string  类型 (ORDER:订单)
     * @param   $sn     string  流水号
     * @return  array
     */
    public function getInvoiceApplyBySn($type, $sn)
    {
        $where = array(
            array('type', $type),
            array('sn', $sn),
        );

        $data = $this->_db->table(self::TABLE_INVOICE_APPLY)->where($where)->first();

        return $data ? get_object_vars($data) : array();
    }

    // --------------------------------------------------------------------

    /**
     * 获取发票申请详情（编码）
     *
     * @param   $type   string  类型 (ORDER:订单)
     * @param   $bn     string  编号
     * @return  array
     */
    public function getInvoiceApplyByBn($type, $bn)
    {
        $return = array();

        $db = $this->_db->table(self::TABLE_INVOICE_APPLY);

        $where = array(
            array('type', $type),
        );

        is_array($bn) OR $bn = array($bn);

        $db->whereIn('bn', $bn);

        $result = $db->where($where)->orderBy('apply_id', 'desc')->get()->toArray();

        if (empty($result)) {
            return $return;
        }

        foreach ($result as $v) {
            $return[] = get_object_vars($v);
        }

        return $return;
    }

    // --------------------------------------------------------------------

    /**
     * 获取发票列表总数量
     *
     * @param   $type       string  类型
     * @param   $filter     array   过滤
     * @return  int
     */
    public function getApplyListCount($type, $filter)
    {
        $db = $result = $this->_db->table(self::TABLE_INVOICE_APPLY);

        $where = array(
            'type' => $type,
        );

        // 申请类型(1:正常 2:换开 3:作废）
        if (!empty($filter['apply_type'])) {
            $where['apply_type'] = $filter['apply_type'];
        }

        // 发票类型(INDIVIDUAL:个人 COMPANY:公司)
        if (!empty($filter['invoice_type'])) {
            $where['invoice_type'] = $filter['invoice_type'];
        }

        // 申请人帐号
        if (!empty($filter['member_id'])) {
            $where['member_id'] = $filter['member_id'];
        }

        // 公司ID
        if (!empty($filter['company_id'])) {
            $where['company_id'] = $filter['company_id'];
        }

        // 发票种类
        if (!empty($filter['ship_type'])) {
            $where['ship_type'] = $filter['ship_type'];
        }

        // 开发票方
        if (!empty($filter['perform'])) {
            $where['perform'] = $filter['perform'];
        }

        // 审核状态
        if (!empty($filter['review_status'])) {
            $where['review_status'] = $filter['review_status'];
        }

        // 编码
        if (!empty($filter['bn'])) {
            $bns = array_filter(explode(',', $filter['bn']));
            $db->whereIn('bn', $bns);
        }

        // 申请状态(1:待处理  2:进行中 3:完成 4:异常 5:已提交待返回 6.换开已作废待重新提交 7.换开已提交待返回)
        if (!empty($filter['apply_status'])) {
            $applyStatus = array_filter(explode(',', $filter['apply_status']));
            $db->whereIn('apply_status', $applyStatus);
        }

        return $db->where($where)->orderBy('apply_id', 'desc')->count('apply_id');
    }

    // --------------------------------------------------------------------

    /**
     * 获取发票列表
     *
     * @param   $type       string  类型
     * @param   $filter     array   过滤
     * @param   $limit      mixed   条数
     * @param   $offset     mixed   起始位置
     * @return  array
     */
    public function getApplyList($type, $filter, $limit = null, $offset = null)
    {
        $return = array();

        $db = $result = $this->_db->table(self::TABLE_INVOICE_APPLY);

        $where = array(
            'type' => $type,
        );

        // 申请类型(1:正常 2:换开 3:作废）
        if (!empty($filter['apply_type'])) {
            $where['apply_type'] = $filter['apply_type'];
        }

        // 发票类型(INDIVIDUAL:个人 COMPANY:公司)
        if (!empty($filter['invoice_type'])) {
            $where['invoice_type'] = $filter['invoice_type'];
        }

        // 申请人帐号
        if (!empty($filter['member_id'])) {
            $where['member_id'] = $filter['member_id'];
        }

        // 公司ID
        if (!empty($filter['company_id'])) {
            $where['company_id'] = $filter['company_id'];
        }

        // 发票种类
        if (!empty($filter['ship_type'])) {
            $where['ship_type'] = $filter['ship_type'];
        }

        // 开发票方
        if (!empty($filter['perform'])) {
            $where['perform'] = $filter['perform'];
        }

        // 审核状态
        if (!empty($filter['review_status'])) {
            $where['review_status'] = $filter['review_status'];
        }

        // 编码
        if (!empty($filter['bn'])) {
            $bns = array_filter(explode(',', $filter['bn']));
            $db->whereIn('bn', $bns);
        }

        if ($limit !== null) {
            $db->limit($limit);
        }

        if ($offset !== null) {
            $db->offset($offset);
        }

        // 申请状态(1:待处理  2:进行中 3:完成 4:异常 5:已提交待返回 6.换开已作废待重新提交 7.换开已提交待返回)
        if (!empty($filter['apply_status'])) {
            $applyStatus = array_filter(explode(',', $filter['apply_status']));
            $db->whereIn('apply_status', $applyStatus);
        }

        $result = $db->where($where)->orderBy('apply_id', 'desc')->get()->toArray();

        if (empty($result)) {
            return $result;
        }

        foreach ($result as $v) {
            $return[] = get_object_vars($v);
        }

        return $return;
    }

    // --------------------------------------------------------------------

    /**
     * 获取发票详情
     *
     * @param   $applyId   int  申请ID
     * @return  array
     */
    public function getInvoiceApply($applyId)
    {
        $return = array();

        $applyIds = is_array($applyId) ? $applyId : array($applyId);
        $result = $this->_db->table(self::TABLE_INVOICE_APPLY)->whereIn('apply_id', $applyIds)->orderBy('apply_id',
            'desc')->get()->toArray();

        if (empty($result)) {
            return $result;
        }

        foreach ($result as $v) {
            $return[] = get_object_vars($v);
        }

        return is_array($applyId) ? $return : current($return);
    }

    // --------------------------------------------------------------------

    /**
     * 添加发票申请明细
     *
     * @param   $applyId    int     申请ID
     * @param   $items      array   明细
     * @return  boolean
     */
    public function addInvoiceApplyItems($applyId, $items)
    {
        $return = true;

        if ($applyId <= 0 OR empty($items)) {
            return false;
        }

        foreach ($items as $item) {
            $item['apply_id'] = $applyId;
            if (!app('api_db')->table(self::TABLE_INVOICE_APPLY_ITEMS)->insertGetId($item)) {
                $return = false;
                break;
            }
        }

        return $return;
    }

    // --------------------------------------------------------------------

    /**
     * 添加发票记录
     *
     * @param   $applyId    int     申请ID
     * @param   $type       int     类型（1:正常 2:换开 3:废弃）
     * @param   $content    mixed   数据
     * @param   $result     mixed   结果
     * @param   $remark     string  备注
     * @param   $status     int     状态
     * @return  boolean
     */
    public function addInvoiceRecord($applyId, $type = 1, $content = '', $result = '', $status = 1, $remark = '')
    {
        $now = time();
        $addRecordData = array(
            'apply_id' => $applyId,
            'type' => $type,
            'content' => $content ? serialize($content) : '',
            'result' => $result ? serialize($result) : '',
            'status' => $status,
            'remark' => $remark,
            'create_time' => $now,
            'update_time' => $now,
        );

        if (!app('api_db')->table(self::TABLE_INVOICE_RECORD)->insertGetId($addRecordData)) {
            return false;
        }

        return true;
    }

    // --------------------------------------------------------------------

    /**
     * 添加发票详情
     *
     * @param   $data   array   数据
     *          apply_id            int     申请ID
     *          invoice_type        string  发票类型(INDIVIDUAL:个人 COMPANY:公司)
     *          invoice_name        string  开票单位名称
     *          invoice_tax_id      string  税号
     *          invoice_addr        string  公司地址
     *          invoice_phone       string  公司电话
     *          invoice_bank        string  开户银行
     *          invoice_bank_no     string  开户银行卡号
     *          invoice_price       double  开票金额
     *          invoice_color       int     发票种类(1:蓝票 2:红票)
     *          ship_type           string  发票种类(发票类型(ORDINARY:普通 SPECIAL:专用 ELECTRONIC:电子）
     *          ship_name           string  收件人姓名
     *          ship_tel            string  收件人电话
     *          ship_addr           string  收货人地址
     *          ship_email          string  收件人邮箱（电子票必填）
     *          third_bn            string  开票方编号
     *          invoice_url         string  发票文件地址
     *          from_invoice_id     int     原始发票ID
     *          extend_data         string  扩展信息
     *          remark              string  备注
     *          create_time         int     创建时间
     *          update_time         int     更新时间
     * @return  mixed
     */
    public function addInvoiceDetail($data)
    {
        return app('api_db')->table(self::TABLE_INVOICES)->insertGetId($data);
    }

    // --------------------------------------------------------------------

    /**
     * 添加发票详情明细
     *
     * @param   $invoiceId    int   发票ID
     * @param   $items      array   明细
     * @return  boolean
     */
    public function addInvoiceDetailItems($invoiceId, $items)
    {
        $return = true;

        if ($invoiceId <= 0 OR empty($items)) {
            return false;
        }

        foreach ($items as $item) {
            $item['invoice_id'] = $invoiceId;
            if (!app('api_db')->table(self::TABLE_INVOICE_ITEMS)->insertGetId($item)) {
                $return = false;
                break;
            }
        }

        return $return;
    }

    // --------------------------------------------------------------------

    /**
     * 获取待处理的发票申请
     *
     * @param   $applyId        int     最小申请ID
     * @param   $type           string  类型（ORDER:订单）
     * @param   $applyType      mixed   申请类型（1:正常 2:换开 3:废弃）
     * @param   $applyStatus    mixed   申请状态（1:待处理 2:进行中 3:通过 4:拒绝）
     * @param   $mantissa       mixed   尾数
     * @return  array
     */
    public function getUnProcessApply($applyId, $type, $mantissa = null, $applyType = null, $applyStatus = null)
    {
        $where = array(
            ['apply_id', '>', $applyId],
            ['type', '=', $type],
        );

        $db = $this->_db->table(self::TABLE_INVOICE_APPLY);

        if (!empty($mantissa)) {
            $db->whereIn('apply_id % 10', $mantissa);
        }

        if (!empty($applyType)) {
            is_array($applyType) OR $applyType = array($applyType);
            $db->whereIn('apply_type', $applyType);
        }

        if (!empty($applyStatus)) {
            is_array($applyStatus) OR $applyStatus = array($applyStatus);
            $db->whereIn('apply_status', $applyStatus);
        }

        $data = $db->where($where)->orderBy('apply_id', 'ASC')->first();

        return $data ? get_object_vars($data) : array();
    }

    // --------------------------------------------------------------------

    /**
     * 更新审核状态
     *
     * @param   $applyId        int     最小申请ID
     * @param   $reviewStatus   mixed   审核状态(1:待审核 2:审核中 3:通过 4:拒绝)
     * @param   $reviewRemark   string  审核备注
     * @return  boolean
     */
    public function updateReviewStatus($applyId, $reviewStatus, $reviewRemark = '')
    {
        if ($applyId <= 0 OR $reviewStatus <= 0) {
            return false;
        }

        $where = array(
            'apply_id' => $applyId,
        );

        $updateData = array(
            'review_status' => $reviewStatus,
            'review_remark' => $reviewRemark,
            'update_time' => time(),
        );

        $result = $this->_db->table(self::TABLE_INVOICE_APPLY)->where($where)->update($updateData);

        return $result ? true : false;
    }

    // --------------------------------------------------------------------

    /**
     * 更新申请状态
     *
     * @param   $applyId        int     最小申请ID
     * @param   $applyStatus    int     申请状态（1:待处理  2:进行中 3:已提交 4:通过 5:拒绝）
     * @param   $applyStatusMsg string  申请状态描述
     * @param   $reviewRemark   string  审核备注
     * @param   $beforeStatus   mixed   更新前状态
     * @return  boolean
     */
    public function updateApplyStatus(
        $applyId,
        $applyStatus,
        $applyStatusMsg = '',
        $reviewRemark = '',
        $beforeStatus = null
    ) {
        if ($applyId <= 0 OR $applyStatus <= 0) {
            return false;
        }

        $where = array(
            'apply_id' => $applyId,
        );

        if ($beforeStatus !== null) {
            $where['apply_status'] = $beforeStatus;
        }

        $updateData = array(
            'review_remark' => $reviewRemark,
            'apply_status' => $applyStatus,
            'update_time' => time(),
        );

        $updateData['apply_status_msg'] = $applyStatusMsg ? $applyStatusMsg : '';

        $result = $this->_db->table(self::TABLE_INVOICE_APPLY)->where($where)->update($updateData);

        return $result ? true : false;
    }

    // --------------------------------------------------------------------

    /**
     * 更新申请信息
     *
     * @param   $applyId        int     最小申请ID
     * @param   $updateData     array   更新数据
     * @return  boolean
     */
    public function updateApplyInfo($applyId, $updateData)
    {
        if ($applyId <= 0 OR empty($updateData)) {
            return false;
        }

        $where = array(
            'apply_id' => $applyId,
        );

        if (!isset($updateData['update_time'])) {
            $updateData['update_time'] = time();
        }

        $result = $this->_db->table(self::TABLE_INVOICE_APPLY)->where($where)->update($updateData);

        return $result ? true : false;
    }

    // --------------------------------------------------------------------

    /**
     * 获取发票申请详情
     *
     * @param   $applyId    mixed   申请ID
     * @return  array
     */
    public function getInvoiceApplyItems($applyId)
    {
        $return = array();

        $applyIds = is_array($applyId) ? $applyId : array($applyId);

        $result = $this->_db->table(self::TABLE_INVOICE_APPLY_ITEMS)->whereIn('apply_id', $applyIds)->get()->toArray();

        if (empty($result)) {
            return $return;
        }

        foreach ($result as $v) {
            $return[$v->apply_id][] = get_object_vars($v);
        }

        return is_array($applyId) ? $return : current($return);
    }

    // --------------------------------------------------------------------

    /**
     * 获取发票详情
     *
     * @param   $applyId    mixed   申请ID
     * @return  array
     */
    public function getInvoiceDetail($applyId)
    {
        $return = array();

        $applyIds = is_array($applyId) ? $applyId : array($applyId);

        $result = $this->_db->table(self::TABLE_INVOICES)->whereIn('apply_id', $applyIds)->get()->toArray();

        if (empty($result)) {
            return $return;
        }

        foreach ($result as $v) {
            $return[] = get_object_vars($v);
        }

        return is_array($applyId) ? $return : current($return);
    }

    // --------------------------------------------------------------------

    /**
     * 获取发票详情明细
     *
     * @param   $invoiceId    mixed   发票ID
     * @return  array
     */
    public function getInvoiceDetailItems($invoiceId)
    {
        $return = array();

        $invoiceIds = is_array($invoiceId) ? $invoiceId : array($invoiceId);

        $result = $this->_db->table(self::TABLE_INVOICE_ITEMS)->whereIn('invoice_id', $invoiceIds)->get()->toArray();

        if (empty($result)) {
            return $return;
        }

        foreach ($result as $v) {
            $info = get_object_vars($v);

            $info['extend_data'] = $info['extend_data'] ? json_decode($info['extend_data'], true) : '';

            $return[$v->invoice_id][] = get_object_vars($v);
        }

        return is_array($invoiceId) ? $return : current($return);
    }

    // --------------------------------------------------------------------

    /**
     * 发票结果通知
     *
     * @param   $invoiceId    mixed   发票ID
     * @return  array
     */
    public function InvoiceResultNotify($invoiceId)
    {
        $return = array();

        $invoiceIds = is_array($invoiceId) ? $invoiceId : array($invoiceId);

        $result = $this->_db->table(self::TABLE_INVOICE_ITEMS)->whereIn('invoice_id', $invoiceIds)->get()->toArray();

        if (empty($result)) {
            return $return;
        }

        foreach ($result as $v) {
            $return[$v->invoice_id][] = get_object_vars($v);
        }

        return is_array($invoiceId) ? $return : current($return);
    }

    // --------------------------------------------------------------------

    /**
     * 更新发票状态
     *
     * @param   $applyId        int     申请ID
     * @param   $thirdBn        mixed   三方编码
     * @param   $status         int     状态(1:可用 2:作废)
     * @return  boolean
     */
    public function updateInvoiceStatusByBn($applyId, $thirdBn, $status)
    {
        if ($applyId <= 0 OR empty($applyId)) {
            return false;
        }

        $where = array(
            'apply_id' => $applyId,
        );

        if (!is_array($thirdBn)) {
            $thirdBn = array($thirdBn);
        }

        $updateData = array(
            'status' => $status,
            'update_time' => time(),
        );

        $result = $this->_db->table(self::TABLE_INVOICES)->where($where)->whereIn('third_bn',
            $thirdBn)->update($updateData);

        return $result ? true : false;
    }


    // --------------------------------------------------------------------

    /**
     * 获取未下单的发票
     *
     * @param   $invoiceId    mixed   发票ID
     * @return  array
     */
    public function getUnDownloadInvoice($invoiceId)
    {
        $where = array(
            ['invoice_id', '>', $invoiceId],
            ['status', '=', 1],
            ['invoice_url', '=', ''],
            ['extend_data', '!=', ''],
        );

        $data = $this->_db->table(self::TABLE_INVOICES)->where($where)->orderBy('invoice_id', 'ASC')->first();

        return $data ? get_object_vars($data) : array();
    }

    // --------------------------------------------------------------------

    /**
     * 更新发票信息
     *
     * @param   $invoiceId      int     发票ID
     * @param   $updateData     array   更新数据
     * @return  boolean
     */
    public function updateInvoiceInfo($invoiceId, $updateData)
    {
        if ($invoiceId <= 0 OR empty($updateData)) {
            return false;
        }

        $where = array(
            'invoice_id' => $invoiceId,
        );

        if (!isset($updateData['update_time'])) {
            $updateData['update_time'] = time();
        }

        $result = $this->_db->table(self::TABLE_INVOICES)->where($where)->update($updateData);

        return $result ? true : false;
    }

    // --------------------------------------------------------------------

    /**
     * 更新申请明细
     *
     * @param   $applyId        int     最小申请ID
     * @param   $bn             string  编码
     * @param   $updateData     array   更新数据
     * @return  boolean
     */
    public function updateApplyItemInfo($applyId, $bn, $updateData)
    {
        if ($applyId <= 0 OR empty($bn)) {
            return false;
        }

        $where = array(
            'apply_id' => $applyId,
            'bn' => $bn,
        );

        $result = $this->_db->table(self::TABLE_INVOICE_APPLY_ITEMS)->where($where)->update($updateData);

        return $result ? true : false;
    }

}
