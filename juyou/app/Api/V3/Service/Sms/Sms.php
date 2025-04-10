<?php
/**
 * neigou_service-stock
 * @package     api
 * @author      xupeng
 * @since       Version
 * @filesource
 */

namespace App\Api\V3\Service\Sms;

use App\Api\V3\Service\Sms\SmsMengWang as SmsMengWangService;
use Neigou\RedisNeigou;

/**
 * 短信
 *
 * @package     api
 * @category    Service
 * @author        xupeng
 */
class Sms
{
    /**
     * 公司列表
     */
    private static $_companyList = array(
        'NEIGOU' => '内购',
        'DIANDI' => '点滴关怀',
    );

    /**
     * 类型
     */
    private static $_types = array(
        'CAPTCHA' => '验证码',
        'NORMAL' => '普通',
    );

    // --------------------------------------------------------------------

    /**
     * 获取物流详情
     *
     * @param   $mobile     string  手机号
     * @param   $content    string  内容
     * @param   $com        string  公司
     * @param   $type       string  类型
     * @param   $errMsg     string  错误信息
     * @return  boolean
     */
    public function send($mobile, $content, $com, $type = 'NORMAL', & $errMsg = '')
    {
        if (empty($mobile) OR empty($content) OR empty($com)) {
            return false;
        }

        if (!isset(self::$_companyList[$com])) {
            $errMsg = '短信签名不支持';
            return false;
        }

        $type OR $type = 'NORMAL';

        if (!isset(self::$_types[$type])) {
            $errMsg = '短信类型不支持';
            return false;
        }

        // 验证手机号
        if (!self::_isMobile($mobile)) {
            $errMsg = '手机号格式不正确';
            return false;
        }

        // 防刷
        if (!self::_forbid($mobile, $content)) {
            $errMsg = '发送过于频发，请稍后重试。';
            return false;
        }

        $smsPlatformService = new SmsMengWangService();

        if (!$smsPlatformService->send($mobile, $content, $com, $type, $errMsg)) {
            return false;
        }

        return true;
    }

    // --------------------------------------------------------------------

    /**
     *  短信发送频率检测
     *
     * @param   $mobile     string  手机号
     * @param   $content    string  短信内容
     * @return  boolean
     */
    private static function _forbid($mobile = '', $content = '')
    {
        if (!$mobile) {
            return false;
        }

        $redis_hour_key = '_sms_hour_limit_forbid_num_' . $mobile;
        $redis_min_key = '_sms_min_limit_forbid_num_' . $mobile;

        // 判断一小时是否存在
        $redis = new RedisNeigou();

        $hourValue = $redis->_redis_connection->get($redis_hour_key);
        if ($hourValue) {
            \Neigou\Logger::General('sms_limit',
                array('action' => 'sms_hour_limit', 'data' => json_encode($content), 'remark' => $mobile));

            return false;
        }

        // 防刷
        $limitValue = $redis->_redis_connection->get($redis_min_key);
        if ($limitValue && $limitValue > 24) {
            \Neigou\Logger::General('sms_limit',
                array('action' => 'sms_min_limit', 'data' => json_encode($content), 'remark' => $mobile));
            $redis->_redis_connection->set($redis_hour_key, $mobile, 3600);

            return false;
        }

        if ($limitValue == false) {
            $limitValue = 0;
        }
        $redis->_redis_connection->set($redis_min_key, ++$limitValue, 60);

        return true;
    }

    // --------------------------------------------------------------------

    /**
     *  检查手机号
     *
     * @param   $mobile string  手机号
     * @return  string
     */
    private static function _isMobile($mobile)
    {
        return (is_numeric($mobile) && strlen($mobile) == 11) ? true : false;
    }

}
