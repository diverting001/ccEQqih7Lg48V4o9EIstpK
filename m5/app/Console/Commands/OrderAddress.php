<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Api\Logic\Service as Service;
use App\Api\Model\Address\Address;
use App\Api\Model\Order\Order as OrderModel;

/**
 * Class OrderAddress
 * @package App\Console\Commands
 *
 *  1） 监控store 项目 修改用户 联系人地址，如果有修改。 则监控队列。同步修改订单未发货地址
 * php artisan OrderAddress store_order_address_change
 *
 *  2） 监控 server_order 订单地址修改 队列。 如果  有未发货订单地址变动。 同步修改 shop订单表联系人地址。
 * php artisan OrderAddress shop_order_addr_update
 */

class OrderAddress extends Command
{
    protected $signature = 'OrderAddress {action}';

    protected $description = '监控用户地址变更消息';


    public function handle()
    {

        set_time_limit(0);
        $action = $this->argument('action');

        switch ($action) {
            /**
             * 监控store 项目 修改用户 联系人地址，如果有修改。 则监控队列。同步修改订单未发货地址
             */
            case 'store_order_address_change':

                $this->storeOrderAddressChanged();
                break;
            case 'shop_order_addr_update' :
                // 监控 server_order 订单地址修改 队列。 如果  有未发货订单地址变动。 同步修改 shop订单表联系人地址。
                $this->shopOrderAddrUpdate() ;
                break ;
            default:
                throw new Exception('～_～不能进行处理   啊哈哈哈哈哈哈～～～～ (^傻^)');
        }
    }

    public function storeOrderAddressChanged()
    {
        try {
            $amqp = new \Neigou\AMQP();
            $amqp->ConsumeMessage('store_order_address_change', 'service', 'member.address.changed',  array($this,'listenStoreMemberAddressChange'));
        }catch ( \Exception $ex ) {
            print_r( $ex->getMessage() ."\n") ;
            // \Neigou\Logger::General( 'mq.store_order_address_change', array( 'remark' => $ex->getMessage(), 'action' => 'store_order_address_change' . '|' . 'service' . '|' . 'member.address.changed' ) );
        }

       $this->info('执行完成');
    }

    public function listenStoreMemberAddressChange($msg = array()) {

        if (empty($msg) || !is_array($msg)) {
            echo '消息错误，跳过' ;
            return false;
        }
        $data = $msg['data'] ;
        if(empty($data['member_id']) || empty($data['company_id']) || empty($data['addr_id'])) {
            \Neigou\Logger::General( 'mq.store_order_address_change', array( 'msg' => $msg  ) );
            echo "error\n" ;
            print_r($data) ;
            return false ;
        }
        $page = 1 ;
        $serviceLogic = new Service() ;

        $addModel = new Address ;
        $addr_info = $addModel->getRow($data['addr_id']);

        if(empty($addr_info)) {
            $this->info('地址参数错误，未获取到详细地址');
            return false ;
        }

       $addr_area = explode(":"  , $addr_info['area'] ) ;
       list($ship_province ,$ship_city,$ship_county,$ship_town)  = explode("/" , $addr_area[1] )  ;
       $post_addr_data = array(
            'ship_mobile' => $addr_info['mobile'] ,
            'ship_name' => $addr_info['name'] ,
            'ship_province' => trim($ship_province) ,
            'ship_city' => trim($ship_city) ,
            'ship_county' => trim($ship_county) ,
            'ship_town' => trim($ship_town) ,
            'ship_addr' =>   str_replace("/" ," " , $addr_area[1])  . " " . trim($addr_info['deliver_area'])." " . $addr_info['addr']  ,
        ) ;
        while (true) {
            // 获取用户 未发货订单列表。
            // 只查询主单
            $where = array(
                'member_id' => $data['member_id'] ,
                'company_id' => $data['company_id'] ,
                'ship_status' => '1' , // 1：未发货 2：已发货 3：已收货 4：已退货
                'status' =>  '1', // 正常订单
                'page_size' => 100 , //
                "page_index" => $page  ,
                'create_time' => array(
                    'start_time' => time() - 30*86400 ,
                    'end_time' =>  time()
                ),
            );
            $order_list =  $serviceLogic->ServiceCall("order_list" ,$where) ;
            $page = $page + 1;
            $order_list = isset($order_list['data']['order_list'])  ? $order_list['data']['order_list'] : array() ;
            if(empty($order_list)) {
                break ;
            }
            foreach ($order_list as $value) {
                $main_order_id  = $value['order_id'] ;
                $post_addr_data['order_id'] = $main_order_id ;
                $order_result =  $serviceLogic->ServiceCall('order_update' ,$post_addr_data) ;
                \Neigou\Logger::General( 'mq.store_order_address_change', array( 'action' => 'store_order_address_change' ,'order_id' => $main_order_id ,'post_addr' => $post_addr_data  ,'order_info' => $value ,'result' =>$order_result) );
                // 更新子单 地址
                if($value['split_orders']) {
                    foreach ($value['split_orders'] as $sorder) {
                        if($sorder['ship_status'] == $where['ship_status'] && $sorder['status'] == $where['status'] ) {
                            $post_addr_data['order_id'] = $sorder['order_id'] ;
                            $sorder_result =   $serviceLogic->ServiceCall('order_update' ,$post_addr_data) ;
                            \Neigou\Logger::General( 'mq.store_order_address_change', array( 'action' => 'store_order_address_change' ,'order_id' => $sorder['order_id']  ,'post_addr' => $post_addr_data  ,'order_info' => $sorder ,'result' =>$sorder_result) );
                        }
                      }
                }
            }
        }

    }


    // 监控队列 修改 shop_admin_orders 订单表里面的消息
    public function shopOrderAddrUpdate()
    {

         $function = function ($msg) {
             $data = $msg['data'];
             if (empty($data['order_id'])) {
                 echo '订单号不能为空===' . "\n";
                 return false;
             }

             // 获取servier 订单详情 。
             $order_info = OrderModel::GetOrderInfoById($data['order_id']);
             if(empty($order_info)) {
                 echo '订单号错误===' . "\n";
                 return  false ;
             }
             $update_data = array(
                 'ship_mobile' => $order_info->ship_mobile ,
                 'ship_name' => $order_info->ship_name ,
                 'ship_province' => $order_info->ship_province ,
                 'ship_city' => $order_info->ship_city ,
                 'ship_county' => $order_info->ship_county ,
                 'ship_town' => $order_info->ship_town ,
                 'ship_addr' => $order_info->ship_addr ,
                 'order_id'  => $data['order_id'] ,
             ) ;
             $res =   $this->updateOrder($update_data) ;
             \Neigou\Logger::General( 'mq.shop_order_address_change', array( 'order_id' =>  $data['order_id']  ,'post_addr' => $update_data  ,'order_info' => $order_info ,'result' =>$res) );
         } ;
        try {
            $amqp = new \Neigou\AMQP();
            $amqp->ConsumeMessage('shop_order_address_change', 'service', 'member.address.pushorder', $function );
        }catch ( \Exception $ex ) {
            print_r( $ex->getMessage() ."\n") ;
            //\Neigou\Logger::General( 'mq.shop_order_address_change', array( 'remark' => $ex->getMessage(), 'action' => 'shop_order_address_change' . '|' . 'service' . '|' . 'member.address.pushorder' ) );
        }
    }

    protected function updateOrder($post_data)
    {
        $post_data = array(
            'class_obj' => 'OrderApi',
            'method' => 'update',
            'data' => json_encode($post_data)
        );
        $token = \App\Api\Common\Common::GetShopSign($post_data);
        $post_data['token'] = $token;
        $curl = new \Neigou\Curl();
        $url = config('neigou.SHOP_DOMIN') ;
        $res = $curl->Post($url . '/Shop/OpenApi/apirun', $post_data);
        $res = json_decode($res, true);
        if ($res['Result'] == 'true') {
            return  true;
        } else {
            return false ;
        }
    }



}
