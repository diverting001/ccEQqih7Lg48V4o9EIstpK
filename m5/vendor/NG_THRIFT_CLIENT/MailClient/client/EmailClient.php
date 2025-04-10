<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2015/8/11 0011
 * Time: 16:32
 */
namespace EmailServer;
use EmailServer\EmailServerClient;
use Thrift\ClassLoader\ThriftClassLoader;
use Thrift\Exception\TException;
use Thrift\Protocol\TBinaryProtocol;
use Thrift\Transport\TBufferedTransport;
use Thrift\Transport\TSocket;

defined("THRIFT_LIB_PATH") or define('THRIFT_LIB_PATH' , dirname(dirname(__DIR__)) . '/lib/php/lib') ;
defined("GEN_PHP_PATH") or define('GEN_PHP_PATH' , dirname(__DIR__) . '/gen-php') ;
if(!class_exists('Thrift\ClassLoader\ThriftClassLoader')) {
    require_once THRIFT_LIB_PATH  . '/Thrift/ClassLoader/ThriftClassLoader.php';
}

$GEN_DIR =  dirname(__DIR__).'/gen-php';
$loader = new ThriftClassLoader();
$loader->registerNamespace('Thrift', THRIFT_LIB_PATH);
$loader->registerDefinition('EmailServer', $GEN_DIR);
$loader->register();
if(!class_exists('\\Neigou\\Logger'))
    die("please include the NG_PHP_LIB/Logger.php Plugin");

class ThriftEmailClient
{
    private $client;
    private $transport;

    public function __construct()
    {
        try {
            $socket=new TSocket(PSR_THRIFT_MAIL_HOST, PSR_THRIFT_MAIL_PORT);
            $socket->setSendTimeout(10000);
            $socket->setRecvTimeout(10000);
            $this->transport=new TBufferedTransport($socket, 1024, 1024);
            $protocol=new TBinaryProtocol($this->transport);
            $this->client=new EmailServerClient($protocol);
            $this->transport->open();
        } catch (TException $e) {

        }
    }

    public function isConnected() {
        if ($this->transport) {
            return $this->transport->isOpen();
        }
        return false;
    }

    public function sendEmail($to, $title, $type, $data)
    {
        if (!$this->isConnected()) {
            return false;
        }
        try {
            \Neigou\Logger::General('action.EmailServerClient', array('action'=>'sendEmailServerClient', 'success'=>1,'data'=>$data,'sparam1'=>$to,'sparam2'=>$title,'sparam3'=>$type));
            return $this->client->sendEmail($to, $title, $type, $data);
        } catch (TException $e) {
            $errorMsg = $e->getMessage();
            \Neigou\Logger::General('action.EmailServerClient', array('action'=>'sendEmailServerClient', 'success'=>1,'data'=>$data,'sparam1'=>$to,'sparam2'=>$title,'sparam3'=>$type,'sparam4'=>$errorMsg));
            return false;
        }
    }
    public function sendEmailByContent($to,$title,$content){
        if (!$this->isConnected()) {
            return false;
        }
        try {
            \Neigou\Logger::General('action.EmailServerClient', array('action'=>'sendEmailByContentServerClient', 'success'=>1,'data'=>$content,'sparam1'=>$to,'sparam2'=>$title));
            return $this->client->sendEmailByContent($to,$title,$content);
        } catch (TException $e) {
            $errorMsg = $e->getMessage();
            \Neigou\Logger::General('action.EmailServerClient', array('action'=>'sendEmailByContentServerClient', 'success'=>0,'data'=>$content,'sparam1'=>$to,'sparam2'=>$title,'sparam3'=>$errorMsg));
            return false;
        }
    }
    
    public function sendDianDiEmail($to, $title, $type, $data)
    {
        if (!$this->isConnected()) {
            return false;
        }
        try {
            \Neigou\Logger::General('action.EmailServerClient', array('action'=>'sendDianDiEmailServerClient', 'success'=>1,'data'=>$data,'sparam1'=>$to,'sparam2'=>$title,'sparam3'=>$type));
            return $this->client->sendDianDiEmail($to, $title, $type, $data);
        } catch (TException $e) {
            $errorMsg = $e->getMessage();
            \Neigou\Logger::General('action.EmailServerClient', array('action'=>'sendDianDiEmailServerClient', 'success'=>1,'data'=>$data,'sparam1'=>$to,'sparam2'=>$title,'sparam3'=>$type,'sparam4'=>$errorMsg));
            return false;
        }
    }
    
    public function sendDianDiEmailByContent($to,$title,$content){
        if (!$this->isConnected()) {
            return false;
        }
        try {
            \Neigou\Logger::General('action.EmailServerClient', array('action'=>'sendDianDiEmailByContentServerClient', 'success'=>1,'data'=>$content,'sparam1'=>$to,'sparam2'=>$title));
            return $this->client->sendDianDiEmailByContent($to,$title,$content);
        } catch (TException $e) {
            $errorMsg = $e->getMessage();
            \Neigou\Logger::General('action.EmailServerClient', array('action'=>'sendDianDiEmailByContentServerClient', 'success'=>0,'data'=>$content,'sparam1'=>$to,'sparam2'=>$title,'sparam3'=>$errorMsg));
            return false;
        }
    }

    public function __destruct()
    {
        $this->transport->close();
    }
}
