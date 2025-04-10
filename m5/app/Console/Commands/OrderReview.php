<?php
/**
 * Created by PhpStorm.
 * User:
 * Date: 2018/11/15
 * Time: 8:21 PM
 */

namespace App\Console\Commands;

use App\Api\V1\Service\Order\Review as OrderReviewLogic;
use Illuminate\Console\Command;

/**
 * 订单审核 Crontab
 *
 * @package     Console
 * @category    Command
 * @author        xupeng
 */
class OrderReview extends Command
{
    protected $force = '';
    protected $signature = 'orderReviewTask {method} ';

    protected $description = '订单审核';

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
    public function process()
    {
        echo "ORDER REVIEW PROCESS START \n";
        $function = function ($data) {
            $orderId = $data['data']['order_id'];

            $orderReviewLogic = new OrderReviewLogic();

            // 保存订单审核信息
            $errMsg = '';
            $orderReviewLogic->process($orderId, $errMsg);

            return true;
        };
        $amqp = new \Neigou\AMQP();
        $amqp->ConsumeMessage('service_order_pay_review', 'service', 'order.pay.success', $function);

        echo "ORDER REVIEW PROCESS END \n";
    }

}
