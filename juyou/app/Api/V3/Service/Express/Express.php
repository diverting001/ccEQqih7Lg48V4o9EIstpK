<?php
/**
 * neigou_service-stock
 * @package     api
 * @author      xupeng
 * @since       Version
 * @filesource
 */

namespace App\Api\V3\Service\Express;

use App\Api\Model\Express\Express as ExpressModel;
use App\Api\Model\Express\Company as ExpressCompanyModel;

/**
 * 物流 Service
 *
 * @package     api
 * @category    Logic
 * @author        xupeng
 */
class Express
{
    /**
     * 物流映射
     */
    private static $_expressComMapping = array(
        'ems' => 'youzhengguonei'
    );

    /**
     * 状态描述
     */
    private static $_expressStatusMsg = array(
        0 => '在途',
        1 => '已揽收',
        2 => '疑难',
        3 => '已签收',
        4 => '已退回签收',
        5 => '派件中',
        6 => '退回中',
        11 => '待清关',
        12 => '已清关',
        13 => '清关异常',
        14 => '收件人拒签',
    );

    /**
     * 获取物流详情
     *
     * @param   $expressCom string  物流公司
     * @param   $expressNo  string  物流单号
     * @return  array|bool
     */
    public function getExpressDetail($expressCom, $expressNo)
    {
        $return = array();

        if (empty($expressCom) OR empty($expressNo)) {
            return $return;
        }

        $expressModel = new ExpressModel();

        // 获取物流详情
        $expressDetail = $expressModel->getExpressDetail($expressCom, $expressNo);

        if ((empty($expressDetail) OR $expressDetail['status'] == 0) && isset(self::$_expressComMapping[$expressCom])) {
            $expressDetail = $expressModel->getExpressDetail(self::$_expressComMapping[$expressCom], $expressNo);
        }

        if (empty($expressDetail)) {
            return $return;
        }

        $data = unserialize($expressDetail['data']);

        $content = !empty($data['data']) ? $data['data'] : array();

        $expressCompanyModel = new ExpressCompanyModel();

        $companyInfo = $expressCompanyModel->getCompanyInfoByKuaidi100Code($expressDetail['company']);

        $return = array(
            'express_com' => $expressDetail['company'],
            'express_name' => !empty($companyInfo) ? $companyInfo['name'] : '',
            'express_no' => $expressDetail['num'],
            'status' => $expressDetail['status'],
            'status_msg' => self::$_expressStatusMsg[$expressDetail['status']],
            'create_time' => date('Y-m-d H:i:s', $expressDetail['addtime']),
            'update_time' => date('Y-m-d H:i:s', $expressDetail['updatetime']),
            'content' => $content,
        );

        return $return;
    }

    // --------------------------------------------------------------------

    /**
     * 保存物流信息
     *
     * @param   $expressCom     string  物流公司
     * @param   $expressNo      string  物流单号
     * @param   $isKuaidi100    boolean 是否为快递100
     * @param   $status         int     状态
     * @param   $content        array   物流轨迹信息
     * @return  bool
     */
    public function saveExpressDetail($expressCom, $expressNo, $isKuaidi100 = true, $status = 0, $content = array())
    {
        // 获取物流详情
        $expressDetail = $this->getExpressDetail($expressCom, $expressNo);

        $expressModel = new ExpressModel();

        // 更新物流
        if (!empty($expressDetail)) {
            if (!empty($content) && ($status != $expressDetail['status'] OR serialize($content) != $expressDetail['data'])) {
                // 更新物流信息
                if (!$expressModel->updateExpress($expressCom, $expressNo, $status, $content)) {
                    return false;
                }
            }

            return true;
        }

        // 添加物流
        if (!$expressModel->addExpress($expressCom, $expressNo, $isKuaidi100, $status, $content)) {
            return false;
        }

        return true;
    }

    // --------------------------------------------------------------------

    /**
     * 获取物流公司列表
     *
     * @return  array
     */
    public function getExpressCompanyList()
    {
        $expressCompanyModel = new ExpressCompanyModel();

        return $expressCompanyModel->getExpressCompanyList();
    }

}
