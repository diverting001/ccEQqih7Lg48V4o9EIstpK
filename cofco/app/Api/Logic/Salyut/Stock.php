<?php

namespace App\Api\Logic\Salyut;

class Stock
{

    /*
     * @todo 获取货品库存
     */
    public static function ProductStock($products_bn, $filter, $product_num_list = array())
    {
        if (empty($products_bn) || empty($filter['province'])) {
            return [];
        }
        foreach ($products_bn as $v) {
            $product_data = array(
                'product_bn' => $v,
                'num' => isset($product_num_list[$v]) ? $product_num_list[$v] : 1,
            );
            $send_products_bn[] = $product_data;
        }
        $client = new \Neigou\Curl();
        $deliver_extend_data = empty($filter['extend_data']) ? array() : json_decode($filter['extend_data'], true);
        $data = array(
            'province' => empty($filter['province']) ? '' : $filter['province'],
            'city' => empty($filter['city']) ? '' : $filter['city'],
            'county' => empty($filter['county']) ? '' : $filter['county'],
            'town' => empty($filter['town']) ? '' : $filter['town'],
            'addr' => empty($filter['addr']) ? '' : $filter['addr'],
            'longitude' => isset($deliver_extend_data['location']['lng']) ? $deliver_extend_data['location']['lng'] : '',
            //经度
            'latitude' => isset($deliver_extend_data['location']['lat']) ? $deliver_extend_data['location']['lat'] : '',
            //纬度
            'products' => json_encode($send_products_bn),
        );
        $token_data = $data;
        $token_data['class_obj'] = 'SalyutGoods';
        $token_data['method'] = 'GetProductStock';
        $token_data['token'] = self::getJDToken($token_data);
        $res = $client->Post(config('neigou.SALYUT_DOMIN') . '/OpenApi/apirun/', $token_data);
        $res = json_decode($res, true);
        if ($res['Result'] == 'true') {
            $data = array();
            if (!empty($res['Data'])) {
                foreach ($res['Data'] as $k => $v) {
                    $data[$k] = $v;
                    $data[$k]['stock_state'] = $v['status'] == 1 ? 2 : 1;    //库存状态 1有库存 2:无库存
                }
            }
            return $data;
        } else {
            return [];
        }
    }

    public static function getJDToken($arr)
    {
        ksort($arr);
        $sign_ori_string = "";
        foreach ($arr as $key => $value) {
            if (!empty($value) && !is_array($value)) {
                if (!empty($sign_ori_string)) {
                    $sign_ori_string .= "&$key=$value";
                } else {
                    $sign_ori_string = "$key=$value";
                }
            }
        }
        $sign_ori_string .= ("&key=" . config('neigou.SALYUT_SIGN'));
        return strtoupper(md5($sign_ori_string));
    }
}
