<?php


namespace App\Api\Logic\AssetOrder;
use App\Api\Logic\AssetOrder\SplitPrice;
use App\Api\Logic\Freight;
use App\Api\Logic\Service;

/**
 * Class FreightCal
 * @package App\Api\Logic\Product
 *
 */
#运费服务
class FreightCal
{
    protected  $order_info ;
    protected  $serviceLogic ;
    protected  $freightLogic ;
    public     $key_product_list = 'product_list' ;
    public function __construct($order_info)
    {
        $this->order_info = $order_info ;
        // 运费计算
        $this->freightLogic = new Freight();
        //初始化运费总额
        $this->setOrderData('freight_amount' ,'0') ;
    }
    // 运费计算
    public function batchFreight()
    {
        $freightList  = [] ;
        $productList = $this->getOrderData($this->key_product_list) ;
        // 根据运费模版计算运费
        // 运费来源  internal | salyut
        foreach ($productList as $product_info)
        {
            if( $product_info['is_free_shipping'] || $product_info['ziti'] == 1) {
                continue ;
            }
            $freight_bn = $product_info['freight_bn'] ;
            $source = $product_info['freight_source'] ;
            $freightList[$source][$freight_bn]['product_list'][] =  array('product_bn' => $product_info['product_bn'], 'nums' => $product_info['quantity']);
            $freightList[$source][$freight_bn]['freight_bn'] = $freight_bn ;
            $freightList[$source][$freight_bn]['freight_source'] = $source ;
            if(isset($freightList[$source][$freight_bn])) {
                $freightList[$source][$freight_bn]['subtotal'] = bcadd($freightList[$source][$freight_bn]['subtotal'] , $product_info['amount'], 2);
                $freightList[$source][$freight_bn]['weight'] = bcadd($freightList[$source][$freight_bn]['weight'], $product_info['total_weight'], 2);
            }  else {
                $freightList[$source][$freight_bn]['subtotal'] = $product_info['amount'] ?? 0 ;
                $freightList[$source][$freight_bn]['weight'] = $product_info['total_weight'] ?? 0 ;
            }
        }
        $error_msg = '' ;
        $internalRes = [] ;
        $salyutRes = [] ;
        // 获取内部运费
        if(isset($freightList['internal'])) {
            $internalRes =   $this->getDeliveryFreight($freightList['internal'] ,$this->order_info['delivery'] ,$this->order_info['delivery_time'] ,$error_msg) ;
            if($internalRes === false) {
                return false ;
            }
        } else {
            $freightList['internal'] = [] ;
        }
        // 获取外部运费  salyut
        if(isset($freightList['salyut'])) {
            $salyutRes = $this->getSalyutDeliveryFreight($freightList['salyut'] ,$this->order_info['delivery'] ,$this->order_info['delivery_time']) ;
            if($salyutRes === false ) {
                return false ;
            }
        } else {
            $freightList['salyut'] = [] ;
        }
        // 内部运费 合并外部运费
        $freight_request_list = array_merge($freightList['salyut'] ,$freightList['internal']) ;
        $freight_result_list = array_merge($internalRes ,$salyutRes) ;
        return  $this->freigh($freight_request_list ,$freight_result_list) ;

    }

    public function freigh($freight_request_list  ,$freight_result_list)
    {
        if(empty($freight_request_list)) {
            return true ;
        }
        $asset_list = [] ;
        foreach ($freight_result_list as $bnRes=>$freight_info) {
            //总件数
            if(bccomp($freight_info['freight'] ,'0')  == 0) {
                continue ; // 0 不分摊运费
            }
            $asset_list_one = [
                'type' => 'freight' ,
                'voucher_id' => $bnRes  ,
                'match_use_money' => $freight_info['freight'] ,
                'products' => [] ,
            ] ;
            $product_list = $freight_request_list[$bnRes]['product_list'] ;
            foreach ($product_list as $product_info) {
                    $product_bn = $product_info['product_bn'] ;
                    $asset_list_one['products'][$product_bn] = $product_info['nums'] ;
            }
            $asset_list[] = $asset_list_one ;
        }
        $splitPriceArr =  SplitPrice::Calculate($asset_list) ;
        if($splitPriceArr === true) {
            return true ;
        }
        $this->setOrderData('freight_amount' ,$splitPriceArr['total']);
        $productListArr = $this->getOrderData($this->key_product_list) ;

        foreach ($splitPriceArr['asset_list'] as $bn=>$item) {
            $productListArr[$bn]['cost_freight'] = $item['voucher_discount'] ;
        }
        $this->setOrderData($this->key_product_list ,$productListArr) ;
        return true ;
    }

    /**
     * 获取内部运费信息
     *
     * @param   $freightInfo    array       运费编信息
     * @param                   freight_bn  运费编码
     * @param                   subtotal   double       总金额
     * @param                               lng         string          经度
     * @param                               lat         string          纬度
     * @param   $deliveryTime   mixed       配送时间
     * @return  array
     */
    public function getDeliveryFreight($freightInfo, $regionInfo = array(), $deliveryTime = null,&$error)
    {
        $return = array();
        if (empty($freightInfo)) {
            return $return;
        }
        $params = array();
        foreach ($freightInfo as $info) {
            if (empty($info['freight_bn'])) {
                continue;
            }
            $params[] = array(
                'template_bn' => $info['freight_bn'],
                'region_info' => array(
                    'province' => $regionInfo['province'],
                    'city' => $regionInfo['city'] ? $regionInfo['city'] : '',
                    'county' => $regionInfo['county'] ? $regionInfo['county'] : '',
                    'town' => $regionInfo['town'] ? $regionInfo['town'] : '',
                    'gps_info'  => $regionInfo['gps_info'] ? $regionInfo['gps_info'] : array(),
                ),
                'time_info' => array(
                    'delivery_time' => $deliveryTime ? $deliveryTime : time(),
                ),
                'subtotal'  => $info['subtotal'] ? $info['subtotal'] : 0,
                'weight'    => $info['weight'] ? $info['weight'] : 0,
            );
        }

        $serviceObj = new Service() ;
        $result = $serviceObj->ServiceCall('get_freight_v2' , ['delivery_list' => $params]) ;
        \Neigou\Logger::Debug('calculate-freight-log', array(
                'action' => 'get_freight_v2',
                'type' => 'internal' ,
                'product_list' => $this->getOrderData($this->key_product_list) ,
                'order_id'  => $this->order_info['order_id'],
                'post_data' => $params  ,
                'result'=> $result ,
            )
        );
        if ( $result['error_code'] == 'SUCCESS' && !empty($result['data']))
        {
            return  $result['data'];
        }
        $error = $result['service_data']['error_msg'][0];
        return false ;
    }



    /**
     * 获取 salyut 运费信息
     *
     * @param   $freightInfo    array       运费编信息
     * @param                   freight_bn  运费编码
     * @param                   subtotal   double       总金额
     * @param                   weight     double       总重量
     * @param                               lng         string          经度
     * @param                               lat         string          纬度
     * @param   $deliveryTime   mixed       配送时间
     * @return  array
     */
    protected function getSalyutDeliveryFreight($freightInfo, $regionInfo = array(), $deliveryTime = null,&$error_msg='')
    {
        $return = array();
        if (empty($freightInfo)) {
            return $return;
        }
        $params = array();
        foreach ($freightInfo as $info){
            if (empty($info['freight_bn'])) {
                continue;
            }
            $params[] = array(
                'template_bn' => $info['freight_bn'],
                'product_list' => $info['product_list'],
                'region_info' => array(
                    'province' => $regionInfo['province'],
                    'city' => $regionInfo['city'] ? $regionInfo['city'] : '',
                    'county' => $regionInfo['county'] ? $regionInfo['county'] : '',
                    'town' => $regionInfo['town'] ? $regionInfo['town'] : '',
                    'gps_info'  => $regionInfo['gps_info'] ? $regionInfo['gps_info'] : array(),
                ),
                'time_info' => array(
                    'delivery_time' => $deliveryTime ? $deliveryTime : time(),
                ),
                'subtotal'  => $info['subtotal'] ? $info['subtotal'] : 0,
                'weight'    => $info['weight'] ? $info['weight'] : 0,
            );
        }
        $curl = new \Neigou\Curl();
        $curl->time_out = 10;
        $request_params = array(
            'class_obj' => 'SalyutGoods',
            'method'    => 'getGoodsDeliveryFreight',
            'data'      => json_encode($params)
        );
        $request_params['token'] =  $this->_generate_yhd_token($request_params);
        $url = config('neigou.SALYUT_DOMIN') . '/OpenApi/apirun';

        $response_data = $curl->Post($url, $request_params);
        \Neigou\Logger::Debug('calculate-freight-log', array(
                'action' => 'get_freight_v2',
                'type' => 'salyut' ,
                'order_id'  => $this->order_info['order_id'],
                'post_data' => $params  ,
                'result'=> $response_data ,
            )
        );
        $response_data = json_decode($response_data, true);
        if($response_data === false) {
            return false ;
        }
        if($response_data['Result'] == 'false' || !isset($response_data['Data']) || empty($response_data['Data'])) {
            return  false ;
        }
        return $response_data['Data'] ;
    }

    // 生成 token
    private function _generate_yhd_token($arr){
        ksort($arr);
        $sign_ori_string = "";
        foreach($arr as $key=>$value) {
            if (!empty($value) && !is_array($value)) {
                if (!empty($sign_ori_string)) {
                    $sign_ori_string .= "&$key=$value";
                } else {
                    $sign_ori_string = "$key=$value";
                }
            }
        }
        $sign_ori_string .= ("&key=".config('neigou.SALYUT_SIGN'));
        return  strtoupper(md5($sign_ori_string));
    }

    private function setOrderData($key,$val) {
        $this->order_info[$key] = $val;
    }

    public function getOrderData($key=null) {

        if(isset($this->order_info[$key]) && $key) {
            return  $this->order_info[$key] ;
        }
        return $this->order_info ;
    }

}
