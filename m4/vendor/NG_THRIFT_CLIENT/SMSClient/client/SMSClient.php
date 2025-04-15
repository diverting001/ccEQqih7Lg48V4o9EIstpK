<?php

use Thrift\ClassLoader\ThriftClassLoader;
use Thrift\Protocol\TBinaryProtocol;
use Thrift\Transport\TSocket;
use Thrift\Transport\THttpClient;
use Thrift\Transport\TBufferedTransport;
use Thrift\Exception\TException;

require_once __DIR__ . '/../../lib/php/lib/Thrift/ClassLoader/ThriftClassLoader.php';
if(!class_exists('\\Neigou\\Logger'))
    die("please include the NG_PHP_LIB/Logger.php Plugin");

class ThriftSMSClient {
    public static function sendSMS($mobile, $sms_content) {
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
            $params_array = array('mobile'=>$mobile, 'sms_content'=>$sms_content);
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

    public static function b2cSendSMS($mobile, $sms_content) {
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
            $params_array = array('mobile'=>$mobile, 'sms_content'=>$sms_content);
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

    public static function diandiSendSMS($mobile, $sms_content) {
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
            $params_array = array('mobile'=>$mobile, 'sms_content'=>$sms_content);
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
    
    
    public static function diandiB2cSendSMS($mobile, $sms_content,$type) {
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
            $params_array = array('mobile'=>$mobile, 'sms_content'=>$sms_content,'type'=>$type);
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
