<?php
/**
 * @author shihuiqian<shihuiqian@neigou.com>
 */

namespace App\Api\Model\WelfareCard;


use Illuminate\Database\Connection;

class WelfareCardUseRecord
{
    const TABLE = 'market_welfare_card_use_record';

    /**
     * @var Connection
     */
    protected $_db;

    /**
     * 对应数据库的真实字段
     *
     * @var array
     */
    protected static $map = array();

    /**
     * 需要把数据库的哪些字段转成datetime格式的字段
     *
     * @var array
     */
    protected static $dates = array(
        'create_time'
    );

    /**
     * 查询字段
     *
     * @var array
     */
    protected static $fields = array(
        'id',
        'company_id',
        'member_id',
        'create_time',
        'memo',
    );

    /**
     * Company constructor.
     */
    public function __construct()
    {
        $this->_db = app('api_db')->connection('neigou_club');
    }

    /**
     * 获取使用记录
     *
     * @param array $where
     * @return array
     */
    protected function getRecord(array $where)
    {
        $welfareCard = $this->_db->table(static::TABLE)
            ->where(static::filters($where))
            ->first(static::$fields);

        return static::filters($welfareCard, true);
    }

    /**
     * 按ID查询使用记录
     *
     * @param int $id
     * @return array
     */
    public function getRecordById($id)
    {
        return $this->getRecord(array('id' => $id));
    }

    /**
     * 按卡ID查询使用记录
     *
     * @param int $cardId
     * @return array
     */
    public function getRecordByCardId($cardId)
    {
        return $this->getRecord(array('card_id' => $cardId));
    }

    /**
     * 创建记录
     *
     * @param array $param
     * @return int
     */
    public function create($param)
    {
        $data = array(
            'card_id' => $param['card_id'],
            'member_id' => $param['member_id'],
            'company_id' => $param['company_id'],
            'create_time' => date('Y-m-d H:i:s')
        );

        $optional = array(
            'memo',
        );

        foreach ($optional as $item) {
            if (isset($param[$item])) {
                $data[$item] = $param[$item];
            }
        }

        return $this->_db->table(static::TABLE)->insertGetId(static::filters($data));
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
}
