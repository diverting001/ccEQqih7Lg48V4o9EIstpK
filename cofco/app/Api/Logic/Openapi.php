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

    public function __construct(){
        $this->__curl = new \Neigou\Curl();
    }


    public function Query($uri,$data){
        if(empty($uri)) return false;
        $access_token   = $this->GetAccessToken();
        if(empty($access_token)) return false;
        $http_url   = config('neigou.OPENAPI_DOMIN').$uri;
        $this->__curl->SetHeader(['AUTHORIZATION'=>'Bearer '.$access_token]);
        $post_data=array(
            'data' => json_encode($data)
        );
        $result = $this->__curl->Post($http_url,$post_data);
        $result = json_decode($result,true);
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
}
