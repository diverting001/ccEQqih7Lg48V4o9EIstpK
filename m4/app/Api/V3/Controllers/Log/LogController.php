<?php
/**
 * neigou_service-stock
 * @package     api
 * @author      xupeng
 * @since       Version
 * @filesource
 */

namespace App\Api\V3\Controllers\Log;

use App\Api\Common\Controllers\BaseController;
use App\Api\V3\Service\Log\Log as LogService;

/**
 * 日志 Controller
 *
 * @package     api
 * @category    Controller
 * @author        xupeng
 */
class LogController extends BaseController
{
    /**
     * @var     $_logService    object  日志
     */
    private $_logService;

    /**
     * CompanyController constructor.
     */
    public function __construct()
    {
        $this->_logService = new LogService();
    }

    /**
     * 获取物流轨迹信息
     *
     * @return array
     */
    public function GetActionList()
    {
        $result = $this->_logService->getActionList();

        $this->setErrorMsg('请求成功');
        return $this->outputFormat($result);
    }

}
