<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-12-06
 * Time: 16:04
 */

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\Api\Model\PointServer\SonAccount as SonAccountModel;
use App\Api\V1\Service\PointScene\BusinessFlow as BusinessFlowServer;
use App\Api\Model\PointServer\AccountRecord as AccountRecordModel;
use App\Api\V1\Service\PointServer\Bill as BillServer;
use App\Api\Model\PointScene\MemberBusinessRecord;
use App\Api\Model\PointServer\AccountConsume as AccountConsumeModel;
use App\Api\V1\Service\PointServer\Account as AccountServer;

class OverduePointConsume extends Command
{
    protected $signature = 'OverduePointConsume {--companyId=}';

    protected $description = '场景积分过期账户余额虚拟消费';

    const MEMO = '积分过期';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $companyId = $this->option('companyId');
        if (!$companyId) {
            $this->error('参数错误 ：--companyId= [ 公司ID ]');
            exit;
        }

        $this->OverduePointConsume($companyId);
        $this->info('执行完成');
    }

    private function OverduePointConsume($companyId)
    {
        $sql = 'select rel.id,rel.account,account.account_id,sonAccount.son_account_id,sonAccount.point from server_new_point_member_scene_rel as rel ';
        $sql .= 'left join server_new_point_account as account on rel.account=account.account ';
        $sql .= 'left join server_new_point_account_son as sonAccount on account.account_id = sonAccount.account_id ';
        $sql .= 'where rel.company_id=' . $companyId . ' and sonAccount.point>0 and overdue_time <' . time();

        $page     = 1;
        $pageSize = 20;
        while (true) {
            if ($page > 100) {
                $this->info('请确认是否有异常！');
                exit;
            }
            $newsql = $sql . ' limit ' . $pageSize;
            $data   = app('api_db')->select($newsql);
            if (!is_array($data) || count($data) <= 0) {
                break;
            }
            foreach ($data as $sonAccount) {
                $this->info(date('Y-m-d H:i:s') . ':处理子账户' . $sonAccount->son_account_id . ',point:' . $sonAccount->point);
                $this->runConsume($sonAccount);
            }
            $page++;
        }
    }

    private function runConsume($sonAccount)
    {
        $orderId = date('YmdHis') . rand(10000, 99999) . rand(1000, 9999);
        app('db')->beginTransaction();

        $accountServer = new AccountServer();

        $memberPointInfo = $accountServer->GetAccountInfo($sonAccount->account);

        $billServer    = new BillServer();
        $billCreateRes = $billServer->Create(array("bill_type" => 'consume'));
        if (!$billCreateRes['status']) {
            $this->error('-error: ' . $billCreateRes['msg']);
            app('db')->rollBack();
            return;
        }

        $billCode = $billCreateRes['data']['bill_code'];

        $consumeCreateRes = AccountConsumeModel::Create(array(
            'bill_code'      => $billCode,
            'son_account_id' => $sonAccount->son_account_id,
            'point'          => $sonAccount->point,
            'memo'           => self::MEMO,
        ));

        if (!$consumeCreateRes) {
            $this->error('-error: 交易数据创建失败');
            app('db')->rollBack();
            return;
        }

        $consumeStatus = SonAccountModel::ConsumePoint($sonAccount->son_account_id, array(
            "point" => $sonAccount->point
        ));
        if (!$consumeStatus) {
            $this->error('-error: 用户子账户出账失败');
            app('db')->rollBack();
            return;
        }

        $consumeRecordId = AccountRecordModel::Create(array(
            'son_account_id'   => $sonAccount->son_account_id,
            'bill_code'        => $billCode,
            'frozen_record_id' => -1,
            'record_type'      => 'reduce',
            'before_point'     => $sonAccount->point,
            'change_point'     => $sonAccount->point,
            'after_point'      => 0,
            'memo'             => self::MEMO,
        ));
        if (!$consumeRecordId) {
            $this->error('-error: 子账户出账流水创建失败');
            app('db')->rollBack();
            return;
        }

        $businessFlowServer = new BusinessFlowServer();

        $bfCreateRes = $businessFlowServer->Create(array(
            "business_type" => 'confirmOrder',
            "business_bn"   => $orderId,
            "system_code"   => 'NEIGOU'
        ));

        if (!$bfCreateRes['status']) {
            $this->error('-error: ' . $bfCreateRes['msg']);
            app('db')->rollBack();
            return;
        }

        $businessFlowCode = $bfCreateRes['data']['business_flow_code'];

        $bindBillRes = $businessFlowServer->BindBillCode($businessFlowCode, $billCode);
        if (!$bindBillRes['status']) {
            $this->error('-error: ' . $bindBillRes['msg']);
            app('db')->rollBack();
            return;
        }

        $status = MemberBusinessRecord::Create(array(
            "business_type"     => 'createOrder',
            "business_bn"       => $orderId,
            "system_code"       => 'NEIGOU',
            'member_account_id' => $sonAccount->id,
            'record_type'       => 'reduce',
            'before_point'      => $memberPointInfo->point + $sonAccount->point,
            'point'             => $sonAccount->point,
            'after_point'       => $memberPointInfo->point,
            'memo'              => self::MEMO,
            'created_at'        => time()
        ));
        if (!$status) {
            $this->error('-error: 用户业务流水添加失败');
            app('db')->rollBack();
            return;
        }

        $this->info('-处理成功');
        app('db')->commit();
    }
}
