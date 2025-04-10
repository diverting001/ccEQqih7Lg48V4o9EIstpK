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
    public      $_header_info = '';     //头信息
    public      $time_out  = 20;   //接口请求超时时间
    private     $_http_code = 0;    //http code


    public function __construct($version=array()){
        $this->__version    = $version;
        $this->__host  = isset($_SERVER['HTTP_HOST']) ?$_SERVER['HTTP_HOST'] :'' ;
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

    /**
     * curl multi方式请求
     * $urls = [
     *  0 => ['url'=>'xxxxxx','params'=> ['one'=>'123','two'=>123,....]],
     *  1 => ['url'=>'xxxxxx','params'=> ['one'=>'123','two'=>123,....]],
     * ];
     * @param $urls
     * @param $method
     * @return array
     */
    public function multiRequest($urls, $method = 'POST')
    {
        $ip = $this->getChinaIP();
        if (class_exists('\Neigou\Logger')) {
            $this->SetHeader('TRACKID', \Neigou\Logger::QueryId());
        }
        //声明一个并发权柄
        $mh = curl_multi_init();
        $urlHandlers = array();//记录子权柄
        $urlData = array();//记录最终结果
        // 初始化多个请求句柄为一个
        foreach ($urls as $key => $value) {
            //声明一个子权柄
            $this->_request = curl_init();

            //设置header
            $this->SetHeader(array(
                "X-FORWARDED-FOR"=>$ip,
                "CLIENT-IP"=>$ip,
            ));
            if (!empty($value['headers'])) {
                $this->SetHeader($value['headers']);
            }
            $this->SetRequestMethod($method);
            $this->SetRequestOptions($value['url'], $value['params']);
            $this->SetRequestCookie();
            $this->SetRequestHeaders();
            $this->SetRequestOpt();

            //配置过去时间
            curl_setopt($this->_request, CURLOPT_TIMEOUT, $this->time_out);
            $urlHandlers[$key] = $this->_request;
            //向curl批处理会话中添加单独的curl句柄
            curl_multi_add_handle($mh, $this->_request);
        }
        $active = null;
        // 检测操作的初始状态是否OK，CURLM_CALL_MULTI_PERFORM为常量值-1
        do {
            //运行当前 cURL 句柄的子连接
            // 返回的$active是活跃连接的数量，$mrc是返回值，正常为0，异常为-1
            $mrc = curl_multi_exec($mh, $active);
        } while ($mrc == CURLM_CALL_MULTI_PERFORM);
        // 如果还有活动的请求，同时操作状态OK，CURLM_OK为常量值0
        while ($active && $mrc == CURLM_OK) {
            // 持续查询状态并不利于处理任务，每50ms检查一次，此时释放CPU，降低机器负载
            usleep(50000);
            // 如果批处理句柄OK，重复检查操作状态直至OK。select返回值异常时为-1，正常为1（因为只有1个批处理句柄）
            //阻塞直到cURL批处理连接中有活动连接。
            //php版本问题会导致 curl_multi_select() 永远等于-1进入死循环
            curl_multi_exec($mh, $active);
            if (curl_multi_select($mh) != -1) {
                do {
                    $mrc = curl_multi_exec($mh, $active);
                } while ($mrc == CURLM_CALL_MULTI_PERFORM);
            }
        }
        // 获取返回结果
        foreach ($urlHandlers as $index => $ch) {
            $urlData[$index] = curl_multi_getcontent($ch);
            // 移除单个curl句柄
            curl_multi_remove_handle($mh, $ch);
        }
        curl_multi_close($mh);
        return $urlData;
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
        $source = 'SCRIPT';
        if(function_exists('config') && config('laravels.server')){
            $curRequest = app('request');
            $cookies = $curRequest->cookie();
            if($cookies){
                foreach ($cookies as $k=>$v){
                    if(strstr($k,'com_AUTOCI_BRANCH')){
                        $this->SetCookie($k,$v);
                    }
                    if(strstr($k,'com_API_CLIENT_LOG_DEBUG')){
                        $this->SetCookie($k,$v);
                    }
                    if(strstr($k,'REQUEST_SOURCE')){
                        $source = in_array($v, array('USER', 'INTERNAL')) ? 'INTERNAL' : 'SCRIPT';
                    }
                }
            }
        }else{
            if(!empty($_COOKIE)){
                foreach ($_COOKIE as $k=>$v){
                    if(strstr($k,'com_AUTOCI_BRANCH')){
                        $this->SetCookie($k,$v);
                    }
                    if(strstr($k,'com_API_CLIENT_LOG_DEBUG')){
                        $this->SetCookie($k,$v);
                    }
                    if(strstr($k,'REQUEST_SOURCE')){
                        $source = in_array($v, array('USER', 'INTERNAL')) ? 'INTERNAL' : 'SCRIPT';
                    }
                }
            }
        }
        $this->SetCookie('REQUEST_SOURCE', $source);
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

    public function getInfo()
    {
        return $this->_response_info;
    }

    /**
     * 获取国内号段的IP地址
     * @return string
     */
    public function getChinaIP()
    {
        $ip_long = array(
            array('607649792', '608174079'), // 36.56.0.0-36.63.255.255
            array('1038614528', '1039007743'), // 61.232.0.0-61.237.255.255
            array('1783627776', '1784676351'), // 106.80.0.0-106.95.255.255
            array('2035023872', '2035154943'), // 121.76.0.0-121.77.255.255
            array('2078801920', '2079064063'), // 123.232.0.0-123.235.255.255
            array('-1950089216', '-1948778497'), // 139.196.0.0-139.215.255.255
            array('-1425539072', '-1425014785'), // 171.8.0.0-171.15.255.255
            array('-1236271104', '-1235419137'), // 182.80.0.0-182.92.255.255
            array('-770113536', '-768606209'), // 210.25.0.0-210.47.255.255
            array('-569376768', '-564133889'), // 222.16.0.0-222.95.255.255
        );
        $rand_key = mt_rand(0, 9);
        return long2ip(mt_rand($ip_long[$rand_key][0], $ip_long[$rand_key][1]));
    }
}
