<?php
/**
 * @author shihuiqian<shihuiqian@neigou.com>
 */

namespace App\Api\Model\Account;


use Illuminate\Database\Connection;

class CompanyAddress
{

    const TABLE = 'server_company_addresses';

    /**
     * @var Connection
     */
    protected $_db;

    /**
     * CompanyAddress constructor.
     */
    public function __construct()
    {
        $this->_db = app('api_db');
    }

    protected function getByWhere(array $where)
    {
        return $this->_db->table(static::TABLE)
            ->where($where)
            ->get();
    }

    protected function findByWhere(array $where)
    {
        $data = $this->_db->table(static::TABLE)
            ->where($where)
            ->first();

        return is_null($data) ? null : (array)$data;
    }

    /**
     * 查询公司所有收货地址
     *
     * @param int $companyId
     * @return \Illuminate\Support\Collection
     */
    public function getByCompanyId($companyId)
    {
        return $this->getByWhere(array('company_id' => $companyId));
    }

    /**
     * 按地址ID查询
     *
     * @param int $addressId
     * @return mixed
     */
    public function getByAddressId($addressId)
    {
        return $this->findByWhere(array('address_id' => $addressId));
    }

    /**
     * 创建收货地址
     *
     * @param array $param
     * @return bool|int
     */
    public function create(array $param)
    {
        $data = array(
            'company_id' => $param['company_id'],
            'create_time' => date('Y-m-d H:i:s'),
            'region_id' => $param['region_id'],
            'mainland' => $param['mainland'],
            'address' => $param['address'],
            'zip' => $param['zip']
        );

        if ($addressId = $this->_db->table(static::TABLE)->insertGetId($data)) {
            return $addressId;
        }

        return false;
    }


    /**
     * 更新
     *
     * @param array $where
     * @param array $param
     * @return mixed
     */
    protected function update(array $where, array $param)
    {
        $optional = array(
            'region_id',
            'mainland',
            'zip',
            'address'
        );

        $data = array();
        foreach ($optional as $item) {
            if (isset($param[$item])) {
                $data[$item] = $param[$item];
            }
        }

        if (empty($data)) {
            return true;
        }

        $data['update_time'] = date('Y-m-d H:i:s');

        return $this->_db->table(static::TABLE)
            ->where($where)
            ->update($data);
    }

    /**
     * 按ID更新
     *
     * @param int $id
     * @param array $data
     * @return mixed
     */
    public function updateById($id, array $data)
    {
        return $this->update(array('address_id' => $id), $data);
    }

    /**
     * 按ID删除
     *
     * @param int $addressId
     * @return int
     */
    public function deleteById($addressId)
    {
        return $this->_db->table(static::TABLE)->where(array('address_id' => $addressId))->delete();
    }
}
