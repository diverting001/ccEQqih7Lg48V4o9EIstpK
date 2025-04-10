<?php
/**
 * @author shihuiqian<shihuiqian@neigou.com>
 */

namespace App\Api\V3\Controllers\Account;


use App\Api\Common\Controllers\BaseController;
use App\Api\V3\Service\Account\Member;
use App\Api\V3\Service\Account\MemberCompany;
use Illuminate\Http\Request;

class CompanyMemberController extends BaseController
{
    /**
     * @var MemberCompany
     */
    protected $memberCompanyService;


    /**
     * CompanyMemberController constructor.
     */
    public function __construct()
    {
        $this->memberCompanyService = new MemberCompany();
    }

    /**
     * 创建员工
     *
     * @return array
     */
    public function create(Request $request)
    {
        $param = $this->getContentArray($request);

        if (empty($param['company_id'])) {
            $this->setErrorMsg('公司ID不能为空');
            return $this->outputFormat($param, 10001);
        } elseif (empty($param['member_id'])) {
            $this->setErrorMsg('用户ID不能为空');
            return $this->outputFormat($param, 10001);
        } elseif (empty($param['account'])) {
            $this->setErrorMsg('员工账号不能为空');
            return $this->outputFormat($param, 10001);
        }

        $response = $this->memberCompanyService->create($param);

        $this->setErrorMsg($response['message']);
        return $this->outputFormat($response['success'] ? $response['data'] : $param, $response['success'] ? 0 : 10002);

    }

    /**
     * 按ID查询员工
     *
     * @return array
     */
    public function queryById(Request $request)
    {
        $param = $this->getContentArray($request);

        if (empty($param['id'])) {
            $this->setErrorMsg('员工ID不能为空');
            return $this->outputFormat($param, 10001);
        }

        $data = $this->memberCompanyService->getCompanyMemberById($param['id']);

        $this->setErrorMsg('success');
        return $this->outputFormat($data);
    }

    /**
     * 按邮箱查询员工
     *
     * @return array
     */
    public function queryByEmail(Request $request)
    {
        $param = $this->getContentArray($request);

        if (empty($param['email'])) {
            $this->setErrorMsg('邮箱不能为空');
            return $this->outputFormat($param, 10001);
        }

        if (!Member::isEmail($param['email'])) {
            $this->setErrorMsg('邮箱格式错误');
            return $this->outputFormat($param, 10001);
        }

        $data = $this->memberCompanyService->getByEmail($param['email']);

        $this->setErrorMsg('success');
        return $this->outputFormat($data);
    }

    /**
     * 按公司ID和用户ID查询员工
     *
     * @return array
     */
    public function queryByCompanyAndMember(Request $request)
    {
        $param = $this->getContentArray($request);

        if (empty($param['company_id'])) {
            $this->setErrorMsg('公司ID不能为空');
            return $this->outputFormat($param, 10001);
        } elseif (empty($param['member_id'])) {
            $this->setErrorMsg('用户ID不能为空');
            return $this->outputFormat($param, 10001);
        }

        $data = $this->memberCompanyService->getByCompanyAndMember($param['company_id'], $param['member_id']);

        $this->setErrorMsg('success');
        return $this->outputFormat($data);
    }

    /**
     * 按公司ID和员工账号查询员工
     *
     * @return array
     */
    public function queryByCompanyAndAccount(Request $request)
    {
        $param = $this->getContentArray($request);

        if (empty($param['company_id'])) {
            $this->setErrorMsg('公司ID不能为空');
            return $this->outputFormat($param, 10001);
        } elseif (empty($param['account'])) {
            $this->setErrorMsg('员工账号不能为空');
            return $this->outputFormat($param, 10001);
        }

        $data = $this->memberCompanyService->getByCompanyAndAccount($param['company_id'], $param['account']);

        $this->setErrorMsg('success');
        return $this->outputFormat($data);
    }

    /**
     * 按ID更新员工
     *
     * @return array
     */
    public function updateById(Request $request)
    {
        $param = $this->getContentArray($request);
        if (empty($param['id'])) {
            $this->setErrorMsg('员工ID不能为空');
            return $this->outputFormat($param, 10001);
        } elseif (empty($param['data'])) {
            $this->setErrorMsg('更新数据不能为空');
            return $this->outputFormat($param, 10001);
        }

        $response = $this->memberCompanyService->updateById($param['id'], $param['data']);
        $this->setErrorMsg($response['message']);

        return $this->outputFormat($response['success'] ? $response['data'] : $param, $response['success'] ? 0 : 10002);
    }

    /**
     * 按邮箱更新员工
     *
     * @return array
     */
    public function updateByCompanyAndMember(Request $request)
    {
        $param = $this->getContentArray($request);

        if (empty($param['company_id'])) {
            $this->setErrorMsg('公司ID不能为空');
            return $this->outputFormat($param, 10001);
        } elseif (empty($param['member_id'])) {
            $this->setErrorMsg('用户ID不能为空');
            return $this->outputFormat($param, 10001);
        } elseif (empty($param['data'])) {
            $this->setErrorMsg('更新数据不能为空');
            return $this->outputFormat($param, 10001);
        }

        $response = $this->memberCompanyService->updateByCompanyAndMember(
            $param['company_id'],
            $param['member_id'],
            $param['data']
        );

        $this->setErrorMsg($response['message']);

        return $this->outputFormat($response['success'] ? $response['data'] : $param, $response['success'] ? 0 : 10002);
    }

}
