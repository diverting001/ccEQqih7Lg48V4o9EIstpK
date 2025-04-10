<?php

namespace SyncServer ;
use Thrift\Protocol\TBinaryProtocol;
use Thrift\Transport\TSocket;
use Thrift\Transport\TBufferedTransport;
use Thrift\Transport\TTransport;
use Thrift\Exception\TException;
use Thrift\ClassLoader\ThriftClassLoader;
defined("THRIFT_LIB_PATH") or define('THRIFT_LIB_PATH' , dirname(dirname(__DIR__)) . '/lib/php/lib') ;
defined("GEN_PHP_PATH") or define('GEN_PHP_PATH' , dirname(__DIR__) . '/gen-php') ;
if(!class_exists('Thrift\ClassLoader\ThriftClassLoader')) {
    require_once THRIFT_LIB_PATH  . '/Thrift/ClassLoader/ThriftClassLoader.php';
}

class AgentClient
{
    private $client;
    private $transport;
    protected $timeout = 1000 ;
    protected $persist = false  ;

    //
    public function __construct($host,$port, $timeout =1000 , $persist=false)
    {
        $this->timeout =$timeout ;
        $this->persist = $persist ;
        $this->connect($host ,$port ,$this->persist) ;
    }

    protected function connect($host,$port,$persist) {

        $loader = new ThriftClassLoader();
        $loader->registerNamespace('Thrift', THRIFT_LIB_PATH);
        $loader->registerDefinition('SyncServer', GEN_PHP_PATH);
        $loader->register();

        $timeout = $this->timeout ;
        try {
            $socket = new TSocket($host, $port ,$persist);
            $socket->setSendTimeout($timeout * 1000); // 毫秒
            $socket->setRecvTimeout($timeout * 1000); // 毫秒
            $this->transport = new TBufferedTransport($socket);
            $protocol = new TBinaryProtocol($this->transport);
            $this->client = new \SyncServer\SyncServerClient($protocol);
        }catch (TException $tx) {
            \Neigou\Logger::General('Tools_rpc_error' ,array('err_msg' =>$tx->getMessage() ,'err_code' =>$tx->getCode() )) ;
            $msg  = $tx->getMessage()  ;
            $msg .= " host={$host}:{$port}" ;
            echo $msg ,"\n" ;
            return false ;
        }
        $this->transport->open();
    }

    public function isConnected() {
        if ($this->transport) {
            return $this->transport->isOpen();
        }
        return false;
    }

    // 代理到具体方法
    public function agent($paramsJson ,$jobId) {
        if (!$this->isConnected()) {
            return false;
        }
        try {
            $result =   $this->client->agent($paramsJson ,$jobId) ;
            \Neigou\Logger::Debug("taskWorkerLocalWebRpc" ,array('params_json' => base64_decode($paramsJson) , 'jov_id' => base64_decode($jobId) ,'res' => $result) ) ;
            return $result ;
        } catch (TException $e) {
            $errorMsg = $e->getMessage();
            \Neigou\Logger::Debug("taskWorkerLocalWebRpc" ,array('params_json' => base64_decode($paramsJson) , 'jov_id' => base64_decode($jobId) ,'err' => $errorMsg) ) ;
            return false;
        }
    }

    public function health()
    {
        return $this->client->health() ;
    }

    public function close() {
        $this->transport->close();
    }

    public function __destruct()
    {
         $this->close();
    }
}
