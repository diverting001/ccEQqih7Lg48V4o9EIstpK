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
class Obj
{
    /**
     * 获取资产对象详情
     *
     * @param   $useType    string      使用对象类型
     * @param   $useObj     string      使用对象
     * @return  array
     */
    public function getAssetObjDetail($useType, $useObj)
    {
        $where = [
            'use_type'  => $useType,
            'use_obj'   => $useObj
        ];

        $return = app('api_db')->table('server_asset_obj')->where($where)->first();

        return $return ? get_object_vars($return) : array();
    }

    // --------------------------------------------------------------------

    /**
     * 获取资产对象明细
     *
     * @param   $objId      int     对象ID
     * @return  array
     */
    public function getAssetObjItem($objId)
    {
        $return = array();

        $where = [
            'obj_id'  => $objId,
        ];

        $result = app('api_db')->table('server_asset_obj_items')->where($where)->get()->toArray();

        if (empty($result))
        {
            return $return;
        }

        foreach ($result as $v)
        {
            $v = get_object_vars($v);
            $v['item_data'] = $v['item_data'] ? json_decode($v['item_data'], true) : $v['item_data'];

            $return[] = $v;
        }

        return $return;
    }

    // --------------------------------------------------------------------

    /**
     * 添加资产对象
     *
     * @param   $useType    string  使用类型
     * @param   $useObj     string  使用对象
     * @param   $useObjData array   使用对象数据
     * @param   $memberId   int     用户ID
     * @param   $companyId  int     公司ID
     * @return  boolean
     */
    public function addAssetObj($useType, $useObj, $useObjData, $memberId = 0, $companyId = 0)
    {
        $data = array(
            'use_type'      => $useType,
            'use_obj'       => $useObj,
            'use_obj_data'  => is_array($useObjData) ? json_encode($useObjData) : $useObjData,
            'member_id'     => $memberId,
            'company_id'    => $companyId,
            'status'        => 0,
            'create_time'   => time(),
            'update_time'   => time(),
        );

        return app('api_db')->table('server_asset_obj')->insertGetId($data);
    }

    // --------------------------------------------------------------------

    /**
     * 添加资产对象明细
     *
     * @param   $objId          int     资产对象ID
     * @param   $useObjItems    array   资产对象明细
     * @return  boolean
     */
    public function addAssetObjItem($objId, $useObjItems)
    {
        if ($objId <= 0 OR empty($useObjItems))
        {
            return false;
        }

        foreach ($useObjItems as $item)
        {
            $data = array(
                'obj_id'    => $objId,
                'item_bn'   => $item['item_bn'], // BN
                'item_id'   => $item['item_id'], // ID
                'name'      => $item['name'], //
                'price'     => $item['price'],
                'quantity'  => $item['quantity'] ? $item['quantity'] : 1,
                'amount'    => $item['amount'],
                'item_data' => ! empty($item['item_data']) && is_array($item['item_data']) ? json_encode($item['item_data']) : null,
            );

            if ( ! app('api_db')->table('server_asset_obj_items')->insertGetId($data))
            {
                return false;
            }
        }

        return true;
    }

    // --------------------------------------------------------------------

    /**
     * 更新资产对象状态
     *
     * @param   $objId      int     资产ID
     * @param   $status     int     状态
     * @return  boolean
     */
    public function updateAssetObjStatus($objId, $status)
    {
        $data = array(
            'status'        => $status,
            'update_time'   => time(),
        );

        $where = array(
            'obj_id' => $objId,
        );

        return app('api_db')->table('server_asset_obj')->where($where)->update($data);
    }

}
