<?php

namespace App\Api\Logic\Address;

use App\Api\Logic\Config\Config;

class AddressTencentMap extends AddressMapBase
{
    /**
     * 获取腾讯逆地址编码【根据经纬度获取具体地址】
     * @param $keySk
     * @param $lon
     * @param $lat
     * @param $url
     * @return array
     */
    public function getReverseGeocoding($keySk, $lon, $lat, &$url): array
    {
        //腾讯地图秘钥
        $params = [
            'key' => $keySk['key'],
            'location' => $lat . ',' . $lon,
        ];

        //获取省市区
        $url = 'https://apis.map.qq.com/ws/geocoder/v1/?' . http_build_query($params);
        $curl = new \Neigou\Curl();
        $res = $curl->Get($url);
        $res = json_decode($res, true);
        return $res;
    }

    /**
     * 获取腾讯地图对应的key
     * @return array[]
     */
    public function getKeySk(): array
    {
        $service_location = Config::getLocation();
        $service_location = json_decode($service_location, true);
        if (!empty($service_location['tencent'])) {
            $key = array_rand($service_location['tencent'], 1);//获取随机key，随机获取一个 fuxi
            $tencent_json = $service_location['tencent'][$key];
            return array(
                'gongsi' => array(
                    'key' => $tencent_json['key'],
                    'times' => '3000000',
                ),
            );
        }
        return array();
    }

    public function getPlaceSuggestion($keySk, $region, $keyword, $number = 0)
    {
        if (empty($region) || empty($keyword)) {
            return $this->returnData(self::ERROR_CODE_NO_RESULT, '参数错误');
        }
        if (($number > $this->place_suggestion_number)) {
            return $this->returnData(self::ERROR_CODE_NO_DATA, '没有对应数据');
        }
        $key = $keySk['key'];
        $api_url = 'https://apis.map.qq.com/ws/place/v1/suggestion';

        $params = array(
            'region' => $this->_parseRegion($region),
            'keyword' => $keyword,
            'region_fix' => 1,
            'policy' => 1,
            'key' => $key,
        );

        $url = "{$api_url}?" . http_build_query($params);
        $curl = new \Neigou\Curl();
        $curl->time_out = 5;
        $res = $curl->Get($url);
        $res = json_decode($res, true);
        if ($res['status'] == 0) {
            return $res;
        } elseif ($res['status'] == '121') {
            //接口10000次;
            \Neigou\Logger::General('service.TENCENTLBS.tencent',
                array(
                    'action' => 'placeSuggestion.tencent.121', 'sparam1' => '接口数量不够用了!!', 'url' => $url, //'sk'=>$sk
                )
            );
            return $this->returnData(self::ERROR_CODE_NO_EXCESS_QUERY, '剩余查询次数不足');
        } elseif ($res['status'] == '120') {
            \Neigou\Logger::General('service.TENCENTLBS',
                array(
                    'action' => 'placeSuggestion.tencent.120', 'sparam1' => '并发上限', 'url' => $url
                )
            );
            return $this->getPlaceSuggestion($keySk, $region, $keyword, ++$number);
        }
        \Neigou\Logger::General('service.TENCENTLBS.tencent', array('action' => 'placeSuggestion.tencent.err', 'sparam1' => $res, 'url' => $url,));
        return $this->returnData(self::ERROR_CODE_TRIPARTITE_SERVICE, '服务端异常: ' . $res['message']);
    }
}
