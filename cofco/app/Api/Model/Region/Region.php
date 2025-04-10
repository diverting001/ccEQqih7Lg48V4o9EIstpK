<?php
/**
 * neigou_service-stock
 * @package     api
 * @author      xupeng
 * @since       Version
 * @filesource
 */

namespace App\Api\Model\Region;

/**
 * 地址 model
 *
 * @package     api
 * @category    Model
 * @author        xupeng
 */
class Region
{
    /**
     * @var Connection
     */
    private $_db;

    /**
     * constructor.
     */
    public function __construct()
    {
        $this->_db = app('api_db')->connection('neigou_store');
    }

    /**
     * 获取物流详情
     *
     * @param   $regionId   mixed     上级地址
     * @return  array
     */
    public function getChildRegion($regionId = null)
    {
        $return = array();

        $where = [
            'p_region_id' => $regionId,
        ];

        $result = $this->_db->table('sdb_ectools_regions')->where($where)->orderBy('ordernum',
            'asc')->orderBy('region_id', 'asc')->get();

        if (empty($result)) {
            return $return;
        }

        foreach ($result as $v) {
            $return[] = get_object_vars($v);
        }

        return $return;
    }

    // --------------------------------------------------------------------

    /**
     * 获取地址详情
     *
     * @param   $regionId   mixed     上级地址
     * @return  array
     */
    public function getRegionInfo($regionId = null)
    {
        $return = array();

        if (empty($regionId)) {
            return $return;
        }

        if (!is_array($regionId)) {
            $result = $this->_db->table('sdb_ectools_regions')->where(array('region_id' => $regionId))->get();
        } else {
            $result = $this->_db->table('sdb_ectools_regions')->whereIn('region_id', $regionId)->get();
        }

        if (empty($result)) {
            return $return;
        }

        foreach ($result as $v) {
            $return[] = get_object_vars($v);
        }

        return is_array($regionId) ? $return : current($return);
    }

}
