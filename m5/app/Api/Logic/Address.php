<?php

namespace App\Api\Logic;

use App\Api\Logic\Address\AddressBaiduMap;
use App\Api\Logic\Address\AddressTencentMap;
use Neigou\RedisNeigou;
use App\Api\Logic\Config\Config;
use App\Api\V3\Service\Region\Region as RegionService;

class Address
{
    protected $redisClient;
    protected $platform; // 平台
    protected $partner; // 定位服务方

    function __construct()
    {
        $this->redisClient = new RedisNeigou();
    }

    public function placeSuggestion($region, $keyword, $platform = 'all', $partner = 'tencent')
    {
        if (empty($region) || empty($keyword)) {
            return false;
        }

        $this->platform = $platform;
        $this->partner = $partner;

        $key_md5 = md5($keyword);
        $region_md5 = md5($region);
        $redisKey = "map.key.v2.{$region_md5}.{$key_md5}";
        $data_redis = $this->redisClient->_redis_connection->get($redisKey);

        if (!empty($data_redis)) {
            return json_decode($data_redis, true);
        }
        $retData = [
            "status" => 0,
            "message" => "ok",
            "data" => [],
        ];
        switch ($partner) {
            case 'baidu':
                $ak = $this->getKeyForType("baidu");
                $AddressBaiduMap = new AddressBaiduMap();
                $retData = $AddressBaiduMap->getPlaceSuggestion($ak, $region, $keyword);
                break;
            case 'tencent':
                $keySk = $this->getKeyForType();
                $AddressTencentMap = new AddressTencentMap();
                $retData = $AddressTencentMap->getPlaceSuggestion($keySk, $region, $keyword);
                break;
            default:
                $retData['status'] = "100";
                $retData['message'] = "暂无服务";
                return  $retData;

        }
        if ($retData['status'] != 0) {
            \Neigou\Logger::General('service.placeSuggestion',
                array(
                    'action' => 'placeSuggestion.error', 'sparam1' => $retData, 'message' => $retData['message'],
                    'region' => $region, 'keyword' => $keyword, 'platform' => $platform, 'partner' => $partner
                )
            );
            return $retData;
        }
        //缓存
        $this->redisClient->_redis_connection->set($redisKey, json_encode($retData), 86400);
        return $retData;
    }

    /**
     * @return mixed|string[]
     */
    private function getKeyData()
    {
        $tencent_lbs_key = "map.key.v2.tencent_lbs_key_certification";
        $data_redis      = $this->redisClient->_redis_connection->get($tencent_lbs_key);

        $data_redis_arr = json_decode($data_redis, true);

        if (empty($data_redis_arr)) {
            $key_json = Config::getLocation($this->platform);
            $this->redisClient->_redis_connection->set($tencent_lbs_key, $key_json,
                43200);
            $data_redis_arr = json_decode($key_json, true);
        }

        $data_redis_arr = $data_redis_arr[$this->partner];

        //'121' 每天10000次
        //'120' 并发5次
        $data_one_key = array_rand($data_redis_arr, 1);
        $data_redis_arr = $data_redis_arr[$data_one_key];
        $data_redis_arr['api_url'] = 'https://apis.map.qq.com/ws/place/v1/suggestion';
        $data_redis_arr['uri'] = '/ws/place/v1/suggestion';
        $data_redis_arr['r_key'] = $data_one_key;
        return $data_redis_arr;
    }

    /**
     * get 请求方式签名
     *
     * @param $sk
     * @param $url
     * @param $querystring_arrays
     * @return string
     */
    public function caculateAKSNGet($sk, $url, $querystring_arrays)
    {
        ksort($querystring_arrays);

        $querystring = '';

        foreach ($querystring_arrays as $k=>$v) {
            if (!empty($querystring)){
                $querystring = $querystring.'&';
            }
            $querystring = $querystring.$k.'='.$v;
        }

        return md5($url.'?'.$querystring.$sk);
    }

    /**
     * @param $data_one_key
     * @return bool
     */
    private function delKeyData($data_one_key)
    {
        $tencent_lbs_key = "map.key.v2.tencent_lbs_key";
        $data_redis = $this->redisClient->_redis_connection->get($tencent_lbs_key);
        $data_redis_arr = json_decode($data_redis, true);
        if (isset($data_redis_arr[$data_one_key])) {
            unset($data_redis_arr[$data_one_key]);
        }
        $json_data = json_encode($data_redis_arr);
        $ttl = $this->redisClient->_redis_connection->ttl($tencent_lbs_key);

        if ($ttl <= 0) {
            return true;
        }
        $this->redisClient->_redis_connection->set($tencent_lbs_key, $json_data, $ttl);
        if (empty($data_redis_arr)) {
            return false;
        }
        return true;
    }

    /**
     * @param string $type
     * @return mixed|string[]
     */
    public function getKeyForType(string $type = 'location')
    {
        $tencent_lbs_key = "map.key.v3.tencent_lbs_key.".$type;
        $data_redis = $this->redisClient->_redis_connection->get($tencent_lbs_key);

        $data_redis_arr = json_decode($data_redis, true);

        $ttl = strtotime(date('Y-m-d',strtotime('+1 days'))) - time();

        if (empty($data_redis_arr)) {
            switch ($type) {
                case 'baidu':
                    $AddressBaiduMap = new AddressBaiduMap();
                    $key_arr = $AddressBaiduMap->getAk();

                    break;
                case 'location':
                case 'tencent':
                default:
                    $AddressTencentMap = new AddressTencentMap();
                    $key_arr = $AddressTencentMap->getKeySk();
                    break;
            }
            //域名白名单模式
            $this->redisClient->_redis_connection->set($tencent_lbs_key, json_encode($key_arr),
                $ttl);
            $data_redis_arr = $key_arr;
        }

        while (true){
            $data_one_key = array_rand($data_redis_arr, 1);
            $data_key_arr = $data_redis_arr[$data_one_key];

            $use_times_key = "map.key.v2.day_times.".$data_one_key.$type;
            if (!$this->redisClient->_redis_connection->get($use_times_key)){
                $this->redisClient->_redis_connection->set($use_times_key, 1, $ttl);
            }else{
                if ($this->redisClient->_redis_connection->incr($use_times_key)>$data_key_arr['times']){
                    unset($data_redis_arr[$data_one_key]);
                    if (empty($data_redis_arr)){
                        break;
                    }
                    $this->redisClient->_redis_connection->set($tencent_lbs_key, json_encode($data_redis_arr), $ttl);
                    continue;
                }
            }
            break;
        }
        return $data_key_arr;
    }

    /**
     * @return string
     */
    public function getLocation(){
        $keySk = $this->getKeyForType();
        $params = ['key' => $keySk['key']];

        ksort($params);

       return $url = 'https://apis.map.qq.com/ws/location/v1/ip?'.http_build_query($params);
    }

    /**
     * 根据定位获取省市区
     * @param $lon
     * @param $lat
     * @return array
     */
    public function getRegionByLocation($lon, $lat): array
    {
        //获取配置文件，根据配置文件决定使用哪一个第三方地图服务
        $switch_key = config('neigou.REVERSE_GEOCODING_PLATFORM');
        switch ($switch_key) {
            case 'baidu':
                $ak = $this->getKeyForType("baidu");
                $AddressBaiduMap = new AddressBaiduMap();
                $res = $AddressBaiduMap->getReverseGeocoding($ak, $lon, $lat, $url);
                break;
            case 'tencent':
            default:
                $keySk = $this->getKeyForType();
                $AddressTencentMap = new AddressTencentMap();
                $res = $AddressTencentMap->getReverseGeocoding($keySk, $lon, $lat, $url);
        }
        \Neigou\Logger::Debug('server.address.getRegionByLocation', array(
            'switch_key' => $switch_key,
            'url' => $url,
            'res' => $res
        ));
        if ($res['status'] != 0) {
            \Neigou\Logger::Debug('server.address.getRegionByLocation', array(
                'msg' => '请求第三方接口异常',
                'error_no' => '10000',
                'switch_key' => $switch_key, 'url' => $url, 'res' => $res
            ));
            return array();
        }
        $treeList = $this->getServiceRegionInfo($res['result']['address_component'], $switch_key);

        //如果没有匹配到则失败
        if (empty($treeList)) {
            \Neigou\Logger::Debug('server.address.getRegionByLocation', array(
                'msg' => '获取我方地址映射失败',
                'error_no' => '10001',
                'switch_key' => $switch_key, 'url' => $url, 'res' => $res
            ));
            return array();
        }

        //组装地区数据
        $return = array();
        foreach ($treeList as $region) {
            if ($region['region_grade'] == 1) {
                $return['province_id'] = $region['region_id'];
                $return['province'] = $region['local_name'];
            } elseif ($region['region_grade'] == 2) {
                $return['city_id'] = $region['region_id'];
                $return['city'] = $region['local_name'];
            } elseif ($region['region_grade'] == 3) {
                $return['area_id'] = $region['region_id'];
                $return['area'] = $region['local_name'];
            }
        }
        return $return;
    }

    public function getDistanceByLocation($from, $to) {
        //腾讯地图秘钥
        $keySk = $this->getKeyForType();

        $fromArr = array();
        foreach ($from as $v) {
            $fromArr[] = $v['lat'].','.$v['lon'];
        }

        foreach ($to as $v) {
            $toArr[] = $v['lat'].','.$v['lon'];
        }

        $params = [
            'key' => $keySk['key'],
            'from'=>implode(';', $fromArr),
            'to'=>implode(';', $toArr),
        ];

        //获取距离
        $url = 'https://apis.map.qq.com/ws/distance/v1/matrix/?'.http_build_query($params);
        $url = urldecode($url);
        $curl = new \Neigou\Curl();
        $res = $curl->Get($url);
        $res = json_decode($res, true);
        if ($res['status'] != 0 || !$res['result']['rows']) {
            return array();
        }

        //组装数据返回
        $data = array();
        foreach ($res['result']['rows'] as $row) {
            $data[] = $row['elements'];
        }

        return $data;
    }

    /**
     * 根据第三方地图返回的省市区数据，获取我方地址映射
     * @param $geo_region array 三方地址信息
     * @param $switch_key string 配置【baidu \ tencent】
     * @return array
     */
    public function getServiceRegionInfo(array $geo_region,string $switch_key): array
    {
        $treeList = array();
        $regionService = new RegionService();

        $city_region = array();
        $city_region_id = 0;
        //区数据可能为空
        if ($geo_region['district']) {
            $district_region = $regionService->getRegionList(['local_name' => $geo_region['district'], 'region_grade' => 3]);
            if(count($district_region) > 1) {
//                $region_id = array_column($district_region, 'region_id');
                //三级地址出现重名，需要根据上溯，确定二级地址
                if ($geo_region['city']) {
                    $city_region = $regionService->getRegionsRow(['local_name' => $geo_region['city'], 'region_grade' => 2]);
                    if ($city_region) {
                        foreach ($district_region as $district_info) {
                            if ($district_info['p_region_id'] == $city_region['region_id']) {
                                $region_id = $district_info['region_id'];
                                break;
                            }
                        }
                        //如果没有对应的三级地址，则使用二级地址
                        if(empty($region_id)){
                            $city_region_id = $city_region['region_id'];
                        }
                    }
                }
            }else{
                $region_id = $district_region[0]['region_id'];
            }
            if (!empty($region_id)) {
                $treeList = $regionService->getParentRegion($region_id);
            }else{
                \Neigou\Logger::Debug('server.address.getRegionByLocation', array(
                    'switch_key' => $switch_key,'msg' => '获取三级地址信息失败', 'error_no' => '10003',
                    'district_region' => $district_region,
                    'city_region' => $city_region ?? [],
                    'geo_region' => $geo_region,
                ));
            }
        }

        //获取市
        if (empty($treeList)) {
            if ($geo_region['city']) {
                //如果已经查询过城市信息了，就不在进行二次查询了，直接使用上门的查询结果
                if (empty($city_region_id)) {
                    $region = $regionService->getRegionsRow(['local_name' => $geo_region['city'], 'region_grade' => 2]);
                    $city_region_id = $region['region_id'];
                }
                if (!empty($city_region_id)) {
                    $treeList = $regionService->getParentRegion($city_region_id);
                }else{
                    \Neigou\Logger::Debug('server.address.getRegionByLocation', array(
                        'switch_key' => $switch_key,'msg' => '获取二级地址信息失败', 'error_no' => '10004',
                        'region' => $region,
                        'city_region' => $city_region ?? [],
                        'geo_region' => $geo_region,
                    ));
                }
            }
        }

        if (empty($treeList) && $switch_key == 'baidu') {
            //获取映射表中的数据，再次尝试获取对应的地区数据
            $region_baidu = $regionService->getBaiduMap($geo_region);
            foreach ($region_baidu as $region_baidu_v) {
                if ($region_baidu_v['depth'] == 3) {
                    //获取映射表中的数据，再次尝试获取对应的地区数据
                    $my_district = $region_baidu_v['mapping_region_id'];
                    break;
                }
                if ($region_baidu_v['depth'] == 2) {
                    $my_city = $region_baidu_v['mapping_region_id'];
                    break;
                }
            }
            if (!empty($my_district)) {
                $treeList = $regionService->getParentRegion($my_district);
            }
            if (!empty($my_city)) {
                $treeList = $regionService->getParentRegion($my_city);
            }
            if (empty($treeList)) {
                \Neigou\Logger::Debug('server.address.getRegionByLocation', array(
                    'switch_key' => $switch_key,'error_no' => '10002',
                    'region_baidu' => $region_baidu, 'geo_region' => $geo_region,
                    'my_district' => $my_district ?? '', 'my_city' => $my_city ?? '',
                ));
            }
        }
        return $treeList;
    }

    /**
     * 根据经纬度获取百度省市区地址信息 longitude and latitude;
     * @param $lon
     * @param $lat
     * @return array|mixed
     */
    public function getBaiduAddressByLonAndLat($lon, $lat)
    {
        $return = array(
            "country" => "",//国家
            "country_code" => -1,//国家代码
            "country_code_iso" => "",//国家英文缩写（三位）
            "country_code_iso2" => "",//国家英文缩写（二位）
            "province" => "",//省
            "city" => "",//市
            "city_level" => 2,//市级别，国内一般是2，国外不一定
            "district" => "",//区
            "town" => "",//镇
            "town_code" => "",//镇代码
            "distance" => "",//距离【距离坐标点】
            "direction" => "",//方向【距离坐标点】
            "adcode" => "0",//城市代码
            "street" => "",//街道
            "street_number" => ""//门牌号
        );
        $ak = $this->getKeyForType("baidu");
        $AddressBaiduMap = new AddressBaiduMap();
        $res = $AddressBaiduMap->getReverseGeocoding($ak, $lon, $lat, $url);
        \Neigou\Logger::Debug('server.address.getBaiduAddressByLonAndLat', array('url' => $url, 'res' => $res));
        if ($res['status'] != 0) {
            \Neigou\Logger::Debug('server.address.getBaiduAddressByLonAndLat', array(
                'msg' => '请求获取百度接口异常', 'error_no' => '10000', 'url' => $url, 'res' => $res
            ));
            return $return;
        }
        $return = $res['result']['addressComponent'] ?? $return;
        if (empty($return['city']) && !empty($return['adcode'])) {
            $regionService = new RegionService();
            $baidu_ret = $regionService->getBaiduRegionByAdcode($return['adcode']);
            if ($baidu_ret) {
                $return['city'] = $baidu_ret['region_name'];
            }
        }
        return $return;
    }
}
