<?php

namespace App\Api\Logic\StockSource;


class Shop
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
        $post_data = array(
            'class_obj' => 'Store',
            'method' => 'getStoreByProductBn',
            'data' => json_encode($post_data)
        );
        $token = \App\Api\Common\Common::GetShopSign($post_data);
        $post_data['token'] = $token;
        $curl = new \Neigou\Curl();
        $res = $curl->Post($this->_host . '/Shop/OpenApi/apirun', $post_data);
        $res = json_decode($res, true);
        if ($res['Result'] == 'true') {
            return $res['Data'];
        } else {
            return [];
        }
    }
}
