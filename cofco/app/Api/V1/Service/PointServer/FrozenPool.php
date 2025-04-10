<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-01-27
 * Time: 15:25
 */

namespace App\Api\V1\Service\PointServer;

use App\Api\V1\Service\PointServer\Account as AccountServer;
use App\Api\Model\PointServer\FrozenPoolRecord;
use App\Api\Model\PointServer\Account as AccountModel;
use App\Api\Model\PointServer\SonAccount as SonAccountModel;
use App\Api\Model\PointServer\FrozenPool as FrozenPoolModel;
use App\Api\Model\PointServer\FrozenAssets as FrozenAssetsModel;
use App\Api\Model\PointServer\FrozenAssetsInfo as FrozenAssetsInfoModel;
use App\Api\Model\PointServer\FrozenPoolRecord as FrozenPoolRecordModel;


class FrozenPool
{
    public function getFrozenPoolAssets($frozenPoolCodeList)
    {
        $poolList = FrozenPoolModel::QueryByPoolCodeList($frozenPoolCodeList);
        if ($poolList->count() <= 0) {
            return $this->Response(false, "冻结池不存在");
        }

        $poolIdList = array_column($poolList->toArray(), 'frozen_pool_id');

        $assetsList = FrozenAssetsModel::QueryByPoolList($poolIdList);
        if ($assetsList->count() <= 0) {
            return $this->Response(false, "锁定池资产获取失败");
        }

        return $this->Response(true, '', $assetsList);
    }

    /**
     * 释放锁定
     */
    public function Release($frozenData)
    {
        $poolCode = $frozenData['frozen_pool_code'];
        $poolInfo = FrozenPoolModel::Find($poolCode);

        $point = $poolInfo->frozen_point;
        if (!$poolInfo || $point <= 0) {
            return $this->Response(true, "冻结池无可释放冻结资产");
        }

        $poolId = $poolInfo->frozen_pool_id;

        $frozenAssetsList = FrozenAssetsModel::Query($poolId);
        if (!$frozenAssetsList->count()) {
            return $this->Response(false, "冻结资产获取错误");
        }
        $totalReleasePoint = 0;
        $returnRelease     = array();
        foreach ($frozenAssetsList as $assets) {
            $releaseRes = $this->ReleaseAccount($assets, $assets->frozen_point, array(
                'memo' => isset($frozenData['memo']) && $frozenData['memo'] ? $frozenData['memo'] : ''
            ));
            if (!$releaseRes['status']) {
                return $releaseRes;
            }
            $totalReleasePoint += $assets->frozen_point;
            if (isset($returnRelease[$assets->account])) {
                $returnRelease[$assets->account]['point'] += $assets->frozen_point;
            } else {
                $returnRelease[$assets->account] = array(
                    'point'   => $assets->frozen_point,
                    'account' => $assets->account
                );
            }
        }

        if ($point != $totalReleasePoint) {
            return $this->Response(false, "冻结池资产核对错误");
        }

        $releaseStatus = FrozenPoolModel::Release($poolId, array(
            "release_point" => $point
        ));

        if (!$releaseStatus) {
            return $this->Response(false, "冻结池资产变更失败");
        }

        return $this->Response(true, "释放成功", $returnRelease);
    }

    private function ReleaseAccount($assets, $point, $extendData = array())
    {
        $accountId = $assets->account_id;
        if ($point <= 0) {
            return $this->Response(true, "冻结资产无可释放积分");
        }
        $frozenAssetsId = $assets->frozen_assets_id;

        $frozenAssetsInfo = FrozenAssetsInfoModel::QueryAvailable($frozenAssetsId);
        if (!$frozenAssetsInfo->count()) {
            return $this->Response(false, "冻结资产详情获取错误");
        }

        $totalReleasePoint = 0;
        foreach ($frozenAssetsInfo as $assetsInfo) {
            $releaseRes = $this->ReleaseSonAccount($assetsInfo, $assetsInfo->frozen_point, $extendData);
            if (!$releaseRes['status']) {
                return $releaseRes;
            }
            $totalReleasePoint += $assetsInfo->frozen_point;
        }

        if ($point != $totalReleasePoint) {
            return $this->Response(false, "冻结资产信息核对失败");
        }

        $releaseFrozenStatus = FrozenAssetsModel::Release($frozenAssetsId, array(
            "release_point" => $point
        ));

        if (!$releaseFrozenStatus) {
            return $this->Response(false, '冻结主账户资产信息变更失败');
        }

        return $this->Response(true, '释放成功');
    }

    private function ReleaseSonAccount($assetsInfo, $point, $extendData = array())
    {
        $memo               = isset($extendData['memo']) ? $extendData['memo'] : '';
        $frozenInfoId       = $assetsInfo->frozen_info_id;
        $sonAccountId       = $assetsInfo->son_account_id;
        $recordCreateStatus = FrozenPoolRecordModel::Create(array(
            'frozen_info_id' => $frozenInfoId,
            'record_type'    => 'reduce',
            'before_point'   => $assetsInfo->frozen_point,
            'change_point'   => $point,
            'after_point'    => $assetsInfo->frozen_point - $point,
            'memo'           => $memo,
        ));
        if (!$recordCreateStatus) {
            return $this->Response(false, '冻结池流水创建失败');
        }

        $updateFrozenRes = FrozenAssetsInfoModel::Release($frozenInfoId, array(
            "release_point" => $point
        ));
        if (!$updateFrozenRes) {
            return $this->Response(false, '锁定子账户变更失败');
        }

        $sonFrozenStatus = SonAccountModel::ReleaseFrozenPoint($sonAccountId, array(
            "release_point" => $point
        ));
        if (!$sonFrozenStatus) {
            return $this->Response(false, '子账户资产信息变更失败');
        }

        return $this->Response(true, '释放成功');
    }

    /**
     * 创建一笔冻结
     */
    public function Create($frozenData)
    {
        $totalPoint        = $frozenData['total_point'];
        $overdueTime       = $frozenData['overdue_time'];
        $frozenAccountList = $frozenData['frozen_account_list'];
        $memo              = $frozenData['memo'] ? $frozenData['memo'] : "";

        if ($totalPoint < 1) {
            return $this->Response(false, "冻结总额错误");
        }

        $frozenListTotalPoint = 0;
        foreach ($frozenAccountList as $frozenPointInfo) {
            $frozenListTotalPoint += $frozenPointInfo['point'];
        }

        if ($totalPoint != $frozenListTotalPoint) {
            return $this->Response(false, "冻结金额核对错误");
        }

        //创建一个积分冻结池
        do {
            $poolCode  = date('YmdHis') . rand(1000, 9999);
            $poolIsset = FrozenPoolModel::Find($poolCode);
        } while ($poolIsset);

        $poolId = FrozenPoolModel::Create(array(
            "frozen_pool_code" => $poolCode,
            "frozen_point"     => $totalPoint,
            "overdue_time"     => $overdueTime,
        ));

        if (!$poolId) {
            return $this->Response(false, "积分冻结池创建失败");
        }

        foreach ($frozenAccountList as $account => $frozenPointInfo) {
            $frozenRes = $this->FrozenAccount($poolId, $account, $overdueTime, $memo, $frozenPointInfo);
            if (!$frozenRes['status']) {
                return $frozenRes;
            }
        }

        return $this->Response(true, "积分冻结成功", array(
            "frozen_pool_code" => $poolCode,
        ));
    }

    /**
     * 冻结主账户指定金额
     */
    private function FrozenAccount($poolId, $account, $overdueTime, $memo, $frozenPointInfo)
    {
        $accountServer = new AccountServer();
        $accountRes    = $accountServer->GetValidAccount($account);
        if (!$accountRes['status']) {
            return $this->Response(false, '资产账户信息无效');
        }

        $curPoint = $point = $frozenPointInfo['point'];

        $accountInfo = $accountRes['data'];
        \Neigou\Logger::Debug('PointServer.FrozenAccount', array(
            'sender' => $point,
            'reason' => json_encode($accountInfo),
        ));
        if ($point - $accountInfo->point > 0.001) {
            return $this->Response(false, '资产账户可用余额不足');
        }

        $accountId = $accountInfo->account_id;
        //主账户处理
        $frozenAssetsId = FrozenAssetsModel::Create(array(
            'frozen_pool_id' => $poolId,
            "account_id"     => $accountId,
            'frozen_point'   => $point
        ));
        if (!$frozenAssetsId) {
            return $this->Response(false, '主账户冻结信息创建失败');
        }

        $frozenStatus = AccountModel::FrozenPoint($accountId, array(
            "frozen_point" => $point
        ));
        if (!$frozenStatus) {
            return $this->Response(false, '主账户资产信息变更失败');
        }

        //子账户处理
        $availableSonAccountList = SonAccountModel::QueryAvailable($accountId, $overdueTime > time() ? $overdueTime : time());
        if (!$availableSonAccountList) {
            return $this->Response(false, '账户有效内子账户不存在');
        }

        /**
         * 冻结子账户金额
         */
        foreach ($availableSonAccountList as $sonAccount) {
            if ($curPoint <= 0) {
                break;
            }
            $sonFrozenPoint = min($curPoint, $sonAccount->point);
            $sonFrozenRes   = $this->FrozenSonAccount($sonAccount, $frozenAssetsId, $memo, $sonFrozenPoint);
            if (!$sonFrozenRes['status']) {
                return $sonFrozenRes;
            }
            $curPoint -= $sonFrozenPoint;
        }

        if ($curPoint > 0) {
            return $this->Response(false, '账户有效内子账户余额不足');
        }

        return $this->Response(true, '账户冻结成功');
    }

    /**
     * 冻结子账户指定金额
     */
    private function FrozenSonAccount($sonAccount, $frozenAssetsId, $memo, $sonFrozenPoint)
    {
        $sonAccountId = $sonAccount->son_account_id;
        $frozenInfoId = FrozenAssetsInfoModel::Create(array(
            'frozen_assets_id' => $frozenAssetsId,
            "son_account_id"   => $sonAccountId,
            'frozen_point'     => $sonFrozenPoint
        ));
        if (!$frozenInfoId) {
            return $this->Response(false, '子账户冻结信息创建失败');
        }

        $recordCreateStatus = FrozenPoolRecordModel::Create(array(
            'frozen_info_id' => $frozenInfoId,
            'record_type'    => 'add',
            'before_point'   => 0,
            'change_point'   => $sonFrozenPoint,
            'after_point'    => $sonFrozenPoint,
            'memo'           => $memo,
        ));
        if (!$recordCreateStatus) {
            return $this->Response(false, '冻结池流水创建失败');
        }

        //变更子账户数据
        $sonFrozenStatus = SonAccountModel::FrozenPoint($sonAccountId, array(
            "frozen_point" => $sonFrozenPoint
        ));
        if (!$sonFrozenStatus) {
            return $this->Response(false, '子账户资产信息变更失败');
        }

        return $this->Response(true, '子账户冻结成功');
    }

    private function Response($status = true, $msg = '成功', $data = [])
    {
        return [
            'status' => $status,
            'msg'    => $msg,
            'data'   => $data,
        ];
    }
}
