<?php

namespace App\Api\Logic\Shop;

class Orders
{

    /*
     * @todo 创建订单
     */
    public function Create($order_data, $split_orders, &$result_data)
    {
        if (empty($order_data) OR empty($split_orders)) {
            return false;
        }

        $orders = array();
        foreach ($split_orders['split_orders'] as $order) {
            if (!in_array($order['wms_code'], array('SHOP', 'SHOPNG'))) {
                continue;
            }

            $items = array();

            foreach ($order['items'] as $item) {
                $items[] = array(
                    'bn' => $item['bn'],
                    'p_bn' => $item['p_bn'],
                    'name' => $item['name'],
                    'cost' => $item['cost'],
                    'price' => $item['price'],
                    'mktprice' => $item['mktprice'],
                    'amount' => $item['amount'],
                    'weight' => $item['weight'],
                    'nums' => $item['nums'],
                    'pmt_amount' => $item['pmt_amount'],
                    'cost_freight' => $item['cost_freight'],
                    'cost_tax' => $item['cost_tax'],
                    'point_amount' => $item['point_amount'],
                    'item_type' => $item['item_type'],
                );
            }

            $orders[$order['wms_code']][] = array(
                'order_id' => $order['order_id'],
                'member_id' => intval($order_data['member_id']),
                'company_id' => intval($order_data['company_id']),
                'ship_name' => $order_data['ship_name'],
                'ship_addr' => $order_data['ship_addr'],
                'ship_town' => $order_data['ship_town'],
                'ship_zip' => $order_data['ship_zip'] ? $order_data['ship_zip'] : '',
                'ship_tel' => $order_data['ship_tel'] ? $order_data['ship_tel'] : '',
                'ship_mobile' => $order_data['ship_mobile'] ? $order_data['ship_mobile'] : '',
                'ship_province' => $order_data['ship_province'] ? $order_data['ship_province'] : '',
                'ship_city' => $order_data['ship_city'] ? $order_data['ship_city'] : '',
                'ship_county' => $order_data['ship_county'] ? $order_data['ship_county'] : '',
                'idcardname' => $order_data['idcardname'] ? $order_data['idcardname'] : '',
                'idcardno' => $order_data['idcardno'] ? $order_data['idcardno'] : '',
                'final_amount' => $order_data['final_amount'],
                'cost_freight' => $order_data['cost_freight'],
                'cost_item' => $order_data['cost_item'],
                'pmt_amount' => $order_data['pmt_amount'],
                'weight' => $order_data['weight'],
                'pop_owner_id' => $order['pop_owner_id'],
                'memo' => $order['memo'],
                'extend_data' => $order_data['extend_data'],
                'create_time' => $order_data['create_time'],
                'items' => $items,
            );
        }

        $return = true;

        foreach ($orders as $wmsCode => $order) {
            $result = $this->SendOrder($order, $wmsCode);
            if (empty($result) OR $result['Result'] != 'true') {
                if (!empty($result['Data']['product_list'])) {
                    $result_data = array_merge($result_data, $result['Data']['product_list']);
                    $return = false;
                }
            }
        }

        return $return;
    }

    /*
     * @todo 提交shop预下单
     */
    private function SendOrder($shop_order, $wms_code)
    {
        $curl = new \Neigou\Curl();
        $curl->time_out = 7;
        $order_info = $shop_order;
        $shop_order = array('data' => base64_encode(json_encode($shop_order)));
        if (empty($shop_order)) {
            return false;
        }

        if ($wms_code == 'SHOP') {
            $appKey = config('neigou.SHOP_APPKEY');
            $appSecret = config('neigou.SHOP_APPSECRET');
        } elseif ($wms_code == 'SHOPNG') {
            $appKey = config('neigou.SHOPNG_APPKEY');
            $appSecret = config('neigou.SHOPNG_APPSECRET');
        } else {
            return true;
        }

        $shop_order['wms_code'] = $wms_code;
        $http_url = config('neigou.SHOP_DOMIN') . '/Shop/OpenApi/Channel/V1/Order/Order/createPreOrder';
        $token = \App\Api\Common\Common::GetShopV2Sign($shop_order, $appSecret);
        $data = array(
            'appkey' => $appKey,
            'data' => json_encode($shop_order),
            'sign' => $token,
            'time' => date('Y-m-d H:i:s'),
        );

        $curl = new \Neigou\Curl();
        $result_str = $curl->Post($http_url, $data);
        $result = trim($result_str, "\xEF\xBB\xBF");

        \Neigou\Logger::General('shop_service_order_create', array('sender' => $data, 'remark' => $result, 'order_info' => $order_info));
        if ($curl->GetHttpCode() != 200) {
            $result = false;
        } else {
            $result = json_decode($result, true);
        }
        return $result;
    }


    /*
     * @todo 订单取消
     */
    public function OrderCancel($order_data)
    {
        if (empty($order_data)) {
            return false;
        }
        if ($order_data['wms_code'] == 'SHOPNG' OR $order_data['wms_code'] == 'SHOP') {
            $http_url = config('neigou.SHOP_DOMIN') . '/Shop/OpenApi/Channel/V1/Order/Order/cancelOrder';

            if ($order_data['wms_code'] == 'SHOP') {
                $appKey = config('neigou.SHOP_APPKEY');
                $appSecret = config('neigou.SHOP_APPSECRET');
            } elseif ($order_data['wms_code'] == 'SHOPNG') {
                $appKey = config('neigou.SHOPNG_APPKEY');
                $appSecret = config('neigou.SHOPNG_APPSECRET');
            } else {
                return true;
            }

            $token = \App\Api\Common\Common::GetShopV2Sign($order_data, $appSecret);
            $data = array(
                'appkey' => $appKey,
                'data' => json_encode($order_data),
                'sign' => $token,
                'time' => date('Y-m-d H:i:s'),
            );

            $curl = new \Neigou\Curl();
            $result_str = $curl->Post($http_url, $data);
            $result = trim($result_str, "\xEF\xBB\xBF");
        }
        \Neigou\Logger::General('shop_service_order_cancel', array('sender' => $data, 'remark' => $result));
        $result = json_decode($result, true);
        return $result;
    }

    /*
     * @todo 创建预下单
     */
    public function PreOrderCreate(
        $order_data,
        $order_items,
        &$result_data,
        &$msg = '',
        &$err_code = '',
        &$err_data = []
    ) {
        return true;
    }

    /*
    * @todo 获取预下单外部源编号
    */
    private function getExternalSourceBn($supplier_bn, $order_id)
    {
        return 'SERVICE-PRE-' . $supplier_bn . '-' . $order_id;
    }
}
