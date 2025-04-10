<?php
/**
 * neigou_service-stock
 * @package     api
 * @author      xupeng
 * @since       Version
 * @filesource
 */

namespace App\Api\Model\Region;
use App\Api\Model\BaseModel ;


/**
 * 地址 model
 *
 * @package     api
 * @category    Model
 * @author        xupeng
 */
class Region extends BaseModel
{
    /**
     * @var Connection
     */
    private $_db;
    protected $table = 'sdb_ectools_regions' ;
    protected $primaryKey = 'region_id' ;

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

    public function getChildRegionIds($regionIds = null)
    {
        $return = array();

        $result = $this->_db->table('sdb_ectools_regions')
            ->whereIn('p_region_id', $regionIds)
            ->orderBy('ordernum', 'asc')
            ->orderBy('region_id', 'asc')
            ->get();


        if (empty($result)) {
            return $return;
        }

        foreach ($result as $v) {
            $return[$v->p_region_id] = get_object_vars($v);
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
            $return[$v->region_id] = get_object_vars($v);
        }

        return is_array($regionId) ? $return : current($return);
    }

    public function getListByIds($region_ids)
    {
        $result = $this->_db->table('sdb_ectools_regions')
            ->whereIn('region_id', $region_ids)
            ->orderBy('region_grade', 'asc')
            ->get(['region_id', 'local_name','p_region_id','region_path','region_grade'])
            ->map(function ($value) {
                return (array)$value;
            })
            ->toArray();
        return $result;
    }

    public function getListByNames($region_names)
    {
        $result = $this->_db->table('sdb_ectools_regions')
            ->whereIn('local_name', $region_names)
            ->orderBy('region_grade', 'asc')
            ->get(['region_id', 'local_name','p_region_id','region_path','region_grade'])
            ->map(function ($value) {
                return (array)$value;
            })
            ->toArray();
        return $result;
    }

    public function getTreeList($region_id)
    {
        $result = $this->_db->table('sdb_ectools_regions')
            ->where('region_path', 'like', "%,$region_id,%")
            ->orderBy('region_grade', 'asc')
            ->get(['region_id', 'local_name','p_region_id','region_path','region_grade'])
            ->map(function ($value) {
                return (array)$value;
            })
            ->toArray();
        return $result;
    }

    /** 获取地址库某一行数据 , 传入参数为where 查询的数组
     *
     * @param array $where
     * @return array
     * @author liuming
     */
    public function getRegionsRow($where = array())
    {
        $return = $this->_db->table('sdb_ectools_regions')->where($where)->first();
        return $return ? get_object_vars($return) : array();

    }

    // 检查区域是否合规
    public function is_correct_leaf_region($area , &$msg) {
        $is_valid_addr = false;
        $regions = explode(':', $area);
        if(count($regions) != 3) {
            $msg = '检查收货地址信息，ship_area 地址异常, 拆分后不是3组, area = '. $area;
            return $is_valid_addr ;
        }
        $region_info = $this->getRegionsRow(array('region_id' =>$regions[2])) ;
        if(empty($region_info)) {
            $msg = '检查收货地址信息，根据第四级region_id，查询数据库地址信息失败，region_id=' . $regions[2] . ' ,area = ' . $area;
           return $is_valid_addr ;
        }
        $child_addr =   $this->getCount(array('p_region_id'=>$regions[2])) ;
        if(!empty($child_addr)) {
            $msg = '检查收货地址信息，传入的region_id还有子地址，不是第四级地址，异常p_region_id=' . $regions[2] . ' ,area = ' . $area;
            return $is_valid_addr ;
        }
        $region_id_tree = explode(",", $region_info['region_path']);
        array_shift($region_id_tree);
        array_pop($region_id_tree);
        $regions_name_list = explode('/', $regions[1]);
        $is_valid_addr = true;
        foreach ($region_id_tree as $key=>$region_id_item) {
            $region_info = $this->getRegionsRow(array('region_id'=>$region_id_item));
            if ($region_info['local_name']!=$regions_name_list[$key]) {
                $msg = '检查收货地址信息，地址名字不一致 数据库地址名：' . $region_info['local_name'] . ' != 订单传入地址名：' . ($regions_name_list[$key] ?? '无') . ' ,area = ' . $area;
                $is_valid_addr = false;
                break;
            }
        }
        return $is_valid_addr;
    }

    /**
     * 获取列表数据
     * @param $where
     * @param $whereIn
     * @return array
     */
    public function getRegionsList($where, $whereIn = [])
    {
        $model = $this->_db->table('sdb_ectools_regions')
            ->where($where);
        if (!empty($whereIn)) {
            foreach ($whereIn as $field => $value) {
                $model = $model->whereIn($field, $value);
            }
        }
        $result = $model->orderBy('region_grade', 'asc')
            ->get(['region_id', 'local_name', 'p_region_id', 'region_path', 'region_grade'])
            ->map(function ($value) {
                return (array)$value;
            })
            ->toArray();
        return $result;
    }

    /**
     * 基于group by 查询指定条件下的唯一数据
     * @param $where
     * @param $whereIn
     * @return mixed
     */
    public function getGroupFirstRegions($where, $whereIn)
    {
        $model = $this->_db->table('sdb_ectools_regions')
            ->where($where);
        if (!empty($whereIn)) {
            foreach ($whereIn as $field => $value) {
                $model = $model->whereIn($field, $value);
            }
        }
        $result = $model->orderBy('region_id')
            ->groupBy('p_region_id')
            ->get(['region_id', 'local_name', 'p_region_id', 'region_path', 'region_grade'])
            ->map(function ($value) {
                return (array)$value;
            })
            ->toArray();
        return $result;
    }
}
