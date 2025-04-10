<?php
/**
 * @author shihuiqian<shihuiqian@neigou.com>
 */

namespace App\Api\V3\Service\Account;

use App\Api\Model\Account\MemberCompany as MemberCompanyModel;
use App\Api\Model\Account\Company as CompanyModel;
use App\Api\Model\Account\Member as MemberModel;

class MemberCompany
{

    /**
     * @var MemberCompanyModel
     */
    protected $memberCompanyModel;

    /**
     * @var CompanyModel
     */
    protected $companyModel;

    /**
     * @var MemberModel
     */
    protected $memberModel;

    /**
     * MemberCompany constructor.
     */
    public function __construct()
    {
        $this->memberCompanyModel = new MemberCompanyModel();
        $this->memberModel = new MemberModel();
        $this->companyModel = new CompanyModel();
    }

    /**
     * 按ID查询
     *
     * @param int $id
     * @return array|null
     */
    public function getCompanyMemberById($id)
    {
        return $this->memberCompanyModel->getCompanyMemberById($id);
    }

    /**
     * 按公司ID和用户ID查询
     *
     * @param $companyId
     * @param $memberId
     * @return array|null
     */
    public function getByCompanyAndMember($companyId, $memberId)
    {
        return $this->memberCompanyModel->getByCompanyAndMember($companyId, $memberId);
    }

    /**
     * 按公司和员工账号查询
     *
     * @param int $company
     * @param string $account
     * @return array|null
     */
    public function getByCompanyAndAccount($company, $account)
    {
        return $this->memberCompanyModel->getByCompanyAndAccount($company, $account);
    }

    /**
     * 按邮箱查询
     *
     * @param string $email
     * @return array|null
     */
    public function getByEmail($email)
    {
        return $this->memberCompanyModel->getByEmail($email);
    }

    /**
     * @param array $params
     * @return array
     */
    public function create(array $params)
    {
        $company = $this->companyModel->getCompanyById($params['company_id']);
        if (empty($company)) {
            return $this->response('公司不存在');
        }
        $member = $this->memberModel->getMemberById($params['member_id']);
        if (empty($member)) {
            return $this->response('用户不存在');
        }
        if ($this->memberCompanyModel->hasByCompanyAndMember($params['company_id'], $params['member_id'])) {
            return $this->response('员工已存在');
        }
        if ($this->memberCompanyModel->hasByCompanyAndAccount($params['company_id'], $params['account'])) {
            return $this->response('账号已被使用');
        }
        if (!empty($params['email']) && $this->memberCompanyModel->hasByEmail($params['email'])) {
            return $this->response('邮箱已被使用');
        }
        $id = $this->memberCompanyModel->create($params);
        if (empty($id)) {
            return $this->response('创建失败');
        }

        return $this->response('创建成功', array('id' => $id), true);
    }

    /**
     * 按ID更新
     *
     * @param $companyMemberId
     * @param array $data
     * @return mixed
     */
    public function updateById($companyMemberId, array $data)
    {
        return $this->pureUpdate($this->getCompanyMemberById($companyMemberId), $data);
    }

    /**
     * 按公司ID和账号更新
     *
     * @param int $companyId
     * @param string $account
     * @param array $data
     * @return mixed
     */
    public function updateByCompanyAndAccount($companyId, $account, array $data)
    {
        return $this->pureUpdate($this->getByCompanyAndAccount($companyId, $account), $data);
    }

    /**
     * 按公司ID和用户ID
     *
     * @param int $companyId
     * @param int $memberId
     * @param array $data
     * @return mixed
     */
    public function updateByCompanyAndMember($companyId, $memberId, array $data)
    {
        return $this->pureUpdate($this->getByCompanyAndMember($companyId, $memberId), $data);
    }

    /**
     * 按公司ID和用户ID
     *
     * @param string $email
     * @param array $data
     * @return mixed
     */
    public function updateByEmail($email, array $data)
    {
        return $this->pureUpdate($this->getByEmail($email), $data);
    }

    /**
     * 更新数据
     *
     * @param array $origin
     * @param array $params
     * @return array
     */
    protected function pureUpdate($origin, array $params)
    {
        if (empty($origin)) {
            return $this->response('员工不存在');
        }

        $data = array();
        $optional = array(
            'name',
            'birthday',
            'no',
            'join_date'
        );
        foreach ($optional as $key) {
            if (isset($params[$key]) && $origin[$key] != $params[$key]) {
                $data[$key] = $params[$key];
            }
        }

        if (!empty($data) && !$this->memberCompanyModel->updateById($origin['id'], $data)) {
            return $this->response('更新失败');
        }

        return $this->response('更新成功', $data, true);
    }

    /**
     * 员工状态切换
     *
     * @param int $id
     * @param string $from
     * @param string $to
     * @return bool
     */
    public function switchStatus($id, $from, $to)
    {
        if (isset($from) && $from == $to) {
            return true;
        }

        if ($to != MemberCompanyModel::STATUS_ACTIVE) {
            // TODO 需要返还公司财产到公司账户
        }

        return $this->memberCompanyModel->updateById($id, array('status' => $to));
    }

    /**
     * @param string $message
     * @param array $data
     * @param bool $success
     * @return array
     */
    protected function response($message = '', array $data = array(), $success = false)
    {
        return array(
            'message' => $message,
            'data' => $data,
            'success' => $success
        );
    }
}
