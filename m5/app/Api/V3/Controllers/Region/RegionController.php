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
use Swoole\Exception;

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

    public function GetListByIds(Request $request){
        $params = $this->getContentArray($request);
        $region_ids = $params['region_ids'];
        if (empty($region_ids)) {
            $this->setErrorMsg('请求参数错误');
            return $this->outputFormat(null, 400);
        }
        $data = $this->_regionService->getListByIds($region_ids);
        $this->setErrorMsg('获取成功');
        return $this->outputFormat($data);
    }

    // --------------------------------------------------------------------

    /**
     * 获取所有子孙节点列表
     *
     * @return array
     */
    public function GetTreeList(Request $request)
    {
        $params = $this->getContentArray($request);
        $regionId = intval($params['region_id']);
        if ($regionId <= 0) {
            $this->setErrorMsg('请求参数错误');
            return $this->outputFormat(null, 400);
        }
        $data = $this->_regionService->getTreeList($regionId);
        $this->setErrorMsg('获取成功');
        return $this->outputFormat($data);
    }

    /** 获取传入的地址id
     *
     *  使用方法:
     *  传入参数: 省/市/区/乡镇 其中任意一至四级
     *  参数示例: {"province":"北京","city":"北京市","county":"海淀区","town":"万寿路街道"} 或 {"province":"北京","city":"北京市"}
     *  返回示例: {"province":{"name":"北京","region_id":1},"city":{"name":"北京市","region_id":2},"county":{"name":"海淀区","region_id":10},"town":{"name":"万寿路街道","region_id":57827}}
     *
     * @param Request $request
     * @return array
     * @author liuming
     */
    public function GetRegionIdList(Request $request){
        $params = $this->getContentArray($request);
        $regions  = array_filter($params);
        if (empty($regions)){
            $this->setErrorMsg('请求参数不能为空');
            return $this->outputFormat(null, 4000);
        }

        if (empty($regions['province'])){
            $this->setErrorMsg('province字段不能为空');
            return $this->outputFormat(null, 4000);
        }

        $searchRegionsCount = count($regions);
        $provinceRegionInfo = $this->_regionService->getRegionsRow(array('local_name' => $regions['province'],'region_grade' => 1));

        // 返回参数,默认有一级地址
        $returnList = array(
            'province' => array(
                'name' => $regions['province'],
                'region_id' => $provinceRegionInfo['region_id']
            ),
        );

        // 获取二级地址信息
        if ($searchRegionsCount >= 2){
            $cityRegionInfo = $this->_regionService->getRegionsRow(array('local_name' => $regions['city'],'region_grade' => 2,'p_region_id' =>$provinceRegionInfo['region_id']));
            $returnList['city'] = array(
                'name' => $regions['city'],
                'region_id' => $cityRegionInfo['region_id']
            );
        }

        // 获取三级地址信息
        if ($searchRegionsCount >= 3 && $cityRegionInfo){
            $countyRegionInfo = $this->_regionService->getRegionsRow(array('local_name' => $regions['county'],'region_grade' => 3,'p_region_id' => $cityRegionInfo['region_id']));
            if ($countyRegionInfo){
                $returnList['county'] = array(
                    'name' => $regions['county'],
                    'region_id' => $countyRegionInfo['region_id']
                );
            }
        }

        // 获取四级地址信息
        if ($searchRegionsCount >= 4 && $countyRegionInfo){
            $townRegionInfo = $this->_regionService->getRegionsRow(array('local_name' => $regions['town'],'region_grade' => 4,'p_region_id' => $countyRegionInfo['region_id']));
            if ($townRegionInfo){
                $returnList['town'] = array(
                    'name' => $regions['town'],
                    'region_id' => $townRegionInfo['region_id']
                );
            }
        }
        return $this->outputFormat($returnList);
    }

    /**
     * @param  Request  $request
     * @return array
     */
    public function getRegionsRowByName(Request $request)
    {
        $params = $this->getContentArray($request);
        $local_name = $params['local_name'];
        if (empty($local_name)) {
            $this->setErrorMsg('请求参数错误');
            return $this->outputFormat(null, 400);
        }
        $data = $this->_regionService->getRegionsRow(['local_name' => $local_name]);
        $this->setErrorMsg('获取成功');
        return $this->outputFormat($data);
    }

    /**
     * @param Request $request
     * @return array
     */
    public function GetRegionByAddr(Request $request)
    {
        $params = $this->getContentArray($request);
        $addr = $params['addr'];
        if (empty($addr)) {
            $this->setErrorMsg('请求参数错误');
            return $this->outputFormat(null, 400);
        }

        $data = $this->_regionService->GetRegionByAddr($addr);
        $this->setErrorMsg('获取成功');
        return $this->outputFormat($data);
    }
}
