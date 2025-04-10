<?php
namespace Neigou;
class Log
{
    /**
     * @var     $_app   string     应用标识
     */
    private static $_app;

    /**
     * @var     $_appConfig   log   应用配置
     */
    private static $_appConfig;

    /**
     * @var     $_errMsg   string   错误信息
     */
    private static $_errMsg = '';

    /**
     * @var     $_commonContent     array   公共内容
     *
     */
    private static $_commonContent = array();

    /**
     * @const   FILE_ROOT  string  文件保存目录
     */
    const FILE_ROOT = '/var/log/php/';

    /**
     * @var $_logPath   string  日志路径
     */
    private static $_logPath;

    /**
     * @const   REDIS_CONFIG_KEY    string   配置文件key
     */
    const REDIS_CONFIG_KEY = 'LOG-LOG_APP_CONFIG';

    /**
     * @var   $_queryId    string    全局跟踪ID
     */
    private static $_queryId;

    /**
     * @var     $_spanId  string   当前系统ID
     */
    private static $_spanId;

    /**
     * @var     $_spanPId  string   调用系统ID
     */
    private static $_spanPId;

    /**
     * @var     $_source  string    来源
     */
    private static $_source;

    /**
     * @var     array $levels 日志等级
     */
    static private $_levels = array(
        'FATAL' => 1, // 致命错误
        'ERROR' => 2, // 业务错误
        'WARN'  => 3, // 警告
        'INFO'  => 4, // 重要信息
        'DEBUG' => 5, // 调试信息
        'TRACE' => 6, // 系统追踪
    );

    /**
     * @var array $_rows 列
     */
    static private $_rows  = array(
        'report_name' => 'string',
        'query_id'  => 'string',
        'span_id'   => 'string',
        'span_pid'  => 'string',
        'url'       => 'string',
        'source'    => 'string',
        'get'       => 'string',
        'post'      => 'string',
        'session'   => 'string',
        'cookie'    => 'string',
        's_time'    => 'integer',
        'e_time'    => 'integer',
        'time_span' => 'double',
        'app'       => 'string',
        'module'    => 'string',
        'msg'       => 'string',
        'action'    => 'string',
        'remark'    => 'string',
        'data'      => 'string',
        'reason'    => 'string',
        'target'    => 'string',
        'result'    => 'string',
        'action_type' => 'string',
        'bn'        => 'string',
        'related_bn'=> 'string',
        'sender'    => 'string',
        'step'      => 'string',
        'user_id'   => 'integer',
        'company_id' => 'integer',
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

    /**
     * @var array   $_noFirstLevel   无需首次信息的等级
     */
    static private $_noFirstLevel;

    /**
     * 初始化配置信息
     *
     * @param   $app    string  应用
     * @return  void
     */
    static public function init($app)
    {
        self::$_app = strtoupper($app);

        if ( ! class_exists('Redis'))
        {
            return;
        }

        set_error_handler(array("Neigou\\Log", "errorHandlerCallback"));

        $redis = new \Redis();
        if ( ! $redis->connect(PSR_REDIS_LOG_HOST, PSR_REDIS_LOG_PORT, 0.5))
        {
            return;
        }

        $redis->auth(PSR_REDIS_LOG_PWD);

        $appConfig = $redis->get(self::REDIS_CONFIG_KEY);

        if (empty($appConfig))
        {
            return;
        }

        self::$_appConfig = json_decode($appConfig, true);

        // 全局跟踪ID
        self::$_queryId = isset($_SERVER['HTTP_TRACKID']) ? $_SERVER['HTTP_TRACKID'] : uniqid();

        // 当前系统ID
        self::$_spanId = uniqid();

        // 调用系统ID
        self::$_spanPId = isset($_SERVER['HTTP_SPAN_PID']) ? $_SERVER['HTTP_SPAN_PID'] : '';

        // 来源
        self::$_source = self::_getClientIP();
    }

    // --------------------------------------------------------------------

    /**
     * 获取错误描述
     *
     * @return  string
     */
    static public function getErrMsg()
    {
        return self::$_errMsg;
    }

    // --------------------------------------------------------------------

    /**
     * 获取全局跟踪ID
     *
     * @return  string
     */
    static public function getQueryId()
    {
        return self::$_queryId;
    }

    // --------------------------------------------------------------------

    /**
     * 获取系统跟踪ID
     *
     * @return  string
     */
    static public function getSpanId()
    {
        return self::$_spanId;
    }


    // --------------------------------------------------------------------

    /**
     * 获取系统跟踪父ID
     *
     * @return  string
     */
    static public function getSpanPId()
    {
        return self::$_spanPId;
    }

    // --------------------------------------------------------------------

    /**
     * debug
     *
     * @param   $module     string      模块
     * @param   $action     string      功能
     * @param   $content    mixed       内容
     * @param   $message    string      信息
     * @param   $target     string      位置
     * @param   $remark     string      备注信息
     * @return  boolean
     */
    static public function debug($module, $action, $content, $message = '', $target = '', $remark = '')
    {
        $result = self::_saveLog('DEBUG', $module, $action, $content, $message, $target, $remark);

        if ($result !== true)
        {
            self::$_errMsg = $result ? $result : 'save log failed';

            return false;
        }

        return true;
    }

    // --------------------------------------------------------------------

    /**
     * 重要信息
     *
     * @param   $module     string      模块
     * @param   $action     string      功能
     * @param   $content    mixed       内容
     * @param   $message    string      信息
     * @param   $target     string      位置
     * @param   $remark     string      备注信息
     * @return  boolean
     */
    static public function info($module, $action, $content, $message = '', $target = '', $remark = '')
    {
        $result = self::_saveLog('INFO', $module, $action, $content, $message, $target, $remark);

        if ($result !== true)
        {
            self::$_errMsg = $result ? $result : 'save log failed';

            return false;
        }

        return true;
    }

    // --------------------------------------------------------------------

    /**
     * 业务错误
     *
     * @param   $module     string      模块
     * @param   $action     string      功能
     * @param   $content    mixed       内容
     * @param   $message    string      信息
     * @param   $target     string      位置
     * @param   $remark     string      备注信息
     * @return  boolean
     */
    static public function warn($module, $action, $content, $message = '', $target = '', $remark = '')
    {
        $result = self::_saveLog('WARN', $module, $action, $content, $message, $target, $remark);

        if ($result !== true)
        {
            self::$_errMsg = $result ? $result : 'save log failed';

            return false;
        }

        return true;
    }

    // --------------------------------------------------------------------

    /**
     * 业务错误
     *
     * @param   $module     string      模块
     * @param   $action     string      功能
     * @param   $content    mixed       内容
     * @param   $message    string      信息
     * @param   $target     string      位置
     * @param   $remark     string      备注信息
     * @return  boolean
     */
    static public function error($module, $action, $content, $message = '', $target = '', $remark = '')
    {
        $result = self::_saveLog('ERROR', $module, $action, $content, $message, $target, $remark);

        if ($result !== true)
        {
            self::$_errMsg = $result ? $result : 'save log failed';

            return false;
        }

        return true;
    }

    // --------------------------------------------------------------------

    /**
     * 致命错误
     *
     * @param   $module     string      模块
     * @param   $action     string      功能
     * @param   $content    mixed       内容
     * @param   $message    string      信息
     * @param   $target     string      位置
     * @param   $remark     string      备注信息
     * @return  boolean
     */
    static public function fatal($module, $action, $content, $message = '', $target = '', $remark = '')
    {
        $result = self::_saveLog('FATAL', $module, $action, $content, $message, $target, $remark);

        if ($result !== true)
        {
            self::$_errMsg = $result ? $result : 'save log failed';

            return false;
        }

        return true;
    }

    // --------------------------------------------------------------------

    /**
     * 系统追踪
     *
     * @param   $module     string      模块
     * @param   $action     string      功能
     * @param   $content    mixed       内容
     * @param   $message    string      信息
     * @param   $target     string      位置
     * @param   $remark     string      备注信息
     * @return  boolean
     */
    static public function trace($module, $action, $content, $message = '', $target = '', $remark = '')
    {
        $result = self::_saveLog('TRACE', $module, $action, $content, $message, $target, $remark);

        if ($result !== true)
        {
            self::$_errMsg = $result ? $result : 'save log failed';

            return false;
        }

        return true;
    }

    // --------------------------------------------------------------------

    /**
     * 保存日志信息
     *
     * @param   $level
     * @param   $module     string      模块
     * @param   $action     string      功能
     * @param   $content    mixed       内容
     * @param   $message    string      信息
     * @param   $target     string      位置
     * @param   $remark     string      备注信息
     * @return boolean
     */
    static private function _saveLog($level, $module, $action, $content, $message = '', $target = '', $remark = '')
    {
        $module = strtoupper($module);

        $action = strtoupper($action);

        if ( !isset(self::$_levels[$level]))
        {
            return 'level is wrong';
        }

        if (empty(self::$_app) OR empty(self::$_appConfig))
        {
            return 'not initialized';
        }

        // 应用
        if ( ! isset(self::$_appConfig['app'][self::$_app]))
        {
            return 'app is not available';
        }

        $modules = ! empty(self::$_appConfig['app'][self::$_app]['module']) ? self::$_appConfig['app'][self::$_app]['module'] : array();

        // 模块
        if (empty($modules) OR ! isset($modules[$module]))
        {
            return 'module is not available';
        }

        $originLevel = $level;

        // 检查action
        if ( ! empty($modules[$module]['action']) && ! empty($modules[$module]['action'][$action]))
        {
            if ( ! $modules[$module]['action'][$action]['status'])
            {
                return 'action is not available';
            }

            // 重新定义 level
            if ( ! empty($modules[$module]['action'][$action]['redefine']))
            {
                $redefine = json_decode($modules[$module]['action'][$action]['redefine'], true);
                if (isset($redefine[$level]))
                {
                    $level = $redefine[$level];
                }
            }
        }

        // 重新定义 level
        if ($originLevel == $level && ! empty($modules[$module]['redefine']))
        {
            $redefine = json_decode($modules[$module]['redefine'], true);
            if (isset($redefine[$level]))
            {
                $level = $redefine[$level];
            }
        }

        // app
        if ( ! in_array($level, explode(',', self::$_appConfig['app'][self::$_app]['levels'])))
        {
            return 'app does not allow this level';
        }

        // 模块
        if ( ! in_array($level, explode(',', $modules[$module]['levels'])))
        {
            return 'module does not allow this level';
        }

        // 首次日志
        if (! in_array($level, self::$_noFirstLevel))
        {
            $content['get']     = $_GET;
            $content['post']    = $_POST;;

            // cookie 筛选
            $content['cookie']  = $_COOKIE;
            $content['url']     = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';

            self::$_noFirstLevel[] = $level;
        }

        $content['report_name'] = self::$_app. '_'. $module. '_'. $action;
        $content['msg'] = $message;
        $content['target'] = $target;
        $content['remark'] = $remark;
        $content['span_id'] = self::$_spanId;
        $content['span_pid'] = self::$_spanPId;
        $content['query_id'] = self::$_queryId;
        $content['source'] = self::$_source;
        $content['app'] = self::$_app;
        $content['module'] = $module;
        $content['action'] = $action;

        //格式化输入信息
        $message = self::_createMessage(array_merge($content, self::$_commonContent), $level);

        //保存日志
        self::_logSaveFile($level, $message);

        return true;
    }

    // --------------------------------------------------------------------

    /**
     * 生成日志信息
     * @param   $data   array   日志内容
     * @param   $level  int     日志等级
     *
     * @return string
     */
    static private function _createMessage($data, $level)
    {
        if (empty($data) OR ! is_array($data))
        {
            return '';
        }

        $message = array();
        foreach ($data as $k => $v)
        {
            if (isset(self::$_rows[$k]))
            {
                if (is_array($v))
                {
                    $v = self::_jsonEncode($v);
                }

                if (gettype($v) != self::$_rows[$k])
                {
                    $v = self::_formatConversion($v, self::$_rows[$k]);
                }
                $message[$k] = $v;
                unset($data[$k]);
            }
        }
        if ( ! empty($data))
        {
            $message['other'] = json_encode($data);
        }

        $message = self::_jsonEncode($message);

        // ISO8601 时间格式
        $microtime =  str_pad(round(current(explode(' ', microtime())) * 1000), 3, '0', STR_PAD_LEFT);

        $time = date("Y-m-d"). "T". date("H:i:s.{$microtime}P");

        $message = sprintf("%s\t%s\t%s\n",$time, $level, $message);

        return $message;
    }

    // --------------------------------------------------------------------

    /**
     * 保存日志
     *
     * @param   $reportType     string  日志类型
     * @param   $message        string  日志内容
     * @return  void
     */
    static private function _logSaveFile($reportType, $message)
    {
        self::$_logPath OR self::$_logPath = self::_getLogPath();

        $logFile = self::$_logPath. '/'. $reportType. ".log";

        if ( ! file_exists($logFile))
        {
            if ( ! is_dir(dirname($logFile)))
            {
                self::_makeDir(dirname($logFile));
            }
        }

        error_log($message, 3, $logFile);
    }

    // --------------------------------------------------------------------

    /**
     * 获取地址
     *
     * @return  string
     */
    static private function _getLogPath()
    {
        $folderName = isset($_SERVER['HTTP_X_FORWARDED_HOST']) ? $_SERVER['HTTP_X_FORWARDED_HOST'] : (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'neigou_unknow');

        $folderName OR $folderName = 'neigou_unknow';

        return self::FILE_ROOT. $folderName;
    }

    // --------------------------------------------------------------------

    /**
     * 创建目录
     *
     * @param   $dir        string  目录
     * @param   $dirMode    int     权限
     * @return  boolean
     */
    static private function _makeDir($dir, $dirMode = 0755)
    {
        $path = explode('/', str_replace('\\', '/', $dir));
        $depth = count($path);
        for ($i = $depth; $i > 0; $i--)
        {
            if (file_exists(implode('/', array_slice($path, 0, $i))))
            {
                break;
            }
        }
        for ($i = 0; $i < $depth; $i++)
        {
            if ($d = implode('/', array_slice($path, 0, $i + 1)))
            {
                if ( ! is_dir($d))
                {
                    mkdir($d, $dirMode);
                }
            }
        }

        return is_dir($dir);
    }

    // --------------------------------------------------------------------

    /**
     * 格式转换
     *
     * @param   $data   mixed   数据
     * @param   $type   string  类型
     * @return  mixed
     */
    static private function _formatConversion($data, $type)
    {
        if ($type == 'integer')
        {
            return intval($data);
        }

        if ($type == 'string')
        {
            return strval($data);
        }

        if ($type == 'double')
        {
            return floatval($data);
        }

        return '';
    }

    // --------------------------------------------------------------------

    /**
     * json encode
     *
     * @param   $data   array   数据
     * @return string
     */
    static private function _jsonEncode($data)
    {
        if ( ! is_array($data))
        {
            $data = array($data);
        }

        $data = version_compare(PHP_VERSION, '5.4.0', '>=') ? json_encode($data, JSON_UNESCAPED_UNICODE) : json_encode($data);

        return $data;
    }

    // --------------------------------------------------------------------

    /**
     * 获取客户端IP
     *
     * @return string
     */
    static private function _getClientIP()
    {
        if (isset($_SERVER))
        {
            if (isset($_SERVER["HTTP_X_FORWARDED_FOR"]))
            {
                $ip = $_SERVER["HTTP_X_FORWARDED_FOR"];
            }
            elseif (isset($_SERVER["HTTP_CLIENT_ip"]))
            {
                $ip = $_SERVER["HTTP_CLIENT_ip"];
            }
            else
            {
                $ip = isset($_SERVER["REMOTE_ADDR"]) ? $_SERVER["REMOTE_ADDR"] : '';
            }
        }
        else
        {
            if (getenv('HTTP_X_FORWARDED_FOR'))
            {
                $ip = getenv('HTTP_X_FORWARDED_FOR');
            }
            elseif (getenv('HTTP_CLIENT_ip'))
            {
                $ip = getenv('HTTP_CLIENT_ip');
            }
            else
            {
                $ip = getenv('REMOTE_ADDR');
            }
        }

        if (trim($ip) == "::1")
        {
            $ip = "127.0.0.1";
        }

        if (strstr($ip, ','))
        {
            $ips = explode(',', $ip);
            $ip = $ips[0];
        }

        return $ip;
    }

    // --------------------------------------------------------------------

    /**
     * 错误处理
     *
     * @return  boolean
     */
    static public function errorHandlerCallback()
    {
        return false;
    }

}
