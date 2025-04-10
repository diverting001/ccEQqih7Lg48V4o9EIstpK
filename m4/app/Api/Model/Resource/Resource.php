<?php
/**
 * neigou_service-stock
 *
 * @package     api
 * @author      xupeng
 * @since       Version
 * @filesource
 */

namespace App\Api\Model\Resource;

/**
 * 资源 model
 *
 * @package     api
 * @category    Model
 * @author      xupeng
 */
class Resource
{
    /**
     * @param       $object         string      对象
     * @param       $objectItem     mixed       对象值
     * @param       $status         mixed       状态
     * @param       $limit          int         数量
     * @param       $offset         int         起始位置
     * @return      array
     */
    public function getResourceList($object, $objectItem = NULL, $status = null, $limit = 20, $offset = 0)
    {
        $return = array();

        if (empty($object))
        {
            return $return;
        }

        $where = [
            'object' => $object,
        ];

        if ($objectItem)
        {
            $where['object_item'] = $objectItem;
        }

        if ($status)
        {
            $where['status'] = $status;
        }

        $result = app('api_db')->table('server_resources')->where($where)->limit($limit)->offset($offset)->get();

        if (empty($result))
        {
            return $return;
        }

        foreach ($result as $v)
        {
            $return[] = get_object_vars($v);
        }

        return $return;
    }

    // --------------------------------------------------------------------

    /**
     * @param       $object         string      对象
     * @param       $objectItem     string      对象值
     * @param       $resource       string      资源
     * @param       $resourceItem   string      资源项
     * @return array
     */
    public function getResourceDetail($object, $objectItem, $resource, $resourceItem = '')
    {
        if (empty($object) OR empty($objectItem) OR empty($resource))
        {
            return array();
        }

        $where = [
            'object' => $object,
            'object_item' => $objectItem,
            'resource' => $resource,
        ];

        if ($resourceItem)
        {
            $where['resource_item'] = $resourceItem;
        }

        $result = app('api_db')->table('server_resources')->where($where)->first();

        return $result ? get_object_vars($result) : array();
    }

    // --------------------------------------------------------------------

    /**
     * 添加资源记录
     *
     * @param       $object         string      对象
     * @param       $objectItem     string      对象值
     * @param       $resource       string      资源
     * @param       $resourceItem   string      资源项
     * @param       $resourceValue  string      资源值
     * @param       $status         int         状态(1:锁定 2:释放 3:消耗)
     * @param       $memo           string      备注
     * @return      boolean
     */
    public function addResource($object, $objectItem, $resource, $resourceItem = '', $resourceValue = '', $status = 1, $memo = '')
    {
        if (empty($object) OR empty($objectItem) OR empty($resource))
        {
            return false;
        }

        $addData = array(
            'object'            => $object,
            'object_item'       => $objectItem,
            'resource'          => $resource,
            'resource_item'     => $resourceItem,
            'resource_value'    => $resourceValue,
            'status'            => intval($status),
            'memo'              => $memo ? $memo : '',
            'create_time'       => time(),
            'update_time'       => time(),
        );

        return app('api_db')->table('server_resources')->insertGetId($addData);
    }

    // --------------------------------------------------------------------

    /**
     * 更新资源状态
     *
     * @param   $resId      string      资源ID
     * @param   $status     int         状态
     * @param   $memo       string      备注
     * @return  boolean
     */
    public function updateResourceStatus($resId, $status, $memo = '')
    {
        if ($resId <= 0 OR $status <= 0)
        {
            return false;
        }

        $updateData = array(
            'status'        => intval($status),
            'memo'          => $memo,
            'update_time'   => time(),
        );

        $where = [
            'res_id' => $resId,
        ];

        return app('api_db')->table('server_resources')->where($where)->update($updateData);
    }

    // --------------------------------------------------------------------

    /**
     * 添加日志操作状态
     *
     * @param   $resId      string      资源ID
     * @param   $status     int         状态
     * @param   $memo       string      备注
     * @return  boolean
     */
    public function addResourceLog($resId, $status, $memo = '')
    {
        if ($resId <= 0 OR $status <= 0)
        {
            return false;
        }

        $addData = array(
            'res_id'        => $resId,
            'status'        => intval($status),
            'memo'          => $memo,
            'create_time'   => time(),
        );

        return app('api_db')->table('server_resource_log')->insert($addData);
    }

}
