<?php
namespace Neigou;

require_once __DIR__ . "/ConsulClient.php" ;
if(!class_exists("Neigou\Logger")) {
    include_once __DIR__ . '/Logger.php' ;
}
use Thrift\Server\TServerSocket;
use Thrift\Factory\TTransportFactory;
use Thrift\Factory\TBinaryProtocolFactory;
use Thrift\Server\TForkingServer;
/**
 * Class SyncServerHandler
 * $obj = new AgentServer($serverName ,$version)
 * $obj->start() ;
 */
class AgentServer
{
    protected $host ;
    protected $port ;
    protected $serverName ;
    protected $version ; // 版本名称
    protected $processor ; //设置 processor

    public function __construct($host,$port ,$serverName ,$version)
    {
        $this->setServerName($serverName)->setVersion($version);
        $this->setHost($host)->setPort($port) ;
    }
    public function setPort($port) {
        $this->port = intval($port) ;
        return $this;
    }
    public function setHost($host) {
        $this->host =$host ;
        return $this;
    }
    public function  setServerName($serverName) {
        $this->serverName = $serverName ;
        return $this;
    }
    public function setVersion($version) {
        $this->version =$version ;
        return $this;
    }
    public function setProcessor($processor) {
        $this->processor = $processor ;
        return $this;
    }

    public function start()
    {
       if(empty($this->host) || empty($this->port)) {
           echo "Please specify the service IP:PORT\n" ;
           return false ;
       }
        $processor = $this->processor ;
        if(empty($processor)) {
           echo "Please specify the processor\n" ;
           return false ;
        }
        $transport = new TServerSocket($this->host, $this->port);
        $transportFactory = new TTransportFactory();
        $protocolFactory = new TBinaryProtocolFactory();
        $server = new TForkingServer($processor, $transport, $transportFactory, $transportFactory,
            $protocolFactory, $protocolFactory);

        $regRes =  ConsulClient::registerServer($this->host ,$this->port ,$this->serverName,$this->version);
        if(empty($regRes)) {
            echo "Registration failed, please check！" . "\n" ;
            exit(1) ;
        }
        echo "Registration Success ! server_name= ". $this->serverName .",  ip= {$this->host} : port=".$this->port . " \n" ;
        $server->serve();
    }
}