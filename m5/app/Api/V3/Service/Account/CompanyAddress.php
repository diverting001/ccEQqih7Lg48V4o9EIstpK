<?php
/**
 * @author shihuiqian<shihuiqian@neigou.com>
 */

namespace App\Api\V3\Service\Account;


use App\Api\Model\Account\CompanyAddress as CompanyAddressModel;
use App\Api\Model\Account\CompanyAddressZiti as CompanyAddressZitiModel;
class CompanyAddress
{

    protected $companyAddressModel;
    protected $companyAddressZitiModel;

    public function __construct()
    {
        $this->companyAddressModel = new CompanyAddressModel();
        $this->companyAddressZitiModel = new CompanyAddressZitiModel();
    }

    /**
     * 按公司ID查询
     *
     * @param int $companyId
     * @return \Illuminate\Support\Collection
     */
    public function getByCompanyId($companyId)
    {
        return $this->companyAddressModel->getByCompanyId($companyId);
    }

    /**
     * 按收货地址ID查询
     *
     * @param int $addressId
     * @return array|null
     */
    public function getByAddressId($addressId)
    {
        return $this->companyAddressModel->getByAddressId($addressId);
    }

    /**
     * 创建
     *
     * @param array $param
     * @return bool|int
     */
    public function create(array $param)
    {
        return $this->companyAddressModel->create($param);
    }

    /**
     * 按ID更新
     *
     * @param int $addressId
     * @param array $data
     * @return mixed
     */
    public function updateById($addressId, array $data)
    {
        return $this->companyAddressModel->updateById($addressId, $data);
    }

    /**
     * @param $addressId
     * @return int
     */
    public function deleteById($addressId)
    {
        return $this->companyAddressModel->deleteById($addressId);
    }

    //////////////////

    /**
     * 按公司ID查询
     *
     * @param int $companyId
     * @return \Illuminate\Support\Collection
     */
    public function getZitiByCompanyId($companyId)
    {
        return $this->companyAddressZitiModel->getByCompanyId($companyId);
    }

    /**
     * 按收货地址ID查询
     *
     * @param int $addressId
     * @return array|null
     */
    public function getZitiByAddressId($addressId)
    {
        return $this->companyAddressZitiModel->getByAddressId($addressId);
    }

    /**
     * 创建
     *
     * @param array $param
     * @return bool|int
     */
    public function createZiti(array $param)
    {
        return $this->companyAddressZitiModel->create($param);
    }

    /**
     * 按ID更新
     *
     * @param int $addressId
     * @param array $data
     * @return mixed
     */
    public function updateZitiById($addressId, array $data)
    {
        return $this->companyAddressZitiModel->updateById($addressId, $data);
    }

    /**
     * @param $addressId
     * @return int
     */
    public function deleteZitiById($addressId)
    {
        return $this->companyAddressZitiModel->deleteById($addressId);
    }
}
