<?php

namespace App\Api\Model\Outlet;

use Illuminate\Support\Facades\DB;
/**
 * 品牌商品的门店信息表
 */
class OutletModel
{
    /**
     * @var \Illuminate\Support\Facades\DB $_db
     */
    private $_db;
    private $_table_scope_channel = 'server_outlet';

    public function __construct()
    {
        $this->_db = app('api_db');
    }

    /**
     * 根据主键id获取指定的记录信息
     * @param $outletId
     * @param array $field
     * @return \Illuminate\Database\Eloquent\Model|\Illuminate\Database\Query\Builder|object|null
     */
    public function getInfoByOutletId($outletId, $field = array())
    {
        if (!$field) {
            $field = ['outlet_id'];
        }
        return $this->_db->table($this->_table_scope_channel)
            ->where(['outlet_id' => $outletId, 'deleted_time' => 0])
            ->select($field)
            ->first();
    }

    /**
     * 获取列表
     * @param int $offset 偏移量
     * @param int $limit 每一页展示数量
     * @param array $outletIds
     * @param array $outletNames
     * @param array $address
     * @return array
     */
    public function getList(int $offset = 0, int $limit = 20, array $outletIds = [], array $outletNames = [], array $address = []): array
    {
        $model = $this->_db->table($this->_table_scope_channel);
        if ($outletIds) {
            $model->whereIn('outlet_id', $outletIds);
        }

        if ($outletNames) {
            if (count($outletNames) == 1) {
                $model->where('outlet_name', 'like', '%'.$outletNames[0] . '%');
            } else {
                $model->whereIn('outlet_name', $outletNames);
            }
        }
        if (isset($address['province_id']) && $address['province_id']) {
            $model->where('province_id', '=', $address['province_id']);
        }
        if (isset($address['city_id']) && $address['city_id']) {
            $model->where('city_id', '=', $address['city_id']);
        }
        if (isset($address['area_id']) && $address['area_id']) {
            $model->where('area_id', '=', $address['area_id']);
        }
        $res = $model
            ->where(['deleted_time' => 0])
            ->limit($limit)->offset($offset)
            ->select([
                "outlet_id",
                "outlet_logo",
                "outlet_name",
                "outlet_description",
                "outlet_address",
                "outlet_phone_number",
                "longitude",
                "latitude",
                "outlet_begin_time",
                "outlet_end_time",
                "outlet_date_horizon",
                "province_id",
                "city_id",
                "area_id",
                "brand_id",
                "is_scanning_code",
                "scanning_code_type"
            ])
            ->orderBy('outlet_id', 'desc')
            ->get();
        $ret = [];
        if ($res) {
            $ret = $res->toArray();
        }
        return $ret;

    }

    /**
     * 获取列表数量
     * @param array $outletIds
     * @param array $outletNames
     * @param array $address
     * @return int
     */
    public function getListCount(array $outletIds = [], array $outletNames = [], array $address = [], array $brandIds = array(), int $isScanningCode = 0): int
    {
        $model = $this->_db->table($this->_table_scope_channel);
        if ($outletIds) {
            $model->whereIn('outlet_id', $outletIds);
        }
        if ($outletNames) {
            if (count($outletNames) == 1) {
                $model->where('outlet_name', 'like', '%'.$outletNames[0] . '%');
            } else {
                $model->whereIn('outlet_name', $outletNames);
            }
        }
        if ($brandIds) {
            $model->whereIn('brand_id', $brandIds);
        }
        if (isset($address['province_id']) && $address['province_id']) {
            $model->where('province_id', '=', $address['province_id']);
        }
        if (isset($address['city_id']) && $address['city_id']) {
            $model->where('city_id', '=', $address['city_id']);
        }

        if (isset($address['area_id']) && $address['area_id']) {
            $model->where('area_id', '=', $address['area_id']);
        }

        if ($isScanningCode == 1) {
            $model->where('is_scanning_code', '=', 0);
        } else if ($isScanningCode == 2) {
            $model->where('is_scanning_code', '=', 1);
        }

        return $model->where(['deleted_time' => 0])
            ->count();

    }

    /**
     * 获取列表数量
     * @param array $outletIds
     * @param array $outletNames
     * @param array $address
     * @return int
     */
    public function getListCountV2(array $outletIds = [], array $outletNames = [], array $address = [], array $brandIds = array(), int $isScanningCode = 0, array $brandRuleIds = array()): int
    {
        $model = $this->_db->table($this->_table_scope_channel . ' as a')
            ->join('server_brand_rule_outlet as b', 'a.outlet_id', '=', 'b.outlet_id');

        if ($outletIds) {
            $model->whereIn('a.outlet_id', $outletIds);
        }
        if ($outletNames) {
            if (count($outletNames) == 1) {
                $model->where('a.outlet_name', 'like', '%'.$outletNames[0] . '%');
            } else {
                $model->whereIn('a.outlet_name', $outletNames);
            }
        }
        if ($brandIds) {
            $model->whereIn('a.brand_id', $brandIds);
        }
        if (isset($address['province_id']) && $address['province_id']) {
            $model->where('a.province_id', '=', $address['province_id']);
        }
        if (isset($address['city_id']) && $address['city_id']) {
            $model->where('a.city_id', '=', $address['city_id']);
        }

        if (isset($address['area_id']) && $address['area_id']) {
            $model->where('a.area_id', '=', $address['area_id']);
        }

        if ($isScanningCode == 1) {
            $model->where('a.is_scanning_code', '=', 0);
        } else if ($isScanningCode == 2) {
            $model->where('a.is_scanning_code', '=', 1);
        }
        if ($brandRuleIds) {
            $model->whereIn('b.brand_rule_id', $brandRuleIds);
        }

        $query = $model->select("a.*")->where(['a.deleted_time' => 0])->groupBy("a.outlet_id");

        return DB::table(DB::raw("({$query->toSql()}) as sub"))
            ->mergeBindings($query) //绑定子查询的参数，必须有，否则报错
            ->count();
    }

    /**
     * 获取带有地理位置的门店列表
     *
     * @param array  $regions
     * @param array  $location
     * @param array  $brandIds
     * @param string $order
     * @param int    $offset
     * @param int    $limit
     *
     * @return array
     */
    public function getListWithCoordinate(array $regions = [], array $location = array(), array $brandIds = [], array $outletIds = [], array $outletNames = [], string $order = "", int $isScanningCode = 0 ,int $offset = 0, int $limit = 20): array
    {
        if (env("APP_ENV") != "production") {
            $selectSql = " *,st_distance (point ( longitude, latitude ),point ( {$location['lon']}, {$location['lat']} )) * 111195 AS distance ";
        } else {
            $selectSql = " *,st_distance_sphere (point (longitude, latitude), point({$location['lon']}, {$location['lat']})) as distance ";
        }

        $sql = 'SELECT ' . $selectSql . ' FROM server_outlet WHERE  deleted_time = 0  ';


        if (!empty($brandIds)) {
            $sql .= ' AND brand_id IN (' . implode(',', $brandIds) . ')';
        }

        if (!empty($outletIds)) {
            $sql .= ' AND outlet_id IN (' . implode(',', $outletIds) . ')';
        }

        if (!empty($outletNames)) {
            $sql .= ' AND outlet_name IN (' . implode(',', $outletNames) . ')';
        }

        if ($isScanningCode == 1) {
            $sql .= ' AND is_scanning_code = 0 ';
        } else if ($isScanningCode == 2) {
            $sql .= ' AND is_scanning_code = 1 ';
        }


        if (!empty($regions))
        {
            if (isset($regions['province_id']))
            {
                $sql .= ' AND province_id = ' . $regions['province_id'];
            }

            if (isset($regions['city_id']))
            {
                $sql .= ' AND city_id = ' . $regions['city_id'];
            }

            if (isset($regions['area_id']))
            {
                $sql .= ' AND area_id = ' . $regions['area_id'];
            }
        }


        if ($order) {
            $sql .= " order by". $order;
        }

        $sql .= " LIMIT ". $offset.",". $limit;

        $listObj = app('api_db')->select($sql);

        if (is_array($listObj) && count($listObj) > 0) {
            return json_decode(json_encode($listObj), true);
        }
        return array();
    }



    /**
     * 获取带有地理位置的门店列表
     *
     * @param array  $regions
     * @param array  $location
     * @param array  $brandIds
     * @param string $order
     * @param int    $offset
     * @param int    $limit
     *
     * @return array
     */
    public function getListWithCoordinateV2(array $regions = [], array $location = array(), array $brandIds = [], array $outletIds = [], array $outletNames = [], string $order = "", int $isScanningCode = 0 , array $brandRuleIds = array(), int $offset = 0, int $limit = 20): array
    {
        if (env("APP_ENV") != "production") {
            $selectSql = "a.*,st_distance (point ( longitude, latitude ),point ( {$location['lon']}, {$location['lat']} )) * 111195 AS distance ";
        } else {
            $selectSql = "a.*,st_distance_sphere (point (longitude, latitude), point({$location['lon']}, {$location['lat']})) as distance ";
        }

        $model = $this->_db->table($this->_table_scope_channel . ' as a')
            ->join('server_brand_rule_outlet as b', 'a.outlet_id', '=', 'b.outlet_id');

        if ($outletIds) {
            $model->whereIn('a.outlet_id', $outletIds);
        }
        if ($outletNames) {
            if (count($outletNames) == 1) {
                $model->where('a.outlet_name', 'like', '%'.$outletNames[0] . '%');
            } else {
                $model->whereIn('a.outlet_name', $outletNames);
            }
        }
        if ($brandIds) {
            $model->whereIn('a.brand_id', $brandIds);
        }
        if (isset($regions['province_id']) && $regions['province_id']) {
            $model->where('a.province_id', '=', $regions['province_id']);
        }
        if (isset($regions['city_id']) && $regions['city_id']) {
            $model->where('a.city_id', '=', $regions['city_id']);
        }

        if (isset($regions['area_id']) && $regions['area_id']) {
            $model->where('a.area_id', '=', $regions['area_id']);
        }

        if ($isScanningCode == 1) {
            $model->where('a.is_scanning_code', '=', 0);
        } else if ($isScanningCode == 2) {
            $model->where('a.is_scanning_code', '=', 1);
        }
        if ($brandRuleIds) {
            $model->whereIn('b.brand_rule_id', $brandRuleIds);
        }

        $query = $model->select(DB::raw($selectSql))
            ->where(['a.deleted_time' => 0])
            ->groupBy("a.outlet_id");

        if ($order) {
            $query->orderByRaw($order);
        }

        return $query->limit($limit)->offset($offset)->get()->map(function ($value) {
                return (array)$value;
            })->toArray();
    }


    /**
     * @param array          $conditions
     * @param array          $options
     * @param array|string[] $columns
     *
     * @return array
     */
    public function getOutletAgg(array $conditions = [], array $options = ["group_by_field" => "brand_id"]) :array
    {
        $model = $this->_db->table($this->_table_scope_channel);
        $model->where(['deleted_time' => 0]);

        if ($conditions['brand_id']) {
            $model->whereIn('brand_id', $conditions['brand_id']);
        }

        if (isset($conditions['province_id']) && $conditions['province_id']) {
            $model->where('province_id', '=', $conditions['province_id']);
        }

        if (isset($conditions['city_id']) && $conditions['city_id']) {
            $model->where('city_id', '=', $conditions['city_id']);
        }

        if (isset($conditions['area_id']) && $conditions['area_id']) {
            $model->where('area_id', '=', $conditions['area_id']);
        }

        if (isset($conditions['is_scanning_code'])) {
            $model->where('is_scanning_code', '=', $conditions['is_scanning_code']);
        }

        $res = $model->select(DB::raw('count(*) as count, brand_id'))->groupBy($options['group_by_field'])->get();

        $ret = [];
        if ($res) {
            $ret = $res->toArray();
        }

        return $ret;
    }

    /**
     * @param array          $conditions
     * @param array          $options
     * @param array|string[] $columns
     *
     * @return array
     */
    public function getOutletAggV2(array $conditions = [], array $options = ["group_by_field" => "brand_id"]) :array
    {
        $model = $this->_db->table($this->_table_scope_channel . ' as a')
            ->join('server_brand_rule_outlet as b','a.outlet_id','=', 'b.outlet_id');

        $model->where(['a.deleted_time' => 0]);

        if ($conditions['brand_id']) {
            $model->whereIn('a.brand_id', $conditions['brand_id']);
        }

        if (isset($conditions['province_id']) && $conditions['province_id']) {
            $model->where('a.province_id', '=', $conditions['province_id']);
        }

        if (isset($conditions['city_id']) && $conditions['city_id']) {
            $model->where('a.city_id', '=', $conditions['city_id']);
        }

        if (isset($conditions['area_id']) && $conditions['area_id']) {
            $model->where('a.area_id', '=', $conditions['area_id']);
        }

        if (isset($conditions['is_scanning_code'])) {
            $model->where('a.is_scanning_code', '=', $conditions['is_scanning_code']);
        }

        if ($conditions['brand_rule_ids']) {
            $model->whereIn('b.brand_rule_id', $conditions['brand_rule_ids']);
        }

        $res = $model->select(DB::raw('count(*) as count, brand_id'))->groupBy($options['group_by_field'])->get();

        $ret = [];
        if ($res) {
            $ret = $res->toArray();
        }

        return $ret;
    }


    /**
     * 插入数据库
     * @param array $data
     * @param bool $is_batch 是否批量，单条插入返回id
     * @return mixed
     */
    public function insert(array $data, bool $is_batch = true)
    {
        $time = time();

        $model = $this->_db->table($this->_table_scope_channel);
        if ($is_batch) {
            $data['created_time'] = $time;
            $res = $model->insertGetId($data);
        } else {
            foreach ($data as &$v) {
                $v['created_time'] = $time;
            }
            $res = $model->insert($data);
        }
        return $res;
    }

    /**
     * 更新门店信息
     * @param int $outletId 门店id
     * @param array $data
     * @return int
     */
    public function update(int $outletId, array $data): int
    {
        return $this->_db->table($this->_table_scope_channel)
            ->where(['outlet_id' => $outletId, 'deleted_time' => 0])
            ->update($data);
    }

    /**
     * 批量获取门店信息
     * @param array $outletIds
     * @param array $field
     * @return array
     */
    public function getDataByOutletId(array $outletIds, array $field = []): array
    {
        if (!$field) {
            $field = ['outlet_id'];
        }
        return $this->_db->table($this->_table_scope_channel)
            ->where(['deleted_time' => 0])
            ->whereIn('outlet_id', $outletIds)
            ->select($field)
            ->get()->map(function ($value) {
                return (array)$value;
            })
            ->toArray();
    }

}
