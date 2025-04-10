<?php
/**
 * neigou_service
 *
 * @package     api
 * @author      xupeng
 * @since       Version
 * @filesource
 */

namespace App\Api\Model\Asset;

/**
 * 资产 model
 *
 * @package     api
 * @category    Model
 * @author      xupeng
 */
class Asset
{
    /**
     * 添加资产
     *
     * @param   $objId  int  对象ID
     * @return  boolean
     */
    public function addAsset($objId, $data)
    {
        $data['obj_id'] = $objId;

        if ( ! isset($data['create_time']))
        {
            $data['create_time'] = time();
        }

        if ( ! isset($data['update_time']))
        {
            $data['update_time'] = time();
        }

        return app('api_db')->table('server_assets')->insertGetId($data);
    }

    // --------------------------------------------------------------------

    /**
     * 获取资产列表
     *
     * @param   $filter     array       过滤条件
     * @return  array
     */
    public function getAssetList($filter = array())
    {
        $return = array();

        if (empty($filter))
        {
            return $return;
        }

        $where = array();

        if ( ! empty($filter['obj_id']))
        {
            $where['obj_id'] = $filter['obj_id'];
        }

        if (empty($where))
        {
            return $return;
        }

        $result = app('api_db')->table('server_assets')->where($where)->get()->toArray();

        foreach ($result as $v)
        {
            $v = get_object_vars($v);
            $v['asset_data'] = $v['asset_data'] ? json_decode($v['asset_data'], true) : $v['asset_data'];

            $return[] = $v;
        }

        return $return;
    }

    // --------------------------------------------------------------------

    /**
     * 获取资产详情
     *
     * @param   $assetId    int     资产ID
     * @return  array
     */
    public function getAssetDetail($assetId)
    {
        $where = array(
            'asset_id' => $assetId,
        );

        $return = app('api_db')->table('server_assets')->where($where)->first();

        return $return ? get_object_vars($return) : array();
    }

    // --------------------------------------------------------------------

    /**
     * 更新资产状态
     *
     * @param   $assetId    int     资产ID
     * @param   $status     int     状态
     * @return  array
     */
    public function updateAssetStatus($assetId, $status)
    {
        $data = array(
            'status'        => $status,
            'update_time'   => time(),
        );

        $where = array(
            'asset_id' => $assetId,
        );

        return app('api_db')->table('server_assets')->where($where)->update($data);
    }

    // --------------------------------------------------------------------

    /**
     * 更新资产数据
     *
     * @param   $assetId    int     资产ID
     * @param   $data       array   数据
     * @return  array
     */
    public function updateAssetData($assetId, $data)
    {
        $dat['update_time'] = time();

        $where = array(
            'asset_id' => $assetId,
        );

        return app('api_db')->table('server_assets')->where($where)->update($data);
    }

    // --------------------------------------------------------------------

    /**
     * 记录资产记录
     *
     * @param   $assetId        int     资产ID
     * @param   $status         int     状态
     * @param   $beforeStatus   int     变更前状态
     * @return  boolean
     */
    public function addAssetRecord($assetId, $status, $beforeStatus)
    {
        $data = array(
            'asset_id'      => $assetId,
            'status'        => $status,
            'before_status' => $beforeStatus,
            'create_time'   => time(),
        );

        $result = app('api_db')->table('server_asset_records')->insert($data);

        return $result ? true : false;
    }

}
