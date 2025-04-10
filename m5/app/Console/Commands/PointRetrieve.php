<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-12-06
 * Time: 16:04
 */

namespace App\Console\Commands;

use Illuminate\Console\Command;

use Neigou\RedisNeigou;
use App\Api\Model\PointScene\SceneCompanyRel;
use App\Api\Model\PointScene\SceneMemberRel;
use App\Api\Model\PointServer\SonAccount as SonAccountModel;
use App\Api\Model\PointServer\AccountTransfer as AccountTransferModel;
use App\Api\V1\Service\PointScene\BusinessFlow as BusinessFlowServer;
use App\Api\Model\PointServer\AccountRecord as AccountRecordModel;
use App\Api\V1\Service\PointServer\Bill as BillServer;
use App\Api\Model\PointScene\MemberBusinessRecord;
use App\Api\Model\PointScene\CompanyBusinessRecord;
use App\Api\Model\PointScene\OverdueRefundLog as OverdueRefundLogModel;
use App\Api\V1\Service\PointServer\Account as AccountServer;

class PointRetrieve extends Command
{
    protected $signature = 'PointRetrieve {--func=}';

    protected $description = '已过期场景积分回收';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $func = $this->option('func');
        if (!$func || !in_array($func, array('retrieve_c2b'))) {
            $this->error('参数错误 ：--func= [ retrieve_c2b：将公司给用户的已过期积分回收至公司 ]');
            exit;
        }

        $funName = camel_case($func);
        $this->$funName($func);
        $this->info('执行完成');
    }

    private function retrieveC2b($func)
    {
        $key    = "CLI-PointRetrieve-retrieveC2b";
        $expire = 3600;
        $redis  = new RedisNeigou();
        $isLock = $redis->_redis_connection->setnx($key, time() + $expire);
        if (!$isLock) {
            $this->info('脚本处理中');
            return;
        }

        while (true) {
            $list = SonAccountModel::getOverdueAccountByFunc($func);
            if ($list->count() <= 0) {
                $this->info('没有过期需要退款账户');
                break;
            }
            foreach ($list as $sonAccount) {
                $memberSAId      = $sonAccount->son_account_id;
                $memberAccount   = $sonAccount->account;
                $point           = $sonAccount->point;
                $memSAccountInfo = SonAccountModel::Find($memberSAId);

                $transferInfo    = AccountTransferModel::GetOneAddRecord($memberSAId);
                $companySAid     = $transferInfo->son_account_id;
                $comSAccount     = SonAccountModel::GetAccountInfoBySonAccountId($companySAid);
                $comSAccountInfo = SonAccountModel::Find($companySAid);
                $companyAccount  = $comSAccount->account;

                $memberAccountInfo  = SceneMemberRel::FindByAccount($memberAccount);
                $companyAccountInfo = SceneCompanyRel::FindByAccount($companyAccount);


                $memberId  = $memberAccountInfo->member_id;
                $companyId = $companyAccountInfo->company_id;

                $memberInfo = $this->getUserInfo($memberId);

                $this->info('retrieveC2b=companyId:' . $companyId . ',memberId:' . $memberId . ',said:' . $memberSAId . ',caid:' . $companySAid . ',account:' . $memberAccount . ',caccount:' . $companyAccount . ',point:' . $point);

                if ($memberAccountInfo->company_id != $companyId) {
                    $this->error('-error: company_id 不匹配' . $memberSAId);
                    continue;
                }

                $memo = $memberInfo['name'] . '积分过期退还';
                app('db')->beginTransaction();

                $accountServer = new AccountServer();

                $memberPointInfo  = $accountServer->GetAccountInfo($memberAccount);
                $companyPointInfo = $accountServer->GetAccountInfo($companyAccount);

                $billServer    = new BillServer();
                $billCreateRes = $billServer->Create(array("bill_type" => 'transfer'));
                if (!$billCreateRes['status']) {
                    $this->error('-error: ' . $billCreateRes['msg']);
                    app('db')->rollBack();
                    continue;
                }

                $billCode = $billCreateRes['data']['bill_code'];

                $transferCreateRes = AccountTransferModel::Create(array(
                    'bill_code'         => $billCode,
                    'son_account_id'    => $memberSAId,
                    'to_son_account_id' => $companySAid,
                    'point'             => $point,
                    'memo'              => $memo,
                ));
                if (!$transferCreateRes) {
                    $this->error('-error: 转帐交易数据创建失败');
                    app('db')->rollBack();
                    continue;
                }

                $consumeStatus = SonAccountModel::ConsumePoint($memberSAId, array(
                    "point" => $point
                ));
                if (!$consumeStatus) {
                    $this->error('-error: 用户子账户出账失败');
                    app('db')->rollBack();
                    continue;
                }

                $refundStatus = SonAccountModel::RefundPoint($companySAid, array(
                    "refund_point" => $point
                ));
                if (!$refundStatus) {
                    $this->error('-error: 公司子账户入账失败');
                    app('db')->rollBack();
                    continue;
                }

                $consumeRecordId = AccountRecordModel::Create(array(
                    'son_account_id'   => $memberSAId,
                    'bill_code'        => $billCode,
                    'frozen_record_id' => -1,
                    'record_type'      => 'reduce',
                    'before_point'     => $memSAccountInfo->point + $memSAccountInfo->frozen_point,
                    'change_point'     => $point,
                    'after_point'      => $memSAccountInfo->point + $memSAccountInfo->frozen_point - $point,
                    'memo'             => $memo,
                ));
                if (!$consumeRecordId) {
                    $this->error('-error: 子账户出账流水创建失败');
                    app('db')->rollBack();
                    continue;
                }

                $incomeRecordId = AccountRecordModel::Create(array(
                    'son_account_id'   => $companySAid,
                    'bill_code'        => $billCode,
                    'frozen_record_id' => -1,
                    'record_type'      => 'add',
                    'before_point'     => $comSAccountInfo->point + $comSAccountInfo->frozen_point,
                    'change_point'     => $point,
                    'after_point'      => $comSAccountInfo->point + $comSAccountInfo->frozen_point + $point,
                    'memo'             => $memo,
                ));
                if (!$incomeRecordId) {
                    return $this->Response(false, "子账户入账流水创建失败");
                }

                $overdueRefundId = OverdueRefundLogModel::Create([
                    'company_id'   => $memberAccountInfo->company_id,
                    'member_id'    => $memberAccountInfo->member_id,
                    'scene_id'     => $memberAccountInfo->scene_id,
                    'overdue_time' => $memSAccountInfo->overdue_time,
                    'point'        => $point
                ]);
                if (!$overdueRefundId) {
                    $this->error('-error: OverdueRefundLogModel创建失败' . json_encode([
                            'company_id'   => $memberAccountInfo->company_id,
                            'member_id'    => $memberAccountInfo->member_id,
                            'scene_id'     => $memberAccountInfo->scene_id,
                            'overdue_time' => $memSAccountInfo->overdue_time,
                            'point'        => $point
                        ])
                    );
                    app('db')->rollBack();
                    continue;
                }

                $businessFlowServer = new BusinessFlowServer();

                $bfCreateRes = $businessFlowServer->Create(array(
                    "business_type" => 'pointAssignRetrieved',
                    "business_bn"   => $overdueRefundId,
                    "system_code"   => 'NEIGOU'
                ));

                if (!$bfCreateRes['status']) {
                    $this->error('-error: ' . $bfCreateRes['msg']);
                    app('db')->rollBack();
                    continue;
                }

                $businessFlowCode = $bfCreateRes['data']['business_flow_code'];

                $bindBillRes = $businessFlowServer->BindBillCode($businessFlowCode, $billCode);
                if (!$bindBillRes['status']) {
                    $this->error('-error: ' . $bindBillRes['msg']);
                    app('db')->rollBack();
                    continue;
                }

                $status = MemberBusinessRecord::Create(array(
                    "business_type"     => 'pointAssignRetrieved',
                    "business_bn"       => $overdueRefundId,
                    "system_code"       => 'NEIGOU',
                    'member_account_id' => $memberAccountInfo->id,
                    'record_type'       => 'reduce',
                    'before_point'      => $memberPointInfo->point + $point,
                    'point'             => $point,
                    'after_point'       => $memberPointInfo->point,
                    'memo'              => '积分过期',
                    'created_at'        => time()
                ));
                if (!$status) {
                    $this->error('-error: 用户业务流水添加失败');
                    app('db')->rollBack();
                    continue;
                }

                $status = CompanyBusinessRecord::Create(array(
                    "business_type"      => 'pointAssignRetrieved',
                    "business_bn"        => $overdueRefundId,
                    "system_code"        => 'NEIGOU',
                    'company_account_id' => $companyAccountInfo->id,
                    'record_type'        => 'add',
                    'before_point'       => $companyPointInfo->point,
                    'point'              => $point,
                    'after_point'        => $companyPointInfo->point + $point,
                    'memo'               => $memo,
                    'created_at'         => time()
                ));
                if (!$status) {
                    $this->error('-error: 公司业务流水添加失败');
                    app('db')->rollBack();
                    continue;
                }
                app('db')->commit();
            }
        }

        $redis->_redis_connection->del($key);
    }


    // 获取用户信息
    private function getUserInfo($memberId)
    {
        $param['params']    = array(
            "member_ids" => array($memberId)
        );
        $param['class_obj'] = 'b2c_member';
        $param['method']    = 'getUserInfoByMemberIds';
        $param['token']     = \App\Api\Common\Common::GetEcStoreSign($param);

        $url = config('neigou.STORE_DOMIN') . '/openapi/cas/api';

        $_curl  = new \Neigou\Curl();
        $result = $_curl->Post($url, $param);
        $result = json_decode($result, true);

        $memberInfo = [];
        if (isset($result['data'][$memberId]['info']) && $result['data'][$memberId]['info']) {
            $memberInfo = $result['data'][$memberId]['info'];
        }

        return $memberInfo;
    }
}
