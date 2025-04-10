<?php

namespace App\Api\Logic\StockSource;

class Salyut
{
    private $_host = '';
    private $_uri = array(
        'get_stock' => '/Home/OpenApi/apirun/'
    );

    public function __construct()
    {
        $this->_host = config('neigou.SALYUT_DOMIN');
    }

    /*
     * @todo 获取salyut的货品库存
     */
    public function GetStock($data)
    {
        if (empty($data)) {
            return [];
        }
        $post_data = array(
            'data' => json_encode($data),
            'class_obj' => 'SalyutGoods',
            'method' => 'getGoodsStock',
        );
        $post_data['token'] = $this->getToken($post_data);
        $curl = new \Neigou\Curl();
        $res = $curl->Post($this->_host . $this->_uri['get_stock'], $post_data);
        $res = json_decode($res, true);
        if ($res['Result'] == 'true') {
            return $res['Data'];
        } else {
            return [];
        }
    }

    public function getToken($arr)
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
