<?php
/**
 * neigou_service-stock
 * @package     api
 * @author      xupeng
 * @since       Version
 * @filesource
 */

namespace App\Api\V1\Service\Resource;

use App\Api\Model\Resource\Resource as ResourceModel;

/**
 * 资源 Controller
 *
 * @package     api
 * @category    Service
 * @author      xupeng
 */
class Resource
{

    /**
     * 保存分销商信息
     *
     * @param   $object         string  对象
     * @param   $objectItem     mixed   对象值
     * @param   $status         int     状态(1:锁定 2:释放 3:消耗)
     * @param   $page           int     页码
     * @param   $pageSize       int     每页数量
     * @return  array
     */
    public function getResourceList($object, $objectItem = NULL, $status = null, $page = 1, $pageSize = 20)
    {
        if (empty($object))
        {
            return array();
        }

        $resourceModel = new ResourceModel();

        $offset  = ($page - 1) * $pageSize;

        return  $resourceModel->getResourceList($object, $objectItem, $status, $pageSize, $offset);
    }

    // --------------------------------------------------------------------

    /**
     * 锁定资源
     *
     * @param       $object         string      对象
     * @param       $objectItem     string      对象值
     * @param       $resource       string      资源
     * @param       $resourceItem   string      资源项
     * @param       $resourceValue  string      资源值
     * @param       $memo           string      备注
     * @param       $errMsg         string      错误信息
     * @return      mixed
     */
    public function lockResource($object, $objectItem, $resource, $resourceItem = '', $resourceValue = '', $memo = '',  & $errMsg = '')
    {
        if (empty($object) OR empty($objectItem) OR empty($resource))
        {
            $errMsg = '参数错误';
            return false;
        }

        $resourceModel = new ResourceModel();

        // 获取资源信息
        $resourceDetail = $resourceModel->getResourceDetail($object, $objectItem, $resource, $resourceItem);

        if ( ! empty($resourceDetail))
        {
            return array('res_id' => $resourceDetail['res_id']);
        }

        // 状态(1:锁定 2:释放 3:消耗)
        $status = 1;
        $resId = $resourceModel->addResource($object, $objectItem, $resource, $resourceItem, $resourceValue, $status, $memo);

        if ($resId <= 0)
        {
            $errMsg = '锁定失败';
            return false;
        }

        // 添加记录
        $resourceModel->addResourceLog($resId, $status, $memo);

        return array('res_id' => $resId);
    }

    // --------------------------------------------------------------------

    /**
     * 释放资源
     *
     * @param       $object         string      对象
     * @param       $objectItem     string      对象值
     * @param       $resource       string      资源
     * @param       $resourceItem   string      资源项
     * @param       $memo           string      备注
     * @param       $errMsg         string      错误信息
     * @return      mixed
     */
    public function releaseResource($object, $objectItem, $resource, $resourceItem = '', $memo = '',  & $errMsg = '')
    {
        if (empty($object) OR empty($objectItem) OR empty($resource))
        {
            $errMsg = '参数错误';

            return false;
        }

        $resourceModel = new ResourceModel();

        // 获取资源信息
        $resourceDetail = $resourceModel->getResourceDetail($object, $objectItem, $resource, $resourceItem);

        if (empty($resourceDetail))
        {
            $errMsg = '资源不存在';
            return true;
        }

        $resId = $resourceDetail['res_id'];

        // 状态(1:锁定 2:释放 3:消耗)
        $status = 2;

        if ($resourceDetail['status'] == $status)
        {
            return array('res_id' => $resId);
        }

        // 更新资源状态
        if ( ! $resourceModel->updateResourceStatus($resId, $status, $memo))
        {
            $errMsg = '释放资源失败';
            return false;
        }

        // 添加记录
        $resourceModel->addResourceLog($resId, $status, $memo);

        return array('res_id' => $resId);
    }

    // --------------------------------------------------------------------

    /**
     * 消耗资源
     *
     * @param       $object         string      对象
     * @param       $objectItem     string      对象值
     * @param       $resource       string      资源
     * @param       $resourceItem   string      资源项
     * @param       $memo           string      备注
     * @param       $errMsg         string      错误信息
     * @return      mixed
     */
    public function deductResource($object, $objectItem, $resource, $resourceItem = '', $memo = '',  & $errMsg = '')
    {
        if (empty($object) OR empty($objectItem) OR empty($resource))
        {
            $errMsg = '参数错误';

            return false;
        }

        $resourceModel = new ResourceModel();

        // 获取资源信息
        $resourceDetail = $resourceModel->getResourceDetail($object, $objectItem, $resource, $resourceItem);

        if (empty($resourceDetail))
        {
            $errMsg = '资源不存在';
            return true;
        }
        $resId = $resourceDetail['res_id'];

        // 状态(1:锁定 2:释放 3:消耗)
        $status = 3;

        if ($resourceDetail['status'] != 1)
        {
            $errMsg = '资源未锁定';
            return false;
        }

        // 更新资源状态
        if ( ! $resourceModel->updateResourceStatus($resId, $status, $memo))
        {
            $errMsg = '消耗资源失败';
            return false;
        }

        // 添加记录
        $resourceModel->addResourceLog($resId, $status, $memo);

        return array('res_id' => $resId);
    }

}
