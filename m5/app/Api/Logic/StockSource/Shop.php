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

    /*
    * @todo 获取货品库存
    */
    public function getProductStock($products_bn, $filter, $product_num_list = array())
    {
        $return = array();

        if (empty($products_bn)) {
            return $return;
        }

        $data = array();
        foreach ($products_bn as $v) {
            $data[] =  array('product_bn' => $v);
        }

        $result = $this->GetStock($data);

        foreach ($products_bn as $v) {
            $stock = ! empty($result[$v]) ? $result[$v]['stock'] : 0;
            $num = ! empty($product_num_list[$v]) ? intval($product_num_list[$v]) : 1;
            $return[$v] = array(
                'stock' => $stock,
                'stock_state' => $stock >= $num ? 1 : 2,
            );
        }

        return $return;
    }

}
