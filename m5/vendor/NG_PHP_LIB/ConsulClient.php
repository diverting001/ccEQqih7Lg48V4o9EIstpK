<?php

namespace Neigou;

if( !defined("PSR_REGISTER_CENTER_ADDR") ) {
    die('Please configure the registry address') ;
}
defined("REGISTER_CENTER_ADDR") or define("REGISTER_CENTER_ADDR" , PSR_REGISTER_CENTER_ADDR) ;
include_once __DIR__ . "/CurlLib.php" ;

class ConsulClient
{
    public static function getServerIp() {
        $server_ip ='' ;
        if(isset($_SERVER['SERVER_ADDR'])) {
            $server_ip = $_SERVER['SERVER_ADDR'];
        } elseif(isset($_SERVER['LOCAL_ADDR'])) {
            $server_ip = $_SERVER['LOCAL_ADDR'];
        } else  {
            $server_ip = getenv('SERVER_ADDR');
        }
        if(!$server_ip) {
            $shell_cmd = "ifconfig eth0 | grep \"inet addr\" | awk '{ print $2}' | awk -F: '{print $2}'" ;
            $server_ip =  exec($shell_cmd) ;
        }
        if(!$server_ip) {
            $shell_cmd = "ip addr show|grep \"inet\"|grep \"eth0\"|awk {'print $2'}|cut -d/ -f1";
            $server_ip =  exec($shell_cmd) ;
        }
        if(!$server_ip) {
            $server_ip = '0.0.0.0' ;
        }
        return $server_ip;
    }

// 注册服务
// version 服务的版本信息
    public static function registerServer($ip ,$port , $server_name,$version='')
    {
        $curl_obj = new  \Curl\CurlLib() ;
        $url = REGISTER_CENTER_ADDR . "/agent/service/register?replace-existing-checks=true" ;
        $server_id = $server_name . '_' .  crc32($ip.":". $port) ;
        $server_info = array(
            "id" => $server_id   ,
            'name' => $server_name ,
            'tags' => array( 'web_localcall' , $version) ,
            'address' => $ip ,
            "Meta" =>array(
                "version" => $version ? $version : 'null' ,
            ) ,
            'port' => intval($port) ,
            'checks' => array(
                array(
                    'tcp' => $ip .":" .$port ,
                    'interval' => '10s' ,
                    'timeout' => '1s' ,
                    'DeregisterCriticalServiceAfter' => '90m' ,
                )
            ) ,
        ) ;
        $curl_obj->setHeader('Content-type' ,'application/json') ;
        $response_obj =  $curl_obj->put($url , json_encode($server_info) ,true) ;
        $response = $response_obj->getResponse() ;
        $curl_obj->close() ;
        if(strlen($response) == 0) {
            return true ;
        }
        \Neigou\Logger::General('rpc_service_register', array('server_name' => $server_name ,'ip' =>$ip,'port' =>$port)) ;
        return false ;

    }

// 获取服务
    public static function fetchServer($server_name,$version='') {
        $curl_obj = new  \Curl\CurlLib() ;
        $url = REGISTER_CENTER_ADDR . "/health/service/{$server_name}?passing=true" ;
        if(!empty($version)) {
            $url .=  "&tag=" .$version ;
        }
        $curl_obj->setHeader('Content-type' ,'application/json') ;
        $response_obj =  $curl_obj->get($url) ;
        $response  = json_decode($response_obj->getResponse(),true) ;
        $curl_obj->close();
        if(empty($response)) {
            \Neigou\Logger::General('rpc_service_fetch_error', array('server_name' => $server_name ,'url' =>$url, 'response' =>$response_obj->getResponse() )) ;
            return array() ;
        }
        $return = array() ;
        foreach ($response as $item) {
            $server  = $item['Service'] ;
            $return[] = array(
                'id' => $server['ID'] ,
                'name' => $server['Service'] ,
                'address' => $server['Address'] ,
                'port' => $server['Port'] ,
                'version' => isset($server['Meta']['version']) ? $server['Meta']['version'] : '' ,
            ) ;
        }
        return $return ;
    }


    // 简单的负载均衡策略
    public function getServerInfo($server_name ,$version) {
        $server_list = self::fetchServer($server_name);
        if(empty($server_list)) {
            return false ;
        }
        $return = array() ;
        foreach ($server_list as $server) {
            if($server['version'] == $version) {
                $return[] = $server ;
            }
        }
        $ret =  $return ?  array_rand($return) : array_rand($server_list) ;
        return $return ? $return[$ret] : $server_list[$ret] ;
    }


}