<?php

namespace App\Api\Logic\Address;

class AddressMapBase
{
    protected $place_suggestion_number = 3;

    /**
     * 成功
     */
    const SUCCESS_CODE = 0;

    /**
     * 错误码 无数据
     */
    const ERROR_CODE_NO_DATA = 1000;

    /**
     * 错误码 缺失指定的参数
     */
    const ERROR_CODE_NO_RESULT = 1001;

    /**
     * 错误码 服务端异常
     */
    const ERROR_CODE_TRIPARTITE_SERVICE = 1002;

    /**
     * 错误码 剩余查询次数不足
     */
    const ERROR_CODE_NO_EXCESS_QUERY = 1003;

    /**
     * 错误码 并发请求过多
     */
    const ERROR_CODE_CONCURRENT_QUERY_OVERFLOW = 1004;

    /**
     * @param $region
     * @return array|string|string[]
     */
    protected function _parseRegion($region)
    {
        return str_replace(
            array('中国台湾', '中国香港', '中国澳门', '海东地区', '昌都地区', '那曲地区', '日喀则地区', '山南地区', '哈密地区', '吐鲁番地区',),
            array('台湾', '香港', '澳门', '海东市', '昌都市', '那曲市', '日喀则市', '山南市', '哈密市', '吐鲁番市',),
            $region
        );
    }

    public function returnData($status = 0, $msg = 'success', $data = array()): array
    {
        return array('status' => $status, 'message' => $msg, 'data' => $data);
    }
}
