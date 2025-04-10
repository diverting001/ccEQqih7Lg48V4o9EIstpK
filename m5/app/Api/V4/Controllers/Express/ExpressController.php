<?php


namespace App\Api\V4\Controllers\Express;


use App\Api\Common\Controllers\BaseController;
use App\Api\V4\Service\Express\Express as ExpressService;
use App\Api\V3\Service\Express\Express as ExpressServiceV3;
use Illuminate\Http\Request;



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
     * 获取物流轨迹
     * @param Request $request
     * @return array
     */
    public function GetExpressInfo(Request $request)
    {
        $params = $this->getContentArray($request);

        // 验证请求参数
        if (empty($params['express_com']) OR empty($params['express_no'])) {
            $this->setErrorMsg('请求参数错误');
            return $this->outputFormat(null, 400);
        }

        $express_mobile = isset($params['express_mobile']) ? addslashes($params['express_mobile']) : '';

        $result = $this->_expressService->getExpressDetail($params['express_com'], $params['express_no'], $express_mobile);

        $this->setErrorMsg('请求成功');
        return $this->outputFormat($result);
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

        $result = $this->_expressService->getExpressDetail($params['express_com'], $params['express_no']);

        $this->setErrorMsg('请求成功');
        return $this->outputFormat($result);
    }

    // --------------------------------------------------------------------

    /**
     * 获取物流公司列表
     *
     * @return array
     */
    public function GetExpressCompanyList()
    {
        $expressService = new ExpressServiceV3();
        $companyList = $expressService->getExpressCompanyList();

        $this->setErrorMsg('获取成功');
        return $this->outputFormat($companyList);
    }

    // --------------------------------------------------------------------

    /**
     * 获取物流公司列表
     *
     * @return array
     */
    public function GetExpressChannelCompanyList(Request $request)
    {
        $params = $this->getContentArray($request);
        if (empty($params['channel'])) {
            $this->setErrorMsg('请求参数错误');
            return $this->outputFormat(null, 400);
        }
        $channel = $params['channel'] ? : '';
        $filter =  $params['filter'] ? : '';
        $expressService = new ExpressService();
        $companyList = $expressService->getChannelCompanyList($channel, $filter);

        $this->setErrorMsg('获取成功');
        return $this->outputFormat($companyList);
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
        if (empty($params['express_com']) OR empty($params['express_no'])) {
            $this->setErrorMsg('请求参数错误');
            return $this->outputFormat(null, 400);
        }

        $expressData = array(
            //物流公司
            'express_com'=>$params['express_com'],
            //物流单号
            'express_no'=>$params['express_no'],
            //是否指定外部渠道
            'is_external_channel'=>$params['is_external_channel'],
            //手机号
            'express_mobile'=>$params['express_mobile'],
            //状态
            'status'=>$params['status'],
            //内容
            'express_data'=>$params['express_data']
        );

        $errMsg = '';
        if (!$this->_expressService->saveExpress($expressData, $errMsg)) {
            $this->setErrorMsg($errMsg);
            return $this->outputFormat(null, 401);
        }

        $this->setErrorMsg('保存物流成功');
        return $this->outputFormat(array());
    }

    /**
     * @param Request $request
     * @return array
     */
    public function ExpressCallback(Request $request) {
        $params = $this->getContentArray($request);

        // 验证请求参数
        if (empty($params['callback_data'])) {
            $this->setErrorMsg('请求参数错误');
            return $this->outputFormat(null, 400);
        }

        $errMsg = '';
        if (!$this->_expressService->expressCallback($params['callback_data'], $errMsg)) {
            $this->setErrorMsg($errMsg);
            return $this->outputFormat(null, 401);
        }

        $this->setErrorMsg('物流回调成功');
        return $this->outputFormat(array());
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
