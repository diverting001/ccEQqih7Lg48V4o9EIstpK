<?php
/**
 * @author shihuiqian<shihuiqian@neigou.com>
 */

namespace App\Api\Model\Account;

use Illuminate\Database\Connection;
use Illuminate\Database\Query\Expression;

class Member
{

    const TABLE = 'sdb_b2c_members';

    const FILED_MOBILE = 'mobile_v3';

    const STATUS_ACTIVE = 'active';

    const STATUS_FREEZE = 'freez';

    const STATUS_DISABLED = 'disabled';

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
        'mobile' => 'mobile_v3',
        'register_ip' => 'reg_ip'
    );

    /**
     * 查询字段
     *
     * @var array
     */
    protected static $fields = array(
        'member_id',
        'company_id',
        'name',
        'nickname',
        'mobile_v3',
        'status',
        'sex',
        'source',
        'password',
        'password_salt',
        'reg_ip',
        'version',
        'data_version',
        'create_time',
        'update_time'
    );

    /**
     * Member constructor.
     */
    public function __construct()
    {
        $this->_db = app('api_db')->connection('neigou_store');
    }

    /**
     * 查询1个符合条件的用户
     *
     * @param array $where
     * @return array|null
     */
    protected function getMember(array $where)
    {
        $member = (array)$this->_db->table(static::TABLE)
            ->where(static::filters($where))
            ->first(static::$fields);

        return static::filters($member, true);
    }

    /**
     * 按用户ID查询
     *
     * @param $id
     * @return array|null
     */
    public function getMemberById($id)
    {
        return $this->getMember(array('member_id' => $id));
    }

    /**
     * 按手机号查询
     *
     * @param $mobile
     * @return array|null
     */
    public function getMemberByMobile($mobile)
    {
        return $this->getMember(array(static::FILED_MOBILE => $mobile));
    }

    /**
     * 创建用户
     *
     * @param array $param
     * @return int|bool
     */
    public function create(array $param)
    {

        if (!$memberId = $this->createMemberId()) {
            return false;
        }

        $data = array(
            'member_id' => $memberId,
            'company_id' => $param['company_id'],
            'name' => $param['name'],
            'nickname' => $param['nickname'] ? $param['nickname'] : $param['name'],
            'status' => static::STATUS_ACTIVE,
            'password_salt' => str_random(8),
            'create_time' => date('Y-m-d H:i:s'),
            'version' => 'v3'
        );

        $optional = array(
            'sex',
            'source',
            'register_ip',
            'mobile'
        );

        foreach ($optional as $item) {
            if (isset($param[$item])) {
                $data[$item] = $param[$item];
            }
        }

        if (!empty($param['password'])) {
            $data['password'] = $this->generatePassword($param['password'], $data['password_salt']);
        }

        if ($this->_db->table(static::TABLE)->insertGetId(static::filters($data)) !== false) {
            return $memberId;
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
            'nickname',
            'mobile',
            'password',
            'sex',
            'status'
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
        $data['data_version'] = new Expression('data_version + 1');

        return $this->_db->table(static::TABLE)
            ->where(static::filters($where))
            ->update(static::filters($data));
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
        return $this->update(array('member_id' => $id), $data);
    }

    /**
     * @param $password
     * @param $salt
     * @return string
     */
    public function generatePassword($password, $salt)
    {
        return md5($password . $salt);
    }

    /**
     * 字段映射
     *
     * @param array $data
     * @param bool $reversed
     * @return array|null
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
     * 创建一个公司ID
     *
     * @return int
     */
    private function createMemberId()
    {
        $data = array(
            'account_type' => 'member',
            'login_name' => 'service_v3_' . microtime(true) . str_random(8),
            'login_password' => md5('service_v3_' . str_random(8)),
            'createtime' => time()
        );

        return $this->_db->table('sdb_pam_account')->insertGetId($data);
    }

    public function beginTransaction()
    {
        $this->_db->beginTransaction();
    }

    public function commit()
    {
        $this->_db->commit();
    }

    public function rollback()
    {
        $this->_db->rollback();
    }
}
