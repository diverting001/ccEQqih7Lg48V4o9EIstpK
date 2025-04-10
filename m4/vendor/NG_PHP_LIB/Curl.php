<?php
namespace Neigou;
class Curl{
    private     $_session_id    = 's_v2';
    protected   $_cookies = array();    //cookie信息
    protected   $_headers = array();    //头信息
    protected   $_opt = array();    //curl配置信息
    protected   $_response_info    = array();  //响应信息
    protected   $_response_header    = array();  //响应头信息
    protected   $_request      = null; //curl对象
    private     $__error    = '';       //错误信息
    private     $__version  = array();  //版本配置信息
    private     $__host = '';   //当前网站地址
    private     $__request_url = '';   //被请求网站地址
    public      $save_log = false;    //是否保存日志
    public      $_get_header = false;   //获取curl头信息
    protected   $_header_info = '';     //头信息
    public      $time_out  = 20;   //接口请求超时时间
    private     $_http_code = 0;    //http code


    public function __construct($version=array()){
        $this->__version    = $version;
        $this->__host  = $_SERVER['HTTP_HOST'];
    }

    public function Post($url,$parameter=array()){
        return $this->Request('post',$url,$parameter);
    }

    public function Get($url,$parameter=array()){
        if(!empty($parameter) && is_array($parameter)){
            $url    = strstr($url,'?')?$url.'&':$url.'?';
            $url .=http_build_query($parameter, '', '&');
        }
        return $this->Request('get',$url);
    }

    public function Put($url,$parameter=array()){
        return $this->Request('PUT',$url,$parameter);
    }

    /*
     * @tood 设置cookie信息
     */
    public function SetCookie($k,$v=''){
        if(is_array($k)){
            foreach ($k as $cookie_k=>$cookie_v){
                if(!is_string($cookie_v)) $cookie_v = json_encode($cookie_v);
                $this->_cookies[$cookie_k]  = $cookie_v;
            }
        }else{
            $this->_cookies[$k] = $v;
        }
        return $this;
    }

    /*
     * @tood 设置头信息
     */
    public function SetHeader($k,$v=''){
        if(is_array($k)){
            foreach ($k as $header_k=>$header_v){
                if(!is_string($header_v)) $header_v = json_encode($header_v);
                $this->_headers[$header_k]  = $header_k.': '.$header_v;
            }
        }else{
            $this->_headers[$k] = $k.': '.$v;
        }
        return $this;
    }

    /*
     * @todo 调协curl参数
     */
    public function SetOpt($k,$v=''){
        if(is_array($k)){
            foreach ($k as $opt_k=>$opt_v){
                $this->_opt[$opt_k]  = $opt_v;
            }
        }else{
            $this->_opt[$k] = $v;
        }
        return $this;
    }

    /*
     * @todo 获取错误信息
     */
    public function GetError(){
        return $this->__error;
    }

    /*
     * @todo 请求主体
     */
    protected function Request($method,$url,$parameter=array()){
		if(class_exists('\Neigou\Logger')){
			$this->SetHeader('TRACKID', \Neigou\Logger::QueryId());
		}
        if(empty($url)) return '';
        $this->__request_url    = $url;
        //设置版本信息
        $this->SetVersion($url);
        //设置session_id
        $url_parse = parse_url($url);
        if(stristr($url_parse['host'],'.'.PSR_NEIGOU_COOKIE_DOMAIN)){
            if($_COOKIE[$this->_session_id]){
                $this->SetCookie($this->_session_id,$_COOKIE[$this->_session_id]);
            }
        }
        $this->_request = curl_init();
        //if (is_array($parameter)) $parameter = http_build_query($parameter, '', '&');
        $this->SetRequestMethod($method);
        $this->SetRequestOptions($url,$parameter);
        $this->SetRequestCookie();
        $this->SetRequestHeaders();
        $this->SetRequestOpt();
        //设置超时
        curl_setopt($this->_request, CURLOPT_TIMEOUT,$this->time_out);
        $response = curl_exec($this->_request);
        $this->_response_info   = curl_getinfo($this->_request);
        if (!$response) {
            $response   = false;
            $this->__error = curl_errno($this->_request).' - '.curl_error($this->_request);
        }
        //记录日志
        if($this->save_log == true){
            $this->SaveLog();
        }
        //清空cookie,headers中的信息
        $this->_cookies = array();
        $this->_headers = array();
        $this->_opt = array();

        // header body 分离
        if($this -> _get_header){
            if (curl_getinfo($this->_request, CURLINFO_HTTP_CODE) == '200') {
                list($header, $body) = explode("\r\n\r\n", $response, 2);
            }
            $this -> _header_info = $header;
            $response = $body;
        }
        $this->_http_code   = curl_getinfo($this->_request, CURLINFO_HTTP_CODE);
        curl_close($this->_request);

        return $response;
    }

    /*
     * @todo 获取CURL Response header cookie
     */
    public function getResponseCookie(){
        $string = $this -> _header_info;
        if(!$string)
            return array();
        preg_match_all('|Set-Cookie: (.*);|U', $string, $arr);
        $result = array();
        foreach ($arr[1] as $k => $v) {
            list($key,$value) = explode('=',$v);
            $result[$key] = $value;
        }
        return $result;
    }

    public function GetHttpCode(){
        return $this->_http_code;
    }



    /*
     * @todo 设置请求头信息
     */
    protected function SetRequestHeaders(){
        if(!empty($this->_headers)){
//            print_r($this->_headers);exit;
            curl_setopt($this->_request,CURLOPT_HTTPHEADER,array_values($this->_headers));
        }
    }

    /*
     * @todo 设置请求cookie信息
     */
    protected function SetRequestCookie(){
        $cookie_data    = http_build_query($this->_cookies, '', ';');
        if(!empty($cookie_data)){
            curl_setopt($this->_request, CURLOPT_COOKIE, $cookie_data);
        }
    }

    /*
     * @todo 设置curl_opt信息
     */
    protected function SetRequestOpt(){
        if(!empty($this->_opt)){
            foreach ($this->_opt as $k=>$v){
                curl_setopt($this->_request, $k, $v);
            }
        }
    }

    /*
     * @todo 设置请求类型
     */
    protected function SetRequestMethod($method){
        switch (strtoupper($method)) {
            case 'GET':
                curl_setopt($this->_request, CURLOPT_HTTPGET, true);
                break;
            case 'POST':
                curl_setopt($this->_request, CURLOPT_POST, true);
                break;
            default:
                curl_setopt($this->_request, CURLOPT_CUSTOMREQUEST, $method);
        }
    }

    /*
     * @todo 设置请求
     */
    protected function SetRequestOptions($url,$parameter){
        curl_setopt($this->_request, CURLOPT_URL, $url);
        if (!empty($parameter)){
            if(is_array($parameter)){
                $parameter = http_build_query($parameter, '', '&');
            }else{
                if(!isset($this->_headers['Content-Type'])){
                    $this->SetHeader('Content-Type','text/plain;');
                }
            }
            curl_setopt($this->_request, CURLOPT_POSTFIELDS, $parameter);
        }
        curl_setopt($this->_request, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->_request, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($this->_request, CURLOPT_AUTOREFERER, 1);
        curl_setopt($this->_request, CURLOPT_TIMEOUT, 30);
        if($this -> _get_header)
            curl_setopt($this->_request, CURLOPT_HEADER, true);
    }

    /*
     * @todo 设置版本信息
     */
    protected function SetVersion($url=''){
        if(function_exists('config') && config('laravels.server')){
            $curRequest = app('request');
            $cookies = $curRequest->cookie();
            if($cookies){
                foreach ($cookies as $k=>$v){
                    if(strstr($k,'com_AUTOCI_BRANCH')){
                        $this->SetCookie($k,$v);
                    }
                }
            }
        }else{
            if(!empty($_COOKIE)){
                foreach ($_COOKIE as $k=>$v){
                    if(strstr($k,'com_AUTOCI_BRANCH')){
                        $this->SetCookie($k,$v);
                    }
                }
            }
        }
    }


    /*
     * @todo 记录日志
     */
    protected function SaveLog(){
        if(class_exists('\Neigou\Logger')){
            $url_info   = parse_url($this->__request_url);
            $loger_data = array(
                'sender'    => $this->__host,
                'target'    => $url_info['host'],
                'action'    => $this->__request_url,
                'time_span'    => $this->_response_info['total_time'],
                'result'    => $this->_response_info['http_code'],
                'response_data'    => $this->_response_info,
                'response_header'    => $this->_response_header,
            );
            \Neigou\Logger::Debug('curl',$loger_data);
        }

    }

}
