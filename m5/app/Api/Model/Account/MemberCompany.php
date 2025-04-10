<?php
/**
 * @author shihuiqian<shihuiqian@neigou.com>
 */

namespace App\Api\Model\Account;

use Illuminate\Database\Connection;
use Illuminate\Database\Query\Expression;

class MemberCompany
{
    const TABLE = 'sdb_b2c_member_company';

    // 状态：正常
    const STATUS_ACTIVE = '0';

    // 状态：已离职
    const STATUS_RESIGNED = '1';

    // 状态：已禁用
    const STATUS_DISABLED = '2';

    /**
     * @var Connection
     */
    protected $_db;

    /**
     * 对应数据库的真实字段
     *
     * @var array
     */
    protected static $map = array(
        'account' => 'member_key',
        'status' => 'state',
        'id' => 'mem_com_id'
    );

    /**
     * 查询的字段
     *
     * @var array
     */
    protected static $fields = array(
        'mem_com_id',
        'company_id',
        'member_id',
        'name',
        'birthday',
        'email',
        'member_key',
        'no',
        'join_date',
        'position',
        'state',
        'version',
        'data_version',
        'create_time',
        'update_time'
    );

    /**
     * MemberCompany constructor.
     */
    public function __construct()
    {
        $this->_db = app('api_db')->connection('neigou_store');
    }

    /**
     * 查询1个符合条件的员工
     *
     * @param array $where
     * @return array|null
     */
    protected function getCompanyMember(array $where)
    {
        $companyMember = (array)$this->_db->table(static::TABLE)
            ->where(static::filters($where))
            ->first(static::$fields);

        return static::filters($companyMember, true);
    }

    /**
     * 按公司ID和用户ID查询员工
     *
     * @param $companyId
     * @param $memberId
     * @return array|null
     */
    public function getByCompanyAndMember($companyId, $memberId)
    {
        return $this->getCompanyMember(array('company_id' => $companyId, 'member_id' => $memberId));
    }

    /**
     * 按公司账号查询
     *
     * @param $companyId
     * @param $account
     * @return array|null
     */
    public function getByCompanyAndAccount($companyId, $account)
    {
        return $this->getCompanyMember(array('company_id' => $companyId, 'account' => $account));
    }

    /**
     * 按公司邮箱查询
     *
     * @param $email
     * @return array|null
     */
    public function getByEmail($email)
    {
        return $this->getCompanyMember(array('email' => $email));
    }

    /**
     * 按ID查询
     *
     * @param int $id
     * @return array|null
     */
    public function getCompanyMemberById($id)
    {
        return $this->getCompanyMember(array('id' => $id));
    }

    /**
     * 员工是否存在
     *
     * @param array $where
     * @return bool
     */
    protected function hasCompanyMember(array $where)
    {
        return $this->_db->table(static::TABLE)->where(static::filters($where))->exists();
    }

    /**
     * 员工是否存在
     *
     * @param int $companyId
     * @param int $memberId
     * @return bool
     */
    public function hasByCompanyAndMember($companyId, $memberId)
    {
        return $this->hasCompanyMember(array('company_id' => $companyId, 'member_id' => $memberId));
    }

    /**
     * 公司账号是否存在
     *
     * @param $companyId
     * @param $account
     * @return bool
     */
    public function hasByCompanyAndAccount($companyId, $account)
    {
        return $this->hasCompanyMember(array('company_id' => $companyId, 'account' => $account));
    }

    /**
     * 公司账号是否存在
     *
     * @param $email
     * @return bool
     */
    public function hasByEmail($email)
    {
        return $this->hasCompanyMember(array('email' => $email));
    }

    /**
     * 创建员工
     *
     * @param array $param
     * @return int|bool
     */
    public function create(array $param)
    {
        $data = array(
            'member_id' => $param['member_id'],
            'company_id' => $param['company_id'],
            'status' => $param['status'] ? $param['status'] : static::STATUS_ACTIVE,
            'create_time' => date('Y-m-d H:i:s'),
            'version' => 'v3'
        );

        $optional = array(
            'name',
            'birthday',
            'account',
            'no',
            'email',
            'join_date',
            'position'
        );

        foreach ($optional as $item) {
            if (isset($param[$item])) {
                $data[$item] = $param[$item];
            }
        }

        return $this->_db->table(static::TABLE)->insertGetId(static::filters($data));
    }

    /**
     * 更新
     *
     * @param array $where
     * @param array $params
     * @return mixed
     */
    protected function update(array $where, array $params)
    {
        $optional = array(
            'name',
            'birthday',
            'account',
            'no',
            'email',
            'join_date',
            'status'
        );

        $data = array();

        foreach ($optional as $item) {
            if (isset($params[$item])) {
                $data[$item] = $params[$item];
            }
        }

        if (empty($data)) {
            return true;
        }

        $data['update_time'] = date('Y-m-d H:i:s');
        $data['data_version'] = new Expression('data_version + 1');

        return $this->_db->table(static::TABLE)
            ->where(static::filters($where))
            ->update(static::filters($data));
    }

    /**
     * 按ID更新
     *
     * @param int $companyMemberId
     * @param array $data
     * @return mixed
     */
    public function updateById($companyMemberId, array $data)
    {
        return $this->update(array('id' => $companyMemberId), $data);
    }

    /**
     * 查询原始数据
     * @param array $where
     * @return array
     */
    public function getRaw(array $where)
    {
        return (array)$this->_db->table(static::TABLE)
            ->where($where)
            ->first();
    }

    /**
     * 更新原始数据
     * @param array $where
     * @param array $data
     * @return int
     */
    public function updateRaw(array $where, array $data)
    {
        return $this->_db->table(static::TABLE)
            ->where($where)
            ->update($data);
    }

    /**
     * @param $password
     * @param $salt
     * @return string
     */
    public static function generatePassword($password, $salt)
    {
        return md5($password . $salt);
    }

    /**
     * 字段映射
     *
     * @param array|null $data
     * @param bool $reversed
     * @return array
     */
    public static function filters($data, $reversed = false)
    {
        if (empty($data)) {
            return $data;
        }
        $mapped = array();

        $mapping = $reversed ? array_flip(static::$map) : static::$map;

        foreach ((array)$data as $key => $value) {
            if (array_key_exists($key, $mapping)) {
                $mapped[$mapping[$key]] = $value;
            } else {
                $mapped[$key] = $value;
            }
        }

        return $mapped;
    }

    /**
     * 按公司ID和用户ID查询员工
     *
     * @param  int   $companyId
     * @param  array $memberId
     * @return array|null
     */
    public function getByCompanyAndMemberId($companyId, $memberId)
    {
        if (empty($companyId) || empty($memberId)) return [];
        if (!is_array($memberId)) $memberId = explode(',', $memberId);

        $where = [
            'member_id' => ['in', $memberId],
            'company_id' => $companyId
        ];

        $companyMembers = $this->_db->table(static::TABLE)->select(static::$fields)
            ->where(['company_id' => $companyId])
            ->whereIn('member_id', $memberId)
            ->get()->toArray();

        return static::filters($companyMembers, true);
    }
}
