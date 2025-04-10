<?php
/**
 * Created by PhpStorm.
 * User:
 * Date: 2018/11/15
 * Time: 8:21 PM
 */

namespace App\Console\Commands;

use App\Api\V3\Service\Log\Log as logService;

use Illuminate\Console\Command;


/**
 * 发票V3 Crontab
 *
 * @package     Console
 * @category    Command
 * @author        xupeng
 */
class LogV3 extends Command
{
    protected $force = '';

    protected $signature = 'logV3Task {method} ';

    protected $description = '日志服务V3';

    /**
     * @var     array $levels 日志等级
     */
    static private $_levels = array(
        'FATAL' => 1, // 致命错误
        'ERROR' => 2, // 业务错误
        'WARN' => 3, // 警告
        'INFO' => 4, // 重要信息
        'DEBUG' => 5, // 调试信息
        'TRACE' => 6, // 系统追踪
    );

    /**
     * 每次处理最大数量
     */
    const PER_MAX_LIMIT = 100000;

    // 处理发票状态
    public function handle()
    {
        $method = $this->argument('method');

        $this->$method();
    }

    // --------------------------------------------------------------------

    /**
     * 收集活动
     *
     * @return  bool
     */
    public function collectAction()
    {
        $logService = new logService();

        foreach (self::$_levels as $level => $levelNumber) {
            // 获取活动
            $actionList = $logService->getActionList();

            if (!empty($actionList)) {
                foreach ($actionList as $key => $action) {
                    $reportName = $action['app'] . '_' . $action['module'] . '_' . $action['action'];
                    $actionList[$reportName] = $action;

                    unset($actionList[$key]);
                }
            }

            $param = array(
                'from' => 0,
                'size' => self::PER_MAX_LIMIT,
                'query' => array(
                    'filtered' => array(
                        'query' => array(
                            'query_string' => array(
                                'analyze_wildcard' => true,
                                'query' => 'type:v3' . strtolower($level),
                            )
                        ),
                        'filter' => array(
                            'bool' => array(
                                'must' => array(
                                    array(
                                        'range' => array(
                                            '@timestamp' => array('gte' => 'now-2m'),
                                        )
                                    )
                                ),
                                'must_not' => array(),
                            )
                        )
                    )
                )
            );

            $logContent = $this->_request($level, $param);

            if (empty($logContent)) {
                continue;
            }
            $addActions = array();

            $updateActions = array();

            foreach ($logContent as $content) {
                $content = $content['_source'];

                if (!isset($actionList[$content['report_name']])) {
                    if (isset($addActions[$content['report_name']])) {
                        continue;
                    }
                    $addActions[$content['report_name']] = array(
                        'app' => $content['app'],
                        'module' => $content['module'],
                        'action' => $content['action'],
                        'levels' => array($content['log_level']),
                    );
                } else {
                    if (isset($updateActions[$content['report_name']])) {
                        continue;
                    }
                    $levels = explode(',', $actionList[$content['report_name']]['levels']);

                    if (!in_array($content['log_level'], $levels)) {
                        $levels[$content['log_level']] = $content['log_level'];
                        $updateActions[$content['report_name']] = array(
                            'action_id' => $actionList[$content['report_name']]['action_id'],
                            'levels' => $levels
                        );
                    }
                }
            }

            // 添加活动
            if (!empty($addActions)) {
                foreach ($addActions as $action) {
                    $logService->addAction($action['app'], $action['module'], $action['action'], $action['levels']);
                }
            }

            // 更新活动
            if (!empty($updateActions)) {
                foreach ($updateActions as $action) {
                    $logService->updateAction($action['action_id'], array('levels' => $action['levels']));
                }
            }
        }

        return true;
    }

    // --------------------------------------------------------------------

    /**
     * 日志请求
     *
     * @param   $level  string      等级
     * @param   $data   array       数据
     * @return  array
     */
    private function _request($level, $data)
    {
        $url = config('neigou.ELK_DOMAIN') . '/logstash-phpv3' . strtolower($level) . '-*/_search?pretty';

        $curl = new \Neigou\Curl();

        $result = $curl->Post($url, json_encode($data));

        $result = json_decode($result, true);

        return !empty($result['hits']['hits']) ? $result['hits']['hits'] : array();
    }

}
