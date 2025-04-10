<?php
/**
 * @author shihuiqian<shihuiqian@neigou.com>
 */

namespace App\Api\Model\WelfareCard;

use Illuminate\Database\Connection;
use Illuminate\Database\Query\Expression;

class WelfareCard
{
    const TABLE = 'market_welfare_card';

    // 状态：审核中
    const STATUS_NORMAL = '0';
    // 状态：已使用
    const STATUS_USED = '1';
    // 状态：已作废
    const STATUS_FREEZE = '2';
    // 状态：已锁定
    const STATUS_LOCKED = '3';

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
        'number' => 'card_number',
        'password' => 'card_password',
        'status' => 'state',
        'is_single' => 'card_single',
        'package_id' => 'pkg_id',
        'update_time' => 'last_modified',
        'expire_time' => 'valid_time'
    );

    /**
     * 需要把数据库的哪些字段转成datetime格式的字段
     *
     * @var array
     */
    protected static $dates = array(
        'create_time',
        'last_modified',
        'valid_time'
    );

    /**
     * 查询字段
     *
     * @var array
     */
    protected static $fields = array(
        'card_id',
        'card_number',
        'card_password',
        'state',
        'card_single',
        'pkg_id',
        'company_id',
        'create_time',
        'last_modified',
        'record_id',
        'memo',
        'valid_time',
        'is_login'
    );

    /**
     * Company constructor.
     */
    public function __construct()
    {
        $this->_db = app('api_db')->connection('neigou_club');
    }

    /**
     * 获取福利卡
     *
     * @param array $where
     * @return array
     */
    protected function getWelfareCard(array $where)
    {
        $welfareCard = $this->_db->table(static::TABLE)
            ->where(static::filters($where))
            ->first(static::$fields);

        return static::filters($welfareCard, true);
    }

    /**
     * 按ID查询福利卡
     *
     * @param int $id
     * @return array
     */
    public function getWelfareCardById($id)
    {
        return $this->getWelfareCard(array('card_id' => $id));
    }

    /**
     * 按卡密查询福利卡
     *
     * @param string $password
     * @return array
     */
    public function getWelfareCardByPassword($password)
    {
        return $this->getWelfareCard(array('password' => $password));
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
        // 只允许更新这些字段
        $optional = array(
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
        return $this->update(array('card_id' => $id), $data);
    }

    /**
     * 按卡密更新
     *
     * @param string $password
     * @param array $data
     * @return mixed
     */
    public function updateByPassword($password, array $data)
    {
        return $this->update(array('password' => $password), $data);
    }

    /**
     * 字段映射
     *
     * @param array $data
     * @param bool $reversed
     * @return array
     */
    protected static function filters($data, $reversed = false)
    {
        if (empty($data)) {
            return $data;
        }

        $data = (array)$data;

        $mapped = array();

        $mapping = $reversed ? array_flip(static::$map) : static::$map;

        if ($reversed) {
            foreach ($data as $key => $value) {
                if (in_array($key, static::$dates)) {
                    $data[$key] = date('Y-m-d H:i:s', $value);
                }
            }
        }

        foreach ($data as $key => $value) {
            if (array_key_exists($key, $mapping)) {
                $mapped[$mapping[$key]] = $value;
            } else {
                $mapped[$key] = $value;
            }
        }

        if (!$reversed) {
            foreach ($mapped as $key => $value) {
                if (in_array($key, static::$dates)) {
                    $mapped[$key] = strtotime($value);
                }
            }
        }

        return $mapped;
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
