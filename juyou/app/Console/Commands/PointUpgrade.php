<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-03-06
 * Time: 14:59
 */

namespace App\Console\Commands;

use App\Api\Model\PointScene\SceneMemberRel;
use Illuminate\Console\Command;

use App\Api\Model\PointScene\SceneCompanyRel as SceneCompanyRelModel;
use App\Api\Model\PointScene\SceneMemberRel as SceneMemberRelModel;

use App\Api\Model\PointServer\Account as AccountModel;
use App\Api\Model\PointServer\SonAccount as SonAccountModel;

class PointUpgrade extends Command
{
    const COMMON_SCENE = 1;

    protected $signature = 'PointUpgrade {--type=} {--company=} {--overdueTime=}';

    protected $description = '积分系统升级';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $type = $this->option('type');
        if (!$type || !in_array($type, array('import_account', 'import_record'))) {
            $this->error('参数错误 ：--type= [ import_account：导入账户余额 ]');
            exit;
        }
        $funName = camel_case($type);
        $this->$funName();
        $this->info('执行完成');
    }

    /**
     * 导入账户流水
     */
    private function importRecord()
    {
        $companyId = $this->option('company');
        if (!$companyId) {
            $this->error('参数错误 ：--company = 公司ID');
            exit;
        }
        $this->importCompanyRecord($companyId);
//        $this->importCompanyMemberAccount($companyId, $overdueTime);
    }

    /**
     * 导入公司流水
     */
    private function importCompanyRecord($companyId)
    {
    }


    /**
     * 导入账户余额
     */
    private function importAccount()
    {
        $companyId = $this->option('company');
        if (!$companyId) {
            $this->error('参数错误 ：--company = 公司ID');
            exit;
        }
        $overdueTime = $this->option('overdueTime');
        if (!$overdueTime) {
            $this->error('参数错误 ：--overdueTime = 到期时间的时间戳');
            exit;
        }
        $this->importCompanyAccount($companyId, $overdueTime);
        $this->importCompanyMemberAccount($companyId, $overdueTime);
    }

    private function importCompanyMemberAccount($companyId, $overdueTime)
    {
        $pageSize = 1;
        $page = 1;
        $storeDb = app('api_db')->connection('neigou_store');

        $importCount = 0;
        while (true) {
            $queryMemberSql = 'SELECT * FROM `balance_point_member` where company_id = ' . $companyId . ' and (disabled is null or disabled = 0 or disabled = "") limit ' . ($page - 1) * $pageSize . ',' . $pageSize;
            $memberList = $storeDb->select($queryMemberSql);
            if (!$memberList) {
                break;
            }
            foreach ($memberList as $memberInfo) {
                $this->importMemberAccount($memberInfo, $overdueTime);
                $importCount++;
            }
            $page++;
        }
        $this->info('共处理用户个数：' . $importCount);
    }

    /**
     * 导入用户账户余额
     */
    private function importMemberAccount($memberInfo, $overdueTime)
    {
        $companyId = $memberInfo->company_id;
        $memberId = $memberInfo->member_id;

        $this->info('用户账户处理开始：' . $memberId);

        $relInfo = SceneMemberRelModel::FindByMemberAndScene($companyId, $memberId, self::COMMON_SCENE);
        if ($relInfo && $relInfo->id) {
            $relId = $relInfo->id;
        } else {
            $accountInfo = array(
                "scene_id" => self::COMMON_SCENE,
                "company_id" => $companyId,
                "member_id" => $memberId
            );
            $relId = SceneMemberRelModel::Create($accountInfo);
            if (!$relId) {
                $this->error('---用户创建关联失败');
                exit;
            }
        }

        //账户创建
        if ($relInfo && $relInfo->account) {
            $account = $relInfo->account;
        } else {
            do {
                $account = date('YmdHis') . rand(1000, 9999);
                $accountIsset = AccountModel::Find($account);
            } while ($accountIsset);
        }
        if (!$account) {
            $this->error('---用户账户account异常');
            exit;
        }

        //资产变更
        $this->accountAssetsSync('member', $relId, $account, $memberInfo, $overdueTime);
        $this->info('用户账户处理成功：' . $memberId);
    }


    /**
     * 导入公司账户余额
     */
    private function importCompanyAccount($companyId, $overdueTime)
    {
        $this->info('公司账户处理开始：' . $companyId);
        $storeDb = app('api_db')->connection('neigou_store');
        $queryCompanySql = 'SELECT * FROM `balance_point_company` where company_id = ' . $companyId . ' and (disabled is null or disabled = 0 or disabled = "")';
        $companyInfo = $storeDb->selectOne($queryCompanySql);
        if (!$companyInfo) {
            $this->error('公司账户不存在或已被禁用.');
            exit;
        }

        //账户关联
        $relInfo = SceneCompanyRelModel::FindByCompanyAndScene($companyId, self::COMMON_SCENE);
        if ($relInfo && $relInfo->id) {
            $relId = $relInfo->id;
        } else {
            $accountInfo = array(
                "scene_id" => self::COMMON_SCENE,
                "company_id" => $companyId
            );
            $relId = SceneCompanyRelModel::Create($accountInfo);
            if (!$relId) {
                $this->error('---公司账户创建关联失败');
                exit;
            }
        }
        if (!$relId) {
            $this->error('---公司账户relId异常');
            exit;
        }

        //账户创建
        if ($relInfo && $relInfo->account) {
            $account = $relInfo->account;
        } else {
            do {
                $account = date('YmdHis') . rand(1000, 9999);
                $accountIsset = AccountModel::Find($account);
            } while ($accountIsset);
        }
        if (!$account) {
            $this->error('---公司账户account异常');
            exit;
        }

        //资产变更
        $this->accountAssetsSync('company', $relId, $account, $companyInfo, $overdueTime);
        $this->info('公司账户处理成功:' . $companyId);
    }

    private function accountAssetsSync($userType, $relId, $account, $oldAccountInfo, $overdueTime)
    {
        $accountObj = AccountModel::Find($account);
        if ($accountObj) {
            if ($accountObj->updated_at == 0 && $oldAccountInfo->last_modified) {
                $this->error('---资产已变更请核对');
                exit;
            }
        } else {
            $accountInfo = array(
                'account' => $account,
                'point' => $oldAccountInfo->point,
                'used_point' => $oldAccountInfo->used_point,
                'frozen_point' => $oldAccountInfo->freeze_point,
                'overdue_point' => 0,
                'disabled' => 0,
                'created_at' => $oldAccountInfo->create_time,
                'updated_at' => $oldAccountInfo->last_modified
            );
            try {
                $accountId = app('api_db')->table('server_new_point_account')->insertGetId($accountInfo);
            } catch (\Exception $e) {
                $accountId = false;
            }
            if (!$accountId) {
                $this->error('---账户创建失败');
                exit;
            }
            $bindRes = false;
            if ($userType == 'company') {
                $bindRes = SceneCompanyRelModel::BindAccount($relId, $account);
            } elseif ($userType == 'member') {
                $bindRes = SceneMemberRelModel::BindAccount($relId, $account);
            }
            if (!$bindRes) {
                $this->error('---账户绑定失败');
                exit;
            }
            $sonAccountList = SonAccountModel::QueryByAccountId($accountId);
            if ($sonAccountList->count() > 0) {
                $this->error('---子账户已存在');
            }
            $createSonRes = SonAccountModel::Create(array(
                'account_id' => $accountId,
                'point' => $oldAccountInfo->point,
                'used_point' => $oldAccountInfo->used_point,
                'frozen_point' => $oldAccountInfo->freeze_point,
                'overdue_time' => $overdueTime
            ));
            if (!$createSonRes) {
                $this->error('---子账户创建失败');
                exit;
            }
        }

    }


}
