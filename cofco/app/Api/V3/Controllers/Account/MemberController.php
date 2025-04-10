<?php
/**
 * @author shihuiqian<shihuiqian@neigou.com>
 */

namespace App\Api\V3\Controllers\Account;


use App\Api\Common\Controllers\BaseController;
use App\Api\V3\Service\Account\Company;
use App\Api\V3\Service\Account\Member;
use Illuminate\Http\Request;

class MemberController extends BaseController
{
    /**
     * @var Member
     */
    protected $memberService;

    /**
     * @var Company
     */
    protected $companyService;

    /**
     * MemberController constructor.
     */
    public function __construct()
    {
        $this->memberService = new Member();
        $this->companyService = new Company();
    }

    /**
     * 创建用户
     *
     * @return array
     * @throws \Exception
     */
    public function create(Request $request)
    {
        $param = $this->getContentArray($request);

        if (empty($param['company_id'])) {
            $this->setErrorMsg('公司ID不能为空');
            return $this->outputFormat($param, 10001);
        }

        if (!empty($param['mobile']) && !Member::isMobile($param['mobile'])) {
            $this->setErrorMsg('手机号格式错误');
            return $this->outputFormat($param, 10001);
        }
        if (!empty($param['email']) && !Member::isEmail($param['email'])) {
            $this->setErrorMsg('邮箱格式错误');
            return $this->outputFormat($param, 10001);
        }

        $response = $this->memberService->create($param);

        $this->setErrorMsg($response['message']);
        return $this->outputFormat($response['success'] ? $response['data'] : $param, $response['success'] ? 0 : 10002);
    }

    /**
     * 按用户ID查询
     *
     * @return array
     */
    public function queryById(Request $request)
    {
        $param = $this->getContentArray($request);

        if (empty($param['member_id'])) {
            $this->setErrorMsg('用户ID不能为空');
            return $this->outputFormat($param, 10001);
        }

        $this->setErrorMsg('success');
        return $this->outputFormat($this->memberService->getMemberById($param['member_id']));
    }

    /**
     * 按手机号查询
     *
     * @return array
     */
    public function queryByMobile(Request $request)
    {
        $param = $this->getContentArray($request);

        if (empty($param['mobile'])) {
            $this->setErrorMsg('手机号不能为空');
            return $this->outputFormat($param, 10001);
        } elseif (!Member::isMobile($param['mobile'])) {
            $this->setErrorMsg('手机号格式错误');
            return $this->outputFormat($param, 10001);
        }

        $this->setErrorMsg('success');
        return $this->outputFormat($this->memberService->getMemberByMobile($param['mobile']));
    }

    /**
     * 按邮箱查询
     *
     * @return array
     */
    public function queryByEmail(Request $request)
    {
        $param = $this->getContentArray($request);

        if (empty($param['email'])) {
            $this->setErrorMsg('邮箱不能为空');
            return $this->outputFormat($param, 10001);
        } elseif (!Member::isEmail($param['email'])) {
            $this->setErrorMsg('邮箱格式错误');
            return $this->outputFormat($param, 10001);
        }

        $this->setErrorMsg('success');
        return $this->outputFormat($this->memberService->getByEmail($param['email']));
    }

    /**
     * 按公司ID和员工账号查询
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

        $this->setErrorMsg('success');
        return $this->outputFormat(
            $this->memberService->getByCompanyAndAccount($param['company_id'], $param['account'])
        );
    }

    /**
     * 按ID更新用户
     *
     * @return array
     */
    public function updateById(Request $request)
    {
        $param = $this->getContentArray($request);

        if (empty($param['member_id'])) {
            $this->setErrorMsg('员工ID不能为空');
            return $this->outputFormat($param, 10001);
        } elseif (empty($param['data'])) {
            $this->setErrorMsg('更新数据不能为空');
            return $this->outputFormat($param, 10001);
        }

        $response = $this->memberService->updateById($param['member_id'], $param['data']);
        $this->setErrorMsg($response['message']);
        return $this->outputFormat($response['success'] ? $response['data'] : $param, $response['success'] ? 0 : 10002);
    }

    /**
     *  创建登录token
     *
     * @return array
     */
    public function createLoginToken(Request $request)
    {
        $param = $this->getContentArray($request);

        if (empty($param['member_id'])) {
            $this->setErrorMsg('员工ID不能为空');
            return $this->outputFormat($param, 10001);
        } elseif (empty($param['company_id'])) {
            $this->setErrorMsg('公司ID不能为空');
            return $this->outputFormat($param, 10001);
        }

        $response = $this->memberService->createLoginToken($param['member_id'], $param['company_id']);
        $this->setErrorMsg($response['message']);

        return $this->outputFormat($response['success'] ? $response['data'] : $param, $response['success'] ? 0 : 10002);
    }

    /**
     * token获取用户信息
     *
     * @return array
     */
    public function getInfoByToken(Request $request)
    {
        $param = $this->getContentArray($request);

        if (empty($param['login_token'])) {
            $this->setErrorMsg('TOKEN不能为空');

            return $this->outputFormat($param, 10001);
        }

        $response = $this->memberService->getInfoByToken($param['login_token']);

        $this->setErrorMsg($response['message']);

        return $this->outputFormat($response['success'] ? $response['data'] : $param, $response['success'] ? 0 : 10002);
    }
}
