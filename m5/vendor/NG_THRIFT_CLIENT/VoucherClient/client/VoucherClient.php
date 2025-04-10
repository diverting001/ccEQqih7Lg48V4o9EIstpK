<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2015/8/11 0011
 * Time: 16:32
 */
namespace VoucherServer;
use Thrift\ClassLoader\ThriftClassLoader;
use Thrift\Exception\TException;
use Thrift\Protocol\TBinaryProtocol;
use Thrift\Transport\TBufferedTransport;
use Thrift\Transport\TSocket;
use Logs;

defined("THRIFT_LIB_PATH") or define('THRIFT_LIB_PATH' , dirname(dirname(__DIR__)) . '/lib/php/lib') ;
defined("GEN_PHP_PATH") or define('GEN_PHP_PATH' , dirname(__DIR__) . '/gen-php') ;
if(!class_exists('Thrift\ClassLoader\ThriftClassLoader')) {
    require_once THRIFT_LIB_PATH  . '/Thrift/ClassLoader/ThriftClassLoader.php';
}
$GEN_DIR =  dirname(__DIR__).'/gen-php';
$loader = new ThriftClassLoader();
$loader->registerNamespace('Thrift', THRIFT_LIB_PATH);
$loader->registerDefinition('VoucherServer', $GEN_DIR);
$loader->register();
if(!class_exists('\\Neigou\\Logger'))
    die("please include the NG_PHP_LIB/Logger.php Plugin");

class ThriftVoucherClient
{
    private $client;
    private $transport;

    public function __construct()
    {
        try {
            $socket=new TSocket(PSR_THRIFT_VOUCHER_HOST, PSR_THRIFT_VOUCHER_PORT);
            $socket->setSendTimeout(3000000);
            $socket->setRecvTimeout(3000000);
            $this->transport=new TBufferedTransport($socket, 102400, 102400);
            $protocol=new TBinaryProtocol($this->transport);
            $this->client=new VoucherServerClient($protocol);
            $this->transport->open();
        } catch (TException $e) {

        }
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
     * @param string $json_data
     * @return string
     */
    public function addVoucher($json_data) {
        if (!$this->isConnected()) {
            return false;
        }
        try {
            \Neigou\Logger::General('action.VoucherServerClient',
                array('action'=>'addVoucherServerClient', 'success'=>1,'data'=>$json_data));
            return $this->client->addVoucher($json_data);
        } catch (TException $e) {
            $errorMsg = $e->getMessage();
            \Neigou\Logger::General('action.VoucherServerClient',
                array('action'=>'addVoucherServerClient', 'success'=>0,'data'=>$json_data,'sparam2'=>$errorMsg));
            return false;
        }
    }

    /**
     * @param string $voucher_number
     * @param string $memo
     * @return string
     */
    public function disableVoucher($voucher_number, $memo) {
        if (!$this->isConnected()) {
            return false;
        }
        try {
            \Neigou\Logger::General('action.VoucherServerClient', array('action'=>'disableVoucherServerClient','success'=>1, 'data'=>$memo,'iparam1'=>$voucher_number));
            return $this->client->disableVoucher($voucher_number, $memo);
        } catch (TException $e) {
            $errorMsg = $e->getMessage();
            \Neigou\Logger::General('action.VoucherServerClient', array('action'=>'disableVoucherServerClient','success'=>0, 'data'=>$memo,'iparam1'=>$voucher_number,'sparam2'=>$errorMsg));
            return false;
        }
    }

    /**
     * @param int $create_id
     * @param string $memo
     * @return string
     */
    public function disableVoucherForCreateID($create_id, $memo) {
        if (!$this->isConnected()) {
            return false;
        }
        try {
            \Neigou\Logger::General('action.VoucherServerClient', array('action'=>'disableVoucherForCreateIDServerClient','success'=>1, 'data'=>$memo,'iparam1'=>$create_id));
            return $this->client->disableVoucherForCreateID($create_id, $memo);
        } catch (TException $e) {
            $errorMsg = $e->getMessage();
            \Neigou\Logger::General('action.VoucherServerClient', array('action'=>'disableVoucherForCreateIDServerClient','success'=>0, 'data'=>$memo,'iparam1'=>$create_id,'sparam2'=>$errorMsg));
            return false;
        }

    }

    /**
     * @param string $voucher_number
     * @param int $member_id
     * @param int $order_id
     * @param double $use_money
     * @param string $memo
     * @return string
     */
    public function useVoucher($voucher_number, $member_id, $order_id, $use_money, $memo) {
        if (!$this->isConnected()) {
            return false;
        }
        try {
            \Neigou\Logger::General('action.VoucherServerClient', array('action'=>'useVoucherServer','success'=>1, 'data'=>$memo,'user_id'=>$member_id,'iparam1'=>$voucher_number,'iparam2'=>$order_id, 'iparam3'=>$use_money));
            return $this->client->useVoucher($voucher_number, $member_id, $order_id, $use_money, $memo);
        } catch (TException $e) {
            $errorMsg = $e->getMessage();
            \Neigou\Logger::General('action.VoucherServerClient', array('action'=>'useVoucherServer','success'=>0, 'data'=>$memo,'user_id'=>$member_id,'iparam1'=>$voucher_number,'iparam2'=>$order_id, 'iparam3'=>$use_money,'sparam2'=>$errorMsg));
            return false;
        }
    }

    /**
     * @param string $voucher_number
     * @return string
     */
    public function queryVoucher($voucher_number) {
        if (!$this->isConnected()) {
            return false;
        }
        try {
            \Neigou\Logger::General('action.VoucherServerClient', array('action'=>'queryVoucherServerClient','success'=>1, 'iparam1'=>$voucher_number));
            return $this->client->queryVoucher($voucher_number);
        } catch (TException $e) {
            $errorMsg = $e->getMessage();
            \Neigou\Logger::General('action.VoucherServerClient', array('action'=>'queryVoucherServerClient','success'=>0, 'iparam1'=>$voucher_number,'sparam2'=>$errorMsg));
            return false;
        }
    }

    /**
     * @param int $order_id
     * @return string
     */
    public function queryOrderVoucher($order_id) {
        if (!$this->isConnected()) {
            return false;
        }
        try {
            \Neigou\Logger::General('action.VoucherServerClient', array('action'=>'queryOrderVoucherServerClient','success'=>1,'iparam2'=>$order_id));
            return $this->client->queryOrderVoucher($order_id);
        } catch (TException $e) {
            $errorMsg = $e->getMessage();
            \Neigou\Logger::General('action.VoucherServerClient', array('action'=>'queryOrderVoucherServerClient','success'=>0,'iparam2'=>$order_id,'sparam2'=>$errorMsg));
            return false;
        }
    }
    /**
     * @param int $member_id
     * @return string
     */
    public function queryMemberVoucher($member_id) {
        if (!$this->isConnected()) {
            return false;
        }
        try {
            \Neigou\Logger::General('action.VoucherServerClient', array('action'=>'queryMemberVoucherServerClient','success'=>1, 'user_id'=>$member_id));
            return $this->client->queryMemberVoucher($member_id);
        } catch (TException $e) {
            $errorMsg = $e->getMessage();
            \Neigou\Logger::General('action.VoucherServerClient', array('action'=>'queryMemberVoucherServerClient','success'=>0, 'user_id'=>$member_id,'sparam2'=>$errorMsg));
            return false;
        }
    }

    /**
     * @param string $voucher_number
     * @param string $status
     * @param string $memo
     * @return string
     */
    public function exchangeStatus($voucher_number, $status, $memo) {
        if (!$this->isConnected()) {
            return false;
        }
        try {
            \Neigou\Logger::General('action.VoucherServerClient', array('action'=>'exchangeStatusServerClient','success'=>1, 'data'=>$memo,'iparam1'=>$voucher_number,'sparam1'=>$status));
            return $this->client->exchangeStatus($voucher_number, $status, $memo);
        } catch (TException $e) {
            $errorMsg = $e->getMessage();
            \Neigou\Logger::General('action.VoucherServerClient', array('action'=>'exchangeStatusServerClient','success'=>0, 'data'=>$memo,'iparam1'=>$voucher_number,'sparam1'=>$status,'sparam2'=>$errorMsg));
            return false;
        }
    }

    /**
     * @param int $member_id
     * @return string
     */
    public function queryMemberBindedVoucher($member_id) {
        if (!$this->isConnected()) {
            return false;
        }
        try {
            \Neigou\Logger::General('action.VoucherServerClient', array('action'=>'queryMemberBindedVoucherServerClient','success'=>1, 'user_id'=>$member_id));
            return $this->client->queryMemberBindedVoucher($member_id);
        } catch (TException $e) {
            $errorMsg = $e->getMessage();
            \Neigou\Logger::General('action.VoucherServerClient', array('action'=>'queryMemberBindedVoucherServerClient','success'=>0, 'user_id'=>$member_id,'sparam2'=>$errorMsg));
            return false;
        }
    }

    /**
     * @param int $member_id
     * @param string $json_data
     * @return string
     */
    public function createMemVoucher($member_id, $json_data) {
        if (!$this->isConnected()) {
            return false;
        }
        try {
            \Neigou\Logger::General('action.VoucherServerClient', array('action'=>'createMemVoucherServerClient','success'=>1, 'data'=>$json_data,'user_id'=>$member_id));
            return $this->client->createMemVoucher($member_id, $json_data);
        } catch (TException $e) {
            $errorMsg = $e->getMessage();
            \Neigou\Logger::General('action.VoucherServerClient', array('action'=>'createMemVoucherServerClient','success'=>0, 'data'=>$json_data,'user_id'=>$member_id,'sparam2'=>$errorMsg));
            return false;
        }
    }

    /**
     * @param string $data
     * @return string
     */
    public function largeBindMemVoucher($data) {
        if (!$this->isConnected()) {
            return false;
        }
        try {
            \Neigou\Logger::General('action.VoucherServerClient', array('action'=>'largeBindMemVoucherServerClient','success'=>1, 'data'=>$data));
            return $this->client->largeBindMemVoucher($data);
        } catch (TException $e) {
            $errorMsg = $e->getMessage();
            \Neigou\Logger::General('action.VoucherServerClient', array('action'=>'largeBindMemVoucherServerClient','success'=>0, 'data'=>$data,'sparam2'=>$errorMsg));
            return false;
        }
    }

    /**
     * @param $type
     * @param $json_data
     * @return string
     */
    public function createRule($type, $json_data) {
        if (!$this->isConnected()) {
            return false;
        }
        try {
            \Neigou\Logger::General('action.VoucherServerClient', array('action'=>'createRuleServerClient','success'=>1, 'data'=>$json_data,'sparam1'=>$type));
            return $this->client->createRule($type, $json_data);
        } catch (TException $e) {
            $errorMsg = $e->getMessage();
            \Neigou\Logger::General('action.VoucherServerClient', array('action'=>'createRuleServerClient','success'=>0, 'data'=>$json_data,'sparam1'=>$type,'sparam2'=>$errorMsg));
            return false;
        }
    }

    /**
     * @param $type
     * @param $json_data
     * @return string
     */
    public function saveRule($json_data) {
        if (!$this->isConnected()) {
            return false;
        }
        try {
            \Neigou\Logger::General('action.VoucherServerClient', array('action'=>'saveRuleServerClient','success'=>1,'data'=>$json_data));
            return $this->client->saveRule($json_data);
        } catch (TException $e) {
            $errorMsg = $e->getMessage();
            \Neigou\Logger::General('action.VoucherServerClient', array('action'=>'saveRuleServerClient','success'=>1,'data'=>$json_data,'sparam2'=>$errorMsg));
            return false;
        }
    }
    /**
     * @param string $json_data
     * @return string
     */
    public function createPackageRule($json_data) {
        if (!$this->isConnected()) {
            return false;
        }
        try {
            \Neigou\Logger::General('action.VoucherServerClient', array('action'=>'createPackageRuleServerClient','success'=>1,'data'=>$json_data));
            return $this->client->createPackageRule($json_data);
        } catch (TException $e) {
            $errorMsg = $e->getMessage();
            \Neigou\Logger::General('action.VoucherServerClient', array('action'=>'createPackageRuleServerClient','success'=>0,'data'=>$json_data,'sparam2'=>$errorMsg));
            return false;
        }
    }

    public function getRule($rule_id){
        if (!$this->isConnected()) {
            return false;
        }
        try {
            \Neigou\Logger::General('action.VoucherServerClient', array('action'=>'getRuleServerClient','success'=>1,'iparam1'=>$rule_id));
            return $this->client->getRule($rule_id);
        } catch (TException $e) {
            $errorMsg = $e->getMessage();
            \Neigou\Logger::General('action.VoucherServerClient', array('action'=>'getRuleServerClient','success'=>0,'iparam1'=>$rule_id,'sparam2'=>$errorMsg));
            return false;
        }
    }

    public function getRuleList($rule_id_list){
        if (!$this->isConnected()) {
            return false;
        }
        $query_param = array(
            'rule_id_list'=>$rule_id_list,
        );
        try {
            \Neigou\Logger::General('action.VoucherServerClient', array('action'=>'getRuleListServerClient','success'=>1,'iparam1'=>json_encode($query_param)));
            return $this->client->getRuleList(json_encode($query_param));
        } catch (TException $e) {
            $errorMsg = $e->getMessage();
            \Neigou\Logger::General('action.VoucherServerClient', array('action'=>'getRuleListServerClient','success'=>0,'iparam1'=>json_encode($query_param),'sparam2'=>$errorMsg));
            return false;
        }
    }
    /**
     * @param string $action
     * @param string $json_data
     * @return string
     */
    public function freeShippingCouponServer($action, $json_data){
        if (!$this->isConnected()) {
            return false;
        }
        try {
            \Neigou\Logger::General('action.VoucherServerClient', array('action'=>'freeShippingCouponServerClient','success'=>1,'data'=>$json_data,'sparam1'=>$action));
            return $this->client->freeShippingCouponServer($action, $json_data);
        } catch (TException $e) {
            $errorMsg = $e->getMessage();
            \Neigou\Logger::General('action.VoucherServerClient', array('action'=>'freeShippingCouponServerClient','success'=>0,'data'=>$json_data,'sparam1'=>$action,'sparam2'=>$errorMsg));
            return false;
        }
    }

    /**
     * @param string $json_data
     * @return string
     */
    public function addBlackList($json_data) {
        if (!$this->isConnected()) {
            return false;
        }
        try {
            \Neigou\Logger::General('action.VoucherServerClient',
                array('action'=>'addBlackListServerClient', 'success'=>1,'data'=>$json_data));
            return $this->client->addBlackList($json_data);
        } catch (TException $e) {
            $errorMsg = $e->getMessage();
            \Neigou\Logger::General('action.VoucherServerClient',
                array('action'=>'addBlackListServerClient', 'success'=>0,'data'=>$json_data,'sparam2'=>$errorMsg));
            return false;
        }
    }
    /**
     * @param string $json_data
     * @return string
     */
    public function queryBlackList($json_data) {
        if (!$this->isConnected()) {
            return false;
        }
        try {
            \Neigou\Logger::General('action.VoucherServerClient',
                array('action'=>'queryBlackListServerClient', 'success'=>1,'data'=>$json_data));
            return $this->client->queryBlackList($json_data);
        } catch (TException $e) {
            $errorMsg = $e->getMessage();
            \Neigou\Logger::General('action.VoucherServerClient',
                array('action'=>'queryBlackListServerClient', 'success'=>0,'data'=>$json_data,'sparam2'=>$errorMsg));
            return false;
        }
    }
    /**
     * @param string $json_data
     * @return string
     */
    public function saveBlackList($json_data) {
        if (!$this->isConnected()) {
            return false;
        }
        try {
            \Neigou\Logger::General('action.VoucherServerClient',
                array('action'=>'saveBlackListServerClient', 'success'=>1,'data'=>$json_data));
            return $this->client->saveBlackList($json_data);
        } catch (TException $e) {
            $errorMsg = $e->getMessage();
            \Neigou\Logger::General('action.VoucherServerClient',
                array('action'=>'saveBlackListServerClient', 'success'=>0,'data'=>$json_data,'sparam2'=>$errorMsg));
            return false;
        }
    }
    /**
     * @param string $json_data
     * @return string
     */
    public function deleteBlackList($json_data) {
        if (!$this->isConnected()) {
            return false;
        }
        try {
            \Neigou\Logger::General('action.VoucherServerClient',
                array('action'=>'deleteBlackListServerClient', 'success'=>1,'data'=>$json_data));
            return $this->client->deleteBlackList($json_data);
        } catch (TException $e) {
            $errorMsg = $e->getMessage();
            \Neigou\Logger::General('action.VoucherServerClient',
                array('action'=>'deleteBlackListServerClient', 'success'=>0,'data'=>$json_data,'sparam2'=>$errorMsg));
            return false;
        }
    }

    
    /**
     * 
     * @param type string $member_id
     * @param type string $json_data
     */
    public function addMemberVoucherByCode($member_id,$json_data){
        if (!$this->isConnected()) {
            return false;
        }
        try {
            \Neigou\Logger::General('action.VoucherServerClient',
                array('action'=>'addMemberVoucherByCode', 'success'=>1,'data'=>$json_data));
            return $this->client->addMemberVoucherByCode($member_id,$json_data);
        } catch (TException $e) {
            $errorMsg = $e->getMessage();
            \Neigou\Logger::General('action.VoucherServerClient',
                array('action'=>'addMemberVoucherByCode', 'success'=>0,'data'=>$json_data,'sparam2'=>$errorMsg));
            return false;
        }
    }

    /**
     *
     * @param type string $json_data
     */
    public function queryVoucherCount($json_data){
        if (!$this->isConnected()) {
            return false;
        }
        try {
            \Neigou\Logger::General('action.VoucherServerClient',
                array('action'=>'queryVoucherCount', 'success'=>1,'data'=>$json_data));
            return $this->client->queryVoucherCount($json_data);
        } catch (TException $e) {
            $errorMsg = $e->getMessage();
            \Neigou\Logger::General('action.VoucherServerClient',
                array('action'=>'queryVoucherCount', 'success'=>0,'data'=>$json_data,'sparam2'=>$errorMsg));
            return false;
        }
    }

    /**
     * 查询用户券列表
     *
     * @param   int     $member_id  用户 ID
     * @param   string  $json_data  查询条件
     * @return  boolean
     */
    public function queryMemberVoucherList($member_id, $json_data){
        if (!$this->isConnected()) {
            return false;
        }
        try {
            \Neigou\Logger::General('action.VoucherServerClient',
                array('action'=>'queryMemberVoucherList', 'success'=>1,'data'=>$json_data));
            return $this->client->queryMemberVoucherList($member_id, $json_data);
        } catch (TException $e) {
            $errorMsg = $e->getMessage();
            \Neigou\Logger::General('action.VoucherServerClient',
                array('action'=>'queryMemberVoucherList', 'success'=>0,'member_id'=>$member_id, 'data'=>$json_data,'sparam2'=>$errorMsg));
            return false;
        }
    }

}
