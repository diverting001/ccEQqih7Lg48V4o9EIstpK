<?php

namespace App\Api\V6\Controllers;
use App\Api\Common\Common;
use App\Api\Common\Controllers\BaseController;
use App\Api\Logic\AssetOrder\OrderService;
use App\Api\V6\Service\Calculate\CalculateV2;
use App\Api\Model\ServerOrders\ServerCreateOrders ;
use Illuminate\Http\Request;
/**
 * Description of CalculateController
 *
 * @author sundongliang
 */
class CalculateV2Controller extends BaseController
{
    // Request $request
    public function orderCalculate(Request $request)
    {
        $start_time = microtime( true) ;
        $params = $this->getContentArray($request);
        //$params = $this->test() ;
        $asset_list = Common::array_group($params['asset_list'],'type') ;
        $paramsData = [] ;
        // 格式化参数格式化参数

        // order_id 可传 可不传
        if(empty($params['order_id'])) {
            $params['order_id'] =  OrderService::getOrderId() ;
        }
        $paramsData['order_id']    = $params['order_id'];
        $paramsData['member_id']   = $params['member_id'];
        $paramsData['company_id']  = $params['company_id'];
        $paramsData['product_list'] = $params['product_list'] ?$params['product_list'] : [] ;

        // 格式化资产参数
        $asset_type = ['point' ,'voucher' ,'freeshipping','dutyfree', 'cash'] ;
        // 格式化 资产 信息
        foreach ($asset_type as $type) {
            $paramsData[$type]   =  isset($asset_list[$type]) ? Common::array_rebuild($asset_list[$type],'bn'): [] ; // 积分
        }
        // 运营活动 使用规则
        $paramsData['order_use_rules'] = $params['order_use_rules'] ;// 订单优惠规则
        $paramsData['delivery'] = $params['delivery'] ;

        $paramsData['delivery_time'] = $params['delivery']['delivery_time'];// 配送时间
        $paramsData['ship_area_id'] = $params['delivery']['ship_area_id'] ; // 配送地区ID
        $paramsData['gps_info'] = $params['delivery']['gps_info'] ;

        // 以订单为纬度，各种资产的统计总和  初始值
        $paramsData['discount']    = [
                "voucher"        => 0, // 优惠券
                "freeshipping"   => 0, // 免邮
                "dutyfree"       => 0, // 免税
                'promotion'      => 0 , //运营活动
                'order_promotion' => 0, // 订单优惠
                'point' => 0 ,
                'point_money' => 0 ,

        ];
        if (!$paramsData['member_id'] || !$paramsData['company_id'] || empty($paramsData['product_list']) || empty($paramsData['order_id'])) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat(null, 500);
        }
        // 验证商品价格 以及基本参数
        foreach ($paramsData['product_list'] as $product) {
            if(!is_array($product['price'])) {
                $this->setErrorMsg('价格为空');
                return $this->outputFormat(null, 501);
            }
            if( empty($product['quantity'])) {
                $this->setErrorMsg('数量为空');
                return $this->outputFormat(null, 502);
            }
        }

        // 检查运费模版是否存在
        $freight_bn_list =  array_column($paramsData['product_list'] ,'freight_bn') ;
        $freight_source_list = array_column($paramsData['product_list'] ,'freight_source') ;
        if(count($freight_bn_list) != count($paramsData['product_list'])) {
            $this->setErrorMsg('运费模版不能为空');
            return $this->outputFormat(null, 503);
        }
        if(count($freight_source_list) != count($paramsData['product_list'])) {
            $this->setErrorMsg('运费模版来源不能为空');
            return $this->outputFormat(null, 504);
        }

        $calculate = new CalculateV2($paramsData);

        $res = $calculate->run();
        \Neigou\Logger::Debug('calculate-v7-log', array(
                'action'  => 'return',
                'sparam1' => $params ,
                'sparam2' => $res ,
            )
        );
        $end_time = microtime(true) ;
        if($res['code'] == 200 ) {
            $version = 'V6' ;
            $log_data = array(
                'version'               => $version,
                'post_data'          => $params ,
                'calculate_result_data' => $res['data'],
            );
            $calculate->saveCalculateLog($paramsData['order_id']  ,$log_data,$version);
            return $this->outputFormat(array('id' => $res['data']['id'],'cost_time' => $end_time - $start_time));
        }
        $this->setErrorMsg($res['msg']);
        return $this->outputFormat(['error_msg' => $res['msg'] ,'cost_time' =>$end_time - $start_time] ,$res['code']);
    }

    // 测试数据
    public function  test()
    {
      $orderParamStr = '{
    "order_id":"202104021551489304",
    "company_id":"18643",
    "member_id":"971254",
    "product_list":[
        {
            "goods_id":"874466",
            "goods_bn":"SHOP-5B4DBD4126F87",
            "product_id":"3818783",
            "product_bn":"SHOP-5B4DBD4126F87-0",
            "product_name":"王松涛测试产品40元",
            "price":{
                "price":"45.00",
                "cost":"40.000"
            },
            "weight":"0",
            "total_weight":"0",
            "quantity":"1",
            "shop_id":"29",
            "taxfees":0,
            "point_account":[
                "201911051448313843",
                "201912051558276497"
            ],
            "use_rule":null,
            "single_freight":false,
            "freight_bn":"1kWhuUHat5",
            "freight_source":"internal",
            "jifen_pay":0,
            "is_gift":0,
            "root_product_bn":null
        }
    ],
    "order_use_rules":[
        311
    ],
    "asset_list":[
        {
            "type":"point",
            "bn":201911051448313843,
            "amount":"0.5",
            "extend_data":{
                "point_channel":"SCENENEIGOU",
                "third_point_pwd":""
            },
            "match_product_bn":[

            ]
        },
        {
            "type":"voucher",
            "bn":"ED48566CCCE9E32A",
            "amount":"0",
            "match_product_bn":[
                "SHOP-5B4DBD4126F87-0"
            ]
        }
    ],
    "delivery":{
        "addr":"上海 上海市 黄浦区 城区 远东大厦 23123123",
        "ship_area_id":"3928",
        "town":"城区",
        "country":null,
        "city":"上海市",
        "province":"上海",
        "shipping_id":5,
        "delivery_time":1616829801,
        "gps_info":{
            "lat":"31.229113899",
            "lng":"121.51741645"
        }
    }
}';
      return json_decode($orderParamStr,true) ;
    }


    // 获取 计算服务的各项资产信息
    public function get(Request $request)
    {
        $request = $this->getContentArray($request);
        $orderId = $request['order_id'] ;
        if (empty($orderId)) {
            $this->setErrorMsg('订单ID为空');
            return $this->outputFormat([], 500);
        }
        $orderModel = new ServerCreateOrders() ;
        // 查询订单详情
        $orderInfo = $orderModel->getRow(['order_id' => $orderId]) ;
        if (empty($orderInfo)) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 501);
        }
        $goods_list = $orderModel->setTable("server_calculate_goods")->getBaseInfo(['order_id'=>$orderId]) ;

        $goods_list = Common::array_rebuild($goods_list ,'id') ;

        $goods_order_ids = array_keys($goods_list) ;
        $asset_list  =$orderModel->setTable("server_calculate_asset")->getBaseInfo(['order_id'=>$orderId ,'asset_goods_id'=> $goods_order_ids],['type','amount','money','asset_bn','asset_goods_id']) ;

        $asset_list  = Common::array_group($asset_list ,'asset_goods_id');

        foreach ($goods_list as $key=>$goods_item) {
            if(isset($asset_list[$key])) {
                $goods_list[$key]['asset_list'] = $asset_list[$key] ;
            }
            unset($goods_list[$key]['create_time']) ;
            unset($goods_list[$key]['last_modified']) ;
        }
        $orderInfo['goods_list'] = $goods_list ;
        unset($orderInfo['create_time'] ,$orderInfo['last_modified']) ;
        return $this->outputFormat($orderInfo);
    }

}
