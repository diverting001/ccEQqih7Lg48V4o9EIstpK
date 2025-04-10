<?php

namespace App\Api\Logic\StockSource;


class Ocs
{
    private $_host = '';
    private $_uri = array(
        'get_stock' => '/openapi/stock/GetStock'
    );


    public function __construct()
    {
        $this->_host = config('neigou.OCS_DOMIN');
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
            'product_list' => json_encode($post_data)
        );
        $curl = new \Neigou\Curl();
        $res = $curl->Post($this->_host . $this->_uri['get_stock'], $post_data);
        $res = json_decode($res, true);
        if ($res['Result'] == 'true') {
            return $res['Data'];
        } else {
            return [];
        }
    }
}
