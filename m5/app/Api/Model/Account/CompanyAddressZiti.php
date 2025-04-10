<?php
/**
 * @author shihuiqian<shihuiqian@neigou.com>
 */

namespace App\Api\Model\Account;

use Illuminate\Database\Connection;

class CompanyAddressZiti
{

    const TABLE = 'club_company_addr_ziti';

    /**
     * @var Connection
     */
    protected $_db;

    /**
     * CompanyAddress constructor.
     */
    public function __construct()
    {
        $this->_db = app('api_db')->connection('neigou_club');
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
        $time = time();
        $data = array(
            'name' => $param['name'],
            'company_id' => $param['company_id'],
            'province' => $param['province'],
            'city' => $param['city'],
            'county' => $param['county'],
            'town' => $param['town'],
            'contacts'=>$param['contacts'],
            'mobile' => $param['mobile'],
            'address' => $param['address'],
            'zip' => $param['zip'],
            'create_time' => $time,
            'update_time' => $time
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
            'name',
            'province',
            'city',
            'county',
            'town',
            'mobile',
            'contacts',
            'address',
            'zip'
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

        $data['update_time'] = time();

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
