<?php
/**
 * Created by PhpStorm.
 * User:
 * Date: 2018/11/15
 * Time: 8:21 PM
 */

namespace App\Console\Commands;

use App\Api\Model\Invoice\V2\Invoice as InvoiceModel;
use App\Api\Logic\Invoice\V2\Invoice as InvoiceLogic;
use Illuminate\Console\Command;

/**
 * 发票V2 Crontab
 *
 * @package     Console
 * @category    Command
 * @author        xupeng
 */
class InvoiceV2 extends Command
{
    protected $force = '';
    protected $signature = 'invoiceV2Task {method} {apply_id?} {type?} {apply_type?} {apply_status?} {mantissa?} ';

    protected $description = '发票服务V2';


    /**
     * 每次处理最大数量
     */
    const PER_MAX_LIMIT = 100000;

    /**
     * 最大错误次数
     */
    const MAX_FAILED_TIMES = 5;

    // 处理发票状态
    public function handle()
    {
        $method = $this->argument('method');

        $this->$method();
    }

    public function process()
    {
        // 申请ID
        $applyId = $this->argument('apply_id') ? $this->argument('apply_id') : 0;

        // 类型
        $type = $this->argument('type') ? $this->argument('type') : 'ORDER';

        // 申请类型(1:正常 2:换开 3:废弃）
        $applyType = $this->argument('apply_type') ? explode(',', $this->argument('apply_type')) : null;

        // 申请状态（1:待处理  2:进行中 3:已提交 4:通过 5:拒绝）
        $applyStatus = $this->argument('apply_status') ? explode(',', $this->argument('apply_status')) : array(1, 2, 6);

        // 尾数
        $mantissa = $this->argument('mantissa') ? explode(',', $this->argument('mantissa')) : null;

        $invoiceModel = new InvoiceModel();

        $invoiceLogic = new InvoiceLogic();

        $times = 0;

        echo "INVOICE V2 HANDLE ORDER AT:{$applyType} AS:" . implode(',', $applyStatus) . " MT:{$mantissa} START \n";
        while ($times < self::PER_MAX_LIMIT) {
            // 获取一条待处理申请ID
            $applyInfo = $invoiceModel->getUnProcessApply($applyId, $type, $mantissa, $applyType, $applyStatus);
            if (empty($applyInfo)) {
                break;
            }
            echo $applyInfo['apply_id'];

            // 待处理
            if ($applyInfo['apply_status'] == 1) {
                $status = 1;
                $reviewRemark = $applyInfo['review_remark'];
                // 待审核
                if ($applyInfo['review_status'] == 1) {
                    // 自动审核完成
                    if ($invoiceLogic->applyAutoReview($applyInfo['apply_id'])) {
                        $status = 2;
                    }
                } // 审核通过
                elseif ($applyInfo['review_status'] == 3) {
                    $status = 2;
                } // 审核拒绝
                elseif ($applyInfo['review_status'] == 4) {
                    $status = 4;
                }

                // 更新状态
                if (!$invoiceModel->updateApplyStatus($applyInfo['apply_id'], $status, '自动审核', $reviewRemark,
                    $applyInfo['apply_status'])) {
                    echo "更新审核申请状态失败 \n";
                } else {
                    echo " REVIEW DONE \n";
                }
            } // 审核通过处理中
            elseif ($applyInfo['apply_status'] == 2) {
                $errMsg = '';

                // 开票方发票申请
                if ($applyInfo['apply_type'] == 1) {
                    if (!$invoiceLogic->invoicePerformApply($applyInfo['apply_id'], $errMsg)) {
                        if ($errMsg == '订单状态不符合状态要求') {
                            $applyInfo['failed_count'] = $applyInfo['failed_count'] - 1;
                        }
                    }
                } // 开票方发票换开
                elseif ($applyInfo['apply_type'] == 2) {
                    if (!$invoiceLogic->invoicePerformCancel($applyInfo['apply_id'], $errMsg)) {
                        echo $errMsg;
                    }
                } // 开票方发票废弃
                elseif ($applyInfo['apply_type'] == 3) {
                    if (!$invoiceLogic->invoicePerformCancel($applyInfo['apply_id'], $errMsg)) {
                        echo $errMsg;
                    }
                }

                if ($errMsg) {
                    $status = $applyInfo['apply_status'];
                    if ($applyInfo['failed_count'] + 1 > self::MAX_FAILED_TIMES) {
                        $status = 4;
                    }

                    $updateData = array(
                        'apply_status' => $status,
                        'apply_status_msg' => $errMsg,
                        'failed_count' => $applyInfo['failed_count'] + 1,
                    );

                    $invoiceModel->updateApplyInfo($applyInfo['apply_id'], $updateData);
                }

                echo " PERFORM DONE \n";
            } // 换开票重新申请
            elseif ($applyInfo['apply_status'] == 6) {
                $errMsg = '';

                // 开票方发票申请
                if ($applyInfo['apply_type'] == 2) {
                    if (!$invoiceLogic->invoicePerformApply($applyInfo['apply_id'], $errMsg)) {
                        echo $errMsg;

                        $status = $applyInfo['apply_status'];
                        if ($applyInfo['failed_count'] + 1 > self::MAX_FAILED_TIMES) {
                            \Neigou\Logger::General('service_invoice_process_failed',
                                array('errMsg' => $errMsg, 'apply_id' => $applyInfo['apply_id']));
                            $status = 4;
                        }

                        $updateData = array(
                            'apply_status' => $status,
                            'apply_status_msg' => $errMsg,
                            'failed_count' => $applyInfo['failed_count'] + 1,
                        );

                        $invoiceModel->updateApplyInfo($applyInfo['apply_id'], $updateData);
                    }
                }
                echo " PERFORM DONE \n";
            }

            $applyId = $applyInfo['apply_id'];
            $times++;
        }

        echo "INVOICE V2 HANDLE ORDER AT:{$applyType} AS:" . implode(',', $applyStatus) . " MT:{$mantissa} END \n";
        echo "TOTAL:{$times} \n";
    }

    /**
     * @return string
     */
    public function downloadFile()
    {
        // 申请ID
        $invoiceId = $this->argument('apply_id') ? $this->argument('apply_id') : 0;

        $invoiceModel = new InvoiceModel();

        $invoiceLogic = new InvoiceLogic();

        $times = 0;

        echo "INVOICE V2 DOWNLOAD FILE: \n";
        while ($times < self::PER_MAX_LIMIT) {
            // 获取一条待处理申请ID
            $invoiceInfo = $invoiceModel->getUnDownloadInvoice($invoiceId);
            if (empty($invoiceInfo)) {
                break;
            }

            // 下载发票文件
            $invoiceLogic->downloadInvoiceFile($invoiceInfo);

            $invoiceId = $invoiceInfo['invoice_id'];
            $times++;
        }

        echo "INVOICE V2 DOWNLOAD FILE END \n";

        echo "TOTAL:{$times} \n";
    }

}
