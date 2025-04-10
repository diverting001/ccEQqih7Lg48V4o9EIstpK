<?php

namespace App\Api\Logic\Address;

use App\Api\Logic\Config\Config;

class AddressBaiduMap extends AddressMapBase
{
    public $url = 'https://api.map.baidu.com';

    /**
     * 获取百度逆地址编码【根据经纬度获取具体地址】
     * @param $ak
     * @param $lon
     * @param $lat
     * @param $url
     * @return bool|mixed|string
     */
    public function getReverseGeocoding($ak, $lon, $lat, &$url)
    {
        //https://api.map.baidu.com/reverse_geocoding/v3/?ak=您的ak&output=json&coordtype=wgs84ll&location=31.225696563611,121.49884033194
        // 构造请求参数
        $param['ak'] = $ak['ak'];
        $param['output'] = 'json';
        $param['coordtype'] = 'gcj02ll';//为了和腾讯经纬度类型保持一致
        $param['extensions_poi'] = '0';
        $param['location'] = $lat . ',' . $lon;
        // 请求地址
        $url = $this->url . '/reverse_geocoding/v3/?' . http_build_query($param);

        $curl = new \Neigou\Curl();
        $res = $curl->Get($url);
        $res = json_decode($res, true);
        if (!empty($res['result']['addressComponent'])) {
            $res['result']['address_component'] = $res['result']['addressComponent'];
        } else {
            $res['result']['address_component'] = array();
        }
        return $res;
    }

    /**
     * 获取百度地图对应的ak
     * @return array[]
     */
    public function getAk(): array
    {
        $service_location = Config::getLocation();
        $service_location = json_decode($service_location, true);
        if (!empty($service_location['baidu'])) {
            $key = array_rand($service_location['baidu'], 1);//获取随机key，随机获取一个 fuxi
            $baidu_json = $service_location['baidu'][$key];
            return array(
                'gongsi' => $baidu_json,//和腾讯保持同一个结构
            );
        }
        return array();
    }

    public function getPlaceSuggestion($ak, $region, $keyword, $number = 0)
    {
        if (empty($region) || empty($keyword)) {
            return $this->returnData(self::ERROR_CODE_NO_RESULT, '参数错误');
        }
        if (($number > $this->place_suggestion_number)) {
            return $this->returnData(self::ERROR_CODE_NO_DATA, '没有对应数据');
        }
// 请求地址
        $api_url = $this->url . '/place/v2/suggestion';

        // 构造请求参数
        $params['region'] = $this->_parseRegion($region);
        $params['query'] = $keyword;
        $params['city_limit'] = 'true';
        $params['output'] = 'json';
        $params['ret_coordtype'] = 'gcj02ll';
        $params['ak'] = $ak['ak'];
        $url = "{$api_url}?" . http_build_query($params);
        $curl = new \Neigou\Curl();
        $curl->time_out = 3;
        $res = $curl->Get($url);
        $res = json_decode($res, true);
        if ($res['status'] == 0) {
            //对齐腾讯返回的参数名称
            $res['data'] = $res['result'];
            unset($res['result']);
            $new_ret = array();
            foreach ($res['data'] as $v) {
                if (empty($v['uid'])) {
                    //没有对应的uid，跳过
                    continue;
                }
                if ((empty($v['province']) && empty($v['city']) && empty($v['district']))) {
                    //省市区三级都是都没有的进行跳过
                    continue;
                }
                if (empty($v['location']['lat']) || empty($v['location']['lng'])) {
                    //没有定位地址的进行跳过
                    continue;
                }
                $v['id'] = $v['uid'];
                $v['title'] = $v['name'];
                $v['category'] = $v['tag'];
                unset($v['uid'], $v['tag'], $v['name']);
                $new_ret[] = $v;
            }
            if (empty($new_ret)) {
                \Neigou\Logger::General('service.TENCENTLBS.baidu',
                    array(
                        //服务返回数据都不可用
                        'action' => 'placeSuggestion.baidu.NOT_DATA', 'sparam1' => '本次查询无可用数据!!', 'url' => $url, 'res' => $res,
                    )
                );
                return $this->returnData(self::ERROR_CODE_NO_DATA, '本次查询无可用数据');
            }
            $res['data'] = $new_ret;
            return $res;
        } elseif ($res['status'] == '4') {
            //接口10000次;
            \Neigou\Logger::General('service.TENCENTLBS.baidu',
                array(
                    //服务当日调用次数已超限，请前往API控制台提升（请优先进行）开发者认证
                    'action' => 'placeSuggestion.baidu.4', 'sparam1' => '接口数量不够用了!!', 'url' => $url, 'res' => $res,
                )
            );
            return $this->returnData(self::ERROR_CODE_NO_EXCESS_QUERY, '剩余查询次数不足');
        } elseif ($res['status'] == '401') {
            \Neigou\Logger::General('service.TENCENTLBS',
                array(
                    'action' => 'placeSuggestion.baidu.401', 'sparam1' => '当前并发量已经超过约定并发配额，限制访问', 'url' => $url, 'res' => $res,
                )
            );
            return $this->getPlaceSuggestion($ak, $region, $keyword, ++$number);
        }
        \Neigou\Logger::General('service.TENCENTLBS.baidu', array('action' => 'placeSuggestion.baidu.err', 'sparam1' => $res, 'url' => $url,));
        return $this->returnData(self::ERROR_CODE_TRIPARTITE_SERVICE, '服务端异常: ' . $res['message']);
    }
}
