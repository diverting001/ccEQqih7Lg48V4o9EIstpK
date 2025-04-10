<?php
/**
 * @author shihuiqian<shihuiqian@neigou.com>
 */

namespace App\Api\Model\Account;

use Illuminate\Database\Connection;

class Company
{
    const TABLE = 'club_company';

    const THIRD_COMPANY_TABLE = 'club_third_company';

    // 状态：审核中
    const STATUS_AUDITING = '1';
    // 状态：正常
    const STATUS_ACTIVE = '2';
    // 状态：审核失败
    const STATUS_REFUSE = '3';
    // 状态：已禁用
    const STATUS_DISABLED = '4';

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
        'company_id' => 'member_id',
        'code' => 'company_code',
        'full_name' => 'company_name',
        'display_name' => 'company_name_short',
        'industry' => 'industry_name',
        'logo' => 'company_logo',
        'address' => 'company_address',
        'create_time' => 'create_date',
        'update_time' => 'update_date',
        'status' => 'company_status'
    );

    /**
     * 查询字段
     *
     * @var array
     */
    protected static $fields = array(
        'member_id',
        'company_code',
        'company_name',
        'company_name_short',
        'industry_name',
        'company_logo',
        'company_address',
        'company_status',
        'staff_amount',
        'contacts_name',
        'contacts_phone',
        'contacts_email',
        'version',
        'data_version',
        'create_date',
        'update_date'
    );

    /**
     * Company constructor.
     */
    public function __construct()
    {
        $this->_db = app('api_db')->connection('neigou_club');
    }

    /**
     * 按条件查询1个公司
     *
     * @param array $where
     * @return array|null
     */
    protected function getCompany(array $where)
    {
        $company = $this->_db->table(static::TABLE)
            ->where(static::filters($where))
            ->first(static::$fields);

        $company = static::filters($company, true);

        if (!empty($company)) {
            $channel = $this->_db->table(static::THIRD_COMPANY_TABLE)
                ->where(array(
                    'internal_id' => $company['company_id']
                ))
                ->first(['channel', 'source']);
            $company['channel'] = $channel ? $channel->channel : null;
            $company['source'] = $channel ? $channel->source : null;
        }

        return $company;
    }

    /**
     * 根据公司ID获取公司
     *
     * @param int $id
     * @return mixed
     */
    public function getCompanyById($id)
    {
        return $this->getCompany(array('company_id' => $id));
    }

    /**
     * 根据公司编码获取公司
     *
     * @param $code
     * @return mixed
     */
    public function getCompanyByCode($code)
    {
        return $this->getCompany(array('code' => $code));
    }

    /**
     * 根据公司全称获取公司
     *
     * @param $fullName
     * @return mixed
     */
    public function getCompanyByFullName($fullName)
    {
        return $this->getCompany(array('full_name' => $fullName));
    }

    /**
     * 创建公司
     *
     * @param array $param
     * @return int|bool
     */
    public function create(array $param)
    {

        if (!$companyId = $this->createCompanyId()) {
            return false;
        }

        $data = array(
            'company_id' => $companyId,
            'full_name' => $param['full_name'],
            'display_name' => $param['display_name'] ? $param['display_name'] : $param['full_name'],
            'status' => $param['status'] ? $param['status'] : static::STATUS_AUDITING,
            'create_time' => date('Y-m-d H:i:s'),
            'code' => $this->generateCode(),
            'version' => 'v3'
        );

        $optional = array(
            'industry',
            'staff_amount',
            'contacts_name',
            'contacts_phone',
            'contacts_email',
            'address',
            'logo'
        );

        foreach ($optional as $item) {
            if (isset($param[$item])) {
                $data[$item] = $param[$item];
            }
        }

        if ($this->_db->table(static::TABLE)->insertGetId(static::filters($data))) {
            return $companyId;
        }

        return false;
    }


    /**
     * 生成公司编码
     *
     * @return string
     */
    protected function generateCode()
    {
        do {
            $code = date('Ymd') . str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
        } while (!empty($this->getCompanyByCode($code)));

        return $code;
    }

    /**
     * 字段映射
     *
     * @param array $data
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
     * 创建一个公司ID
     *
     * @return int
     */
    private function createCompanyId()
    {
        $data = array(
            'username' => 'service_v3_' . microtime(true) . str_random(8),
            'password' => md5('service_v3_' . str_random(8)),
            'ng_code' => '',
            'valid_date' => date('Y-m-d H:i:s', time() + 86400 * 2),
            'create_date' => date('Y-m-d H:i:s')
        );

        return $this->_db->table('club_member')->insertGetId($data);
    }
}
