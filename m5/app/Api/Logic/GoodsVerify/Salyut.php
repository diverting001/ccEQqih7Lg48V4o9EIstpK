<?php

namespace App\Api\Logic\GoodsVerify;

use App\Api\Common\Common;
use Neigou\Curl;

class Salyut extends GoodsVerify
{

    /**
     * 调用salyut 匹配商品列表
     * @param $params
     * @return array
     */
    public function GetMatchGoodsList($params): array
    {
        $requestData = [
            'data' => json_encode($params),
        ];

        return $this->request('GoodsVerify', 'getMatchGoodsList', $requestData);
    }

    /**
     * 请求salyut
     * @param $classObj
     * @param $method
     * @param $params
     * @return array
     */
    public function request($classObj, $method, $params) : array
    {
        //请求salyut
        $client = new Curl();
        $requestData = $params;
        $requestData['class_obj'] = $classObj;
        $requestData['method'] = $method;
        $requestData['token'] = Common::GetSalyutSign($requestData);
        $res = $client->Post(config('neigou.SALYUT_DOMIN') . '/OpenApi/apirun/', $requestData);
        $res = json_decode($res, true);
        if (!isset($res['Result']) || $res['Result'] != 'true') {
            return $this->Response(false, $res['ErrorMsg']);
        }
        return $this->Response(true, '', $res['Data']);
    }
}
