<?php
/**
 * neigou_service-stock
 * @package     api
 * @author      xupeng
 * @since       Version
 * @filesource
 */

namespace App\Api\V3\Service\Log;

use App\Api\Model\Log\Log as LogModel;

/**
 * 日志 Service
 *
 * @package     api
 * @category    Logic
 * @author        xupeng
 */
class Log
{

    /**
     * 获取活动列表
     *
     * @return  array|bool
     */
    public function getActionList()
    {
        $logModel = new LogModel();

        return $logModel->getActionList();
    }

    // --------------------------------------------------------------------

    /**
     * 添加活动
     *
     * @param   $app        string  app
     * @param   $module     string  module
     * @param   $action     string  action
     * @param   $levels     array   等级
     * @param   $status     int     状态
     * @param   $alarm      int     报警状态
     * @return  boolean
     */
    public function addAction($app, $module, $action, $levels = array(), $status = 1, $alarm = 0)
    {
        if (empty($app) OR empty($module) OR empty($action)) {
            return false;
        }

        $logModel = new LogModel();

        if (is_array($levels)) {
            $levels = implode(',', $levels);
        }

        // status
        $status = $status == 1 ? 1 : 0;

        //alarm
        $alarm = $alarm == 1 ? 1 : 0;

        return $logModel->addAction($app, $module, $action, $levels, $status, $alarm);
    }

    // --------------------------------------------------------------------

    /**
     * 更新活动
     *
     * @param   $actionId   int     活动ID
     * @param   $data       array   更新数据
     * @return  bool
     */
    public function updateAction($actionId, $data)
    {
        $logModel = new LogModel();

        if ($actionId <= 0 OR empty($data)) {
            return false;
        }

        if (isset($data['levels']) && is_array($data['levels'])) {
            $data['levels'] = implode(',', $data['levels']);
        }

        if (isset($data['status'])) {
            $data['status'] = $data['status'] == 1 ? 1 : 0;
        }

        if (isset($data['alarm'])) {
            $data['alarm'] = $data['alarm'] == 1 ? 1 : 0;
        }

        return $logModel->updateAction($actionId, $data);
    }

}
