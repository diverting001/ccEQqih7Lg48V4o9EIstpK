<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-12-24
 * Time: 10:35
 */

namespace App\Api\V3\Controllers\ScenePoint;

use App\Api\Common\Controllers\BaseController;
use App\Api\V3\Service\ScenePoint\MemberAccount as MemberAccountServer;
use Illuminate\Http\Request;

/**
 * 场景积分管理
 * Class ScenePointManageController
 * @package App\Api\V3\Controllers\ScenePoint
 */
class AccountManageController extends BaseController
{
    /**
     * 查询公司下所有场景积分
     */
    public function GetMemberList(Request $request)
    {
        $requestData = $this->getContentArray($request);

        if (empty($requestData['company_id'])) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }

        if ($requestData['pageSize'] > 100) {
            $this->setErrorMsg('单次查询不能大于100条');
            return $this->outputFormat([], 400);
        }

        $accountServer = new MemberAccountServer();

        $accountRes = $accountServer->GetMemberList(
            [
                'company_id'   => $requestData['company_id'],
                'scene_id'     => $requestData['scene_id'] ?? '',
                'overdue_time' => $requestData['overdue_time'] ?? time()
            ],
            $requestData['page'] ?? 1,
            $requestData['pageSize'] ?? 10
        );

        $this->setErrorMsg($accountRes['msg']);

        if (!$accountRes['status']) {
            return $this->outputFormat([], 400);
        }

        return $this->outputFormat($accountRes['data']);
    }
}
