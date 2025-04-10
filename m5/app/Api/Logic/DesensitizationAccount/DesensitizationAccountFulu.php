<?php

namespace App\Api\Logic\DesensitizationAccount;

use App\Api\Logic\DesensitizationAccount\DesensitizationAccountBase;
use App\Api\Logic\Openapi;

/**
 * 福禄
 */
class DesensitizationAccountFulu extends DesensitizationAccountBase
{
    /**
     * Notes:获取脱敏账号
     * Date: 2024/11/19 上午11:17
     * @param $accountNo
     * @param $accountType
     */
    public function getAccount($params)
    {
        $accountNo = $params['accountNo'] ?: '';
        $accountType = $params['accountType'] ?: '';
        if (empty($accountNo) || empty($accountType)) {
            return $this->returnData(self::ERROR_CODE_NO_RESULT, '参数错误');
        }

        $params = array(
            "account_no" => $accountNo,
            "account_type" => $accountType
        );
        $openapiLogic = new Openapi();
        $res = $openapiLogic->QueryV2('/ChannelInterop/V1/FuLu/DirectRecharge/getDesensitizationAccount', $params);
        if($res['Result'] !==true){
            return $this->returnData(self::ERROR_CODE_TRIPARTITE_SERVICE, '查询异常');
        }

        return $this->returnData(0, 'success', $res['Data']);
    }
}
