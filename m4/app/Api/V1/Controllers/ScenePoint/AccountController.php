<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-11-19
 * Time: 20:47
 */

namespace App\Api\V1\Controllers\ScenePoint;

use App\Api\Common\Controllers\BaseController;
use App\Api\V1\Service\PointServer\Account as AccountService;
use Illuminate\Http\Request;

class AccountController extends BaseController
{
    public function GetSonAccount(Request $request)
    {
        $param    = $this->getContentArray($request);
        $accounts = $param['account_bns'];

        $accountService = new AccountService();

        $res = $accountService->GetSonAccount($accounts);

        if ($res['status']) {
            $this->setErrorMsg('请求成功');
            return $this->outputFormat($res['data'], 0);
        } else {
            $this->setErrorMsg($res['msg']);
            return $this->outputFormat(array(), 400);
        }
    }
}
