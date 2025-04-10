<?php

namespace App\Api\V1\Controllers;

use App\Api\Common\Controllers\BaseController;
use Illuminate\Http\Request;
use App\Api\Logic\DesensitizationAccount;

/**
 * 脱敏账号
 */
class DesensitizationAccountController extends BaseController
{
    /**
     * Notes:获取脱敏账号
     * Date: 2024/11/19 上午10:59
     * @param Request $request
     */
    public function getAccount(Request $request)
    {
        $params = $this->getContentArray($request);
        if (empty($params['accountNo']) || empty($params['accountType'])) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat($params, 50001);
        }

        $params['accountNo'] = str_replace(['(', ')', '#', '"', '\''], ['（', '）', '', '', ''], $params['accountNo']);
        $partner = !empty($params['partner']) ? $params['partner'] : config('neigou.DESENSITIZATION_ACCOUNT_PLATFORM');

        try {
            $accountLogic = new DesensitizationAccount();
            $result = $accountLogic->getAccount($params, $partner);
            if (!empty($result) && $result['status'] == 0) {
                $this->setErrorMsg('success');
                return $this->outputFormat($result['data'], 0);
            }
        } catch (\Exception $e) {
            $this->setErrorMsg($e->getMessage());
            return $this->outputFormat([], 400);
        }

        $this->setErrorMsg('失败 ' . $res['message'] ?? '');
        return $this->outputFormat([], $res['status'] ?? 100010);
    }
}
