<?php

namespace App\Console\Commands;

use App\Api\Logic\Address as AddressLogic;
use App\Api\Model\Region\Region as RegionModel;
use App\Api\Model\Region\RegionGpsModel;
use Illuminate\Console\Command;

class FourRegionGpsSupplement extends Command
{
    protected $signature = 'FourRegionGpsSupplement  {--action=}';
    protected $description = '补充四级区域配送范围GPS信息';
    /**
     * @var RegionModel
     */
    private $region_model;
    /**
     * @var RegionGpsModel
     */
    private $region_gps_model;

    public function __construct()
    {
        parent::__construct();
        $this->region_model = new RegionModel();
        $this->region_gps_model = new RegionGpsModel();
    }

    public function handle()
    {
        set_time_limit(0);

        $action = $this->option('action');
        switch ($action) {
            //补充gps表中不存在的地址信息，已存在的不会二次增加
            case  'supplement':
                $this->supplement();
                break;
            //清空region总表中不存在，但是gps表中还存在的记录
            case 'clear_not_exist_data':
                $this->clear_not_exist_data();
                break;
            default:
                echo '请给定操作类型参数' . PHP_EOL;
                break;
        }
    }

    /**
     * 初始化补充地址的GPS信息【全部二级地址的第一个三级地址，三级地址的第一个四级地址】
     * @return void
     */
    private function supplement()
    {
        //获取二三四级地址信息
        list($city, $district, $town) = $this->getTwoThreeForeRegionsData();

        $time = time();
        //对当前以获取的二三四级地址，进行唯一性过滤，获取GPS表中还不存在的地址信息
        $filter_region = $this->getFilterRegion($city, $district, $town);

        //根据筛选过后的地址，请求根据地名获取经纬度的接口服务，获取到这些指定地址的经纬度信息 并组合成可以存入GPS表的数据格式
        list($retry, $error, $create_arr) = $this->getRegionGpsDataAndCombinationCreateGPSData($filter_region, $time);
        if ($error) {
            echo '新增失败' . count($error) . '条数据' . PHP_EOL;
            \Neigou\Logger::General('EcFourRegionGpsSupplement_create_error', array(
                'data' => $error,
            ));
        }
        if ($create_arr) {
            $count = $this->region_gps_model->addRegionsGps($create_arr);
            if ($count) {
                echo '新增' . count($create_arr) . '条数据' . PHP_EOL;
            } else {
                echo '新增sql异常，共' . count($create_arr) . '条数据失败' . PHP_EOL;
            }
        }
        if ($retry) {
            echo '新增失败,需要重试：' . count($error) . '条数据' . PHP_EOL;
            \Neigou\Logger::General('EcFourRegionGpsSupplement_create_retry', array(
                'data' => $retry,
            ));
        }
        echo '结束' . PHP_EOL;
    }

    /**
     * 清除已经不存在或变更的地址GPS信息
     * @return void
     */
    private function clear_not_exist_data()
    {
        //获取已经存在的有gps信息的二级城市
        $gps_data = $this->getRegionGps();
        $gps_new = [];
        $search_Id = [];
        //获取每一组GPS数据中最后一级对应的地址id，因为有的地址没有四级或三级地址
        foreach ($gps_data as $datum) {
            $key = '';
            $one = $two = $three = $four = 0;
            if (!empty($datum['region_id'])) {
                $key .= $datum['region_id'];
                $one = $datum['region_id'];
            }
            if (!empty($datum['region_id_2'])) {
                $key .= $datum['region_id_2'];
                $two = $datum['region_id_2'];
            }
            if (!empty($datum['region_id_3'])) {
                $key .= $datum['region_id_3'];
                $three = $datum['region_id_3'];
            }
            if (!empty($datum['region_id_4'])) {
                $key .= $datum['region_id_4'];
                $four = $datum['region_id_4'];
            }
            $gps_new[$key] = $datum['id'];
            if ($four) {
                $search_Id[] = $four;
                continue;
            }
            if ($three) {
                $search_Id[] = $three;
                continue;
            }
            if ($two) {
                $search_Id[] = $two;
                continue;
            }
            if ($one) {
                $search_Id[] = $one;
                continue;
            }
        }

        //根据GPS筛选出来的地址id，查询地址总表，获取对应的数据
        $search_data = [];
        if ($search_Id) {
            $search_data = $this->region_model->getRegionsList([], ['region_id' => $search_Id]);
        }
        //组合总表数据的唯一下标，以便用来进行排重
        $search_new = [];
        foreach ($search_data as $search_datum) {
            $region_path = explode(',', $search_datum['region_path']);
            $key = '';
            if (!empty($region_path[1])) {
                $key .= $region_path[1];
            }
            if (!empty($region_path[2])) {
                $key .= $region_path[2];
            }
            if (!empty($region_path[3])) {
                $key .= $region_path[3];
            }
            if (!empty($region_path[4])) {
                $key .= $region_path[4];
            }
            $search_new[$key] = $search_datum['region_id'];
        }

        //开始进行排重，找出gps标存在，但是地址总表中不存在的数据，进行标记保存
        $delete_ids = [];
        foreach ($gps_new as $k => $v) {
            if (isset($search_new[$k])) {
                continue;
            }
            $delete_ids[] = $v;
        }
        //删除gps标存在，但是总表不存在的数据
        if (!empty($delete_ids)) {
            $this->region_gps_model->updateRegionsGps([], ['id' => $delete_ids], ['disabled' => 'true']);
            echo '删除' . count($delete_ids) . '个GPS预存数据' . PHP_EOL;
        }
        echo '结束' . PHP_EOL;
    }

    /**
     * 请求服务，获取指定名字对应的经纬度信息
     * @param $region
     * @param $keyword
     * @return array|mixed
     */
    public function getServiceGps($region, $keyword)
    {
        $addressLogic = new AddressLogic();
        $ret = $addressLogic->placeSuggestion($region, $keyword, 'all', config('neigou.ADDRESS_SUGGESTION_PLATFORM'));
        return $ret;
    }

    /**
     * 获取全部的二级城市，及其下的三四级城市信息
     * @return array
     */
    private function getTwoThreeForeRegionsData(): array
    {

        /**
         * 获取地址总表中全部的二级地址【城市】记录
         */
        $where = array('region_grade' => 2);
        $city = $this->region_model->getRegionsList($where);
        //获取全部二级地址id
        $city_id = array_column($city, 'region_id');

        /**
         * 根据二级地址获取每一个二级地址下的第一个三级地址，基于sql group by 进行查询，获取到全部二级城市下的唯一三级城市地址信息
         */
        $where = array('region_grade' => 3);
        $whereIn = array('p_region_id' => $city_id);
        $district = $this->region_model->getGroupFirstRegions($where, $whereIn);
        $district_id = array_column($district, 'region_id');

        /**
         * 根据三级地址获取到该三级地址下的第一个四级地址，基于sql group by 进行查询，获取到全部上门查询的全部三级城市下的唯一四级城市地址信息
         */
        //根据三级地址获取到该三级地址下的第一个四级地址
        $where = array('region_grade' => 4);
        $whereIn = array('p_region_id' => $district_id);
        $town = $this->region_model->getGroupFirstRegions($where, $whereIn);

        return array(
            $city,
            $district,
            $town,
        );
    }

    /**
     * 获取已经存在的有gps信息的二级城市
     * @param array $where
     * @return array
     */
    private function getRegionGps(array $where = []): array
    {
        $where['disabled'] = 'false';
        $gps_data = $this->region_gps_model->getRegionsGpsList($where, []);
        $r_gps = array();
        foreach ($gps_data as $tmp_data) {
            $key = $tmp_data['region_id'] . $tmp_data['region_id_2'] . $tmp_data['region_id_3'] . $tmp_data['region_id_4'];
            $r_gps[$key] = $tmp_data;
        }
        return $r_gps;
    }

    /**
     * 对当前以获取的二三四级地址，进行唯一性过滤，获取GPS表中还不存在的地址信息
     * @param $city
     * @param $district
     * @param $town
     * @return array
     */
    private function getFilterRegion($city, $district, $town): array
    {
        //根据四级地址获取到该四级地址下的所有GPS信息
        $r_gps = $this->getRegionGps();
        $r_district = array_column($district, NULL, 'p_region_id');
        $r_town = array_column($town, NULL, 'p_region_id');
        $new_region = array();
        foreach ($city as $k => $v) {
            $tmp_three = $r_district[$v['region_id']];
            $tmp_four = $r_town[$tmp_three['region_id']];
            $tmp_key = $v['p_region_id'] . $v['region_id'] . ($tmp_three['region_id'] ?? 0) . ($tmp_four['region_id'] ?? 0);
            if (!empty($r_gps[$tmp_key])) {
                //GPS表中已存在，不需要进行处理
                continue;
            }
            if (empty($tmp_three['region_id'])) {
                //这个二级地址下没有三级地址，以二级地址填三级地址
                $tmp_three['local_name'] = $v['local_name'];
            }
            if (empty($tmp_four['region_id'])) {
                //这个三级地址下没有四级地址，以三级地址填四级地址
                $tmp_four['local_name'] = $tmp_three['local_name'];
            }
            $new_region[] = [
                'region_name' => $v['local_name'],
                'region_id' => $v['p_region_id'],
                'region_id_2' => $v['region_id'],
                'region_id_3' => $tmp_three['region_id'] ?? 0,
                'region_id_4' => $tmp_four['region_id'] ?? 0,
                'keyword' => $tmp_four['local_name'],
            ];
        }
        return $new_region;
    }

    /**
     * 根据筛选过后的地址，请求根据地名获取经纬度的接口服务，获取到这些指定地址的经纬度信息
     * 并组合成可以存入GPS表的数据格式
     * @param array $new_region
     * @param int $time
     * @return array
     */
    private function getRegionGpsDataAndCombinationCreateGPSData(array $new_region, int $time): array
    {
        $create_arr = $error = $retry = array();
        foreach ($new_region as $region_info) {
            //请求服务，获取指定名字对应的经纬度信息
            $res = $this->getServiceGps($region_info['region_name'], $region_info['keyword']);
            if ($res['status'] != 0) {
                //获取失败的，直接归类为异常，进行记录
                $region_info['error_info'] = $res;
                $error[] = $region_info;
                continue;
            }
            $region_info['deliver_json'] = json_encode($res['data'][0] ?? []);
            if (empty($res['data'])) {
                //没有数据的地址，需要重试
                $retry[] = $region_info;
            }
            $region_info['longitude'] = $res['data'][0]['location']['lng'];
            $region_info['latitude'] = $res['data'][0]['location']['lat'];

            //组合GPS表需要的数据结构
            $create_arr[] = array(
                'region_id' => $region_info['region_id'],
                'region_id_2' => $region_info['region_id_2'],
                'region_id_3' => $region_info['region_id_3'],
                'region_id_4' => $region_info['region_id_4'],
                'longitude' => $region_info['longitude'],
                'latitude' => $region_info['latitude'],
                'deliver_json' => $region_info['deliver_json'],
                'create_time' => $time,
            );
            echo '新增加一 ' . $region_info['region_name'] . ' - ' . $region_info['keyword'] . ' 的数据' . PHP_EOL;
            sleep(1.5);//防止key请求超上限
        }
        return array($retry, $error, $create_arr);
    }
}
