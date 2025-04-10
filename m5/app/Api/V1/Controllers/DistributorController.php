<?php
/**
 * neigou_service-stock
 * @package     api
 * @author      xupeng
 * @since       Version
 * @filesource
 */

namespace App\Api\V1\Controllers;

use App\Api\Common\Controllers\BaseController;
use App\Api\V1\Service\Distribution\Distributor as DistributorLogic;
use App\Api\Model\Distribution\Distributor as DistributorModel;
use Illuminate\Http\Request;

/**
 * 分销商 Controller
 *
 * @package     api
 * @category    Controller
 * @author        xupeng
 */
class DistributorController extends BaseController
{
    /**
     * 保存分销商信息
     *
     * @return array
     */
    public function saveDistributorInfo(Request $request)
    {
        $params = $this->getContentArray($request);

        // 验证请求参数
        if (empty($params['dt_bn']) OR empty($params['dt_name'])) {
            $this->setErrorMsg('请求参数错误');
            return $this->outputFormat(null, 400);
        }

        // 分销商标识
        $dtBn = $params['dt_bn'];

        // 分销商名称
        $dtName = $params['dt_name'];

        // 分销商状态
        $status = isset($params['status']) ? $params['status'] : null;

        // 分销商备注
        $memo = isset($params['memo']) ? $params['memo'] : null;

        $errMsg = '';
        $distributorLogic = new DistributorLogic();

        // 保存分销商信息
        $distributorInfo = $distributorLogic->saveDistributorInfo($dtBn, $dtName, $status, $memo, $errMsg);
        if (empty($distributorInfo)) {
            $errMsg OR $errMsg = '保存分销商信息失败';
            $this->setErrorMsg($errMsg);
            return $this->outputFormat(null, 404);
        }

        $this->setErrorMsg('请求成功');
        return $this->outputFormat($distributorInfo);
    }

    // --------------------------------------------------------------------

    /**
     * 获取分销商商品范围
     *
     */
    public function getGoodsScope(Request $request)
    {
        $params = $this->getContentArray($request);

        // 验证请求参数
        if (empty($params['dt_bn'])) {
            $this->setErrorMsg('请求参数错误');
            return $this->outputFormat(null, 400);
        }

        $dtModel = new DistributorModel();

        // 获取分销商商品范围
        $dtGoodsScope = $dtModel->getDistributorGoodsScope($params['dt_bn']);

        $this->setErrorMsg('请求成功');
        return $this->outputFormat($dtGoodsScope);
    }

    // --------------------------------------------------------------------

    /**
     * 获取所有分销商商品范围
     *
     */
    public function getAllGoodsScope()
    {
        $dtModel = new DistributorModel();

        // 获取分销商商品范围
        $allDtGoodsScope = $dtModel->getAllDistributorGoodsScope();

        $this->setErrorMsg('请求成功');
        return $this->outputFormat($allDtGoodsScope);
    }
    // --------------------------------------------------------------------

    /**
     * 获取分销商列表
     *
     */
    public function getDistributorList(Request $request)
    {
        $params = $this->getContentArray($request);
        $dtModel = new DistributorModel();
        $status = $params['status'] === 0 ? 0 : 1;

        // 获取分销商商品范围
        $allDtGoodsScope = $dtModel->getDistributorList($status);

        $this->setErrorMsg('请求成功');
        return $this->outputFormat($allDtGoodsScope);
    }
    // --------------------------------------------------------------------

    /**
     * 检查资金池
     *
     */
    public function checkDeductMoneyPool(Request $request)
    {
        $params = $this->getContentArray($request);

        // 验证请求参数
        if (empty($params['dt_bn']) OR empty($params['money']) OR $params['money'] <= 0) {
            $this->setErrorMsg('请求参数错误');
            return $this->outputFormat(null, 400);
        }

        $money = number_format($params['money'], 3, '.', '');

        $dtModel = new DistributorModel();

        $errMsg = '';
        // 检查资金池额度
        $dtMoneyPool = $dtModel->checkDeductMoneyPool($params['dt_bn'], $money, $errMsg);
        if (empty($dtMoneyPool)) {
            $errMsg OR $errMsg = '额度不足';
            $this->setErrorMsg($errMsg);
            return $this->outputFormat(null, 404);
        }

        $this->setErrorMsg('请求成功');
        return $this->outputFormat($dtMoneyPool);
    }

    // --------------------------------------------------------------------

    /**
     * 扣除资金池
     *
     */
    public function deductMoneyPool(Request $request)
    {
        $params = $this->getContentArray($request);

        // 验证请求参数
        if (empty($params['dt_bn']) OR empty($params['money']) OR $params['money'] <= 0 OR empty($params['out_trade_no'])) {
            $this->setErrorMsg('请求参数错误');
            return $this->outputFormat(null, 400);
        }

        $money = number_format($params['money'], 3, '.', '');

        $dtLogic = new DistributorLogic();

        // 扣除资金池额度
        $errMsg = '';
        $result = $dtLogic->deductMoneyPool($params['dt_bn'], $money, $params['out_trade_no'], $errMsg);

        if (empty($result)) {
            $errMsg OR $errMsg = '扣除失败';
            $this->setErrorMsg($errMsg);
            return $this->outputFormat(null, 404);
        }

        $this->setErrorMsg('请求成功');
        return $this->outputFormat($result);
    }

    // --------------------------------------------------------------------

    /**
     * 退还扣除资金池
     *
     */
    public function refundMoneyPool(Request $request)
    {
        $params = $this->getContentArray($request);

        // 验证请求参数
        if (empty($params['money']) OR $params['money'] <= 0 OR empty($params['out_trade_no']) OR empty($params['origin_out_trade_no'])) {
            $this->setErrorMsg('请求参数错误');
            return $this->outputFormat(null, 400);
        }

        $money = number_format($params['money'], 3, '.', '');

        $dtLogic = new DistributorLogic();

        // 退还资金池额度
        $errMsg = '';
        $result = $dtLogic->refundMoneyPool($money, $params['out_trade_no'], $params['origin_out_trade_no'], $errMsg);

        if (empty($result)) {
            $errMsg OR $errMsg = '退还失败';
            $this->setErrorMsg($errMsg);
            return $this->outputFormat(null, 404);
        }

        $this->setErrorMsg('请求成功');
        return $this->outputFormat($result);
    }

    // --------------------------------------------------------------------

    /**
     * 获取消息范围
     *
     */
    public function getMessageScope(Request $request)
    {
        $params = $this->getContentArray($request);

        // 验证请求参数
        $filter = array();
        if ($params['dt_bn']) {
            $filter['dt_bn'] = $params['dt_bn'];
        }
        if (isset($params['status'])) {
            $filter['status'] = $params['status'];
        }
        if ($params['scope']) {
            $filter['scope'] = $params['scope'];
        }
        $dtModel = new DistributorModel();
        // 获取分销商商品范围
        $dtGoodsScope = $dtModel->getDistributosMessageScope($filter);

        $this->setErrorMsg('请求成功');
        return $this->outputFormat($dtGoodsScope);
    }
    
}
