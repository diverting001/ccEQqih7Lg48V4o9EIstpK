<?php

namespace App\Api\Logic\Promise;

class Salyut
{
    /**
     * 获取货品预计送达时间
     * @param $product
     * @param $area
     * @param $extendData
     * @return array|mixed
     */
    public function getProductPromiseInfo($product, $area, $extendData = array())
    {
        $return = array();
        if (empty($product['product_bn']) || empty($product['num']) || empty($area['province']) || empty($area['city']) || empty($area['county'])) {
            return $return;
        }

        //请求salyut
        $client = new \Neigou\Curl();
        $requestData = array(
            'province' => empty($area['province']) ? '' : $area['province'],
            'city' => empty($area['city']) ? '' : $area['city'],
            'county' => empty($area['county']) ? '' : $area['county'],
            'town' => empty($area['town']) ? '' : $area['town'],
            'addr' => empty($area['addr']) ? '' : $area['addr'],
            'product' => json_encode($product),
        );

        $requestData['class_obj'] = 'SalyutGoods';
        $requestData['method'] = 'getProductPromiseInfo';
        $requestData['token'] =  \App\Api\Common\Common::GetSalyutSign($requestData);
        $res = $client->Post(config('neigou.SALYUT_DOMIN'). '/OpenApi/apirun/', $requestData);
        $res = json_decode($res, true);
        if ($res['Result'] == 'true' && !empty($res['Data'])) {
            $return = $res['Data'];
        }

        return  $return;
    }

}
