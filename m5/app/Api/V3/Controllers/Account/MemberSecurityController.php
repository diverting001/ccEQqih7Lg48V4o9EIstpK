<?php
/**
 * @author shihuiqian<shihuiqian@neigou.com>
 */

namespace App\Api\V3\Controllers\Account;


use App\Api\Common\Controllers\BaseController;
use App\Api\V3\Service\Account\Member;
use Illuminate\Http\Request;

class MemberSecurityController extends BaseController
{
    /**
     * @var Member
     */
    protected $memberService;

    /**
     * MemberController constructor.
     */
    public function __construct()
    {
        $this->memberService = new Member();
    }

    /**
     * 验证密码
     *
     * @return array
     */
    public function checkPassword(Request $request)
    {
        $param = $this->getContentArray($request);
        if (empty($param['member_id'])) {
            $this->setErrorMsg('用户ID不能为空');
            return $this->outputFormat($param, 10001);
        } elseif (empty($param['password'])) {
            $this->setErrorMsg('密码不能为空');
            return $this->outputFormat($param, 10001);
        }

        $response = $this->memberService->checkPassword($param['member_id'], $param['password']);
        $this->setErrorMsg($response['message']);
        return $this->outputFormat($response['success'] ? $response['data'] : $param, $response['success'] ? 0 : 10002);
    }

    /**
     * 设置密码
     *
     * @return array
     */
    public function setPassword(Request $request)
    {
        $param = $this->getContentArray($request);

        if (empty($param['member_id'])) {
            $this->setErrorMsg('用户ID不能为空');
            return $this->outputFormat($param, 10001);
        } elseif (empty($param['password'])) {
            $this->setErrorMsg('密码不能为空');
            return $this->outputFormat($param, 10001);
        }

        $response = $this->memberService->setPassword($param['member_id'], $param['password']);
        $this->setErrorMsg($response['message']);
        return $this->outputFormat($response['success'] ? $response['data'] : $param, $response['success'] ? 0 : 10002);
    }
}
