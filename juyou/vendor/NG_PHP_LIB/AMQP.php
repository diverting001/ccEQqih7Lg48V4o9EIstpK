<?php
namespace Neigou;
@include_once dirname(__FILE__).'/vendor/autoload.php';
@include_once dirname(__FILE__).'/Config.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
class AMQP{
    private $_error = '';
    private $_host   = '';
    private $_port   = '';
    private $_user   = '';
    private $_password   = '';
    private $_amqp_connection    = null;


    public function __construct($is_new=false){
        $this->_host= SERVICE_MQ_HOST;
        $this->_port= SERVICE_MQ_PORT;
        $this->_user    = SERVICE_MQ_USER;
        $this->_password    = SERVICE_MQ_PASSWORD;
        $this->Connection();
    }

    public function __destruct(){
        $this->Close();
    }

    /*
     * @todo 发送消息
     * $channel_name 交换机名
     */
    public function PublishMessage($channel_name,$routing_key,$msg){
        if(empty($channel_name) || empty($msg) || !is_array($msg) || empty($routing_key)) return $this->SetError('参数错误');
        if(!$this->Connection()) return false;
        $channel = $this->_amqp_connection->channel();
        $channel->exchange_declare($channel_name, 'topic', false, true, false);
        //消息
        $msg_obj = new AMQPMessage(json_encode($msg),array('delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT));
        //推送消息
        $res = $channel->basic_publish($msg_obj, $channel_name,$routing_key);
        \Neigou\Logger::Debug('publish_rabbitmq',array('sender'=>json_encode($msg),'bn'=>$routing_key));
        //关闭链接
        $channel->close();
        $this->Close();
        return $res;
    }

    /*
     * @todo 处理消息
     */
    public function ConsumeMessage($queue_name,$channel_name,$routing_key, $callback){
        if(empty($queue_name) || empty($channel_name) || empty($routing_key)) return $this->SetError('参数错误');
        if(!$this->Connection()) return false;
        $channel = $this->_amqp_connection->channel();
        //队列进行绑定交换机
        $channel->queue_declare($queue_name, false, true, false, false);
        $channel->queue_bind($queue_name, $channel_name,$routing_key);
        //处理
        $my_callback = function($msg) use ($callback){
            $msg_data   = json_decode($msg->body,true);
            $res = call_user_func($callback,$msg_data);
            if($res){
                //处理成功，进行消息确认
                $msg->delivery_info [ 'channel' ]->basic_ack($msg->delivery_info['delivery_tag']);
            }else{
                //保存错误处理
                $ack_function   = function() use($msg){
                    $msg->delivery_info [ 'channel' ]->basic_ack($msg->delivery_info['delivery_tag']);
                };
//                $this->SaveFileMessage($msg->body,$ack_function,function(){});
                $mq = new \Neigou\AMQP();
                $mq->SaveFileMessage($msg->body,$ack_function,function(){});
            }
            return ;
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

    /*
     * @todo 保存错误处理
     * $msg 需要保存的消息
     * $ack_callback 保存成功后的回调处理函数
     * $nack_callback 保存失败后的回调处理函数
     */
    public function SaveFileMessage($msg,$ack_callback,$nack_callback){
        $dead_letter_exchange = 'dead_letter_exchange';
        $channel = $this->_amqp_connection->channel();
        $channel->set_ack_handler($ack_callback);
        $channel->set_nack_handler($nack_callback);
        $channel->confirm_select();
        $channel->queue_declare($dead_letter_exchange, false, false, false, false);
        $msg_obj = new AMQPMessage($msg);
        $res = $channel->basic_publish($msg_obj, '', $dead_letter_exchange);
        $channel->wait_for_pending_acks();
        $channel->close();
        return $res;
    }


    private function SetError($error_msg){
        $this->_error   = $error_msg;
        return false;
    }

    /*
     * @todo 链接
     */
    private function Connection(){
        if(is_null($this->_amqp_connection)){
            try{
                $this->_amqp_connection = new AMQPStreamConnection($this->_host,$this->_port,$this->_user,$this->_password);
            }catch (Exception $e){
                $this->SetError('链接失败');
                return false;
            }
        }
        return true;
    }


    /*
     * @todo 关闭链接
     */
    private function Close(){
        if(is_object($this->_amqp_connection)){
            $this->_amqp_connection->close();
        }
        $this->_amqp_connection = null;
    }
}
?>