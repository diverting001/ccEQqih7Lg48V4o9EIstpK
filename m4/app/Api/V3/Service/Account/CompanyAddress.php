<?php
/**
 * @author shihuiqian<shihuiqian@neigou.com>
 */

namespace App\Api\V3\Service\Account;


use App\Api\Model\Account\CompanyAddress as CompanyAddressModel;

class CompanyAddress
{

    protected $companyAddressModel;

    public function __construct()
    {
        $this->companyAddressModel = new CompanyAddressModel();
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
}
