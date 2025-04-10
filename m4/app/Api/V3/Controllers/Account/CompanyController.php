<?php
/**
 * @author shihuiqian<shihuiqian@neigou.com>
 */

namespace App\Api\V3\Controllers\Account;


use App\Api\Common\Controllers\BaseController;
use App\Api\V3\Service\Account\Company;
use App\Api\Model\Account\Company as CompanyModel;
use App\Api\V3\Service\Account\Member;
use Illuminate\Http\Request;

class CompanyController extends BaseController
{

    /**
     * @var Company
     */
    protected $companyService;

    /**
     * CompanyController constructor.
     */
    public function __construct()
    {
        $this->companyService = new Company();
    }

    /**
     * 创建公司
     *
     * @return array
     */
    public function create(Request $request)
    {
        $param = $this->getContentArray($request);

        if (empty($param['full_name'])) {
            $this->setErrorMsg('公司全称不能为空');
            return $this->outputFormat($param, 10001);
        }

        $allowedStatus = array(
            CompanyModel::STATUS_ACTIVE,
            CompanyModel::STATUS_DISABLED,
            CompanyModel::STATUS_AUDITING
        );

        if (isset($param['status']) && !in_array($param['status'], $allowedStatus)) {
            $this->setErrorMsg('状态错误');
            return $this->outputFormat($param, 10001);
        }
        if (!empty($param['contacts_email']) && !Member::isEmail($param['contacts_email'])) {
            $this->setErrorMsg('邮箱格式错误');
            return $this->outputFormat($param, 10001);
        }

        $response = $this->companyService->create($param);

        $this->setErrorMsg($response['message']);
        return $this->outputFormat($response['success'] ? $response['data'] : $param, $response['success'] ? 0 : 10002);
    }

    /**
     * 查询1个符合条件的公司
     *
     * @return array
     */
    public function query(Request $request)
    {
        $param = $this->getContentArray($request);
        if (empty($param)) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat($param, 10001);
        }

        if (!empty($param['company_id'])) {
            $data = $this->companyService->getCompanyById($param['company_id']);
        } elseif (!empty($param['code'])) {
            $data = $this->companyService->getCompanyByCode($param['code']);
        } elseif (!empty($param['full_name'])) {
            $data = $this->companyService->getCompanyByFullName($param['full_name']);
        } else {

            $this->setErrorMsg('参数错误');
            return $this->outputFormat($param, 10001);
        }

        $this->setErrorMsg('success');
        return $this->outputFormat($data);
    }

    /**
     * 按公司ID查询
     *
     * @return array
     */
    public function queryById(Request $request)
    {
        $param = $this->getContentArray($request);

        if (empty($param['company_id'])) {
            $this->setErrorMsg('公司ID不能为空');
            return $this->outputFormat($param, 10001);
        }

        $data = $this->companyService->getCompanyById($param['company_id']);

        $this->setErrorMsg('success');
        return $this->outputFormat($data);
    }

    /**
     * 按公司编码查询
     *
     * @return array
     */
    public function queryByCode(Request $request)
    {
        $param = $this->getContentArray($request);

        if (empty($param['code'])) {
            $this->setErrorMsg('公司编码不能为空');
            return $this->outputFormat($param, 10001);
        }

        $data = $this->companyService->getCompanyByCode($param['code']);

        $this->setErrorMsg('success');
        return $this->outputFormat($data);
    }

    /**
     * 按公司全称查询
     *
     * @return array
     */
    public function queryByFullName(Request $request)
    {
        $param = $this->getContentArray($request);

        if (empty($param['full_name'])) {
            $this->setErrorMsg('公司全称不能为空');
            return $this->outputFormat($param, 10001);
        }

        $data = $this->companyService->getCompanyByFullName($param['full_name']);

        $this->setErrorMsg('success');
        return $this->outputFormat($data);
    }
}
