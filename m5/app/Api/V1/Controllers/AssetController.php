<?php
/**
 * neigou_service-stock
 *
 * @package     api
 * @author      xupeng
 * @since       Version
 * @filesource
 */

namespace App\Api\V1\Controllers;

use App\Api\Common\Controllers\BaseController;
use App\Api\V1\Service\Asset\Asset as AssetLogic;
use Illuminate\Http\Request;

/**
 * 资产相关
 *
 * @package     api
 * @category    Controller
 * @author        xupeng
 */
class AssetController extends BaseController
{
    /**
     * 注册资产
     *
     * @return array
     */
    public function registerAsset(Request $request)
    {
        $params = $this->getContentArray($request);

        // 参数验证
        if (empty($params['use_type']) OR empty($params['use_obj'] OR empty($params['asset_list'])))
        {
            $this->setErrorMsg('请求参数错误');
            return $this->outputFormat(null, 400);
        }

        // 使用类型
        $useType = $params['use_type'];

        // 使用对象
        $useObj = $params['use_obj'];

        // 资源列表
        $assetList = $params['asset_list'];

        // 用户ID
        $memberId = $params['member_id'];

        // 公司ID
        $companyId = $params['company_id'];

        // 使用对象数据
        $useObjData = $params['use_obj_data'];

        // 使用对象明细
        $useObjItems = $params['use_obj_items'];

        $assetLogic = new AssetLogic();

        // 注册资产
        $errMsg = '';
        $assetInfo = $assetLogic->registerAsset($useType, $useObj, $memberId, $companyId, $assetList, $useObjData, $useObjItems, $errMsg);
        if (empty($assetInfo))
        {
            $errMsg OR $errMsg = '资产注册失败';
            $this->setErrorMsg($errMsg);
            return $this->outputFormat(null, 400);
        }

        $this->setErrorMsg('请求成功');
        return $this->outputFormat($assetInfo);
    }

    // --------------------------------------------------------------------

    /**
     * 获取资产详情
     *
     * @return array
     */
    public function getAssetDetail(Request $request)
    {
        $params = $this->getContentArray($request);

        // 参数验证
        if (empty($params['use_type']) OR empty($params['use_obj']))
        {
            $this->setErrorMsg('请求参数错误');
            return $this->outputFormat(null, 400);
        }

        $assetLogic = new AssetLogic();

        // 更新账户信息
        $accountInfo = $assetLogic->getAssetObjDetail($params['use_type'], $params['use_obj'], $params['sync_query']);

        $this->setErrorMsg('请求成功');

        return $this->outputFormat($accountInfo);
    }

}
