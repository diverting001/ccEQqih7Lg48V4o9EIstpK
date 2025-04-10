<?php
/**
 * Created by phpstorm.
 * User: xuhaohao
 * Date: 2021/11/3
 * Time: 18:11
 */

namespace App\Console\Commands;

use App\Api\Logic\Service;
use Illuminate\Console\Command;

class Promotion extends Command
{
    protected $signature = 'promotion {action}';
    protected $description = '运营活动';

    public function handle()
    {
        set_time_limit(0);

        $action = $this->argument('action');
        switch ($action) {
            case 'return_limit_buy_stock':  //归还限购数量
                $this->return_limit_buy_stock();
                break;
            default:
                throw new \Exception('miss param action');
                break;
        }
    }

    /**
     *
     */
    protected function return_limit_buy_stock()
    {
        $function = function ($data) {
            //日志记录
            \Neigou\Logger::Debug('service_message_return_limit_buy_stock', array('action' => 'init', 'data' => $data));
            $routing_key = $data['routing_key'];
            $data = $data['data'];


            if ($routing_key == 'aftersale.finish.success') {
                if (empty($data['after_sale_bn'])) {
                    echo '售后单号不能为空==='."\n";

                    return false;
                }

                echo '处理售后单'.$data['after_sale_bn'].PHP_EOL;

                return $this->_handlePromotionStock(
                    'promotion_timeBuy_afterSaleUnLock',
                    array('after_sale_bn' => $data['after_sale_bn'])
                );
            } else {
                if (empty($data['order_id'])) {
                    echo '订单号不能为空==='."\n";

                    return false;
                }
            }
            echo '取消订单号'.$data['order_id'].PHP_EOL;
            return $this->_handlePromotionStock(
                'promotion_timeBuy_payedCancelUnLock',
                array('order_id' => $data['order_id'])
            );
        };

        $queryArr = array(
            'payedcancel'             => 'order.payedcancel.success',
            'payed_cancel_for_refund' => 'order.payed_cancel_for_refund.success',
            'after_sale'              => 'aftersale.finish.success',
        );


        $mq = new \Neigou\AMQP();

        foreach ($queryArr as $item) {
            try {
                echo "处理订阅：{$item}".PHP_EOL;
                $mq->ConsumeMessage('return.limit_buy_stock', 'service', $item, $function);
            } catch (\Exception $e) {
                echo '处理完毕'.PHP_EOL;
            }
        }
    }

    private function _handlePromotionStock($serviceName, $data)
    {
        try {
            $serviceObj = new Service();
            $res = $serviceObj->ServiceCall($serviceName ,$data);
            if($res['error_code'] == 'SUCCESS' && !empty($res['data'])) {
                return $res['data'];
            }
            \Neigou\Logger::General('service_message_return_promotion_stock_handle_error', array(
                    'action'       => $serviceName,
                    'request_data' => $data,
                    'response'     => $res
             ));
            return false;
        } catch (\Exception $e) {
            \Neigou\Logger::Debug('service_message_return_promotion_stock_call_error',
                array('action' => 'error', 'data' => $data, 'msg' => $e->getCode().$e->getMessage()));
            return false;
        }
    }
}
