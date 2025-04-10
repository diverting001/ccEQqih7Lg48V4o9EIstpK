<?php
/**
 * neigou_service-stock
 * @package     api
 * @author      xupeng
 * @since       Version
 * @filesource
 */

namespace App\Api\V3\Controllers\Express;


use App\Api\Common\Controllers\BaseController;
use App\Api\V3\Service\Express\Express as ExpressService;
use Illuminate\Http\Request;


/**
 * 物流 Controller
 *
 * @package     api
 * @category    Controller
 * @author        xupeng
 */
class ExpressController extends BaseController
{
    /**
     * @var Express
     */
    private $_expressService;

    /**
     * CompanyController constructor.
     */
    public function __construct()
    {
        $this->_expressService = new ExpressService();
    }

    /**
     * 获取物流轨迹信息
     *
     * @return array
     */
    public function GetExpressInfo(Request $request)
    {
        $params = $this->getContentArray($request);

        $express_mobile = isset($params['express_mobile']) ? addslashes($params['express_mobile']) : '';

        // 验证请求参数
        if (empty($params['express_com']) OR empty($params['express_no'])) {
            $this->setErrorMsg('请求参数错误');
            return $this->outputFormat(null, 400);
        }

        $result = $this->_expressService->getExpressDetail($params['express_com'], $params['express_no'], $express_mobile);

        $this->setErrorMsg('请求成功');
        return $this->outputFormat($result);
    }

    // --------------------------------------------------------------------

    /**
     * 注册物流轨迹信息
     *
     * @return array
     */
    public function RegisterExpress(Request $request)
    {
        $params = $this->getContentArray($request);

        // 验证请求参数
        if (empty($params['express_com']) OR empty($params['express_no'])) {
            $this->setErrorMsg('请求参数错误');
            return $this->outputFormat(null, 400);
        }

        $isKuaidi100 = $params['is_kuaid100'] ? true : false;
        if (!$this->_expressService->saveExpressDetail($params['express_com'], $params['express_no'], $isKuaidi100)) {
            $this->setErrorMsg('添加物流信息失败');
            return $this->outputFormat(null, 401);
        }

        $this->setErrorMsg('注册物流成功');
        return $this->outputFormat(array());
    }

    // --------------------------------------------------------------------

    /**
     * 保存物流轨迹信息
     *
     * @return array
     */
    public function SaveExpress(Request $request)
    {
        $params = $this->getContentArray($request);

        // 验证请求参数
        if (empty($params['express_com']) OR empty($params['express_no']) OR empty($params['content'])) {
            $this->setErrorMsg('请求参数错误');
            return $this->outputFormat(null, 400);
        }

        if (!$this->_expressService->saveExpressDetail($params['express_com'], $params['express_no'], null,
            $params['status'], $params['content'])) {
            $this->setErrorMsg('添加物流信息失败');
            return $this->outputFormat(null, 401);
        }

        $this->setErrorMsg('更新物流成功');
        return $this->outputFormat(array());
    }

    // --------------------------------------------------------------------

    /**
     * 获取物流公司列表
     *
     * @return array
     */
    public function GetExpressCompanyList()
    {
        $companyList = $this->_expressService->getExpressCompanyList();

        $this->setErrorMsg('获取成功');
        return $this->outputFormat($companyList);
    }

    // --------------------------------------------------------------------

    /**
     * 获取物流轨迹信息
     *
     * @return array
     */
    public function GetExpressInfoByCode(Request $request)
    {
        $params = $this->getContentArray($request);

        // 验证请求参数
        if (empty($params['express_com']) OR empty($params['express_no'])) {
            $this->setErrorMsg('请求参数错误');
            return $this->outputFormat(null, 400);
        }

        $result = $this->_expressService->getExpressDetailByCode($params['express_com'], $params['express_no']);

        $this->setErrorMsg('请求成功');
        return $this->outputFormat($result);
    }

    /**
     * @param  Request  $request
     * @return array
     */
    public function GetAutonumber(Request $request)
    {
        $params = $this->getContentArray($request);

        // 验证请求参数
        if (empty($params['express_no'])) {
            $this->setErrorMsg('请求参数错误');

            return $this->outputFormat(null, 400);
        }

        $data = $this->_expressService->getAutonumber($params['express_no']);

        $this->setErrorMsg('获取成功');

        return $this->outputFormat($data);
    }

}
