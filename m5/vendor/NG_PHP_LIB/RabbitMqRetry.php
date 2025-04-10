<?php
/**
 *Create by PhpStorm
 *User:liangtao
 *Date:2020-8-6
 */

namespace Neigou;
use PhpAmqpLib\Message\AMQPMessage;

@include_once dirname( __FILE__ ) . '/vendor/autoload.php';
@include_once dirname( __FILE__ ) . '/Config.php';

class RabbitMqRetry
{
    private static $mq_retry_exchange = MQ_RETRY_EXCHANGE;
    private static $mq_retry_routing_key = MQ_RETRY_ROUTING_KEY;
    private static $system_retry_level = array(
        'real' => array(//级别
            1 => 1,//次数=>时间(秒)
            2 => 1,
            3 => 1
        ),
        'general' => array(
            1 => 60,
            2 => 300,
            3 => 600
        ),
        'slow' => array(
            1 => 1800,
            2 => 3600,
            3 => 7200
        )
    );

    private $current_delay_level;

    public $original_mq_exchange;
    public $original_mq_routing_key;
    public $original_mq_queue;

    public function __construct( $original_mq_message = array(), $original_mq_queue = '', $delay_level = 'general' )
    {
        $this->original_mq_exchange = $original_mq_message->delivery_info['exchange'];
        $this->original_mq_routing_key = $original_mq_message->delivery_info['routing_key'];
        $this->original_mq_queue = $original_mq_queue;
        $this->current_delay_level = $delay_level;
    }

    private function check()
    {
        if (!$this->original_mq_queue )
        {
            \Neigou\Logger::General( 'mq_retry_error', array( 'action' => 'retry_do_fail', 'remark' => json_encode( array( $this->original_mq_exchange, $this->original_mq_routing_key ) ) ) );
            return false;
        }

        if ( !in_array( strtolower( $this->current_delay_level ), array( MQ_RETRY_LEVEL_REAL, MQ_RETRY_LEVEL_GENERAL, MQ_RETRY_LEVEL_SLOW ) ) )
        {
            \Neigou\Logger::General( 'mq_retry_error', array( 'action' => 'retry_do_fail', 'remark' => 'delay level fail' . $this->current_delay_level ) );
            return false;
        }

        return true;
    }

    private function getRetryCount()
    {
        $current_delay_level = self::$system_retry_level[$this->current_delay_level];

        return count( $current_delay_level );
    }

    private function getRetryTime( $retry_surplus_num )
    {
        if ( $retry_surplus_num == 0 )
        {
            return 0;
        }

        $current_delay_level = self::$system_retry_level[$this->current_delay_level];

        $delay_level_count = count( $current_delay_level );

        $current_surplus_num = ($delay_level_count - $retry_surplus_num) + 1;

        return $current_delay_level[$current_surplus_num];
    }

    /**
     * 放入重试队列中
     * @param array $batch_message
     * @return bool
     */
    public function _do( $batch_message = array(), $connect = '')
    {
        if ( !is_array( $batch_message ) || !$batch_message )
        {
            return false;
        }

        if ( !$this->check() )
        {
            return false;
        }

        $time = time();
        foreach ( $batch_message as &$message_body )
        {

            if ( $message_body['retry_surplus_num'] )
            {
                $message_body['retry_surplus_num'] = $message_body['retry_surplus_num'] - 1;
                $message_body['retry_per_time'] = $time + $this->getRetryTime( $message_body['retry_surplus_num'] );
                continue;
            }

            //重试init
            {
                $message_body['retry_surplus_num'] = $this->getRetryCount();
                $message_body['retry_per_time'] = $time + $this->getRetryTime( $message_body['retry_surplus_num'] );

                //原信息
                $message_body['original_mq_queue'] = $this->original_mq_queue;
            }
        }

        try
        {

            $mq = new \Neigou\AMQP($connect);
            $channel = $mq->getChannel();
            $channel->queue_declare( MQ_RETRY_QUEUE, false, true, false, false );
            foreach ( $batch_message as $messageItem )
            {
                $message = new AMQPMessage( json_encode( $messageItem ), array( 'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT ) );
                $channel->batch_basic_publish( $message, '', MQ_RETRY_QUEUE );
            }

            $channel->publish_batch();
            $channel->close();
            return true;

        } catch ( \Exception $exception )
        {
            \Neigou\Logger::General( 'mq_retry_error', array( 'action' => 'retry_do_fail', 'remark' => json_encode( array( 'error_message' => $exception->getMessage(), 'batch_message' => $batch_message ) ) ) );
            return false;
        }
    }
}
