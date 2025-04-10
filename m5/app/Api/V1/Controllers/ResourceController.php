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
use App\Api\V1\Service\Resource\Resource as ResourceSerivce;
use Illuminate\Http\Request;

/**
 * 资源 Controller
 *
 * @package     api
 * @category    Controller
 * @author      xupeng
 */
class ResourceController extends BaseController
{
    /**
     * 保存分销商信息
     *
     * @return array
     */
    public function getResourceList(Request $request)
    {
        $params = $this->getContentArray($request);

        // 验证请求参数
        if (empty($params['object']))
        {
            $this->setErrorMsg('请求参数错误');

            return $this->outputFormat(null, 400);
        }

        // 对象
        $object = $params['object'];

        // 对象值
        $objectItem = isset($params['object_item']) ? $params['object_item'] : null;

        // 状态
        $status = isset($params['status']) ? $params['status'] : null;

        // 页码
        $page = isset($params['page']) ? $params['page'] : 1;

        // 每页数量
        $pageSize = isset($params['page_size']) ? $params['page_size'] : 20;

        $filter = array();

        if (isset($params['status']))
        {
            $filter['status'] = $params['status'];
        }

        if (isset($params['gt_id']))
        {
            $filter['gt_id'] = $params['gt_id'];
        }

        if (isset($params['system_code']))
        {
            $filter['system_code'] = $params['system_code'];
        }

        $resourceService = new ResourceSerivce();

        // 保存分销商信息
        $resourceList = $resourceService->getResourceList($object, $objectItem, $page, $pageSize, $filter);

        $this->setErrorMsg('请求成功');
        return $this->outputFormat($resourceList);
    }

    // --------------------------------------------------------------------

    /**
     * 锁定资源
     *
     */
    public function lock(Request $request)
    {
        $params = $this->getContentArray($request);

        // 对象
        $object = $params['object'];

        // 对象值
        $objectItem = $params['object_item'];

        // 资源
        $resource = $params['resource'];

        // 资源项
        $resourceItem = $params['resource_item'] ? $params['resource_item'] : '';

        // 资源值
        $resourceValue = $params['resource_value'] ? $params['resource_value'] : '';

        // 备注
        $memo = $params['memo'] ? $params['memo'] : '';

        // 系统
        $systemCode = $params['system_code'] ? $params['system_code'] : 'neigou';


        if (empty($object) OR empty($objectItem) OR empty($resource))
        {
            $this->setErrorMsg('请求参数错误');
            return $this->outputFormat(null, 404);
        }

        $resourceService = new ResourceSerivce();

        $errMsg = '';
        $result = $resourceService->lockResource($object, $objectItem, $resource, $resourceItem, $resourceValue, $memo, $systemCode,  $errMsg);

        if (empty($result)) {
            $errMsg OR $errMsg = '锁定资源失败';
            $this->setErrorMsg($errMsg);
            return $this->outputFormat(null, 404);
        }

        $this->setErrorMsg('请求成功');
        return $this->outputFormat($result);
    }

    // --------------------------------------------------------------------

    /**
     * 释放资源
     *
     */
    public function release(Request $request)
    {
        $params = $this->getContentArray($request);

        // 对象
        $object = $params['object'];

        // 对象值
        $objectItem = $params['object_item'];

        // 资源
        $resource = $params['resource'];

        // 资源项
        $resourceItem = $params['resource_item'] ? $params['resource_item'] : '';

        // 备注
        $memo = $params['memo'] ? $params['memo'] : '';

        if (empty($object) OR empty($objectItem) OR empty($resource))
        {
            $this->setErrorMsg('请求参数错误');
            return $this->outputFormat(null, 404);
        }

        $resourceService = new ResourceSerivce();

        $errMsg = '';
        $result = $resourceService->releaseResource($object, $objectItem, $resource, $resourceItem, $memo, $errMsg);

        if ( ! $result)
        {
            $errMsg OR $errMsg = '释放资源失败';
            $this->setErrorMsg($errMsg);

            return $this->outputFormat(null, 404);
        }

        $this->setErrorMsg('请求成功');
        return $this->outputFormat($result);
    }

    // --------------------------------------------------------------------

    /**
     * 消耗资源
     *
     */
    public function deduct(Request $request)
    {
        $params = $this->getContentArray($request);

        // 对象
        $object = $params['object'];

        // 对象值
        $objectItem = $params['object_item'];

        // 资源
        $resource = $params['resource'];

        // 资源项
        $resourceItem = $params['resource_item'] ? $params['resource_item'] : '';

        // 备注
        $memo = $params['memo'] ? $params['memo'] : '';

        if (empty($object) OR empty($objectItem) OR empty($resource))
        {
            $this->setErrorMsg('请求参数错误');
            return $this->outputFormat(null, 404);
        }

        $resourceService = new ResourceSerivce();

        $errMsg = '';
        $result = $resourceService->deductResource($object, $objectItem, $resource, $resourceItem, $memo, $errMsg);

        if ( ! $result)
        {
            $errMsg OR $errMsg = '消耗资源失败';
            $this->setErrorMsg($errMsg);

            return $this->outputFormat(null, 404);
        }

        $this->setErrorMsg('请求成功');
        return $this->outputFormat($result);
    }

}
