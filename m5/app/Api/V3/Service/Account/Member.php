<?php
/**
 * @author shihuiqian<shihuiqian@neigou.com>
 */

namespace App\Api\V3\Service\Account;

use App\Api\Model\Account\Member as MemberModel;
use App\Api\Model\Account\MemberCompany as MemberCompanyModel;
use App\Api\Model\Account\Company as CompanyModel;
use Faker\Provider\Uuid;
use Neigou\RedisNeigou;

class Member
{
    /**
     * @var CompanyModel
     */
    protected $companyModel;

    /**
     * @var MemberModel
     */
    protected $memberModel;

    /**
     * @var MemberCompanyModel
     */
    protected $memberCompanyModel;

    /**
     * @var $sign
     */
    protected $sign = '37fe1976-0985-5bd0-84b9-5fc9927214dd';

    /**
     * Company constructor.
     */
    public function __construct()
    {
        $this->companyModel = new CompanyModel();
        $this->memberModel = new MemberModel();
        $this->memberCompanyModel = new MemberCompanyModel();
    }

    /**
     * 按手机号查询
     *
     * @param $mobile
     * @return mixed
     */
    public function getMemberByMobile($mobile)
    {
        return $this->memberModel->getMemberByMobile($mobile);
    }

    /**
     * 按ID查询
     *
     * @param $id
     * @return array|null
     */
    public function getMemberById($id)
    {
        return $this->memberModel->getMemberById($id);
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
        $memberCompany = $this->memberCompanyModel->getByCompanyAndAccount($companyId, $account);

        if (!empty($memberCompany)) {
            return $this->getMemberById($memberCompany['member_id']);
        }

        return null;
    }

    /**
     * 按公司邮箱查询
     *
     * @param $email
     * @return array|null
     */
    public function getByEmail($email)
    {
        $memberCompany = $this->memberCompanyModel->getByEmail($email);

        if (!empty($memberCompany)) {
            return $this->getMemberById($memberCompany['member_id']);
        }

        return null;
    }

    /**
     * 创建用户
     *
     * @param array $param
     * @return array
     */
    public function create(array $param)
    {

        if (empty($this->companyModel->getCompanyById($param['company_id']))) {
            return $this->response('公司不存在');
        }

        if (!empty($param['email'])) {
            if ($this->memberCompanyModel->hasByEmail($param['email'])) {
                return $this->response('邮箱已被注册');
            }
        }

        if (!empty($param['account'])) {
            if ($this->memberCompanyModel->hasByCompanyAndAccount($param['company_id'], $param['account'])) {
                return $this->response('员工账号已被注册');
            }
        }

        //开启事务
        $this->memberModel->beginTransaction();

        try {
            $member = null;

            if (!empty($param['mobile'])) {
                // 查询手机号账号
                $member = $this->memberModel->getMemberByMobile($param['mobile']);

                if (!empty($member) && !empty($member['member_id'])) {
                    // 手机号账号存在，查询是否已经加入公司
                    $companyMember = $this->memberCompanyModel->getByCompanyAndMember(
                        $param['company_id'],
                        $param['member_id']
                    );
                    if (!empty($companyMember)) {
                        return $this->response('success', array('member_id' => $member['member_id']), true);
                    }
                }
            }

            if (empty($member)) {
                // 主账号不存在，创建
                $memberData = array(
                    'company_id' => $param['company_id'],
                    'name' => $param['name'],
                    'nickname' => $param['nickname'],
                    'sex' => $param['sex'],
                    'source' => $param['source'],
                    'mobile' => $param['mobile'],
                    'register_ip' => $param['register_ip']
                );
                $member = array('member_id' => $this->memberModel->create($memberData));
            }

            if (!empty($member) && !empty($member['member_id'])) {

                $memberCompanyData = array(
                    'company_id' => $param['company_id'],
                    'member_id' => $member['member_id'],
                    'name' => $param['name'],
                    'no' => $param['no'],
                    'birthday' => $param['birthday'],
                    'account' => $param['account'],
                    'email' => $param['email'],
                    'join_date' => $param['join_date'],
                    'position' => $param['position'],
                    'status' => $param['status']
                );


                if (!empty($this->memberCompanyModel->create($memberCompanyData))) {

                    // 提交事务
                    $this->memberModel->commit();

                    return $this->response('success', array('member_id' => $member['member_id']), true);
                }
            }

        } catch (\Exception $exception) {
            // 回滚事务
            $this->memberModel->rollback();
            throw $exception;
        }
        // 回滚事务
        $this->memberModel->rollback();
        return $this->response('创建失败');
    }

    /**
     * 验证密码正确性
     *
     * @param $memberId
     * @param $password
     * @return array|bool
     */
    public function checkPassword($memberId, $password)
    {
        $member = $this->getMemberById($memberId);
        if (empty($member)) {
            return $this->response('用户不存在');
        }

        $success = empty($member['password']) ? false
            : $this->memberModel->generatePassword($password, $member['password_salt']) === $member['password'];

        return $this->response('success', array('result' => $success), true);
    }

    /**
     * 设置密码
     *
     * @param $memberId
     * @param $password
     * @return array
     */
    public function setPassword($memberId, $password)
    {
        $member = $this->getMemberById($memberId);

        if (empty($member)) {
            return $this->response('用户不存在');
        }

        $hashedPassword = $this->memberModel->generatePassword($password, $member['password_salt']);

        if ($this->memberModel->updateById($memberId, array('password' => $hashedPassword))) {
            return $this->response('success', array(), true);
        }

        return $this->response('密码设置失败');
    }

    /**
     * 更新用户
     *
     * @param int $memberId
     * @param array $data
     * @return array
     */
    public function updateById($memberId, array $data)
    {
        return $this->pureUpdate($this->getMemberById($memberId), $data);
    }

    /**
     * 更新数据
     *
     * @param array $origin
     * @param array $params
     * @return array
     */
    protected function pureUpdate($origin, array $params)
    {
        if (empty($origin)) {
            return $this->response('用户不存在');
        }

        $data = array();
        $optional = array(
            'company_id',
            'sex',
            'name',
            'nickname',
            'status'
        );
        foreach ($optional as $key) {
            if (isset($params[$key]) && $origin[$key] != $params[$key]) {
                $data[$key] = $params[$key];
            }
        }

        if (!empty($data) && !$this->memberModel->updateById($origin['member_id'], $data)) {
            return $this->response('更新失败');
        }

        return $this->response('更新成功', $data, true);
    }

    /**
     * 是否是手机号格式
     *
     * @param $mobile
     * @return bool
     */
    public static function isMobile($mobile)
    {
        return preg_match('/^1[03456789]\d{9}$/', $mobile) === 1;
    }

    /**
     * 是否是邮箱格式
     *
     * @param $email
     * @return bool
     */
    public static function isEmail($email)
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    }

    /**
     * @param string $message
     * @param array $data
     * @param bool $success
     * @return array
     */
    protected function response($message = '', array $data = array(), $success = false)
    {
        return array(
            'message' => $message,
            'data' => $data,
            'success' => $success
        );
    }

    /**
     * @param $password
     * @param $salt
     * @return string
     */
    protected function generatePassword($password, $salt)
    {
        return md5($password . $salt);
    }

    /**
     * 升级账号到V3
     *
     * @param $companyId
     * @param $memberId
     * @return bool
     */
    public function upgradeToV3($companyId, $memberId)
    {

        $this->memberModel->getRaw(array('member_id' => $memberId));

        $member = $this->getMemberById($memberId);
        $date = date('Y-m-d H:i:s');
        if ($member['version'] !== 'v3') {
            $memberData = array(
                'version' => 'v3',
                'data_version' => 1,
                'create_time' => $date,
                'update_time' => $date
            );
            if (!empty($member['mobile'])) {
                $memberData['mobile_v3'] = $member['mobile'];
            }
            $this->memberModel->updateRaw(array('member_id' => $memberId), $memberData);
        }

        $memberCompany = $this->memberCompanyModel->getRaw(array(
            'company_id' => $companyId,
            'member_id' => $memberId
        ));
        // 员工信息
        if ($memberCompany['version'] !== 'v3') {
            $memberCompanyData = array(
                'version' => 'v3',
                'data_version' => 1,
                'create_time' => $date,
                'update_time' => $date
            );
            if (!empty($memberCompany['email'])) {
                $memberCompanyData['company_email'] = $memberCompany['email'];
            }
            $this->memberCompanyModel->updateRaw(array('company_id' => $companyId, 'member_id' => $memberId),
                $memberCompanyData);
        }
        return true;
    }

    /**
     * 创建登录token
     *
     * @param $memberId
     * @param $companyId
     *
     * @return array
     */
    public function createLoginToken($memberId, $companyId)
    {
        $tokenData = array(
            'version' => 1,
            'member_id' => $memberId,
            'company_id' => $companyId,
            'timestamp' => time(),
            'ttl' => 60,
        );

        $redisKey = $this->getLoginRedisKey();

        $redis = new RedisNeigou();
        $redis->_redis_connection->set($redisKey, json_encode($tokenData), $tokenData['ttl']);
        \Neigou\Logger::Debug('mvp_login_token',array('data'=>$tokenData,'remark'=>$redisKey));

        return $this->response('成功', [
            'login_token' => base64_encode($redisKey),
            'ttl' => $tokenData['ttl'],
            'expire_at' => $tokenData['timestamp'] + $tokenData['ttl'],
        ], true);
    }

    /**
     * token获取用户信息
     *
     * @param $loginToken
     *
     * @return array
     */
    public function getInfoByToken($loginToken)
    {
        $loginToken = base64_decode($loginToken);
        $redis = new RedisNeigou();
        $data = $redis->_redis_connection->get($loginToken);

        if ($data === false) {
            return $this->response('token失效');
        }

        $data = json_decode($data, true);

        if (empty($data['member_id']) || empty($data['company_id'])) {
            return $this->response('参数缺失');
        }

        $userInfo = $this->getMemberById($data['member_id']);

        if (!$userInfo) {
            return $this->response('用户信息不存在');
        }

        $userInfo['company_id'] = $data['company_id'];

        return $this->response('成功', $userInfo, true);
    }

    /**
     * token redis key
     *
     * @return string
     */
    private function getLoginRedisKey()
    {
        return 'service:login_token-' . md5(Uuid::uuid() . microtime() . $this->sign);
    }
}
