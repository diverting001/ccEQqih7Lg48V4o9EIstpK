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
use App\Api\V3\Service\ScenePoint\CompanyAccount as CompanyAccountServer;
use Illuminate\Http\Request;

/**
 * 场景积分管理
 * Class ScenePointManageController
 * @package App\Api\V3\Controllers\ScenePoint
 */
class AccountManageController extends BaseController
{

    /**
     * 获取公司账户
     * @param Request $request
     * @return array
     */
    public function GetCompanyAccount(Request $request)
    {
        $requestData = $this->getContentArray($request);
        if (empty($requestData['company_id'])) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }

        $accountServer = new CompanyAccountServer();

        $accountRes = $accountServer->GetCompanyAccount([
            'company_id' => $requestData['company_id']
        ]);

        $this->setErrorMsg($accountRes['msg']);

        if (!$accountRes['status']) {
            return $this->outputFormat([], 400);
        }

        return $this->outputFormat($accountRes['data']);
    }

    /**
     * 获取公司流水
     * @param Request $request
     * @return array
     */
    public function GetCompanyRecord(Request $request)
    {
        $requestData = $this->getContentArray($request);

        if (empty($requestData['company_id_list'])) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }

        $accountServer = new CompanyAccountServer();

        $recordRes = $accountServer->GetCompanyRecord([
            'company_id_list' => $requestData['company_id_list'],
            'last_created_at' => $requestData['last_created_at'],
            'direction'       => $requestData['direction'] ? $requestData['direction'] : 'DESC',
            'page'            => $requestData['page'] ? $requestData['page'] : 1,
            'page_size'       => $requestData['page_size'] ? $requestData['page_size'] : 20,
        ]);

        $this->setErrorMsg($recordRes['msg']);

        if (!$recordRes['status']) {
            return $this->outputFormat([], 400);
        }

        return $this->outputFormat($recordRes['data']);
    }


    /**
     * 查询公司下用户余额
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
                'overdue_time' => $requestData['overdue_time'] ?? time(),
                'member_ids' =>$requestData['member_ids'] ?? '',
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

    /**
     * 积分撤回
     * @param Request $request
     * @return array
     */
    public function MemberPointRecovery(Request $request)
    {
        $requestData = $this->getContentArray($request);

        if (
            empty($requestData['company_id']) ||
            empty($requestData['member_id']) ||
            empty($requestData['scene_id']) ||
            empty($requestData['assign_flow_code']) ||
            empty($requestData['point']) ||
            empty($requestData['money'])
        ) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }

        $accountServer = new MemberAccountServer();

        $recoveryRes = $accountServer->MemberPointRecovery(
            $requestData['company_id'],
            $requestData['member_id'],
            $requestData['scene_id'],
            $requestData['assign_flow_code'],
            $requestData['point'],
            $requestData['money']
        );

        $this->setErrorMsg($recoveryRes['msg']);

        if (!$recoveryRes['status']) {
            return $this->outputFormat([], 400);
        }

        return $this->outputFormat($recoveryRes['data']);

    }
    /**
     * 积分撤回指定额度
     * @param Request $request
     * @return array
     */
    public function MemberPointRecoveryAmount(Request $request)
    {
        $requestData = $this->getContentArray($request);

        if (
            empty($requestData['company_id']) ||
            empty($requestData['member_id']) ||
            empty($requestData['scene_id']) ||
            empty($requestData['point']) ||
            empty($requestData['money'])
        ) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }

        $accountServer = new MemberAccountServer();

        $recoveryRes = $accountServer->MemberPointRecoveryAmount(
            $requestData['company_id'],
            $requestData['member_id'],
            $requestData['scene_id'],
            $requestData['point'],
            $requestData['money']
        );

        $this->setErrorMsg($recoveryRes['msg']);

        if (!$recoveryRes['status']) {
            return $this->outputFormat([], 400);
        }

        return $this->outputFormat($recoveryRes['data']);
    }

    /**
     * 查询公司下用户余额(子账户)
     *
     * @param  array $request
     * @return array
     */
    public function GetSonMemberList(Request $request)
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

        $accountRes = $accountServer->GetSonMemberList(
            [
                'company_id' => $requestData['company_id'],
                'scene_id'   => $requestData['scene_id'] ?? [],
                'filter_time'=> $requestData['filter_time'] ?? [],
                'page'       => $requestData['page'] ?? 1,
                'pageSize'   => $requestData['pageSize'] ?? 10
            ]
        );

        $this->setErrorMsg($accountRes['msg']);

        if (!$accountRes['status']) {
            return $this->outputFormat([], 400);
        }

        return $this->outputFormat($accountRes['data']);
    }

}
