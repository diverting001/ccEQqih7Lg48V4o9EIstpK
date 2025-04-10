<?php

namespace App\Api\Logic;

use App\Api\Logic\DesensitizationAccount\DesensitizationAccountFulu;

class DesensitizationAccount
{
    protected $partner;

    /**
     * Notes:获取脱敏账号
     * Date: 2024/11/19 上午11:25
     * @param $params
     * @param $partner
     */
    public function getAccount($params, $partner = 'fulu')
    {
        if (empty($params)) {
            return false;
        }

        $this->partner = $partner;

        $retData = [
            "status" => 0,
            "message" => "ok",
            "data" => [],
        ];
        switch ($partner) {
            case 'fulu':
                $fuluLogic = new DesensitizationAccountFulu();
                $res = $fuluLogic->getAccount($params);
                $retData['status'] = $res['status'];
                $retData['message'] = $res['message'];
                $retData['data'] = $res['data'];
                break;
            default:
                $retData['status'] = "100";
                $retData['message'] = "暂无服务";
                return $retData;
        }

        return $retData;
    }
}
