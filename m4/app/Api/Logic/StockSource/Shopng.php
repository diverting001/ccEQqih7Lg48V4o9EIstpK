<?php

namespace App\Api\Logic\StockSource;


class Shopng
{
    private $_host = '';


    public function __construct()
    {
        $this->_host = config('neigou.SHOP_DOMIN');
    }

    /*
     * @todo 获取ocs的货品库存
     */
    public function GetStock($post_data)
    {
        if (empty($post_data)) {
            return [];
        }
        $http_url = $this->_host . '/Shop/OpenApi/Channel/V1/Goods/Store/getStoreByProductBn';

        $token = \App\Api\Common\Common::GetShopV2Sign($post_data, config('neigou.SHOPNG_APPSECRET'));

        $data = array(
            'appkey' => config('neigou.SHOPNG_APPKEY'),
            'data' => json_encode($post_data),
            'sign' => $token,
            'time' => date('Y-m-d H:i:s'),
        );
        $curl = new \Neigou\Curl();
        $res = $curl->Post($http_url, $data);
        $res = json_decode($res, true);
        if ($res['Result'] == 'true') {
            return $res['Data'];
        } else {
            return [];
        }
    }
}
