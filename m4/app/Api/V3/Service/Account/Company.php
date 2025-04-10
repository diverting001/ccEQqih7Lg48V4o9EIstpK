<?php
/**
 * @author shihuiqian<shihuiqian@neigou.com>
 */

namespace App\Api\V3\Service\Account;

use App\Api\Model\Account\Company as CompanyModel;

class Company
{

    /**
     * @var CompanyModel
     */
    protected $companyModel;

    /**
     * Company constructor.
     */
    public function __construct()
    {
        $this->companyModel = new CompanyModel();
    }

    /**
     * 查询公司
     *
     * @param int $id
     * @return mixed
     */
    public function getCompanyById($id)
    {
        return $this->companyModel->getCompanyById($id);
    }

    /**
     * 查询公司
     *
     * @param string $code
     * @return mixed
     */
    public function getCompanyByCode($code)
    {
        return $this->companyModel->getCompanyByCode($code);
    }

    /**
     * 查询公司
     *
     * @param string $name
     * @return mixed
     */
    public function getCompanyByFullName($name)
    {
        return $this->companyModel->getCompanyByFullName($name);
    }

    /**
     * 创建公司
     *
     * @param array $data
     * @return array
     */
    public function create(array $data)
    {
        if (!is_null($this->companyModel->getCompanyByFullName($data['full_name']))) {

            return $this->response('公司名称已存在');
        }

        $id = $this->companyModel->create($data);

        $company = $this->companyModel->getCompanyById($id);

        if (!empty($id) && !empty($company)) {
            return $this->response('success', $company, true);
        }

        return $this->response('创建失败');
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
