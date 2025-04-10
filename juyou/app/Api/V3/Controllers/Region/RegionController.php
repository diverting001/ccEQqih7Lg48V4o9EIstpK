<?php
/**
 * neigou_service-stock
 * @package     api
 * @author      xupeng
 * @since       Version
 * @filesource
 */

namespace App\Api\V3\Controllers\Region;

use App\Api\Common\Controllers\BaseController;
use App\Api\V3\Service\Region\Region as RegionService;
use Illuminate\Http\Request;

/**
 * 物流 Controller
 *
 * @package     api
 * @category    Controller
 * @author        xupeng
 */
class RegionController extends BaseController
{
    /**
     * @var Region
     */
    private $_regionService;

    /**
     * CompanyController constructor.
     */
    public function __construct()
    {
        $this->_regionService = new RegionService();
    }

    // --------------------------------------------------------------------

    /**
     * 获取地址列表
     *
     * @return array
     */
    public function GetChildList(Request $request)
    {
        $params = $this->getContentArray($request);

        $regionId = intval($params['region_id']);

        // 获取子级地址
        $data = $this->_regionService->getChildRegion($regionId);

        $this->setErrorMsg('获取成功');
        return $this->outputFormat($data);
    }

    // --------------------------------------------------------------------

    /**
     * 获取父节点列表
     *
     * @return array
     */
    public function GetParentList(Request $request)
    {
        $params = $this->getContentArray($request);

        $regionId = intval($params['region_id']);

        if ($regionId <= 0) {
            $this->setErrorMsg('请求参数错误');
            return $this->outputFormat(null, 400);
        }

        // 获取地所有父节点列表
        $data = $this->_regionService->getParentRegion($regionId);

        $this->setErrorMsg('获取成功');
        return $this->outputFormat($data);
    }

    // --------------------------------------------------------------------

    /**
     * 获取所有父节点列表
     *
     * @return array
     */
    public function GetParentAll(Request $request)
    {
        $params = $this->getContentArray($request);

        $regionId = intval($params['region_id']);

        if ($regionId <= 0) {
            $this->setErrorMsg('请求参数错误');
            return $this->outputFormat(null, 400);
        }

        // 获取地所有父节点列表
        $data = $this->_regionService->getParentRegionAll($regionId);

        $this->setErrorMsg('获取成功');
        return $this->outputFormat($data);
    }

}
