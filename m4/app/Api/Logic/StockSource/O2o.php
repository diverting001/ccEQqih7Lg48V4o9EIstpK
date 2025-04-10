<?php

namespace App\Api\Logic\StockSource;


class O2o
{
    private $_host = '';
    private $_sign = '';
    private $_uri = array(
        'get_stock' => '/Admin/OpenApi/apirun'
    );


    public function __construct()
    {
        $this->_sign = config('neigou.MIS_SIGN');
        $this->_host = config('neigou.MIS_DOMIN');
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
            'method' => 'GetStock',
            'class_obj' => 'O2OStock',
            'product_list' => json_encode($post_data)
        );
        $token = $this->GetToken($post_data);
        $post_data['token'] = $token;
        $curl = new \Neigou\Curl();
        $res = $curl->Post($this->_host . $this->_uri['get_stock'], $post_data);
        $res = json_decode($res, true);
        if ($res['Result'] == 'true') {
            return $res['Data'];
        } else {
            return [];
        }
    }

    /*
     * @todo 获取请求token
     */
    private function GetToken($arr)
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
        $sign_ori_string .= ("&key=" . $this->_sign);
        return strtoupper(md5($sign_ori_string));
    }
}
