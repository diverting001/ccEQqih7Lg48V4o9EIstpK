<?php
defined("THRIFT_LIB_PATH") or define('THRIFT_LIB_PATH' , dirname(dirname(__DIR__)) . '/lib/php/lib') ;
defined("GEN_PHP_PATH") or define('GEN_PHP_PATH' , dirname(__DIR__) . '/gen-php') ;
if(!class_exists('Thrift\ClassLoader\ThriftClassLoader')) {
    require_once THRIFT_LIB_PATH  . '/Thrift/ClassLoader/ThriftClassLoader.php';
}
use Thrift\ClassLoader\ThriftClassLoader;
use Thrift\Protocol\TBinaryProtocol;
use Thrift\Transport\TSocket;
use Thrift\Transport\THttpClient;
use Thrift\Transport\TBufferedTransport;
use Thrift\Exception\TException;


$GEN_DIR = realpath(dirname(__FILE__)) . '/../gen-php';
$loader = new ThriftClassLoader();
$loader->registerNamespace('Thrift', THRIFT_LIB_PATH);
$loader->registerDefinition('NG_RPC\Thrift', $GEN_DIR);
$loader->register();
if(!class_exists('\\Neigou\\Logger'))
    die("please include the NG_PHP_LIB/Logger.php Plugin");


class RPCClient {

    private $client;
    private $transport;

    public function __construct() {
        try {
            $socket = new TSocket(PSR_THRIFT_NGRPC_HOST, PSR_THRIFT_NGRPC_PORT);
            $socket->setSendTimeout(10000);
            $socket->setRecvTimeout(10000);
            $this->transport = new TBufferedTransport($socket, 10240, 10240);
            $protocol = new TBinaryProtocol($this->transport);
            $this->client = new \NG_RPC\Thrift\RemoteQueuedRPCClient($protocol);
            $this->transport->open();
        } catch (TException $tx) {
        }
    }

    public function dispatchScriptCommandTaskSimpleNoReply($script_name, $param_string,$filter = '') {
        if($filter){
            if(is_array($filter)){
                if(count($filter) == 1)
                    $filter[] = 'normal';
                $filter = implode(' ',$filter);
            }else{
                $filter = trim($filter);
                $filter = array($filter,'normal');
                $filter = implode(' ',$filter);
            }
        }
        try {
            \Neigou\Logger::General('action.Client',
                array('action'=>'dispatchScriptCommandTaskSimpleNoReplyClient', 'success'=>1,'sparam1'=>$script_name,'sparam2'=>$param_string,'sparam3'=>json_encode($filter)));
            $this->client->dispatchScriptCommandTaskSimpleNoReply($script_name, $param_string, 0, $filter);

        } catch (TException $tx) {
            $errorMsg = $tx->getMessage();
            \Neigou\Logger::General('action.RPCClient',
                array('action'=>'dispatchScriptCommandTaskSimpleNoReplyClient', 'success'=>0,'sparam1'=>$script_name,'sparam2'=>$param_string,'sparam3'=>json_encode($filter),'sparam4'=>$errorMsg));
            return false;
        }
    }
    //有返回值的调用
    public function dispatchScriptCommandTaskSimple($script_name, $param_string,$timeout=120,$filter = '')
    {
        if($filter){
            if(is_array($filter)){
                if(count($filter) == 1)
                    $filter[] = 'normal';
                $filter = implode(' ',$filter);
            }else{
                $filter = trim($filter);
                $filter = array($filter,'normal');
                $filter = implode(' ',$filter);
            }
        }
        try {
            \Neigou\Logger::General('action.RPCClient',
                array('action'=>'dispatchScriptCommandTaskSimpleClient', 'success'=>1,'sparam1'=>$script_name,'sparam2'=>$param_string,'sparam3'=>json_encode($filter)));
            return $this->client->dispatchScriptCommandTaskSimple($script_name, $param_string, $timeout, $filter);
        } catch (TException $tx) {
            $errorMsg = $tx->getMessage();
            \Neigou\Logger::General('action.RPCClient',
                array('action'=>'dispatchScriptCommandTaskSimpleClient', 'success'=>0,'sparam1'=>$script_name,'sparam2'=>$param_string,'sparam3'=>json_encode($filter),'iparam1'=>$timeout,'sparam1'=>$errorMsg));
            return false;
        }
    }

    public function dispatchWebShellCallTask($domain,$input,$worker_name,$filter = ''){
        if($filter){
            if(is_array($filter)){
                if(count($filter) == 1)
                    $filter[] = 'normal';
                $filter = implode(' ',$filter);
            }else{
                $filter = trim($filter);
                $filter = array($filter,'normal');
                $filter = implode(' ',$filter);
            }
        }
        try {
            \Neigou\Logger::General('action.RPCClient',
                array('action'=>'dispatchWebShellCallTaskClient', 'success'=>1,'sparam1'=>$domain,'sparam2'=>$input,'sparam3'=>json_encode($filter),'sparam4'=>$worker_name));
            return $this->client->dispatchWebShellCallTask($domain, $input, $worker_name, $filter);
        } catch (TException $tx) {
            $errorMsg = $tx->getMessage();
            \Neigou\Logger::General('action.RPCClient',
                array('action'=>'dispatchWebShellCallTaskClient', 'success'=>0,'sparam1'=>$domain,'sparam2'=>$input,'sparam3'=>json_encode($filter),'sparam4'=>$worker_name,'sparam5'=>$errorMsg));
            return false;
        }
    }
    public function dispatchTask($worker_name, $job_data, $timeout, $access_token_ttl, $durability, $idempotent_level, $identifier, $filter)    {

        try {
            \Neigou\Logger::General('action.RPCClient',
                array('action'=>'dispatchTask', 'success'=>1,'sparam1'=>$job_data,'sparam2'=>$job_data,'sparam3'=>json_encode($filter),'sparam4'=>$worker_name));
            return $this->client->dispatchTask($worker_name, $job_data, $timeout, $access_token_ttl, $durability, $idempotent_level, $identifier, $filter);
        } catch (TException $tx) {
            $errorMsg = $tx->getMessage();
            \Neigou\Logger::General('action.RPCClient',
                array('action'=>'dispatchTask', 'success'=>0,'sparam1'=>$job_data,'sparam2'=>$job_data,'sparam3'=>json_encode($filter),'sparam4'=>$errorMsg));
            return false;
        }
    }

    //获得任务的状态
    public function getJobState($token)
    {
        try {
            \Neigou\Logger::General('action.RPCClient',
                array('action'=>'getJobStateClient', 'success'=>1,'data'=>$token));
            return $this->client->getJobState($token);
        } catch (TException $tx) {
            $errorMsg = $tx->getMessage();
            \Neigou\Logger::General('action.RPCClientRPC',
                array('action'=>'getJobStateClient', 'success'=>0,'data'=>$token,'sparam1'=>$errorMsg));
            return false;
        }
    }
    public function __destruct() {
        $this->transport->close();
    }
}




