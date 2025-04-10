<?php

namespace App\Api\Model\Region;

use App\Api\Model\BaseModel;

class RegionGpsModel extends BaseModel
{
    private $_db;
    protected $table = 'sdb_ectools_regions_gps';
    protected $primaryKey = 'id';

    /**
     * constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->_db = app('api_db')->connection('neigou_store');
    }

    public function getRegionsGpsList($where, $whereIn)
    {
        $model = $this->_db->table($this->table)
            ->where($where);
        if (!empty($whereIn)) {
            foreach ($whereIn as $field => $value) {
                $model = $model->whereIn($field, $value);
            }
        }
        $result = $model
            ->get(['id', 'region_id', 'region_id_2', 'region_id_3', 'region_id_4'])
            ->map(function ($value) {
                return (array)$value;
            })
            ->toArray();
        return $result;
    }

    public function addRegionsGps($data)
    {
        return $this->_db->table($this->table)->insert($data);
    }

    public function updateRegionsGps($where, $whereIn = [], $data = [])
    {
        if (!$where && !$whereIn) {
            return false;
        }
        if (!$data) {
            return false;
        }
        $model = $this->_db->table($this->table);
        if ($where) {
            $model->where($where);
        }
        if (!empty($whereIn)) {
            foreach ($whereIn as $field => $value) {
                $model = $model->whereIn($field, $value);
            }
        }
        return $model->update($data);
    }
}
