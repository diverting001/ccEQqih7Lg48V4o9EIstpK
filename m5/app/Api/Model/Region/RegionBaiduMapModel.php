<?php

namespace App\Api\Model\Region;

use App\Api\Model\BaseModel;
use Illuminate\Database\MySqlConnection;

class RegionBaiduMapModel extends BaseModel
{
    /**
     * @var MySqlConnection
     */
    private $_db;
    protected $table = 'server_region_baidu_part_mapping';
    protected $primaryKey = 'id';

    /**
     * constructor.
     */
    public function __construct()
    {
        Parent::__construct();
        $this->_db = app('api_db');
    }

    public function getMapIdByRegionName($where,$limit = 1): array
    {
        if (empty($where)) {
            return array();
        }
        if (!empty($where['city'])) {
            $map['city'] = $where['city'];
        }
        if (!empty($where['district'])) {
            $map['district'] = $where['district'];
        }
        if (!isset($map)) {
            return array();
        }
        $return = array();
        $result = $this->_db->table($this->table)->whereIn('region_name', $map)
            ->select(['region_name', 'region_code', 'depth', 'mapping_region_id', 'mapping_region_name',])
            ->limit($limit)
            ->orderBy('depth', 'desc')
            ->get();
        if (empty($result)) {
            return array();
        }

        foreach ($result as $v) {
            $return[] = get_object_vars($v);
        }

        return $return;
    }
}
