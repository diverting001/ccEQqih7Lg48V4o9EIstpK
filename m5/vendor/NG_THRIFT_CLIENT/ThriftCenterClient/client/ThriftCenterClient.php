<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2015/8/11 0011
 * Time: 16:32
 */
namespace ThriftCenter;
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
$loader->registerDefinition('ThriftCenter', $GEN_DIR);
$loader->register(true);
if(!class_exists('\\Neigou\\Logger'))
    die("please include the NG_PHP_LIB/Logger.php Plugin");

class ThriftCenterClientAdapters{
    private $client;
    private $transport;
    public function __construct()
    {
        try {
            $socket=new TSocket(PSR_THRIFT_CENTER_HOST, PSR_THRIFT_CENTER_PORT);
            $socket->setSendTimeout(10000);
            $socket->setRecvTimeout(10000);
            $this->transport=new TBufferedTransport($socket, 1024, 1024);
            $protocol=new TBinaryProtocol($this->transport);
            $this->client=new ThriftCenterClient($protocol);
            $this->transport->open();
        } catch (TException $e) {

        }
    }

    public function connect(){
        $socket=new TSocket(PSR_THRIFT_CENTER_HOST, PSR_THRIFT_CENTER_PORT);
        $socket->setSendTimeout(10000);
        $socket->setRecvTimeout(10000);
        $this->transport=new TBufferedTransport($socket, 1024, 1024);
        $protocol=new TBinaryProtocol($this->transport);
        $this->client=new ThriftCenterClient($protocol);
        $this->transport->open();
    }

    public function __destruct()
    {
        if ($this->transport) {
            $this->transport->close();
        }
    }

    public function isConnected() {
        if ($this->transport) {
            return $this->transport->isOpen();
        }
        return false;
    }

    /**
     * @param string $action
     * @param string $json_data
     * @return string
     */
    public function BalancePointServer($action, $json_data) {
        $this->connect();
        try {
            \Neigou\Logger::General('action.ThriftCenterClient',
                array('action'=>'BalancePointServer', 'success'=>1,'reason'=>$action,'data'=>$json_data));
            return $this->client->BalancePointServer($action, $json_data);
        } catch (TException $e) {
            $errorMsg = $e->getMessage();
            \Neigou\Logger::General('action.ThriftCenterClient',
                array('action'=>'BalancePointServer', 'success'=>0,'reason'=>$action,'data'=>$json_data,'sparam1'=>$errorMsg));
            return false;
        }
    }

    /**
     * @param string $action
     * @param string $json_data
     * @return string
     */
    public function WeixinMessageServer($action, $json_data) {
        if (!$this->isConnected()) {
            return false;
        }
        try {
            \Neigou\Logger::General('action.ThriftCenterClient',
                array('action'=>'welfareCardServerClient', 'success'=>1,'reason'=>$action,'data'=>$json_data));
            return $this->client->WeixinMessageServer($action, $json_data);
        } catch (TException $e) {
            $errorMsg = $e->getMessage();
            \Neigou\Logger::General('action.ThriftCenterClient',
                array('action'=>'BalancePointServer', 'success'=>0,'reason'=>$action,'data'=>$json_data,'sparam1'=>$errorMsg));
            return false;
        }
    }

    /**
     * @param string $action
     * @param string $json_data
     * @return string
     */
    public function AppMessageServer($action, $json_data) {
        if (!$this->isConnected()) {
            return false;
        }
        try {
            \Neigou\Logger::General('action.ThriftCenterClient',
                array('action'=>'AppMessageServer', 'success'=>1,'reason'=>$action,'data'=>$json_data));
            return $this->client->AppMessageServer($action,$json_data);
        } catch (TException $e) {
            $errorMsg = $e->getMessage();
            \Neigou\Logger::General('action.ThriftCenterClient',
                array('action'=>'AppMessageServer', 'success'=>0,'reason'=>$action,'data'=>$json_data,'sparam1'=>$errorMsg));

            return false;
        }
    }

    /**
     * @param string $action
     * @param string $json_data
     * @return string
     */
    public function welfareCardServer($action, $json_data) {
        try {
            \Neigou\Logger::General('action.ThriftCenterClient',
                array('action'=>'welfareCardServerClient', 'success'=>1,'reason'=>$action,'data'=>$json_data));
            return $this->client->welfareCardServer($action, $json_data);
        } catch (TException $e) {
            $errorMsg = $e->getMessage();
            \Neigou\Logger::General('action.ThriftCenterClient',
                array('action' => 'welfareCardServerClient', 'success' => 0, 'reason' => $action, 'data' => $json_data, 'sparam1' => $errorMsg));

            return false;
        }
    }





}
