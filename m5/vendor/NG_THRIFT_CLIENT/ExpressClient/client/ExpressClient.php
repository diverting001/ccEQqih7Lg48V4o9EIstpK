<?php
/**
 * Created by PhpStorm.
 * User: liyunlong
 * Date: 2015/7/8
 * Time: 18:03
 */
use Thrift\Protocol\TBinaryProtocol;
use Thrift\Transport\TBufferedTransport;
use Thrift\Transport\TSocket;

include_once __DIR__ . '/../inc/inc.php';

class ThriftExpressClient
{
    private $client;
    private $transport;

    public function __construct()
    {
        try {
            $socket = new TSocket(PSR_THRIFT_EXPRESS_HOST, PSR_THRIFT_EXPRESS_PORT);
            $this->transport = new TBufferedTransport($socket, 1024, 1024);
            $protocol = new TBinaryProtocol($this->transport);
            $this->client = new \ExpressServer\ExpressServerClient($protocol);
            $this->transport->open();
        } catch (\TException $e) {

        }
    }

    public function getExpressFromKuaidi($company, $num)
    {
        try {
//            \Neigou\Logger::General('action.ThriftExpressClient', array('action'=>'getExpressFromKuaidiServerClient', 'success'=>1,"data"=>$company, "iparam1"=>$num));
            return $this->client->getExpressFromKuaidi($company, $num);
        } catch (\TException $e) {
            $errorMsg = $e->getMessage();
            \Neigou\Logger::General('action.ThriftExpressClient', array('action'=>'getExpressFromKuaidiServerClient', 'success'=>0,"data"=>$company, "iparam1"=>$num,'sparam4'=>$errorMsg));
            return false;
        }
    }

    public function callBackFromKuaidi($json_data)
    {
        try {
//            \Neigou\Logger::General('action.ThriftExpressClient', array('action'=>'callBackFromKuaidiServer', 'success'=>1,"data"=>$json_data));
            return $this->client->callBackFromKuaidi($json_data);
        }catch (\TException $e) {
            $errorMsg = $e->getMessage();
            \Neigou\Logger::General('action.ThriftExpressClient', array('action'=>'callBackFromKuaidiServer', 'success'=>0,"data"=>$json_data,'sparam4'=>$errorMsg));
            return false;
        }
    }

    public function subscribeExpress($json_order)
    {
        try {
            \Neigou\Logger::General('action.ThriftExpressClient', array('action'=>'subscribeExpressServerClient', 'success'=>1,"data"=>$json_order));
            $this->client->subscribeExpress($json_order);
        } catch (\TException $e) {
            $errorMsg = $e->getMessage();
            \Neigou\Logger::General('action.ThriftExpressClient', array('action'=>'subscribeExpressServerClient', 'success'=>0,"data"=>$json_order,'sparam4'=>$errorMsg));
            return false;
        }
    }

    public function needSendSMSForComplete($company, $num)
    {
        try {
            \Neigou\Logger::General('action.ThriftExpressClient', array('action'=>'needSendSMSForCompleteServerClient', 'success'=>1,"data"=>$company,"iparam1"=>$num));
            return $this->client->needSendSMSForComplete($company, $num);
        } catch (\TException $e) {
            $errorMsg = $e->getMessage();
            \Neigou\Logger::General('action.ThriftExpressClient', array('action'=>'needSendSMSForCompleteServerClient', 'success'=>0,"data"=>$company,"iparam1"=>$num,'sparam4'=>$errorMsg));
            return false;
        }
    }

    public function __destruct()
    {
        $this->transport->close();
    }
}
