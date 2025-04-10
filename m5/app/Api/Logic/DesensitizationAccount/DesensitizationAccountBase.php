<?php

namespace App\Api\Logic\DesensitizationAccount;

class DesensitizationAccountBase
{
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

    public function returnData($status = 0, $msg = 'success', $data = array()): array
    {
        return array('status' => $status, 'message' => $msg, 'data' => $data);
    }
}
