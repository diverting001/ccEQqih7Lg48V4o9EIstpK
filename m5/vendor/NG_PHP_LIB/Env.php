<?php

namespace Neigou;
class Env
{
    const EnvLoginKey = 'login_env_name';

    public static function GetEnv($key, $type = 'cookie', $param = array())
    {
        if (empty($key)) {
            return false;
        }
        // 暂时仅支持cookie
        if ($type !== 'cookie') {
            return false;
        }
        return $_COOKIE[$key] ?: '';
    }

    public static function SetEnv($key, $val, $type = 'cookie', $param = array())
    {
        if (empty($key) || empty($val)) {
            return false;
        }
        // 暂时仅支持cookie
        if ($type !== 'cookie') {
            return false;
        }
        $expire = isset($param['expire']) ? $param['expire'] : 315360000;
        setcookie($key, $val, time() + $expire, $param['path'] ?: '/', $param['domain'] ?: SESSION_COOKIE_DOMAIN);
        $_COOKIE[$key] = $val;
        return true;
    }

    public static function ClearEnv($key, $type = 'cookie', $param = array())
    {
        if (empty($key)) {
            return false;
        }
        // 暂时仅支持cookie
        if ($type !== 'cookie') {
            return false;
        }
        setcookie($key, null, 0, null, $param['domain'] ?: SESSION_COOKIE_DOMAIN);
        unset($_COOKIE[$key]);
        return true;
    }

    public static function IsWeiXin($ua = '')
    {
        $ua = $ua ?: $_SERVER['HTTP_USER_AGENT'];
        if (empty($ua) || (stripos($ua, 'MicroMessenger') === false)) {
            return false;
        }
        return true;
    }

    public static function IsWeiXinWork($ua)
    {
        $ua = $ua ?: $_SERVER['HTTP_USER_AGENT'];
        if (!self::IsWeiXin($ua)) {
            return false;
        }
        return stripos($ua, 'wxwork') !== false;
    }


    public static function IsWxMiniProgram($ua = '')
    {
        $ua = $ua ?: $_SERVER['HTTP_USER_AGENT'];
        if (!self::IsWeiXin($ua)) {
            return false;
        }
        return stripos($ua, 'miniProgram') !== false;
    }

    public static function isWeiXinH5($ua)
    {
        $ua = $ua ?: $_SERVER['HTTP_USER_AGENT'];
        if (!self::IsWeiXin($ua)) {
            return false;
        }
        // 非企业微信
        if (self::IsWeiXinWork($ua)) {
            return false;
        }
        // 非小程序
        if (self::IsWxMiniProgram($ua)) {
            return false;
        }
        return true;
    }


    public static function EnvChangeCheck($checkEnv, $ua)
    {
        $ua = $ua ?: $_SERVER['HTTP_USER_AGENT'];
        // 非微信环境 以及 无环境 不检测
        if (!self::IsWeiXin($ua) || empty($checkEnv)) {
            return true;
        }
        $env = array(
            'mini' => array('miniprogram'),
            'h5' => array('youfendui', 'privatization')
        );
        // 如果是小程序，判断登录环境不是小程序
        $isMini = self::IsWxMiniProgram($ua);
        if ($isMini) {
            if (!in_array($checkEnv, $env['mini'])) {
                return false;
            }
            return true;
        }
        // 此时环境为h5，判断登录环境是不是h5
        if (!in_array($checkEnv, $env['h5'])) {
            return false;
        }
        return true;
    }
}