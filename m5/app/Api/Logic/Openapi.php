<?php
/**
 * Created by PhpStorm.
 * User: chuanbin
 * Date: 2017/11/8
 * Time: 14:38
 */

namespace App\Api\Logic;

class Openapi{
    private $__curl   = null;
    private $__token_url    = '/Authorize/V1/OAuth2/Platform/token';

    static $OpenapiSign = OPENAPI_SIGN;

    public function __construct(){
        $this->__curl = new \Neigou\Curl();
    }


    public function Query($uri,$data){
        if(empty($uri)) return false;
        $result = $this->QueryV2($uri,$data);
        if ($result['Result'] == 'false')
        {
            \Neigou\Logger::Debug('service.openapi.Query', array(
                'action'    => 'QueryV2.openapi',
                'uri'       =>  $uri,
                'data'      =>  json_encode($data),
                'result'    =>  json_encode($result),
            ));
        }

        if ($result['ErrorId'] == 'INVALID_ACCESS_TOKEN' || $result['ErrorMsg'] == 'INVALID_ACCESS_TOKEN')
        {
            $access_token   = $this->GetAccessToken();
            if(empty($access_token)) return false;
            $http_url   = config('neigou.OPENAPI_DOMIN').$uri;
            $this->__curl->SetHeader(['AUTHORIZATION'=>'Bearer '.$access_token]);
            $post_data=array(
                'data' => json_encode($data)
            );
            $result = $this->__curl->Post($http_url,$post_data);
            $result = json_decode($result,true);
        }
        return $result;
    }


    public function GetAccessToken(){
        $data = array(
            'grant_type' => 'client_credentials',
            'client_id' => config('neigou.OPENAPI_CLIENT_ID'),
            'client_secret' => config('neigou.OPENAPI_CLIENT_SECRET'),
            'scope' => 'internal',
        );
        $result = $this->__curl->Post(config('neigou.OPENAPI_DOMIN').$this->__token_url, $data);
        $result = json_decode($result, true);
        $access_token = $result['Data']['access_token'];
        return $access_token;
    }

    public function QueryV2($uri,$data){
        if (empty($uri)) {
            return false;
        }

        $access_token  = $this->GetAccessTokenV2();
        if (!$access_token) {
            return false;
        };

        $http_url   = config('neigou.OPENAPI_DOMIN').$uri;
        $this->__curl->SetHeader(['AUTHORIZATION'=>'Bearer '.$access_token]);
        $post_data=array(
            'data' => json_encode($data)
        );
        $result = $this->__curl->Post($http_url,$post_data);
        $result = json_decode($result,true);
        return $result;
    }


    public function GetAccessTokenV2(){
        //获取缓存
        $data = array(
            'grant_type' => 'client_credentials',
            'client_id' => config('neigou.OPENAPI_CLIENT_ID'),
            'client_secret' => config('neigou.OPENAPI_CLIENT_SECRET'),
            'scope' => 'internal',
        );

        //链接redis
        $config = array(
            'host' => config('neigou.REDIS_THIRD_WEB_HOST'),
            'port' => config('neigou.REDIS_THIRD_WEB_PORT'),
            'auth' => config('neigou.REDIS_THIRD_WEB_PWD'),
        );
        $redis = new Redis($config);

        //获取缓存
        $key = 'openapi_access_token_'.md5(json_encode($data));
        $result = array();
        $redis->fetch('service_', $key, $result);
        if (!empty($result['access_token'])) {
            return $result['access_token'];
        }

        //实时获取
        $result = $this->__curl->Post(config('neigou.OPENAPI_DOMIN').$this->__token_url, $data);
        $result = json_decode($result, true);
        if (!$result['Data']['access_token']) {
            return false;
        }

        //设置缓存
        $redis->store('service_', $key, $result['Data'], $result['Data']['expires_in'] - 60);
        return $result['Data']['access_token'];
    }


    private function check_token($arr) {
        $token = $arr["token"];
        unset($arr["token"]);
        ksort($arr);
        $sign_ori_string = "";
        foreach($arr as $key=>$value) {
            if (!empty($value) && !is_array($value)) {
                if (!empty($sign_ori_string)) {
                    $sign_ori_string .= "&$key=$value";
                } else {
                    $sign_ori_string = "$key=$value";
                }
            }
        }
        $sign_ori_string .= ("&key=".self::$OpenapiSign);
        return  $token == strtoupper(md5($sign_ori_string)) ? true : false;
    }

    private function get_token($arr) {
        ksort($arr);
        $sign_ori_string = "";
        foreach($arr as $key=>$value) {
            if (!empty($value) && !is_array($value)) {
                if (!empty($sign_ori_string)) {
                    $sign_ori_string .= "&$key=$value";
                } else {
                    $sign_ori_string = "$key=$value";
                }
            }
        }
        $sign_ori_string .= ("&key=".self::$OpenapiSign);
        return  strtoupper(md5($sign_ori_string));
    }

    /**
     * @param $arr
     */
    public function CurlOpenApi($api_url,$arr){
        $arr['token']=$this->get_token($arr);
        // $this->__curl->SetOpt(CURLOPT_SSL_VERIFYPEER, true);
        $result =  $this->__curl->Post($api_url,$arr);
        return json_decode($result ,true) ;
    }
    public function aa() {
        "aa" ;
    }
}
