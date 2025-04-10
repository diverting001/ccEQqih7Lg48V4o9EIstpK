<?php

namespace Neigou;

/**
 * Description of SwooleProxyClient
 *
 * @author liuweidong
 */
class SwooleProxyClient {
    const VERSION = '1.0.0';
    const SWOOLE_PROXY_SRV_REQ_FIELD_ID = 'swoole_proxy_srv_list';
    
    //put your code here
    private $timeout = 5;
    private $connect_timeout = 3;
    private $enable_debug = false;
    private $curl_error = '';
    private $swoole_proxy_url;

    private $curl;
    private $srv_list = array();
    private $ng_srv_names = array();
    private $auto_clear_srv_list = true;
    private $track_id;
    private $response_headers = array();
    private $only_ret_body = true;

    private function __construct(){  
    }
 
    static private $instance;
    static public function getInstance(){
        if(!self::$instance){
            self::$instance = new self();
        }
        return self::$instance;
    }
 
    public function setTimeOut($timeout){
        $this->timeout = $timeout;
    }
    
    public function getResponseHeaders($request_id){
        return isset($this->response_headers[$request_id]) ? $this->response_headers[$request_id] : array();
    }

    public function setResponseHeaders($request_id,$data){
        $this->response_headers[$request_id] = $data;
    }

    public function getTimeOut(){
        return $this->timeout;
    }
    
    public function setSwooleProxyUrl($swoole_proxy_url){
        $this->swoole_proxy_url = $swoole_proxy_url;
    }
    
    public function getSwooleProxyUrl(){
        return $this->swoole_proxy_url;
    }
    
    public function setAutoClearSrvList($auto_clear_srv_list){
        $this->auto_clear_srv_list = (boolean)$auto_clear_srv_list;
    }

    /**
     * 请求id
     * @return string
     */
    public function getTrackId()
    {
        if(empty($this->track_id)){
            $this->track_id = Logger::QueryId();
        }
        return $this->track_id;
    }
    
    public function addRequest($request_id,$url,$params = array(),$method = 'POST',$timeout = 0,$headers = array(),$cookies = array(),$need_decode_body = 1){
        $srv = array(
            'request_id'=>$request_id,
            'url'=>$url,
            'body'=> json_encode($params),
            'method'=>$method,
            'timeout'=> $timeout > 0 ? $timeout : $this->timeout,
            'headers'=>$headers,
            'cookies'=>$cookies,
            'need_decode_body'=>$need_decode_body,
        );
        
        $this->srv_list[] = $srv;
        return true;
    }
    
    public function getSrvList(){
        return $this->srv_list;
    }
    
    public function clearSrvList(){
        $this->srv_list = array();
    }
    
    public function setDebugEnabled($enable_debug)
    {
        $this->enable_debug = (boolean)$enable_debug;
    }
    
    public function getCurlInstance(){
        if(!$this->curl) {
            $this->curl = new Curl();
        }
        return $this->curl;
    }

    /*
     * @tood 设置头信息
     */
    public function SetHeader($k,$v=''){
        $this->getCurlInstance()->SetHeader($k,$v);
    }

    /*
     * @tood 设置cookie信息
     */
    public function SetCookie($k,$v=''){
        $this->getCurlInstance()->SetCookie($k,$v);
    }

    private function Post($url,$parameter=array()){
        //100-continue
        $this->SetHeader('Expect','');
        $this->SetHeader('Content-Type','application/x-www-form-urlencoded');
        $resp = $this->getCurlInstance()->Post($url,$parameter);
        if($this->enable_debug){
            $this->curl_error = $this->getCurlInstance()->GetError();
        }
        return $resp;
    }
    
    public function GetError(){
        return $this->curl_error;
    }
    
    public function execute($use_curl_multi = 0){
        $data = array();
        if($use_curl_multi){
            $data = $this->executeByCurlMulti();
        }else{
            if(empty($this->swoole_proxy_url)){
                throw new \Exception("未设置Swoole代理URL");
            }
            $data = $this->executeBySw();
        }
        $this->clear();
        return $data;
    }
    
    private function executeByCurlMulti($wait_usec = 0){
        $result    = array();
        $handle  = array();
        
        $running = 0;
        $mh = curl_multi_init(); // multi curl handler

        foreach ($this->srv_list as $srv){
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_HEADER, true);
            // Set the HTTP request type, GET / POST most likely.
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $srv['method']);
            // Set URL to connect to
            curl_setopt($ch, CURLOPT_URL, $srv['url']);
            // We want the response returned to us.
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            // Maximum number of seconds allowed to set up the connection.
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->connect_timeout);
            // Maximum number of seconds allowed to receive the response.
            
            $this->timeOut = $this->timeOut > $srv['timeout'] ? $this->timeOut : $srv['timeout'];
            curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeOut);
            // How do we authenticate ourselves? Using HTTP BASIC authentication (https://en.wikipedia.org/wiki/Basic_access_authentication)
            //curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            // To be tidy, we identify ourselves with a User Agent. (not required)
            curl_setopt($ch, CURLOPT_USERAGENT, 'SwooleProxyClient/' . self::VERSION .' PHP/'. phpversion());
            
            switch ($srv['method']){
                case 'GET':
                    break;
                case 'POST':
                    curl_setopt($ch, CURLOPT_POST, true);//设置为POST方式
                    if($srv['need_decode_body']){
                        $post_data = json_decode($srv['body'],true);
                        curl_setopt($ch,CURLOPT_POSTFIELDS,$post_data);
                    }else{
                        curl_setopt($ch,CURLOPT_POSTFIELDS,$srv['body']);
                    }
                    break;
                default:
                    break;
            }
            
            if(!empty($srv['headers'])){
                $headers = array();
                foreach($srv['headers'] as $key=>$val){
                    $headers[] = $key.': '.$val;
                }
                curl_setopt($ch, CURLOPT_HTTPHEADER,$headers);
            }
            
            if(!empty($srv['cookies'])){
                $cookies = array();
                foreach($srv['cookies'] as $key=>$val){
                    $cookies[] = $key.'='.$val;
                }
                curl_setopt($ch, CURLOPT_COOKIE, implode(';',$cookies));
            }
            
            curl_multi_add_handle($mh,$ch);
            $handle[$srv['request_id']] = $ch;
        }

        // 执行批处理句柄
        do {
            curl_multi_exec($mh,$running);
            if($wait_usec > 0){
                usleep($wait_usec);
            }else usleep(10000);
        } while ($running > 0);
        
        $result['status'] = 'OK';
        /* 读写数据 */
        foreach($handle as $req_id => $item) {
            $response  = curl_multi_getcontent($item);

            $curl_error = curl_errno($item).' - '.curl_error($item);
            $status_code = curl_getinfo($item, CURLINFO_HTTP_CODE);
            list($header_info, $body) = explode("\r\n\r\n", $response,2);
            
            //解析服务
            if($this->only_ret_body){
                $format_header_info = function($header_info){
                    $headers = array();
                    $cookies = array();
                    $tmp_arr = explode("\r\n",$header_info);
                    foreach($tmp_arr as $key=>$key_val){
                        if($key > 0){
                            list($name,$name_val) = explode(": ",$key_val,2);
                            $name = strtolower($name);
                            $headers[$name] = $name_val;
                            if($name == 'set-cookie'){
                               $cookie_tmp_arr = explode(";",$name_val);
                               foreach($cookie_tmp_arr as $cookie_val){
                                    list($c_name,$c_name_val) = explode("=",$cookie_val,2);
                                    if(strtolower($c_name) != 'path'){
                                       $cookies[$c_name] = $c_name_val;
                                    }
                               }
                            }
                        }
                    }
                    return array('headers'=>$headers,'cookies'=>$cookies);
                };
                $header_info_data = $format_header_info($header_info);
                $response_headers = array(
                    'status_code'=>$status_code,
                    'headers'=>$header_info_data['headers'],
                    'cookies'=>$header_info_data['cookies'],
                );
                $this->setResponseHeaders($req_id,$response_headers);
            }
            
            //内购服务返回格式化
            if(in_array($req_id,$this->ng_srv_names)){
                $ret_data = json_decode($body,true);
                if(0 < json_last_error()){
                    $ret_data['status'] = 'FAIL';
                    $ret_data['error_msg'] = '接口返回数据非json格式';
                }else{
                    $ret_data['status'] = 'OK';
                }
                $result['data'][$req_id] = ApiClient::ApiClientResult($ret_data);
            }else{
                $result['data'][$req_id] = $body;
            }
        
            if($this->enable_debug){
                $result['debug_msg'][$req_id] = $curl_error;
            }            
        }
        
        /* 移除 handle*/
        foreach($handle as $ch) {
            curl_multi_remove_handle($mh, $ch);
        }
        curl_multi_close($mh);
        
        return $result;
    }

    private function executeBySw(){
        $parameter = array(
            self::SWOOLE_PROXY_SRV_REQ_FIELD_ID=>json_encode($this->srv_list)
        );
        
        $response = $this->Post($this->swoole_proxy_url,$parameter);
        
        $resp = json_decode($response, true);
        $result = array();
        $result['status'] = 'OK';
        if(0 < json_last_error()){
            $result['status'] = 'FAIL';
            $result['error_msg'] = '接口返回数据非json格式';
            Logger::General('neigou.SwooleProxyClient.parse',array(
                'swoole_proxy_url'=>$this->swoole_proxy_url,
                'parameter'=>$$parameter,
                'response'=>$response,
                'this->curl_error'=>$this->curl_error,
                )
            );
            return $result;
        }elseif(empty($resp['data'])){
            $result['status'] = 'FAIL';
            $result['error_msg'] = isset($resp['msg']) ? $resp['msg'] : '接口返回数据为空';
        }else{
            //
        }
        
        //解析服务
        if($this->only_ret_body){
            $tmp_ret = array();
            foreach($resp['data'] as $req_id=>$row){
                $response_headers = array(
                    'status_code'=>$row['code'],
                    'headers'=>$row['headers'],
                    'cookies'=>$row['cookies'],
                );
                $this->setResponseHeaders($req_id,$response_headers);
                $tmp_ret['data'][$req_id] = $row['body'];
            }
            $resp = $tmp_ret;
        }
        //内购服务返回格式化
        if(count($this->ng_srv_names) > 0){
            foreach($this->ng_srv_names as $request_id){
                if(isset($resp['data'][$request_id])){
                    $ret_data = json_decode($resp['data'][$request_id],true);
                    if(0 < json_last_error()){
                        $ret_data['status'] = 'FAIL';
                        $ret_data['error_msg'] = '接口返回数据非json格式';
                    }else{
                        $ret_data['status'] = 'OK';
                    }
                    $resp['data'][$request_id] = ApiClient::ApiClientResult($ret_data);
                }
            }
        }
        
        if($this->enable_debug){
            $result['debug_msg'][] = $this->curl_error;
        }
        
        $result['data'] = $resp['data'];
        return $result;
    }
    
    public function clear(){
        if($this->auto_clear_srv_list){
            $this->clearSrvList();
            $this->ng_srv_names = array();
        }
    }

    public function doServiceCall($service_name, $api, $api_version, $get_params = array(), $post_params = array(), $extend_config = array())
    {
        $service = ApiClient::getOneService($service_name, $api_version);
        if($service){
            $url = ApiClient::getServiceUrl($service, $api);
            $method = empty($post_params) ? 'GET' : 'POST';
            if('GET' == $method && !empty($get_params)){
                $url .= '?'.http_build_query($get_params);
            }
            $is_debug = isset($extend_config['debug']) ?  $extend_config['debug'] : false;
            $this->setDebugEnabled($is_debug);
            $timeout = isset($extend_config['timeout']) ?  $extend_config['timeout'] : $this->timeout;
            
            $request_id = $service_name.'|'.$api;
            $header = array();
            //默认数据格式是json
            $header['Content-Type'] = 'application/json';

            //没有请求id时重新生成一个
            $trace_id = isset($_SERVER['HTTP_TRACKID']) ? $_SERVER['HTTP_TRACKID'] : $this->getTrackId();
            $header['TRACKID'] = $trace_id;

            //压测标记
            if(isset($_SERVER['HTTP_TESTING'])){
                $header['TESTING'] = $_SERVER['HTTP_TESTING'];
            }
            //dinggo切换版本header
            if(!empty($this->_apiVersion)){
                $header['Accept'] = "application/vnd.myapp.{$this->_apiVersion}+json";
            }
            $cookie = array();
            if(!empty($_COOKIE)){
                $cookie_key = array_keys($_COOKIE);
                $cookie_param = array();
                foreach($cookie_key as $c_item){
                    if(strstr($c_item, 'neigou_com_AUTOCI_BRANCH')){
                        $cookie_param[$c_item] = $_COOKIE[$c_item];
                    }
                }
                $cookie = $cookie_param;
            }
            $this->addRequest($request_id,$url,$post_params,$method,$timeout,$header,$cookie,$need_decode_body = 0);
            $this->ng_srv_names[] = $request_id;
        }
    }
    
    public function microtime_float()
    {
        $mtime = microtime();
        $mtime = explode(' ', $mtime);
        return $mtime[1] + $mtime[0];
    }
    

}
