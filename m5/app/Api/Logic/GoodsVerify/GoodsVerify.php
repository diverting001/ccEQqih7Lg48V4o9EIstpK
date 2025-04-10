<?php

namespace App\Api\Logic\GoodsVerify;

abstract class GoodsVerify
{
    /**
     * 匹配商品列表
     * @param $params
     * @return mixed
     */
    abstract public function GetMatchGoodsList($params);

    public function Response($status = true, $msg = '成功', $data = [])
    {
        return [
            'status' => $status,
            'msg' => $msg,
            'data' => $data,
        ];
    }
}
