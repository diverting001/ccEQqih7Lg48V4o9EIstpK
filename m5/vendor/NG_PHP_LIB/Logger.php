<?php
namespace Neigou;
class Logger{
    static public $SAVE_FOLDER_NAME = '';  //保存文件夹名
    static private $__log_root  = '/var/log/php/';    //文件保存目录
    static private $__id;    //执行标识
    static private $__is_first  = array(        //是否为第一次执行
        'debug'     => false,
        'general'   => false,
        'core'      => false,
        'apiclient' => false,
    );
    static private $__standard_row  = array(    //标准列
        'success'   => 'integer',
        'action'    => 'string',
        'result'    => 'string',
        'action_type' => 'string',
        'bn'        => 'string',
        'related_bn'=> 'string',
        'sender'    => 'string',
        'target'    => 'string',
        'step'      => 'string',
        'reason'    => 'string',
        'data'      => 'string',
        'remark'    => 'string',
        'time_span' => 'double',
        'user_id'   => 'integer',
        'company_id'    => 'integer',
        'fparam1'   => 'double',
        'fparam2'   => 'double',
        'fparam3'   => 'double',
        'fparam4'   => 'double',
        'fparam5'   => 'double',
        'iparam1'   => 'integer',
        'iparam2'   => 'integer',
        'iparam3'   => 'integer',
        'iparam4'   => 'integer',
        'iparam5'   => 'integer',
        'sparam1'   => 'string',
        'sparam2'   => 'string',
        'sparam3'   => 'string',
        'sparam4'   => 'string',
        'sparam5'   => 'string',

    );

    //apiclient
    static private $__apiclient_row  = array(
        'service_name'   => 'string',
        'service_url'    => 'string',
        'used_time'        => 'double',
        'trace_id'=> 'string',
        'service_status' => 'string',
        'call_host' => 'string',
        'request_uri' => 'string',
        'request_params' => 'string',
        'response_result' => 'string',
    );

    static private $__source = '';    //来源信息
    static private $__log_levels    = array(    //执行日志级别
            1 => 'EMERG',
            2 => 'ALERT',
            3  => 'CRIT',
            4   => 'ERR',
            5 => 'WARNING',
            6 => 'NOTICE',
            7  => 'INFO',
            8 => 'DEBUG'
    );

    /*
     * @todo debug 日志
     */
    static public function Debug($report_name,$message,$__id=null,$levels=6){
        if(!is_null($__id)) self::$__id = $__id;
        self::Main('debug',$report_name,$message,$levels);
        return self::$__id;
    }
    /*
     * @todo GENERAL 日志
     */
    static public function General($report_name,$message,$__id=null,$levels=6){
        if(!is_null($__id)) self::$__id = $__id;
        self::Main('general',$report_name,$message,$levels);
        return self::$__id;
    }
    /*
     * @todo CORE 日志
     */
    static public function Core($report_name,$message,$__id=null,$levels=6){
        if(!is_null($__id)) self::$__id = $__id;
        self::Main('core',$report_name,$message,$levels);
        return self::$__id;
    }

    /*
     * @todo apiClient 日志
     */
    static public function ApiClient($report_name,$message,$__id=null,$levels=6){
        if(!is_null($__id)) self::$__id = $__id;
        self::ApiClientMain('apiclient',$report_name,$message,$levels);
        return self::$__id;
    }


    /*
     * @todo 获取本次执行id
     */
    static public function QueryId(){
        if(empty(self::$__id)){
            self::Init();
        }
        return self::$__id;
    }


    /*
     * @todo 执行主体
     */
    static private function Main($report_type,$report_name,$message,$levels){
        $data   = array();
        $message    = self::FormatMessageData($message);
        if(!is_array($message)) $data['content']   = self::JsonDncode($message);
        else $data  = $message;
        $firse_message  = array();
        self::Init();   //生成基础信息
        //第一次生成日志
        if(!self::$__is_first[$report_type]){
            $firse_message  = self::FirsetMessage();
            self::$__is_first[$report_type] = true;
        }
        //格式化输入信息
        $message    = self::FormatMessage($data);
        //生成日志所需格式
        $message    = self::MakeMessage($report_name,array_merge($message,$firse_message),$levels);
        //保存日志
        self::LogSave($report_type,$message);
    }


    /*
     * @todo apiclient执行主体
     */
    static private function ApiClientMain($report_type,$report_name,$message){
        $data   = array();
        $message    = self::FormatMessageData($message);
        if(!is_array($message)) $data['content']   = self::JsonDncode($message);
        else $data  = $message;
        $firse_message  = array();
        self::Init();   //生成基础信息
        //第一次生成日志
        if(!self::$__is_first[$report_type]){
            $firse_message  = self::FirsetMessage();
            self::$__is_first[$report_type] = true;
        }
        //格式化输入信息
        $message    = self::ApiClientFormatMessage($data);
        //生成日志所需格式
        $message    = self::MakeMessage($report_name,array_merge($message,$firse_message),null);
        //保存日志
        self::LogSave($report_type,$message);
    }
    /*
     * @todo 第一次执行数据
     */
    static private function FirsetMessage(){
        $data['session']    = @self::JsonDncode($_SESSION);
        $data['get']        = self::JsonDncode($_GET);
        $data['post']       = self::JsonDncode($_POST);
        $data['cookie']       = self::JsonDncode($_COOKIE);
        $data['url']       = isset($_SERVER['REQUEST_URI'])?$_SERVER['REQUEST_URI']:'';
        return $data;
    }

    /*
     * @todo 基础信息
     */
    static private function Init(){
        if(empty(self::$__id)) self::$__id = (isset($_SERVER['HTTP_TRACKID'])) ? $_SERVER['HTTP_TRACKID'] : uniqid();
        if(empty(self::$__source)) self::$__source   = self::GetClientIP();
    }
    
    /*
     * @todo 格式化输入信息
     */
    static private function FormatMessage($message){
        $new_message    = array();
        if(empty($message)) return $new_message;
        foreach ($message as $k=>$v){
            if(isset(self::$__standard_row[$k])){
                if(gettype($v) != self::$__standard_row[$k]){
                    $v  = self::FormatConversion($v,self::$__standard_row[$k]);
                }
                $new_message[$k]    = $v;
                unset($message[$k]);
            }
        }
        if(!empty($message))$new_message['other']    = self::JsonDncode($message);
        unset($message);
        return  $new_message;

    }

    /*
     * @todo 格式化输入信息
     */
    static private function ApiClientFormatMessage($message){
        $new_message    = array();
        if(empty($message)) return $new_message;
        foreach ($message as $k=>$v){
            if(isset(self::$__apiclient_row[$k])){
                if(gettype($v) != self::$__apiclient_row[$k]){
                    $v  = self::FormatConversion($v,self::$__apiclient_row[$k]);
                }
                $new_message[$k]    = $v;
                unset($message[$k]);
            }
        }
        if(!empty($message))$new_message['other']    = self::JsonDncode($message);
        unset($message);
        return  $new_message;

    }

    /*
     * @todo 格式化数据类型
     */
    static private function FormatConversion($data,$type){
        if($type == 'integer'){
            return intval($data);
        }else if($type == 'string'){
            return strval($data);
        }else if($type == 'double'){
            return floatval($data);
        }else{
            return '';
        }
    }

    /*
     * @todo 生成log格式数据
     */
    static public function MakeMessage($report_name,$message,$levels){
        $message['report_name'] = $report_name;
        //$message['levels']      = $levels;
        $message['query_id']    = self::$__id;
        $message['source']      = self::$__source;
        $levels = isset(self::$__log_levels[$levels])?self::$__log_levels[$levels]:self::$__log_levels[6];
        $message    = self::JsonDncode($message);
        $message = sprintf("%s\t%s\t%s\n", date("c"), $levels, $message);
        return $message;
    }

    /*
     * @todo 保存日志
     */
    static private function LogSave($report_type,$message){
        $logfile = self::GetRoot().'/'. $report_type .".log";
        if(!file_exists($logfile)){
            if(!is_dir(dirname($logfile))) self::MkdirP(dirname($logfile)) ; //utils::mkdir_p(dirname($logfile));
        }
        error_log($message, 3, $logfile);
    }

    /*
     * @todo创建目录
     */
    static function MkdirP($dir,$dirmode=0755){
        $path = explode('/',str_replace('\\','/',$dir));
        $depth = count($path);
        for($i=$depth;$i>0;$i--){
            if(file_exists(implode('/',array_slice($path,0,$i)))){
                break;
            }
        }
        for($i;$i<$depth;$i++){
            if($d= implode('/',array_slice($path,0,$i+1))){
                if(!is_dir($d)) mkdir($d,$dirmode);
            }
        }
        return is_dir($dir);
    }

    /*
     * @todo 获取日志地址
     */
    static private function GetRoot(){
        if(empty(self::$SAVE_FOLDER_NAME)){
            self::$SAVE_FOLDER_NAME = isset($_SERVER['HTTP_X_FORWARDED_HOST']) ? $_SERVER['HTTP_X_FORWARDED_HOST'] : (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'neigou_unknow');
            //For $_SERVER 变量存在且为空字符串
            self::$SAVE_FOLDER_NAME = strlen(self::$SAVE_FOLDER_NAME) > 0 ? self::$SAVE_FOLDER_NAME : 'neigou_unknow';
        }
        $log_path   = self::$__log_root.self::$SAVE_FOLDER_NAME;
        return $log_path;
    }

    static public function JsonDncode($data){
        $data = @json_encode($data);
        return self::JsonCN($data);
    }

    //处理转json中,中文字符
    static public function JsonCN($json_data){
        return $json_data;
        /*
        $unescaped = preg_replace_callback('/\\\\u(\w{4})/', function ($matches) {
            return html_entity_decode('&#x' . $matches[1] . ';', ENT_COMPAT, 'UTF-8');
        }, $json_data);
        return $unescaped;
        */
    }

    /*
     * @todo 格式化输入数据
     */
    static public function  FormatMessageData($data){
        if(empty($data)) return $data;
        if(!is_array($data) && strpos($data,'{') > -1) return self::JsonCN($data);;
        foreach ($data as $k=>$v){
            if(is_array($v)){
                $data[$k]   = self::JsonDncode($v);
            }
            elseif(is_object($v)){
                $data[$k] = json_encode($v);

            }else{
                if(strpos($v,'{') > -1){
                    $data[$k]   = self::JsonCN($v);
                }
            }
        }
        return $data;
    }

    /**
     * 获取客户端ip
     */
    static public function GetClientIP() {
        if (isset($_SERVER)) {
            if (isset($_SERVER["HTTP_X_FORWARDED_FOR"])) {
                $ip = $_SERVER["HTTP_X_FORWARDED_FOR"];
            } elseif (isset($_SERVER["HTTP_CLIENT_ip"])) {
                $ip = $_SERVER["HTTP_CLIENT_ip"];
            } else {
                $ip = isset($_SERVER["REMOTE_ADDR"])?$_SERVER["REMOTE_ADDR"]:'';
            }
        } else {
            if (getenv('HTTP_X_FORWARDED_FOR')) {
                $ip = getenv('HTTP_X_FORWARDED_FOR');
            } elseif (getenv('HTTP_CLIENT_ip')) {
                $ip = getenv('HTTP_CLIENT_ip');
            } else {
                $ip = getenv('REMOTE_ADDR');
            }
        }
        if(trim($ip)=="::1"){
            $ip="127.0.0.1";
        }
        if(strstr($ip,',')){
            $ips    = explode(',',$ip);
            $ip =$ips[0];
        }
        return $ip;
    }

    public static function clean()
    {
        self::$__id = null;
        self::$SAVE_FOLDER_NAME = '';
        self::$__is_first = array(
            'debug' => false,
            'general' => false,
            'core' => false,
            'apiclient' => false,
        );
    }
}