<?php
/**
 * Created by PhpStorm.
 * User: maojz
 * Date: 17/5/23
 * Time: 14:58
 */

namespace Neigou;

class InternalCall
{

    const DEFAULT_URL = '';
    const VERSION = '1.0.0.0';
    const CONNECTTIMEOUT = 3;
    private $_timeOut;
    protected $_restApiUrl = self::DEFAULT_URL;
    protected $_debugEnabled = false;
    protected $_debugData = null;
    protected $_lastResponseData;
    protected $_apiVersion;

    /**
     *
     */
    public function __construct()
    {
        if (!extension_loaded('curl')){
            die('curl 扩展未安装');
        }
    }

    /**
     *
     * @param $method GET|POST
     * @param $url
     * @param array $data
     * @param array $file_params
     * @return mixed
     */
    protected function _doRestCall($method, $url, $data = array(), $file_params = array())
    {

        $ch = curl_init();
        // Set the HTTP request type, GET / POST most likely.
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        // Set URL to connect to
        curl_setopt($ch, CURLOPT_URL, $url);
        // We want the response returned to us.
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        // Maximum number of seconds allowed to set up the connection.
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, self::CONNECTTIMEOUT);
        // Maximum number of seconds allowed to receive the response.
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->_timeOut);
        // How do we authenticate ourselves? Using HTTP BASIC authentication (https://en.wikipedia.org/wiki/Basic_access_authentication)
        //curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        // To be tidy, we identify ourselves with a User Agent. (not required)
        curl_setopt($ch, CURLOPT_USERAGENT, 'ApiClient/' . self::VERSION .' PHP/'. phpversion());

        if(!empty($_COOKIE)){

            $cookie_key = array_keys($_COOKIE);
            $cookie_param = array();
            foreach($cookie_key as $c_item){
                if(strstr($c_item, '_com_AUTOCI_BRANCH')){
                    $cookie_param[$c_item] = $_COOKIE[$c_item];
                }
            }
            if(!empty($cookie_param)){
                $cookie_param   = http_build_query($cookie_param, '', ';');
                curl_setopt($ch, CURLOPT_COOKIE, $cookie_param);
            }
        }
        if (!empty($file_params)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $file_params);
        } else {
            // Add any data as JSON encoded information
            if ($method != 'GET' && isset($data)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
            //默认数据格式是json
            $headers[] = 'Content-Type: application/json';
        }

        //没有请求id时重新生成一个
        $trace_id = isset($_SERVER['HTTP_TRACKID']) ? $_SERVER['HTTP_TRACKID'] : $this->getTrackId();
        $headers[] = 'TRACKID: '.$trace_id;

        //压测标记
        if(isset($_SERVER['HTTP_TESTING'])){
            $headers[] = 'TESTING: ' . $_SERVER['HTTP_TESTING'];
        }
        //dinggo切换版本header
        if(!empty($this->_apiVersion)){
            $headers[] = "Accept: application/vnd.myapp.{$this->_apiVersion}+json";
        }
        //传输大于1024字节请求
        $headers[] = 'Expect:';
        //echo "<pre>"; print_r($headers);exit();
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        //print_r([$method,$data,$this->_debugEnabled]);exit();
        // Various debug options
        if ($this->_debugEnabled)
        {
            curl_setopt($ch, CURLINFO_HEADER_OUT, true);
            curl_setopt($ch, CURLOPT_HEADER, true);
        }

        // Do the request
        $response = curl_exec($ch);
        // Remember the HTTP status code we receive
        $responseStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        // Any errors? Remember them now.
        $curlError = curl_error($ch);
        $curlErrorNr = curl_errno($ch);

        if ($this->_debugEnabled)
        {
            $this->_debugData['request'] = curl_getinfo($ch, CURLINFO_HEADER_OUT);
            if ($method != 'GET' && isset($data))
                $this->_debugData['request'] .= json_encode($data);
            $this->_debugData['response'] = $response;
            // Strip off header that was added for debug purposes.
            //$response = substr($response, strpos($response, "\r\n\r\n{") + 4);//从json格式位置开始截取
            if ('200' == $responseStatusCode) {
                $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
                $response = substr($response, $headerSize);
            }

        }
        $curlAllInfo = curl_getinfo($ch);

        // And close the cURL handle
        curl_close($ch);
        if ($curlError)
        {
            return array('status' => 'FAIL', 'error_msg' => $curlErrorNr . $curlError .'; curl_getinfo:'.json_encode($curlAllInfo));
        }
        //echo"<pre>";print_r($response);
        // Parse the response as JSON, will be null if not parsable JSON.
        $jsonResponse = json_decode($response, true);
        $result = array();
        $result['status'] = 'OK';
        if(0 < json_last_error())
        {
            $result['status'] = 'FAIL';
            $result['error_msg'] = '接口返回数据非json格式';
        }
        $result['data'] = $jsonResponse;
        $result['trace_id'] = $trace_id;
        $this->_lastResponseData = $jsonResponse;
        return $result;

    }

    public function setDebugEnabled($debugEnabled = true)
    {
        $this->_debugEnabled = (boolean)$debugEnabled;
        if (!$this->_debugEnabled)
            $this->_debugData = null;
    }

    public function getDebugData()
    {
        return $this->_debugData;
    }

    public function setTimeOut($time_out)
    {
        $this->_timeOut = $time_out;
    }
    public function getTimeOut()
    {
        return $this->_timeOut;
    }

    public function setVersion($version)
    {
        $this->_apiVersion = $version;
    }

    public function getVersion()
    {
        return $this->_apiVersion;
    }

    public function call($url, $param = array(), $version, $is_debug, $method = 'GET', $time_out, $file_params = array())
    {
        $this->setDebugEnabled($is_debug);
        $this->setTimeOut($time_out);
        $this->setVersion($version);

        $result = $this->_doRestCall($method, $url, $param, $file_params);
        if($is_debug){

            $result['debug_msg'] = $this->_debugData;
        }
        return $result;
    }

    public function multiCall($request_array)
    {

        $response = $this->_doRestMultiCall($request_array);
        return $response;
    }
    /**
     *
     * @return array|null
     */
    public function getLastResponseData()
    {
        return $this->_lastResponseData;
    }

    /**
     * 请求id
     * @return string
     */
    public function getTrackId()
    {
        static $track_id;
        if(empty($track_id)){
            $track_id = \Neigou\Logger::QueryId();
        }
        return $track_id;
    }

    function _doRestMultiCall($request_array, $wait_usec = 0)
    {
        if (!is_array($request_array))
            return false;
        $wait_usec = intval($wait_usec);
        $data    = array();
        $handle  = array();
        $running = 0;
        $mh = curl_multi_init(); // multi curl handler
        //$i = 0;
        //没有请求id时重新生成一个
        $trace_id = isset($_SERVER['HTTP_TRACKID:']) ? $_SERVER['HTTP_TRACKID:'] : $this->getTrackId();
        foreach($request_array as $item_id => $item) {
            $ch = curl_init();

            // Set the HTTP request type, GET / POST most likely.
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $item['method']);
            // Set URL to connect to
            curl_setopt($ch, CURLOPT_URL, $item['url']);
            // We want the response returned to us.
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            // Maximum number of seconds allowed to set up the connection.
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, self::CONNECTTIMEOUT);
            // Maximum number of seconds allowed to receive the response.
            curl_setopt($ch, CURLOPT_TIMEOUT, $this->_timeOut);
            // How do we authenticate ourselves? Using HTTP BASIC authentication (https://en.wikipedia.org/wiki/Basic_access_authentication)
            //curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            // To be tidy, we identify ourselves with a User Agent. (not required)
            curl_setopt($ch, CURLOPT_USERAGENT, 'ApiClient/' . self::VERSION .' PHP/'. phpversion());

            if(!empty($_COOKIE)){

                $cookie_key = array_keys($_COOKIE);
                $cookie_param = array();
                foreach($cookie_key as $c_item){
                    if('autoci_' == substr($c_item, 0, 7)){
                        $cookie_param[$c_item] = $_COOKIE[$c_item];
                    }
                }
                if(!empty($cookie_param)){
                    curl_setopt($ch, CURLOPT_COOKIE, $cookie_param);
                }
            }
            // Add any data as JSON encoded information
            if ($item['method'] != 'GET' && isset($item['data']))
            {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($item['data']));
            }


            $headers[] = 'Content-Type: application/json';
            $headers[] = 'TRACKID: '.$trace_id;
            if(isset($_SERVER['HTTP_TESTING'])){
                $headers[] = 'TESTING: ' . $_SERVER['HTTP_TESTING'];
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_multi_add_handle($mh, $ch); // 把 curl resource 放进 multi curl handler 里
            $handle[$item_id] = $ch;
        }
        /* 执行 */
        do {
            curl_multi_exec($mh, $running);
            if ($wait_usec > 0) /* 每个 connect 要间隔多久 */
                usleep($wait_usec); // 250000 = 0.25 sec
        } while ($running > 0);
        /* 读写数据 */
        foreach($handle as $item_id => $item) {
            $response  = curl_multi_getcontent($item);
            $curlError = curl_errno($item);
            $responseStatusCode = curl_getinfo($item, CURLINFO_HTTP_CODE);
            $result = array();
            if ($curlError)
            {
                $result['status'] = 'FAIL';
                $result['error_msg'] = $curlError;
            } else {
                $jsonResponse = json_decode($response, true);
                $result['status'] = 'OK';
                if(0 < json_last_error())
                {
                    $result['status'] = 'FAIL';
                    $result['error_msg'] = json_last_error_msg();
                }
                $result['data'] = $jsonResponse;
                $result['trace_id'] = $trace_id;

            }
            $data[$item_id] = $result;

        }
        /* 移除 handle*/
        foreach($handle as $ch) {
            curl_multi_remove_handle($mh, $ch);
        }
        curl_multi_close($mh);
        return $data;
    }


}

class ApiClient
{
    private static $_request ;
    private function __construct(){}
    private function __clone(){}
    private static $_yac ;
    private static $_redis;

    /**
     * 请求服务控制
     * demo
     * $ret = \Neigou\ApiClient::doServiceCall('demo1', 'apiformat.php', ['aa'=>'aa','b'=>'b'],null,null);
     * @param $service_name
     * @param $api
     * @param array $get_params
     * @param array $post_params
     * @param null $api_version
     * @param array $extend_config
     * @param array $file_params
     * @return array
     */
    static function doServiceCall($service_name, $api, $api_version, $get_params = array(), $post_params = array(), $extend_config = array(), $file_params = array())
    {

        $service = self::getOneService($service_name, $api_version);
        if($service){
            $url = self::getServiceUrl($service, $api);
            if(!isset(self::$_request)){
                self::$_request = new InternalCall();
            }

            $method = empty($post_params) && empty($file_params) ? 'GET' : 'POST';
            if('GET' == $method && !empty($get_params)){
                $url .= '?'.http_build_query($get_params);
            }
            $is_debug = isset($extend_config['debug']) ?  $extend_config['debug'] : false;
            $time_out = isset($extend_config['timeout']) ?  $extend_config['timeout'] : API_CLIENT_TIIME_OUT;

            $time_start = self::microtime_float();
            $temp_ret = self::$_request->call($url, $post_params, $api_version, $is_debug, $method, $time_out, $file_params);
            $run_time = sprintf('%0.4f', self::microtime_float() - $time_start);

            $ret = self::ApiClientResult($temp_ret);
            self::setServiceState($service, $ret['service_status']);

            $message = array(
                'service_name' => $service_name,
                'service_url' => $url,
                'used_time' =>$run_time,
                'trace_id' => $ret['trace_id'],
                'service_status' => $ret['service_status'],
                'call_host' => $_SERVER['HTTP_HOST'],
                'request_uri' => $_SERVER['REQUEST_URI'],
                'request_params' => $post_params,
                'response_result' => $temp_ret['data'],
                'response_msg' => isset($temp_ret['error_msg']) ? $temp_ret['error_msg'] : '',
            );

            \Neigou\Logger::ApiClient($service_name, $message, $ret['trace_id']);


            return $ret;
        }

        return  self::ApiClientResult(SERVICE_UNAVAILABLE);
    }


    /**
     * demo
     *  $request_array = array(
     *  array('service_name'=>'demo1', 'api'=>'apiformat.php', 'get_params'=>array('aa'=>'aa','b'=>'b'),'post_params'=>array()),
     *  array('service_name'=>'demo1','api'=>'apiformat2.php', 'get_params'=>array('aa'=>'aa','b'=>'b'),'post_params'=>array()),
     *  );
     *  $_SERVER['HTTP_TESTING'] = 1;
     *  $ret = \Neigou\ApiClient::doServiceCallMulti($request_array);
     * @param $request_array
     * @return array
    */

    static function doServiceCallMulti($request_array)
    {
        $tmp_status = array();
        foreach($request_array as $item_id => &$item){
            if(!isset($item['api_version'])){
                $item['api_version'] = null;
            }
            $service = self::getOneService($item['service_name'], $item['api_version']);
            if(!$service){
                return self::ApiClientResult(SERVICE_UNAVAILABLE);
            }
            $tmp_status[$item_id] = $service;
            $url = self::getServiceUrl($service, $item['api']);
            if(!isset(self::$_request)){
                self::$_request = new InternalCall();
            }

            $method = empty($item['post_params']) ? 'GET' : 'POST';
            if('GET' == $method){
                $item['url'] = $url . '?' . http_build_query($item['get_params']);
            }
            $tmp_status[$item_id]['url'] = $item['url'];
            $tmp_status[$item_id]['data'] = $item['post_params'];
            $item['method'] = $method;

            //$item['extend_config']['debug'] = isset($item['extend_config']['debug']) ?  $request_array['extend_config']['debug'] : false;
            $item['extend_config']['timeout'] = isset($item['extend_config']['timeout']) ?  $item['extend_config']['timeout'] : API_CLIENT_TIIME_OUT;
        }
        $time_start = self::microtime_float();
        $ret = self::$_request->multiCall($request_array);
        $run_time = sprintf('%0.4f', self::microtime_float() - $time_start);

        foreach($ret as $id => $data){
            $formt_ret = self::ApiClientResult($data);
            self::setServiceState($tmp_status[$id], $formt_ret['service_status']);

            $message = array(
                'service_name' => $tmp_status[$id]['service_name'],
                'service_url' => $tmp_status[$id]['url'],
                'used_time' =>$run_time,
                'trace_id' => $data['trace_id'],
                'service_status' => $formt_ret['service_status'],
                'call_host' => $_SERVER['HTTP_HOST'],
                'request_uri' => $_SERVER['REQUEST_URI'],
                'request_params' => $tmp_status[$id]['data'],
                'response_result' => $data['data'],
            );

            \Neigou\Logger::ApiClient($tmp_status[$id]['service_name'], $message, $formt_ret['trace_id']);
        }

        return $ret;
    }


    /**
     * 获取地址
     * @param $name
     * @return string
     */
    static function getOneService($service_name, $api_version = null, $node = null)
    {
        static $service_list;

        if(!isset($service_list)){

            $tmp =  self::syncServiceListFromConfig();
            $service_list = self::getBranchServcieList($tmp);
        }
        if(empty($service_list) || !isset($service_list[$service_name])){
            return false;
        }
        $service_nodes_tmp = $service_list[$service_name];
        $service_nodes = self::getServiceVersionList($service_nodes_tmp, $api_version);

        $service_list_enable = array();
        foreach($service_nodes as $item){
            if('alive' == $item['status']){
                $service_list_enable[] = $item;
            }
        }

        //@todo 暂时随机获取，最终实现按node或build的筛选获取对应服务
        $key =  array_rand($service_list_enable);
        $service = $service_list_enable[$key];
        return $service;
    }

    /**
     * 按分支维度获取服务列表
     * @param $service_list
     * @return array
     */
    static function getBranchServcieList($service_list)
    {
        if(isset($_COOKIE[SERVICE_COOKIE_BRANCH_NAME]) && !empty($_COOKIE[SERVICE_COOKIE_BRANCH_NAME])) {

            $branch =  $_COOKIE[SERVICE_COOKIE_BRANCH_NAME];
            //分支版本和默认版本进行合并，分支版在默认版本基础上进行覆盖
            $default_branch_merage = array_merge($service_list[SERVICE_DEFAULT_BRANCH],$service_list[$branch]);
            return $default_branch_merage;
        }
        return $service_list[SERVICE_DEFAULT_BRANCH];
    }

    /**
     * 按版本维度获取服务列表
     * @param $service_list
     * @param $api_version
     * @return mixed
     */
    static function getServiceVersionList($service_list, $api_version)
    {
        if(!empty($api_version)){
            return $service_list[$api_version];
        } else {
            $key = $service_list[SERVICE_CURRENT_VERSION];
            return $service_list[$key];
        }
    }

    /**
     * 服务请求地址
     * @param $service
     * @return string
     */
    static function getServiceUrl($service, $api)
    {
        //@todo 暂时只支持http协议
        $url = $service['protocol'] .'://'. $service['address'].'/'. ltrim($api, '/'); //exit($url);
        return $url;
    }


    /**
     * 记录服务状态
     * @param $name
     * @return bool
     */
    static function setServiceState($service, $c_code)
    {
        $group = $service['service_name'];
        $service_status = self::getYac(SERVICE_STATUS_KEY);


        if(empty($service_status)) {
            $service_status = array(
                 $service['node'] =>  array(),
            );
        }

        if(!isset($service_status[$group])){
            $service_status[$group] = array();
        }

        if(!isset($service_status[$group][$service['node']])){
            $service_status[$group][$service['node']] = array(
                'succ' => 0,
                'fail' => 0,
            );
        }
        //print_r($service_status); exit();
        $tmp = &$service_status[$group][$service['node']];

        if(in_array($c_code, array(200,204))) {
            $tmp['succ'] = $tmp['succ'] +1;
        } else {
            $tmp['fail'] = $tmp['fail'] +1;
        }
        $tmp['last_time'] = time();

        return self::setYac(SERVICE_STATUS_KEY, $service_status);

    }

    /**
     * 同步服务列表到本地yac的cache
     * @return mixed
     */
    static function syncServiceList()
    {
        $slist = SERVICE_LIST_KEY;
        $redis_service_list = self::getRedis($slist);
        return self::setYac($slist, json_decode($redis_service_list, true));
    }

    /**
     * 从配置中同步服务列表到本地yac的cache
     * @return bool
     */
    static function syncServiceListFromConfig()
    {
        //@todo 暂时从配置文件中获取服务列表，最终从consul中获取
        $service_list = unserialize(SERVICE_LIST);
        if(!is_array($service_list)){
            return false;
        }
        //$slist = SERVICE_LIST_KEY;
        //return self::setYac($slist, $service_list);

        return $service_list;
    }

    /**Config
     * 同步服务可以状态到本地Yac的cache
     */
    static function syncServiceState()
    {
        $slist = SERVICE_LIST_KEY;
        $redis_service_str = self::getRedis($slist);

        $redis_service_list = json_decode($redis_service_str, true);
        if(is_array($redis_service_list)){
            $service_list_tmp = array();
            //循环获取服务端接口
            foreach($redis_service_list as $name => $service){
                $one_sk = YAC_KEY_PREFIX.$name;
                $service_list_tmp[$name] = self::getRedis($one_sk);
            }
            //同步到本地
            foreach($service_list_tmp as $name => $service){
                $one_sk = YAC_KEY_PREFIX.$name;
                $local_service = self::getYac($one_sk);
                if($local_service){
                    $local_service['enable'] = $service['enable'];
                } else {
                    $local_service = array(
                        'succ' => 0,
                        'fail' => 0,
                        'last_time' => 0,
                        'enable' => $service['enable']
                    );
                }
                self::setYac($one_sk, $local_service);
            }
            return true;
        }
        return false;
    }

    static function setYac($key, $value)
    {
        return true;

        /*
        if(!isset(self::$_yac)){
            self::$_yac = new \Yac();
        }
        return self::$_yac->set($key, $value);
        */
    }

    static function getYac($key)
    {
        return array();

        /*
        if(!isset(self::$_yac)){
            self::$_yac = new \Yac();
        }
        return self::$_yac->get($key);
        */
    }
    /*
    static function setRedis($key, $value)
    {
        if(!isset(self::$_redis)){
            self::$_redis = new \redis();
            $ret = self::$_redis->connect(REDIS_SERVER_HOST, REDIS_SERVER_PORT);
            if ($ret && !empty(REDIS_AUTH)){
                self::$_redis->auth(REDIS_AUTH);
            }
        }
        return self::$_redis->set(REDIS_SERVER_PREFIX.$key, $value);
    }

    static function getRedis($key)
    {
        if(!isset(self::$_redis)){
            self::$_redis = new \redis();
            $ret = self::$_redis->connect(REDIS_SERVER_HOST, REDIS_SERVER_PORT);
            if ($ret && !empty(REDIS_AUTH)){
                self::$_redis->auth(REDIS_AUTH);
            }
        }
        return self::$_redis->get(REDIS_SERVER_PREFIX.$key);
    }
*/
    static function ApiClientResult($ret)
    {
        $result = array();

        if($ret['status'] == SERVICE_OK){
            $result['service_status'] = SERVICE_OK;
            $result['service_result']['error_code'] = 'SUCCESS';
        } else {
            $result['service_status'] = SERVICE_UNAVAILABLE;
            $result['service_result']['error_code'] = '';
        }

        if(isset($ret['error_msg'])){
            $result['service_result']['error_msg'] = $ret['error_msg'];
        } else {
            $result['service_result']['error_msg'] = '';
        }

        if(isset($ret['debug_msg'])){
            $result['service_result']['debug_msg'] = $ret['debug_msg'];
        } else {
            $result['service_result']['debug_msg'] = '';
        }

        $result['service_result']['timestamp'] = time();


        if(isset($ret['trace_id'])){
            $result['trace_id']= $ret['trace_id'];
        } else {
            $result['trace_id']= '';
        }

        if(isset($ret['data'])){
            $result['service_data']= $ret['data'];
        } else {
            $result['service_data']= '';
        }
        $result['extend_info'] = '';
        return $result;
    }

    static function microtime_float()
    {

        $mtime = microtime();

        $mtime = explode(' ', $mtime);

        return $mtime[1] + $mtime[0];

    }
}
