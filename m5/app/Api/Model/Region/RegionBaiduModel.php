<?php

namespace App\Api\Model\Region;



use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class RegionBaiduModel extends Model
{
    protected $table = 'server_region_baidu';
    protected $primaryKey = 'id';

    /**
     * @param $region_code
     * @return false|Builder|Model|object|null
     */
    public function getBaiduRegionByAdcode($region_code)
    {
        if (empty($region_code)) {
            return false;
        }
        if (!is_array($region_code)) {
            return false;
        }
        $result = $this->query()->from($this->table)->whereIn('region_code', $region_code)
            ->select(['region_name', 'region_code', 'depth',])
            ->first();
        return $result;
    }
}
