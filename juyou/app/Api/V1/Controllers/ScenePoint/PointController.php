<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-02-13
 * Time: 11:01
 */

namespace App\Api\V1\Controllers\ScenePoint;

use App\Api\Common\Controllers\BaseController;
use App\Api\V1\Service\PointScene\Account as AccountServer;
use Illuminate\Http\Request;

class PointController extends BaseController
{
    public function ReleaseFrozen(Request $request)
    {
        $frozenData = $this->getContentArray($request);
        if (
            empty($frozenData['business_type']) ||
            empty($frozenData['business_bn']) ||
            empty($frozenData['system_code'])
        ) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }

        $companyServer = new AccountServer();
        $res = $companyServer->ReleaseFrozen($frozenData);
        $this->setErrorMsg($res['msg']);
        if ($res['status']) {
            return $this->outputFormat($res['data'], 0);
        } else {
            return $this->outputFormat(array(), 400);
        }
    }

}
