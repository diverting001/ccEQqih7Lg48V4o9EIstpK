<?php

namespace App\Api\Logic\Neigou;

use App\Api\Logic\OrderWMS\Order as Order;
use App\Api\Logic\Service;

class Ec extends Order
{
    private $_host = '';
    private $_ocs_host = '';
    private $_uri = array(
        'create_order' => '/openapi/orderV3/CreateOrder',
        'get_order_info' => '/openapi/orderV3/GetOrderInfo',
        'get_order_info_from_ocs' => '/openapi/orders/GetOrderInfo',
        'get_products_list' => '/openapi/productsV2/GetProductsList',
        'get_split_goods_list' => '/openapi/productsV2/SplitGoodsList',
        'get_goods_branch' => '/openapi/productsV2/GetGoodsBranch',
        'get_branch_id' => '/openapi/branch/GetBranchId',
        'get_product_price' => '/openapi/productsV2/GetProductPrice',
        'get_product_base_price' => '/openapi/productsV2/GetProductBasePrice',
    );


    public function __construct()
    {
        $this->_host = config('neigou.STORE_DOMIN');
        $this->_ocs_host = config('neigou.OCS_DOMIN');
    }

    /*
     * @todo 创建订单
     */
    public function Create($order_data)
    {
        if (empty($order_data)) {
            return false;
        }
        $result = $this->SendData('create_order', $order_data);
        if ($result['Result'] != 'true' || empty($result['Data']['order_id'])) {
            return false;
        } else {
            return $result['Data']['order_id'];
        }
    }

    /*
     * @todo 获取订单详情
     */
    public function GetInfo($order_id)
    {
        if (empty($order_id)) {
            return false;
        }
        $send_data = [
            'order_id' => $order_id
        ];
        //$result = $this->SendData('get_order_info',$send_data);
        $result = $this->SendDataToOCS('get_order_info_from_ocs', $send_data);
        if ($result['Result'] != 'true' || empty($result['Data'])) {
            return false;
        } else {
            return $result['Data'];
        }
    }

    /*
     * @todo 获取商品微仓map
     */
    public function GetGoodsBranch($goods_ids)
    {
        if (empty($goods_ids)) {
            return false;
        }
        $result = $this->SendData('get_goods_branch', array(
            'goods_ids' => $goods_ids
        ));
        if ($result['code'] != 10000) {
            return false;
        } else {
            $list = [];
            foreach ($result['data']['goods_branch_list'] as $item) {
                if ($item['supplier_bn'] !== 'MRYX') {
                    unset($item['supplier_bn']);
                    $list[] = $item;
                }
            }
            return $list;
        }
    }

    /*
     * @todo 获取微仓商品list
     */
    public function GetSplitGoodsList($goods_ids, $branch_list)
    {
        if (empty($goods_ids)) {
            return false;
        }
        $result = $this->SendData('get_split_goods_list', array(
            'goods_ids' => $goods_ids,
            'branch_list' => $branch_list
        ));

        foreach ($result['data']['split_goods_list']['standard'] as $goods_id => &$standard_goods) {
            if (strpos($standard_goods['bn'], 'MRYX-') !== false) {
                unset($result['data']['split_goods_list']['standard'][$goods_id]);
                continue;
            }
            $p = 1;
            $p_value = $standard_goods['store'] > 0 ? 1 : 0;
            while ($p <= 31) {
                if (!isset($standard_goods['province_stock']['p' . $p])) {
                    $standard_goods['province_stock']['p' . $p] = $p_value;
                }
                $p++;
            }
        }
        foreach ($result['data']['split_goods_list']['vbranch'] as $goods_id => &$vbranch_goods) {
            if (strpos($vbranch_goods['bn'], 'MRYX-') !== false) {
                unset($result['data']['split_goods_list']['vbranch'][$goods_id]);
                continue;
            }
            $p = 1;
            $p_value = $vbranch_goods['store'] > 0 ? 1 : 0;
            while ($p <= 31) {
                if (!isset($vbranch_goods['province_stock']['p' . $p])) {
                    $vbranch_goods['province_stock']['p' . $p] = $p_value;
                }
                $p++;
            }
        }
        if ($result['code'] != 10000) {
            return false;
        } else {
            return $result['data']['split_goods_list'];
        }
    }

    private function SendDataToOCS($uri, $order_data)
    {
        if (empty($order_data)) {
            return false;
        }
        if (!isset($this->_uri[$uri])) {
            return false;
        }
        $send_data = array('data' => base64_encode(json_encode($order_data)));
        //$token= \App\Api\Common\Common::GetEcStoreSign($send_data);
        //$send_data['token'] = $token;

        $curl = new \Neigou\Curl();
        $result_str = $curl->Post($this->_ocs_host . $this->_uri[$uri], $send_data);
        $result = trim($result_str, "\xEF\xBB\xBF");
        $result = json_decode($result, true);

        $tmp = array(
            '$send_data' => $send_data,
            '$result' => $result,
            'url' => $this->_ocs_host . $this->_uri[$uri]
        );
        \Neigou\Logger::General('wms_ocs_order', array('data' => json_encode($send_data), 'result' => $result_str));
        return $result;
    }

    /*
     * @todo 获取商品微仓map
     */
    public function GetBranchId()
    {
        $arr = array(
            'province' => '北京市',
            'city' => '北京市',
            'county' => '海淀区',
            'addr' => '房地首华大厦',
            'extend_data' => json_encode(array(
                'location' => array(
                    'lat' => 39.94918,
                    'lng' => 116.44681,
                )
            ))
        );
        $arr = array(
            'province' => '北京市',
            'city' => '北京市',
            'county' => '朝阳区',
            'addr' => '芍药居北里317号楼 芍药居北里317#3-1304',
            'extend_data' => json_encode(array(
                'location' => array(
                    'lat' => 39.98521,
                    'lng' => 116.43131,
                )
            ))
        );
        $result = $this->SendData('get_branch_id', $arr);
        if ($result['code'] != 10000) {
            return false;
        } else {
            return $result['data']['goods_branch_list'];
        }
    }

    public function GetProductPrice($product_bn_list)
    {
        if(empty($product_bn_list)) {
            return false ;
        }
        $arr = [
            'filter' =>  [
                "product_bn_list" => array_unique($product_bn_list) ,
            ]
        ];
        $service_logic = new Service();
        $result  = $service_logic->ServiceCall('operate_price_batch', $arr);
        if ('SUCCESS' == $result['error_code']) {
           return  $result['data'];
        } else {
          return false ;
        }
    }

    public function GetProductBasePrice($product_bn_list)
    {
        if(empty($product_bn_list)) {
            return false ;
        }
        $arr = [
            'filter' =>  [
                "product_bn_list" => array_unique($product_bn_list) ,
            ]
        ];
        $service_logic = new Service();
        $result  = $service_logic->ServiceCall('base_price_batch', $arr);
        if ('SUCCESS' == $result['error_code']) {
            return  $result['data'];
        } else {
            return false ;
        }
    }

    private function SendData($uri, $order_data)
    {
        if (empty($order_data)) {
            return false;
        }
        if (!isset($this->_uri[$uri])) {
            return false;
        }
        $send_data = array('data' => base64_encode(json_encode($order_data)));
        $token = \App\Api\Common\Common::GetEcStoreSign($send_data);
        $send_data['token'] = $token;

        $curl = new \Neigou\Curl();
//        $curl->SetCookie(array(
//            'q_juyoufuli_com_AUTOCI_BRANCH' => 'jenkins-neigou-store-m5-632',
//            'www_fulufuxi_com_AUTOCI_BRANCH' => 'jenkins-neigou-store-m5-730',
//        ));
        $result_str = $curl->Post($this->_host . $this->_uri[$uri], $send_data);
        $result = trim($result_str, "\xEF\xBB\xBF");
        $result = json_decode($result, true);
        //\Neigou\Logger::Debug('service_ec_api', [
        //    'data' => json_encode($send_data),
        //    'uri' => $this->_host . $this->_uri[$uri],
        //    'result' => $result_str
        //]);
        return $result;
    }
}
