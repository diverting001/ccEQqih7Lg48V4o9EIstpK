<?php

use Thrift\ClassLoader\ThriftClassLoader;
use Thrift\Protocol\TBinaryProtocol;
use Thrift\Transport\TSocket;
use Thrift\Transport\THttpClient;
use Thrift\Transport\TBufferedTransport;
use Thrift\Exception\TException;
defined("THRIFT_LIB_PATH") or define('THRIFT_LIB_PATH' , dirname(dirname(__DIR__)) . '/lib/php/lib') ;
defined("GEN_PHP_PATH") or define('GEN_PHP_PATH' , dirname(__DIR__) . '/gen-php') ;
if(!class_exists('Thrift\ClassLoader\ThriftClassLoader')) {
    require_once THRIFT_LIB_PATH  . '/Thrift/ClassLoader/ThriftClassLoader.php';
}
if(!class_exists('\\Neigou\\Logger'))
    die("please include the NG_PHP_LIB/Logger.php Plugin");

class ThriftSMSClient {
    public static function sendSMS($mobile, $sms_content,$message_channel = "") {
        $GEN_DIR = realpath(dirname(__FILE__)) . '/../gen-php';

        $loader = new ThriftClassLoader();
        $loader->registerNamespace('Thrift', THRIFT_LIB_PATH);
        $loader->registerDefinition('SMSServer', $GEN_DIR);
        $loader->register();

        try {
            $socket = new TSocket(PSR_THRIFT_SMS_HOST, PSR_THRIFT_SMS_PORT);
            $socket->setSendTimeout(30000);
            $socket->setRecvTimeout(30000);
            $transport = new TBufferedTransport($socket, 1024, 1024);
            $protocol = new TBinaryProtocol($transport);
            $client = new \SMSServer\SMSServerClient($protocol);

            $transport->open();
            $params_array = array('mobile'=>$mobile, 'sms_content'=>$sms_content,'message_channel'=>$message_channel);
            if ($transport->isOpen()) {
                \Neigou\Logger::General('action.SMSServerClient', array('action'=>'sendSMSServerClient', 'success'=>1,'data'=>json_encode($params_array)));
                $result = $client->sendSMS(json_encode($params_array));
            }
            $transport->close();

            return $result;
        } catch (TException $tx) {
            $errorMsg = $tx->getMessage();
            \Neigou\Logger::General('action.SMSServerClient', array('action'=>'sendSMSServerClient', 'success'=>0,'data'=>$sms_content,'iparam1'=>$mobile,'sparam1'=>$errorMsg));
            return 'fail';
        }
    }

    public static function b2cSendSMS($mobile, $sms_content,$message_channel = "") {
        $GEN_DIR = realpath(dirname(__FILE__)) . '/../gen-php';

        $loader = new ThriftClassLoader();
        $loader->registerNamespace('Thrift', __DIR__ . '/../../lib/php/lib');
        $loader->registerDefinition('SMSServer', $GEN_DIR);
        $loader->register();

        try {
            $socket = new TSocket(PSR_THRIFT_SMS_HOST, PSR_THRIFT_SMS_PORT);
            $socket->setSendTimeout(10000);
            $socket->setRecvTimeout(10000);
            $transport = new TBufferedTransport($socket, 1024, 1024);
            $protocol = new TBinaryProtocol($transport);
            $client = new \SMSServer\SMSServerClient($protocol);
            $transport->open();
            $params_array = array('mobile'=>$mobile, 'sms_content'=>$sms_content,'message_channel'=>$message_channel);
            $result = $client->b2cSendSMS(json_encode($params_array));
            $transport->close();
            \Neigou\Logger::General('action.SMSServerClient', array('action'=>'b2cSendSMSServerClient', 'success'=>1,'data'=>json_encode($params_array)));
            return $result;
        } catch (TException $tx) {
            $errorMsg = $tx->getMessage();
            \Neigou\Logger::General('action.SMSServerClient', array('action'=>'b2cSendSMSServerClient', 'success'=>0,'data'=>$sms_content,'iparam1'=>$mobile,'sparam1'=>$errorMsg));
            return 'fail';
        }
    }

    public static function diandiSendSMS($mobile, $sms_content,$message_channel = "") {
        $GEN_DIR = realpath(dirname(__FILE__)) . '/../gen-php';

        $loader = new ThriftClassLoader();
        $loader->registerNamespace('Thrift', __DIR__ . '/../../lib/php/lib');
        $loader->registerDefinition('SMSServer', $GEN_DIR);
        $loader->register();

        try {
            $socket = new TSocket(PSR_THRIFT_SMS_HOST, PSR_THRIFT_SMS_PORT);
            $socket->setSendTimeout(30000);
            $socket->setRecvTimeout(30000);
            $transport = new TBufferedTransport($socket, 1024, 1024);
            $protocol = new TBinaryProtocol($transport);
            $client = new \SMSServer\SMSServerClient($protocol);

            $transport->open();
            $params_array = array('mobile'=>$mobile, 'sms_content'=>$sms_content,'message_channel'=>$message_channel);
            if ($transport->isOpen()) {
                \Neigou\Logger::General('action.SMSServerClient', array('action'=>'dianSendSMSServerClient', 'success'=>1,'data'=>json_encode($params_array)));
                $result = $client->diandiSendSMS(json_encode($params_array));
            }
            $transport->close();

            return $result;
        } catch (TException $tx) {
            $errorMsg = $tx->getMessage();
            \Neigou\Logger::General('action.SMSServerClient', array('action'=>'dianSendSMSServerClient', 'success'=>0,'data'=>$sms_content,'iparam1'=>$mobile,'sparam1'=>$errorMsg));
            return 'fail';
        }
    }
    
    
    public static function diandiB2cSendSMS($mobile, $sms_content,$type,$message_channel = "") {
        $GEN_DIR = realpath(dirname(__FILE__)) . '/../gen-php';

        $loader = new ThriftClassLoader();
        $loader->registerNamespace('Thrift', __DIR__ . '/../../lib/php/lib');
        $loader->registerDefinition('SMSServer', $GEN_DIR);
        $loader->register();

        try {
            $socket = new TSocket(PSR_THRIFT_SMS_HOST, PSR_THRIFT_SMS_PORT);
            $socket->setSendTimeout(10000);
            $socket->setRecvTimeout(10000);
            $transport = new TBufferedTransport($socket, 1024, 1024);
            $protocol = new TBinaryProtocol($transport);
            $client = new \SMSServer\SMSServerClient($protocol);

            $transport->open();
            $params_array = array('mobile'=>$mobile, 'sms_content'=>$sms_content,'type'=>$type,'message_channel'=>$message_channel);
            if ($transport->isOpen()) {
                \Neigou\Logger::General('action.SMSServerClient', array('action'=>'dianDiB2cSendSMSServerClient', 'success'=>1,'data'=>json_encode($params_array)));
                $result = $client->diandiB2cSendSMS(json_encode($params_array));
            }
            $transport->close();

            return $result;
        } catch (TException $tx) {
            $errorMsg = $tx->getMessage();
            \Neigou\Logger::General('action.SMSServerClient', array('action'=>'dianDiB2cSendSMSServerClient', 'success'=>0,'data'=>$sms_content,'iparam1'=>$mobile,'sparam1'=>$errorMsg));
            return 'fail';
        }
    }
};

?>
