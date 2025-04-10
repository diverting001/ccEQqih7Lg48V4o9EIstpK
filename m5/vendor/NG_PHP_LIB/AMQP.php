<?php
namespace Neigou;
@include_once dirname( __FILE__ ) . '/vendor/autoload.php';
@include_once dirname( __FILE__ ) . '/Config.php';
@include_once dirname( __FILE__ ) . '/RabbitMqRetry.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class AMQP
{
    private $_error = '';
    private $_host = '';
    private $_port = '';
    private $_user = '';
    private $_password = '';
    private $_amqp_connection = null;
    private $_connect = '';
    private $_vhost = '/';
    private $_insist = false;
    private $_login_method = 'AMQPLAIN';
    private $_login_response = null;
    private $_locale = 'en_US';
    private $_connection_timeout = 3.0;
    private $_read_write_timeout = 3.0;

    private $waiting_num = 0;
    public $queue_lock_key;


    public function __construct($connect = '', $retry_default = true)
    {
        if ($connect == 'goods') {
            $this->_connection_timeout = 1.0;
            $this->_host = SERVICE_MQ_GOODS_HOST;
            $this->_port = SERVICE_MQ_GOODS_PORT;
            $this->_user = SERVICE_MQ_GOODS_USER;
            $this->_password = SERVICE_MQ_GOODS_PASSWORD;
            if ($this->Connection()) {
                $this->_connect = 'goods';
                return;
            }
            if ( ! $retry_default) {
                return;
            }
        }
        $this->_host = SERVICE_MQ_HOST;
        $this->_port = SERVICE_MQ_PORT;
        $this->_user = SERVICE_MQ_USER;
        $this->_password = SERVICE_MQ_PASSWORD;
        $this->Connection();
    }

    public function __destruct()
    {
        $this->Close();
    }

    /*
     * @todo 发送消息
     * $channel_name 交换机名 exchange
     */
    public function PublishMessage( $channel_name, $routing_key, $msg ,$logger = true)
    {
        if ( empty( $channel_name ) || empty( $msg ) || !is_array( $msg ) || empty( $routing_key ) ) return $this->SetError( '参数错误' );
        if ( !$this->Connection()) {
            $this->saveLogger($channel_name, 'publish_message_fail', $msg ,$routing_key, true);
            return false;
        }
        $channel = $this->_amqp_connection->channel();
        $channel->exchange_declare( $channel_name, 'topic', false, true, false );
        //消息
        $msg_obj = new AMQPMessage( json_encode( $msg ), array( 'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT ) );
        //推送消息
        $res = $channel->basic_publish( $msg_obj, $channel_name, $routing_key );

        if($logger)
        {
            $this->saveLogger( $channel_name, 'publish_message', $msg ,$routing_key);
        }

        //关闭链接
        $channel->close();
        $this->Close();
        return $res;
    }

    /**单个消息消费
     * @param $queue_name string 队列名称
     * @param $channel_name string 交换机名称
     * @param $routing_key string 路由标识
     * @param $callback array 批量消息获取后回调
     * @param array $extraParams array 额外参数[is_retry-重试/delay_level--重试级别/disabled_logger-是否禁用日志]
     * @return bool
     */
    public function ConsumeMessage( $queue_name, $channel_name, $routing_key, $callback, $extraParams = array() )
    {
        if ( empty( $queue_name ) || empty( $channel_name ) || empty( $routing_key ) ) return $this->SetError( '参数错误' );
        if ( !$this->Connection() ) return false;
        $channel = $this->_amqp_connection->channel();
        //队列进行绑定交换机
        $channel->queue_declare( $queue_name, false, true, false, false );
        $channel->queue_bind( $queue_name, $channel_name, $routing_key );
        $connect = $this->_connect;

        //处理
        $my_callback = function ( $msg ) use ( $callback, $channel_name, $queue_name, $extraParams ,$routing_key, $connect) {
            $msg_data = json_decode( $msg->body, true );
            $res = call_user_func( $callback, $msg_data );

            //是否禁用日志
            $disabled_logger = (isset( $extraParams['disabled_logger'] ) && $extraParams['disabled_logger'] == true) ? true : false;
            if ( !$disabled_logger )
            {
                call_user_func( array( new AMQP(), 'saveLogger' ), $channel_name . '_' . $queue_name, 'consume_message', $msg ,$routing_key);
            }

            if ( $res ) {
                //处理成功，进行消息确认
                $msg->delivery_info ['channel']->basic_ack( $msg->delivery_info['delivery_tag'] );
            } else {

                //是否重试
                if ( $extraParams['is_retry'] ) {
                    $mq_retry = new RabbitMqRetry( $msg, $queue_name, $extraParams['delay_level'] );
                    $retry_result = $mq_retry->_do( array( $msg_data ) , $connect);

                    //监控
                    if ( !$retry_result ) {
                        \Neigou\Logger::General( 'rabbit_mq', array( 'action' => 'retry_do_consume_fail', 'remark' => json_encode(array('msg'=>$msg, 'queue_name'=>$queue_name, 'msg_data'=>$msg_data)) ) );
                    }

                    call_user_func( array( new AMQP(), 'saveLogger' ), $channel_name . '_' . $queue_name, 'consume_message_retry', $msg ,$routing_key);

                    $msg->delivery_info ['channel']->basic_ack( $msg->delivery_info['delivery_tag'] );

                } else {
                    //保存错误处理
                    $ack_function = function () use ( $msg ) {
                        $msg->delivery_info ['channel']->basic_ack( $msg->delivery_info['delivery_tag'] );
                    };
                    $mq = new \Neigou\AMQP($connect);
                    $mq->SaveFileMessage( $msg->body, $ack_function, function () {
                    } );
                }
            }
        };

        $channel->basic_consume($queue_name, '', false, false, false, false, $my_callback);

        //进行等待处理
        while(count($channel->callbacks)) {
            $channel->wait(null,false,5);
        }
        //关闭链接
        $channel->close();
        $this->Close();
        return true;
    }

    /**
     * TODO 批量发布
     * @param string $exchange
     * @param string $routing_key
     * @param array $batch_message
     * @return bool
     */
    public function BatchPublishMessage( $exchange = '', $routing_key = '', $batch_message = array() )
    {
        if ( empty( $exchange ) || empty( $batch_message ) || !is_array( $batch_message ) || empty( $routing_key ) ) return $this->SetError( '参数错误' );

        if ( !$this->Connection()) {
            $this->saveLogger($exchange, 'batch_publish_message_fail', $batch_message, $routing_key, true);
            return false;
        }

        $channel = $this->_amqp_connection->channel();

        $channel->exchange_declare( $exchange, 'topic', false, true, false );

        foreach ( $batch_message as $message ) {

            $this->saveLogger( $exchange, 'batch_publish_message', $message ,$routing_key);

            $message = new AMQPMessage( json_encode( $message ), array( 'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT ) );

            $channel->batch_basic_publish( $message, $exchange, $routing_key );
        }

        $channel->publish_batch();

        //关闭链接
        $channel->close();
        $this->_amqp_connection->close();
        $this->Close();

        return true;
    }

    /** TODO 批量消息处理
     * @param $queue_name string 队列名称
     * @param $exchange  string 交换机名称
     * @param $routing_key string 路由标识
     * @param $callback array 批量消息获取后回调
     * @param $counter int 批量获送数量
     * @param $extraParams array 额外参数[is_retry-重试/delay_level--重试级别/disabled_logger-是否禁用日志]
     * @param $unlock bool
     * @return bool
     */
    public function BatchConsumeMessage( $queue_name, $exchange, $routing_key, $callback, $counter = 50, $extraParams = array(), $unlock = false )
    {
        if ( empty( $queue_name ) || empty( $exchange ) || empty( $routing_key ) ) return $this->SetError( '参数错误' );

        if ( !$this->Connection() ) return false;

        //获取信道
        $channel = $this->_amqp_connection->channel();

        //交换机持久化
        $channel->exchange_declare( $exchange, 'topic', false, true, false );
        //队列持久化
        $channel->queue_declare( $queue_name, false, true, false, false );
        //绑定
        $channel->queue_bind( $queue_name, $exchange, $routing_key );
        //调度分发条数
        $channel->basic_qos( null, 1, null );

        //批量消息
        $batch_message = array();

        $ind = 0;
        $redis_lock_key = 'mq_' .$routing_key.'_'. $exchange . '_' . $queue_name;
        $this->queue_lock_key = $redis_lock_key;
        
        if ( $unlock ) {
            $this->unlock( $redis_lock_key );
            echo "\n unlock success \n";
            exit;
        }

        if ( !$this->lock( $redis_lock_key ) ) {
            echo "\n this queue is locked \n";
            exit;
        }

        //是否禁用日志
        $disabled_logger = (isset( $extraParams['disabled_logger'] ) && $extraParams['disabled_logger'] == true) ? true : false;
        $connect = $this->_connect;

        while ( true ) {

            $message = $channel->basic_get();

            //debug
            if ( $disabled_logger )
            {
                $log = array(
                    'body_size' => $message->body_size,
                    'delivery_info' => $message->delivery_info
                );
                \Neigou\Logger::Debug( 'canal.debug', array( 'data' => $log ) );
            }

            if ( $message != null ) {
                $this->waiting_num = 0;

                $batch_message[] = json_decode( $message->body, true );

                if ( !$disabled_logger )
                {
                    $this->saveLogger( $exchange . '_' . $queue_name, 'batch_consume_message', $message->body, $routing_key );
                }

                if ( $ind % $counter == $counter - 1 || $message->delivery_info['message_count'] == 0 ) {

                    $callback_result = call_user_func( $callback, $batch_message );

                    //是否重试
                    $retry_result = true;
                    if ( !$callback_result && $extraParams['is_retry'] ) {
                        $mq_retry = new RabbitMqRetry( $message, $queue_name, $extraParams['delay_level'] );
                        $retry_result = $mq_retry->_do( $batch_message , $connect);

                        //logger
                        foreach ( $batch_message as $batch_item )
                        {
                            $this->saveLogger( $exchange . '_' . $queue_name, 'batch_consume_message_retry', $batch_item, $routing_key );
                        }
                    }

                    //监控
                    if ( !$retry_result ) {
                        \Neigou\Logger::General( 'rabbit_mq', array( 'action' => 'retry_do_batch_consume_fail', 'remark' => json_encode(array('message'=>$message, 'queue_name'=>$queue_name, 'set_retry'=>$extraParams, 'batch_message'=>$batch_message)) ) );
                    }

                    $channel->basic_ack( $message->delivery_info['delivery_tag'], true );

                    $batch_message = array();
                }

                $ind++;
            } else {

                $ind = 0;

                //waiting
                if ( $this->waiting_num <= 5 )
                {
                    sleep( 1 );
                    $this->waiting_num++;
                }
                else
                {
                    $channel->close();
                    $this->Close();
                    $this->unlock( $redis_lock_key );
                }
            }
        }
    }

    /*
     * @todo 保存错误处理
     * $msg 需要保存的消息
     * $ack_callback 保存成功后的回调处理函数
     * $nack_callback 保存失败后的回调处理函数
     */
    public function SaveFileMessage( $msg, $ack_callback, $nack_callback )
    {
        $dead_letter_exchange = 'dead_letter_exchange_v2';
        $channel = $this->_amqp_connection->channel();
        $channel->set_ack_handler( $ack_callback );
        $channel->set_nack_handler( $nack_callback );
        $channel->confirm_select();
        $channel->queue_declare( $dead_letter_exchange, false, false, false, false );
        $msg_obj = new AMQPMessage( $msg );
        $res = $channel->basic_publish( $msg_obj, '', $dead_letter_exchange );
        $channel->wait_for_pending_acks();
        $channel->close();
        return $res;
    }

    private function SetError( $error_msg )
    {
        $this->_error = $error_msg;
        return false;
    }

    /*
     * @todo 链接
     */
    private function Connection()
    {
        if ( is_null( $this->_amqp_connection ) ) {
            try {
                $this->_amqp_connection = new AMQPStreamConnection( $this->_host, $this->_port, $this->_user, $this->_password, $this->_vhost, $this->_insist, $this->_login_method, $this->_login_response, $this->_locale, $this->_connection_timeout, $this->_read_write_timeout);
            } catch (\Exception $e ) {
                $this->SetError( '链接失败' );
                return false;
            }
        }
        return true;
    }


    /*
     * @todo 关闭链接
     */
    private function Close()
    {
        if ( is_object( $this->_amqp_connection ) ) {
            $this->_amqp_connection->close();
        }
        $this->_amqp_connection = null;
    }


    public function getChannel()
    {
        $channel = $this->_amqp_connection->channel();
        if ( $channel ) {
            return $channel;
        }

        return false;
    }

    public function saveLogger( $key = '', $action = '', $message = '' ,$target = '', $error = false)
    {
        $time = explode( ' ', microtime() );
        $microtime = date( 'Y-m-d H:i:s' ) . ' ' . $time[0];

        $log = array(
            'action' => $action,
            'target' => $target,
            'data' => json_encode( $message ),
            'remark' => $microtime
        );
        if ($error) {
            \Neigou\Logger::General( $key, $log );
        } else {
            \Neigou\Logger::Debug( $key, $log );
        }

    }

    private function lock( $lock_key = '' )
    {
        if ( !$lock_key ) {
            return false;
        }

        $persistent_id = md5( $lock_key );
        $redis = new \Neigou\RedisNeigou( $persistent_id );

        if ( $redis->_redis_connection->get( $lock_key ) ) {
            return false;
        }

        return $redis->_redis_connection->set( $lock_key, true, 1800 );
    }

    public function unlock( $lock_key = '' )
    {
        if ( !$lock_key ) {
            return false;
        }

        $persistent_id = md5( $lock_key );
        $redis = new \Neigou\RedisNeigou( $persistent_id );
        return $redis->_redis_connection->del( $lock_key);
    }
}

?>
