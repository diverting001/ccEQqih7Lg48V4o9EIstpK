<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-01-22
 * Time: 16:37
 */

namespace App\Api\V1\Controllers\ScenePoint;

use App\Api\Common\Controllers\BaseController;
use App\Api\Logic\PointServer\AdaperPoint;
use App\Api\Model\Point\Point;
use App\Api\Model\PointScene\CompanyBusinessRecord;
use App\Api\Model\PointScene\SceneAdapterCompany;
use App\Api\Model\PointScene\SceneCompanyRel;
use App\Api\Model\PointServer\Account;
use App\Api\Model\PointServer\SonAccount as SonAccountModel;
use App\Api\V1\Service\PointScene\CompanyAccount;
use App\Api\V1\Service\PointScene\CompanyAccount as CompanyAccountServer;
use App\Api\V1\Service\PointServer\Account as AccountServer;
use Illuminate\Http\Request;

class CompanyAccountController extends BaseController
{
    public function GetChannel(Request $request)
    {
        $data = $this->getContentArray($request);
        if (!$data['company_id']) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }

        $sceneAdapterCompany = SceneAdapterCompany::FindByCompanyId($data['company_id']);
        if ($sceneAdapterCompany) {
            $this->setErrorMsg('请求成功');
            $sceneAdapterCompany->point_version = 2;
            return $this->outputFormat($sceneAdapterCompany, 0);
        } else {
            $this->setErrorMsg('公司未开通渠道');
            return $this->outputFormat(array(), 400);
        }
    }

    public function SaveChannel(Request $request)
    {
        $data = $this->getContentArray($request);
        if (!$data['company_id'] || !$data['channel']) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }

        $sceneAdapterCompany = SceneAdapterCompany::FindByCompanyId($data['company_id']);
        if ($sceneAdapterCompany) {
            if ($sceneAdapterCompany->channel == $data['channel']) {
                $this->setErrorMsg('请求成功');
                return $this->outputFormat(['status' => true], 0);
            } else {
                $status = SceneAdapterCompany::UpdateCompanyChannel($data['company_id'], $data['channel']);
                if ($status) {
                    $this->setErrorMsg('请求成功');
                    return $this->outputFormat(['status' => true], 0);
                }
            }
        } else {
            $status = SceneAdapterCompany::AddCompanyChannel($data['company_id'], $data['channel']);
            if ($status) {
                $this->setErrorMsg('请求成功');
                return $this->outputFormat(['status' => true], 0);
            }
        }

        $this->setErrorMsg('保存失败');
        return $this->outputFormat(array(), 400);
    }


    /**
     * 内购向公司发放积分
     */
    public function Income(Request $request)
    {
        $incomeData = $this->getContentArray($request);
        if (
            empty($incomeData['channel']) ||
            empty($incomeData['income_id']) ||
            empty($incomeData['company_id']) ||
            empty($incomeData['scene_id']) ||
            empty($incomeData['point']) ||
            empty($incomeData['overdue_time']) ||
            empty($incomeData['system_code']) ||
            $incomeData['overdue_time'] <= time()
        ) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }

        $incomeData['business_type'] = 'pointCreate';
        $incomeData['business_bn']   = $incomeData['income_id'];

        $adaperPoint         = AdaperPoint::getInstance();
        $incomeData['point'] = $adaperPoint->GetPoint($incomeData['point'], $incomeData['channel'],
            AdaperPoint::RATE_TYPE_INT);

        $companyServer = new CompanyAccountServer();
        $res           = $companyServer->Income($incomeData);

        if ($res['status']) {
            $this->setErrorMsg('请求成功');
            return $this->outputFormat($res['data'], 0);
        } else {
            $this->setErrorMsg($res['msg']);
            return $this->outputFormat(array(), 400);
        }
    }

    /**
     * 查询公司下所有场景积分
     */
    public function QueryAll(Request $request)
    {
        $queryFilter = $this->getContentArray($request);
        if (empty($queryFilter['channel']) || empty($queryFilter['company_id'])) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }
        $companyServer = new CompanyAccountServer();
        $res           = $companyServer->QueryAll($queryFilter);
        if ($res['status']) {
            $adaperPoint = AdaperPoint::getInstance();
            foreach ($res['data'] as $key => $account) {
                $res['data'][$key]['point']         = $adaperPoint->GetPoint(
                    $account['point'],
                    $queryFilter['channel'],
                    AdaperPoint::RATE_TYPE_OUT
                );
                $res['data'][$key]['used_point']    = $adaperPoint->GetPoint(
                    $account['used_point'],
                    $queryFilter['channel'],
                    AdaperPoint::RATE_TYPE_OUT
                );
                $res['data'][$key]['frozen_point']  = $adaperPoint->GetPoint(
                    $account['frozen_point'],
                    $queryFilter['channel'],
                    AdaperPoint::RATE_TYPE_OUT
                );
                $res['data'][$key]['overdue_point'] = $adaperPoint->GetPoint(
                    $account['overdue_point'],
                    $queryFilter['channel'],
                    AdaperPoint::RATE_TYPE_OUT
                );
            }
            $this->setErrorMsg('请求成功');
            return $this->outputFormat($res['data'], 0);
        } else {
            $this->setErrorMsg($res['msg']);
            return $this->outputFormat(array(), 400);
        }
    }

    /**
     * 查询公司下所有场景积分
     */
    public function getCompanyAccount(Request $request)
    {
        $queryFilter = $this->getContentArray($request);
        if (empty($queryFilter['company_id']) || empty($queryFilter['scene_id'])) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }
        $companyServer = new CompanyAccountServer();
        $accountInfo   = $companyServer->FindByCompanyAndSceneId($queryFilter['company_id'], $queryFilter['scene_id']);
        if (!$accountInfo) {
            $this->setErrorMsg('账户不存在');
            return $this->outputFormat(array(), 400);
        }
        $this->setErrorMsg('成功');
        return $this->outputFormat([
            'account' => $accountInfo->account
        ]);
    }

    /**
     * 公司场景积分锁定
     */
    public function AssignFrozen(Request $request)
    {
        $frozenData = $this->getContentArray($request);
        if (
            empty($frozenData['channel']) ||
            empty($frozenData['frozen_id']) ||
            empty($frozenData['system_code']) ||
            empty($frozenData['company_id']) ||
            empty($frozenData['total_point']) ||
            empty($frozenData['overdue_time']) ||
            !is_array($frozenData['frozen_info']) ||
            count($frozenData['frozen_info']) < 1
        ) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }

        $frozenData['business_type'] = 'pointAssign';
        $frozenData['business_bn']   = $frozenData['frozen_id'];

        $adaperPoint               = AdaperPoint::getInstance();
        $frozenData['total_point'] = $adaperPoint->GetPoint($frozenData['total_point'], $frozenData['channel'],
            AdaperPoint::RATE_TYPE_INT);
        foreach ($frozenData['frozen_info'] as $key => $memberInfo) {
            $frozenData['frozen_info'][$key]['point'] = $adaperPoint->GetPoint($memberInfo['point'],
                $frozenData['channel'], AdaperPoint::RATE_TYPE_INT);
        }

        $companyServer = new CompanyAccountServer();
        $res           = $companyServer->Frozen($frozenData);
        $this->setErrorMsg($res['msg']);
        if ($res['status']) {
            return $this->outputFormat($res['data'], 0);
        } else {
            return $this->outputFormat(array(), 400);
        }

    }

    public function UnAssignFrozen(Request $request)
    {
        $frozenData = $this->getContentArray($request);
        if (
            empty($frozenData['channel']) ||
            empty($frozenData['frozen_id']) ||
            empty($frozenData['system_code'])
        ) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }

        $frozenData['business_type'] = 'pointAssign';
        $frozenData['business_bn']   = $frozenData['frozen_id'];

        $accountServer = new CompanyAccountServer();
        $res           = $accountServer->ReleaseFrozen($frozenData);
        $this->setErrorMsg($res['msg']);
        if ($res['status']) {
            $adaperPoint = AdaperPoint::getInstance();
            foreach ($res['data'] as $key => $info) {
                $res['data'][$key]['point'] = $adaperPoint->GetPoint($res['data'][$key]['point'],
                    $frozenData['channel'], AdaperPoint::RATE_TYPE_OUT);
            }
            return $this->outputFormat($res['data'], 0);
        } else {
            return $this->outputFormat(array(), 400);
        }
    }

    /**
     * 公司场景积分发放
     */
    public function AssignToMembers(Request $request)
    {
        $assignData = $this->getContentArray($request);
        if (
            empty($assignData['channel']) ||
            empty($assignData['dispatch_id']) ||
            empty($assignData['system_code']) ||
            empty($assignData['company_id']) ||
            empty($assignData['scene_id']) ||
            empty($assignData['total_point']) ||
            empty($assignData['frozen_flow_code']) ||
            empty($assignData['overdue_time']) ||
            !is_array($assignData['member_list']) ||
            count($assignData['member_list']) < 1
        ) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }
        $assignData['business_type'] = 'pointAssign';
        $assignData['business_bn']   = $assignData['dispatch_id'];

        $adaperPoint               = AdaperPoint::getInstance();
        $assignData['total_point'] = $adaperPoint->GetPoint(
            $assignData['total_point'],
            $assignData['channel'],
            AdaperPoint::RATE_TYPE_INT
        );
        foreach ($assignData['member_list'] as $key => $memberInfo) {
            $assignData['member_list'][$key]['point'] = $adaperPoint->GetPoint(
                $memberInfo['point'],
                $assignData['channel'],
                AdaperPoint::RATE_TYPE_INT
            );
        }


        $assignData['overdue_func'] = $assignData['overdue_func'] ?? 'inaction';

        $companyServer = new CompanyAccountServer();
        $res           = $companyServer->AssignToMembers($assignData);
        $this->setErrorMsg($res['msg']);
        if ($res['status']) {
            return $this->outputFormat($res['data'], 0);
        } else {
            return $this->outputFormat($res['data'], 400);
        }
    }

    public function RecordList(Request $request)
    {
        $queryData = $this->getContentArray($request);
        if (
            empty($queryData['system_code']) ||
            empty($queryData['channel']) ||
            empty($queryData['company_ids']) ||
            empty($queryData['scene_ids'])
        ) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }
        $queryData['page']      = $queryData['page'] ? $queryData['page'] : 1;
        $queryData['page_size'] = $queryData['page_size'] ? $queryData['page_size'] : 10;

        $companyServer = new CompanyAccountServer();
        $res           = $companyServer->RecordList($queryData);
        $this->setErrorMsg($res['msg']);
        if ($res['status']) {
            $adaperPoint = AdaperPoint::getInstance();
            foreach ($res['data']['data'] as $key => $record) {
                $res['data']['data'][$key]['point'] = $adaperPoint->GetPoint($record['point'], $queryData['channel'],
                    AdaperPoint::RATE_TYPE_OUT);
            }
            return $this->outputFormat($res['data'], 0);
        } else {
            return $this->outputFormat($res['data'], 400);
        }
    }

    public function AssignList(Request $request)
    {
        $queryData = $this->getContentArray($request);
        if (
            empty($queryData['channel']) ||
            empty($queryData['system_code']) ||
            empty($queryData['business_type']) ||
            empty($queryData['business_bn'])
        ) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }

        $companyServer = new CompanyAccountServer();
        $res           = $companyServer->AssignList($queryData);
        $this->setErrorMsg($res['msg']);
        if ($res['status']) {
            $adaperPoint = AdaperPoint::getInstance();
            foreach ($res['data']['assign_list'] as $key => $record) {
                $res['data']['assign_list'][$key]['point'] = $adaperPoint->GetPoint(
                    $record['point'],
                    $queryData['channel'],
                    AdaperPoint::RATE_TYPE_OUT
                );
                $res['data']['assign_list'][$key]['member_account_son_point'] = $adaperPoint->GetPoint(
                    $record['member_account_son_point'],
                    $queryData['channel'],
                    AdaperPoint::RATE_TYPE_OUT
                );
                $res['data']['assign_list'][$key]['member_account_son_used_point'] = $adaperPoint->GetPoint(
                    $record['member_account_son_used_point'],
                    $queryData['channel'],
                    AdaperPoint::RATE_TYPE_OUT
                );
                $res['data']['assign_list'][$key]['member_account_son_frozen_point'] = $adaperPoint->GetPoint(
                    $record['member_account_son_frozen_point'],
                    $queryData['channel'],
                    AdaperPoint::RATE_TYPE_OUT
                );
            }
            return $this->outputFormat($res['data'], 0);
        } else {
            return $this->outputFormat($res['data'], 400);
        }
    }

    //开通场景积分的公司列表
    public function CompanyList(Request $request)
    {
        $params   = $this->getContentArray($request);
        $page     = isset($params['page']) ? $params['page'] : 1;
        $pageSize = isset($params['page_size']) ? $params['page_size'] : 10;
        $channel  = isset($params['channel']) ? $params['channel'] : 'SCENENEIGOU';
        $list     = Point::GetCompanyIdsByChannel($channel, $page, $pageSize);//场景积分的Channel 为 SCENENEIGOU
        if ($list) {
            return $this->outputFormat($list, 0);
        } else {
            return $this->outputFormat([], 400);
        }

    }

    //根据公司ID列表获取账户列表
    public function QueryWithCompanyIds(Request $request)
    {
        $ids  = $this->getContentArray($request);
        $list = SceneCompanyRel::FindByCompanyListAndSceneList($ids, false);
        if ($list) {
            return $this->outputFormat($list, 0);
        } else {
            return $this->outputFormat([], 400);
        }
    }

    //批量查询账户信息
    public function GetWithAccounts(Request $request)
    {
        $param    = $this->getContentArray($request);
        $acc_list = $param['account_list'];
        $channel  = $param['channel'];

        $accountServer = new AccountServer();
        $listRes       = $accountServer->QueryBatch($acc_list);

        $list = $listRes['data'];

        if ($list) {
            $adapterPoint = AdaperPoint::getInstance();
            foreach ($list as $key => $account) {
                $list[$key]->point         = $adapterPoint->GetPoint($account->point, $channel,
                    AdaperPoint::RATE_TYPE_OUT);
                $list[$key]->used_point    = $adapterPoint->GetPoint($account->used_point, $channel,
                    AdaperPoint::RATE_TYPE_OUT);
                $list[$key]->frozen_point  = $adapterPoint->GetPoint($account->frozen_point, $channel,
                    AdaperPoint::RATE_TYPE_OUT);
                $list[$key]->overdue_point = $adapterPoint->GetPoint($account->overdue_point, $channel,
                    AdaperPoint::RATE_TYPE_OUT);
            }
            return $this->outputFormat($list, 0);
        } else {
            return $this->outputFormat([], 400);
        }
    }

    //获取子账户 ----将来最好废弃
    public function GetSonAccountByAccountId(Request $request)
    {
        $param          = $this->getContentArray($request);
        $account        = $param['account'];
        $channel        = $param['channel'];
        $sonAccountList = SonAccountModel::QueryByAccountId($account);
        if ($sonAccountList) {
            $adapterPoint = AdaperPoint::getInstance();
            foreach ($sonAccountList as $key => $account) {
                $sonAccountList[$key]->point         = $adapterPoint->GetPoint($account->point, $channel,
                    AdaperPoint::RATE_TYPE_OUT);
                $sonAccountList[$key]->used_point    = $adapterPoint->GetPoint($account->used_point, $channel,
                    AdaperPoint::RATE_TYPE_OUT);
                $sonAccountList[$key]->frozen_point  = $adapterPoint->GetPoint($account->frozen_point, $channel,
                    AdaperPoint::RATE_TYPE_OUT);
                $sonAccountList[$key]->overdue_point = $adapterPoint->GetPoint($account->overdue_point, $channel,
                    AdaperPoint::RATE_TYPE_OUT);
            }
            return $this->outputFormat($sonAccountList, 0);
        } else {
            return $this->outputFormat([], 400);
        }
    }

    //查询流水
    public function QueryRecord(Request $request)
    {
        //交易编码 开始时间 截止时间 交易类型 公司ID
        $params   = $this->getContentArray($request);
        $where    = $params['where'];
        $page     = isset($params['page']) ? $params['page'] : 1;
        $pageSize = isset($params['page_size']) ? $params['page_size'] : 10;
        $channel  = isset($params['channel']) ? $params['channel'] : 'SCENENEIGOU';
        $list     = CompanyBusinessRecord::queryRecord($where, $page, $pageSize);
        if ($list['count'] > 0) {
            $adapterPoint = AdaperPoint::getInstance();
            foreach ($list['list'] as $key => $account) {
                $list['list'][$key]->point = $adapterPoint->GetPoint($account->point, $channel,
                    AdaperPoint::RATE_TYPE_OUT);
            }
            return $this->outputFormat($list, 0);
        } else {
            return $this->outputFormat([], 400);
        }
    }

    //查询公司记录
    public function QueryCompanyAccountRecord(Request $request)
    {
        $params     = $this->getContentArray($request);
        $companyIds = $params['companyIds'];
        $page       = isset($params['page']) ? $params['page'] : 1;
        $pageSize   = isset($params['page_size']) ? $params['page_size'] : 10;
        $channel    = isset($params['channel']) ? $params['channel'] : 'SCENENEIGOU';
        $list       = CompanyBusinessRecord::queryCompanyAccountRecord($companyIds, $page, $pageSize);
        if ($list['count'] > 0) {
            $adapterPoint = AdaperPoint::getInstance();
            foreach ($list['list'] as $key => $account) {
                $list['list'][$key]->point          = $adapterPoint->GetPoint($account->point, $channel,
                    AdaperPoint::RATE_TYPE_OUT);
                $list['list'][$key]->balance        = $adapterPoint->GetPoint($account->balance, $channel,
                    AdaperPoint::RATE_TYPE_OUT);
                $list['list'][$key]->sum_used_point = $adapterPoint->GetPoint($account->sum_used_point, $channel,
                    AdaperPoint::RATE_TYPE_OUT);
            }
            return $this->outputFormat($list, 0);
        } else {
            return $this->outputFormat([], 400);
        }
    }

    //根据场景ID获取已经推送的公司列表
    public function GetBySceneId(Request $request)
    {
        $params   = $this->getContentArray($request);
        $scene_id = $params['scene_id'];
        $list     = CompanyBusinessRecord::queryCompanyListBySceneId($scene_id);
        if ($list) {
            return $this->outputFormat($list, 0);
        } else {
            return $this->outputFormat([], 400);
        }

    }

    /**
     * 根据公司获取即将过期的员工积分
     */
    public function getMemberOverduePointByCompany(Request $request)
    {
        $data = $this->getContentArray($request);
        if (empty($data['company_id']) || empty($data['overdue_date'])) {
            $this->setErrorMsg('company_id与overdue_date不能为空');
            return $this->outputFormat([], 400);
        }
        $companyServer = new CompanyAccountServer();
        $overdueList = $companyServer->CompanyOverduePoint(
            $data['company_id'],
            $data['overdue_date'],
            $data['page_size'],
            $data['page']
        );
        $count = $companyServer->CompanyOverduePointCount($data['company_id'], $data['overdue_date']);
        if (empty($count)) {
            $this->setErrorMsg('公司无过期积分');
            return $this->outputFormat([], 500);
        }
        $adapterPoint = AdaperPoint::getInstance();
        foreach ($overdueList as $key => $value) {
            foreach ($value as $kkey => $item) {
                $overdueList[$key][$kkey]['point'] = $adapterPoint->GetPoint(
                    $item['point'],
                    'SCENENEIGOU',
                    AdaperPoint::RATE_TYPE_OUT
                );
            }
        }
        $this->setErrorMsg('查询成功');
        return $this->outputFormat(['count' => $count, 'list' => $overdueList], 0);
    }
}
