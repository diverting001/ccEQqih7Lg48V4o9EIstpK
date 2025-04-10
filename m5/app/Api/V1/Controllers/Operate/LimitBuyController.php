<?php
/**
 * neigou_service
 * @package     api
 * @author      xupeng
 * @since       Version
 * @filesource
 */

namespace App\Api\V1\Controllers\Operate;

use App\Api\Common\Controllers\BaseController;
use App\Api\V1\Service\Operate\LimitBuy as LimitBuyLogic;
use Illuminate\Http\Request;

/**
 *  Controller
 *
 * @package     api
 * @category    Controller
 * @author        xupeng
 */
class LimitBuyController extends BaseController
{
    /**
     * 获取商品限制
     *
     * @return array
     */
    public function GetGoodsLimitBuy(Request $request)
    {
        $params = $this->getContentArray($request);

        // 验证请求参数
        if (empty($params['company_id']) OR empty($params['member_id']) OR empty($params['product'])) {
            $this->setErrorMsg('请求参数错误');
            return $this->outputFormat(null, 400);
        }

        // 公司ID
        $companyId = $params['company_id'];

        // 用户ID
        $memberId = $params['member_id'];

        // 商品列表
        $product = $params['product'];

        $limitBuyLogic = new LimitBuyLogic();

        // 获取商品限制
        $limitBuyInfo = $limitBuyLogic->getGoodsLimitBuy($product, $companyId, $memberId);

        $this->setErrorMsg('请求成功');
        return $this->outputFormat($limitBuyInfo);
    }

    // --------------------------------------------------------------------

    /**
     * 获取供应商品限制
     *
     */
    public function GetSupplierGoodsLimitBuy(Request $request)
    {
        $params = $this->getContentArray($request);

        // 验证请求参数
        if (empty($params['product'])) {
            $this->setErrorMsg('请求参数错误');
            return $this->outputFormat(null, 400);
        }

        // 商品列表
        $product = $params['product'];

        $limitBuyLogic = new LimitBuyLogic();

        // 获取商品限制
        $limitBuyInfo = $limitBuyLogic->GetSupplierGoodsLimitBuy($product);

        $this->setErrorMsg('请求成功');
        return $this->outputFormat($limitBuyInfo);
    }

}
