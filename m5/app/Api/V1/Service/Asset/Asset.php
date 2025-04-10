<?php
/**
 * neigou_service-stock
 *
 * @package     api
 * @author      xupeng
 * @since       Version
 * @filesource
 */

namespace App\Api\V1\Service\Asset;

use App\Api\Model\Asset\Asset as AssetModel;
use App\Api\Model\Asset\Obj as AssetObjModel;

/**
 * 账户 Service
 *
 * @package     api
 * @category    Service
 * @author      xupeng
 */
class Asset
{
    /**
     * @var array 状态说明
     */
    static $_statusMsg = array(
        0   => '注册',
        1   => '取消',
        3   => '使用',
        4   => '异常',
    );

    /**
     * @var array 使用类型列表
     */
    static $_useTypeList = array(
        'ORDER'   => '订单',
    );

    /**
     * 注册资产
     *
     * @param   $useType        string      使用类型
     * @param   $useObj         string      使用对象
     * @param   $memberId       int         用户ID
     * @param   $companyId      int         公司ID
     * @param   $assetList      array       资产列表
     * @param   $useObjData     array       使用对象数据
     * @param   $useObjItems    array       使用对象明细
     * @param   $errMsg         string      错误信息
     * @return  mixed
     */
    public function registerAsset($useType, $useObj, $memberId, $companyId, $assetList, $useObjData = array(), $useObjItems = array(), & $errMsg = '')
    {
        if ( ! isset(self::$_useTypeList[$useType]))
        {
            $errMsg = '不支持此类型';
            return false;
        }

        // 查询资产
        $objDetail = $this->getAssetObjDetail($useType, $useObj);

        if ( ! empty($objDetail))
        {
            return $objDetail;
        }

        app('db')->beginTransaction();

        $objModel = new AssetObjModel();
        $assetModel = new AssetModel();

        // 新建资产对象
        $objId = $objModel->addAssetObj($useType, $useObj, $useObjData, $memberId, $companyId);

        if (empty($objId))
        {
            app('db')->rollBack();
            $errMsg = '添加资产主体失败';
            return false;
        }

        // 添加 items
        if ( ! empty($useObjItems) && ! $objModel->addAssetObjItem($objId, $useObjItems))
        {
            app('db')->rollBack();
            $errMsg = '创建资产明细失败';
            return false;
        }

        // 新建资产
        foreach ($assetList as $assetInfo)
        {
            $data = array(
                'asset_type'        => $assetInfo['asset_type'], // 资产类型
                'asset_bn'          => $assetInfo['asset_bn'], // 资产编码
                'asset_category'    => $assetInfo['name'], // 资产分类
                'asset_amount'      => $assetInfo['asset_amount'], // 资产金额
                'asset_data'        => is_array($assetInfo['asset_data']) && $assetInfo['asset_data'] ? json_encode($assetInfo['asset_data']) : '', // 资产金额
                'status'            => 0,
                'memo'              => $assetInfo['memo'],
            );

            // 添加资产
            if ( ! $assetModel->addAsset($objId, $data))
            {
                app('db')->rollBack();
                $errMsg = '添加资产失败';
                return false;
            }
        }

        app('db')->commit();

        return $this->getAssetObjDetail($useType, $useObj);
    }

    // --------------------------------------------------------------------

    /**
     * 获取资产对象详情
     *
     * @param   $useType    string      使用对象类型
     * @param   $useObj     string      使用对象
     * @param   $syncQuery  int         同步查询
     * @return  array
     */
    public function getAssetObjDetail($useType, $useObj, $syncQuery = 0)
    {
        $assetObjModel = new AssetObjModel();
        $assetModel = new AssetModel();

        if ($syncQuery == 1)
        {
            $this->syncAssetObj($useType, $useObj);
        }

        $objDetail = $assetObjModel->getAssetObjDetail($useType, $useObj);

        if (empty($objDetail))
        {
            return array();
        }
        $objDetail['use_obj_data'] = is_array($objDetail['use_obj_data']) && $objDetail['use_obj_data'] ? json_encode($objDetail['asset_data']) : '';

        $objDetail['obj_items'] = $assetObjModel->getAssetObjItem($objDetail['obj_id']);

        // 获取资产列表
        $filter = array(
            'obj_id' => $objDetail['obj_id']
        );

        // 获取资产列表
        $objDetail['asset_list'] = $assetModel->getAssetList($filter);

        return $objDetail;
    }

    // --------------------------------------------------------------------

    /**
     * 同步资产对象明细
     *
     * @param   $useType    string      使用对象类型
     * @param   $useObj     string      使用对象
     * @return  boolean
     */
    public function syncAssetObj($useType, $useObj)
    {
        $assetObjModel = new AssetObjModel();
        $assetModel = new AssetModel();

        // 同步资产状态
        $objDetail = $assetObjModel->getAssetObjDetail($useType, $useObj);

        if (empty($objDetail))
        {
            return false;
        }

        // 获取资产列表
        $filter = array(
            'obj_id' => $objDetail['obj_id']
        );

        $assetList = $assetModel->getAssetList($filter);

        foreach ($assetList as $assetInfo)
        {
            if (in_array($assetInfo['status'], array(2, 3)))
            {
                continue;
            }
            $assetLogic = 'App\\Api\\Logic\\AssetType\\' . ucfirst(strtolower($assetInfo['asset_type']));
            if ( ! class_exists($assetLogic))
            {
                continue;
            }
            $classObj = new $assetLogic();
            $status = $classObj->getAssetStatus($assetInfo, $objDetail);

            if ($status === false)
            {
                continue;
            }

            // 更新资产状态
            $this->updateAssetStatus($assetInfo['asset_id'], $status);
        }

        // 同步资产对象状态
        if ( ! $this->syncAssetObjStatus($useType, $useObj))
        {
            return false;
        }

        return true;
    }

    // --------------------------------------------------------------------

    /**
     * 更新资产状态
     *
     * @param   $assetId    int     资产ID
     * @param   $status     int     状态
     * @return  boolean
     */
    public function updateAssetStatus($assetId, $status)
    {
        $assetModel = new AssetModel();

        // 资产详情
        $assetDetail = $assetModel->getAssetDetail($assetId);

        if (empty($assetDetail))
        {
            return false;
        }

        if ($status == $assetDetail['status'])
        {
            return true;
        }

        if ($assetModel->updateAssetStatus($assetId, $status))
        {
            // 记录资产记录
            $assetModel->addAssetRecord($assetId, $status, $assetDetail['status']);
        }

        return true;
    }

    // --------------------------------------------------------------------

    /**
     * 同步资产对象状态
     *
     * @param   $useType    string      使用对象类型
     * @param   $useObj     string      使用对象
     * @return  boolean
     */
    public function syncAssetObjStatus($useType, $useObj)
    {
        $assetModel = new AssetModel();

        $assetObjModel = new AssetObjModel();

        // 获取资产对象详情
        $objDetail = $assetObjModel->getAssetObjDetail($useType, $useObj);

        if (empty($objDetail))
        {
            return false;
        }

        // 获取资产列表
        $filter = array(
            'obj_id' => $objDetail['obj_id']
        );
        $assetList = $assetModel->getAssetList($filter);

        if (empty($assetList) OR in_array($objDetail['status'], array(2, 3, 4)))
        {
            return true;
        }

        $status = $objDetail['status'];

        foreach ($assetList as $assetInfo)
        {
            if ($objDetail['status'] == $assetInfo['status'] OR $assetInfo['status'] == 4)
            {
                $status = $objDetail['status'];
                break;
            }

            // 首次赋值
            if ($status == $objDetail['status'])
            {
                $status = $assetInfo['status'];
                continue;
            }
            // 状态差异变为锁定
            if ($status != $assetInfo['status'])
            {
                $status = 1;
            }
        }

        // 状态差异
        if ($status != $objDetail['status'])
        {
            return $assetObjModel->updateAssetObjStatus($objDetail['obj_id'], $status);
        }

        return true;
    }

}
