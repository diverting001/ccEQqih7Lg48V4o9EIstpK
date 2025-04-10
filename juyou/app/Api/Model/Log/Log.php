<?php
/**
 * neigou_service-stock
 * @package     api
 * @author      xupeng
 * @since       Version
 * @filesource
 */

namespace App\Api\Model\Log;

/**
 * 日志 model
 *
 * @package     api
 * @category    Model
 * @author        xupeng
 */
class Log
{
    /**
     * 获取活动列表
     *
     * @param   $app    string      app
     * @param   $module string      moduel
     * @param   $action string      action
     * @return  array
     */
    public function getActionList($app = '', $module = '', $action = '')
    {
        $return = array();

        $where = array();

        if ($app) {
            $where['app'] = $app;
        }

        if ($module) {
            $where['module'] = $module;
        }

        if ($action) {
            $where['action'] = $action;
        }

        if ($where) {
            $result = app('api_db')->table('server_log_actions')->where($where)->get();
        } else {
            $result = app('api_db')->table('server_log_actions')->get();
        }

        if (empty($result)) {
            return $return;
        }

        foreach ($result as $v) {
            $return[] = get_object_vars($v);
        }

        return $return;
    }

    // --------------------------------------------------------------------

    /**
     * 添加活动
     *
     * @param   $app        string  app
     * @param   $module     string  module
     * @param   $action     string  action
     * @param   $levels     string   等级
     * @param   $status     int     状态
     * @param   $alarm      int     报警状态
     * @return  boolean
     */
    public function addAction($app, $module, $action, $levels = '', $status = 1, $alarm = 0)
    {
        if (empty($app) OR empty($module) OR empty($action)) {
            return false;
        }

        $insertData = array(
            'app' => $app,
            'module' => $module,
            'action' => $action,
            'levels' => $levels,
            'status' => $status,
            'alarm' => $alarm,
        );

        if (!app('api_db')->table('server_log_actions')->insert($insertData)) {
            return false;
        }

        return true;
    }

    // --------------------------------------------------------------------

    /**
     * 添加物流信息
     *
     * @param   $actionId   int      活动ID
     * @param   $data       array       数据
     * @return  boolean
     */
    public function updateAction($actionId, $data)
    {
        if ($actionId <= 0 OR empty($data)) {
            return false;
        }

        $where = array(
            'action_id' => $actionId,
        );

        if (!app('api_db')->table('server_log_actions')->where($where)->update($data)) {
            return false;
        }

        return true;
    }

}
