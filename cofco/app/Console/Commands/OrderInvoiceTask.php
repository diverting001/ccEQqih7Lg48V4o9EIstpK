<?php
/**
 * Created by PhpStorm.
 * User:
 * Date: 2018/11/15
 * Time: 8:21 PM
 */

namespace App\Console\Commands;

use App\Api\Model\Order\OrderInvoice as OrderInvoiceModel;
use App\Api\V1\Service\Order\Invoice as OrderInvoice;
use Illuminate\Console\Command;

/**
 * 订单发票 Crontab
 *
 * @package     Console
 * @category    Command
 * @author        xupeng
 */
class OrderInvoiceTask extends Command
{
    protected $force = '';
    protected $signature = 'orderInvoiceTask {method} {id?} {mantissa?} ';

    protected $description = '订单发票';

    /**
     * 每次处理最大数量
     */
    const PER_MAX_LIMIT = 100;

    // 处理发票状态
    public function handle()
    {
        $method = $this->argument('method');

        $this->$method();
    }

    // --------------------------------------------------------------------

    /**
     * 发票申请处理
     */
    public function apply()
    {
        // 申请ID
        $invoiceId = $this->argument('id') ? $this->argument('id') : 0;

        // 尾数
        $mantissa = $this->argument('mantissa') ? explode(',', $this->argument('mantissa')) : null;

        $orderInvoiceService = new OrderInvoice();
        $orderInvoiceModel = new OrderInvoiceModel();

        echo "ORDER INVOICE APPLY HANDLE ORDER MT:{$mantissa} START \n";

        $offset = 0;
        // 获取待申请的发票记录
        $page = 1;
        while($page < 99999)
        {
            // 获取待处理的订单发票
            $orderInvoiceList = $orderInvoiceModel->getUnProcessOrderInvoice($invoiceId, 1, NULL, self::PER_MAX_LIMIT);

            if (empty($orderInvoiceList))
            {
                break;
            }

            foreach ($orderInvoiceList as $orderInvoice)
            {
                echo $orderInvoice['invoice_id']. " ". $orderInvoice['order_id'];

                $errMsg = '';
                $result = $orderInvoiceService->apply($orderInvoice['order_id'], NULL, NULL, $errMsg, $orderInvoice['invoice_id']);

                echo $result ? "OK\n" : $errMsg. "\n";

                $invoiceId = $orderInvoice['invoice_id'];
            }

            $page++;
        }

        echo "ORDER INVOICE APPLY HANDLE ORDER MT:{$mantissa} END \n";
    }

}
