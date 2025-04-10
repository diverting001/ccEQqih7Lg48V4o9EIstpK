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
     * @param   $express_mobile     string  用户手机号 非必填
     * @return  array|bool
     */
    public function getExpressDetail($expressCom, $expressNo, $express_mobile='')
    {
        $return = array();

        if (empty($expressCom) OR empty($expressNo)) {
            return $return;
        }

        if (strlen($expressNo) > 100) {
            return $return;
        }

        $expressModel = new ExpressModel();

        // 获取物流详情
        $expressDetail = $expressModel->getExpressDetail($expressCom, $expressNo);

        //代表该订单还没有开始从快递100拉取。 需要club脚本 拉数据。然后改status
        $status = config('neigou.KUAIDI100_STATUS_100');//status : 100 
        if(empty($expressDetail) && $express_mobile){
            $expressModel->addExpress($expressCom, $expressNo, true, $status, '', $express_mobile);
            $expressDetail = $expressModel->getExpressDetail($expressCom, $expressNo);
        }

        //该代码块：例如ems邮件，优先查youzhengguonei
        if ((empty($expressDetail) or $expressDetail['status'] == 0) && isset(self::$_expressComMapping[$expressCom])) {
            if (!empty($expressDetail)) {
                $this->dealEmptyDataExpress($expressDetail, $express_mobile);
            }

            $expressDetail = $expressModel->getExpressDetail(self::$_expressComMapping[$expressCom], $expressNo);
        }

        if(empty($expressDetail)){
            return $return;
        }
        
        $data = unserialize($expressDetail['data']);

        $content = !empty($data['data']) ? $data['data'] : array();

        //反序列化 物流信息是空值， 物流无手机号， 参数有手机号。则更新手机号
        $this->dealEmptyDataExpress($expressDetail, $express_mobile);

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
     * 获取物流详情 By Code
     *
     * @param   $expressCom string  物流公司
     * @param   $expressNo  string  物流单号
     * @return  array|bool
     */
    public function getExpressDetailByCode($expressCom, $expressNo)
    {
        $return = array();

        if (empty($expressCom) OR empty($expressNo)) {
            return $return;
        }

        $expressCompanyModel = new ExpressCompanyModel();

        $companyInfo = $expressCompanyModel->getCompanyInfoByCode($expressCom);

        if (empty($companyInfo))
        {
            return $return;
        }
        $expressModel = new ExpressModel();

        // 获取物流详情
        $expressDetail = $expressModel->getExpressDetail($companyInfo['kuaidi100_code'], $expressNo);

        if ((empty($expressDetail) OR $expressDetail['status'] == 0) && isset(self::$_expressComMapping[$expressCom])) {
            $expressDetail = $expressModel->getExpressDetail(self::$_expressComMapping[$expressCom], $expressNo);
        }

        if (empty($expressDetail)) {
            return $return;
        }

        $data = unserialize($expressDetail['data']);

        $content = !empty($data['data']) ? $data['data'] : array();

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

    /**
     * @param  string  $expressNo
     * @return array|bool|mixed|string
     */
    public function getAutonumber(string $expressNo)
    {
        $return = array();

        if (empty($expressNo)) {
            return $return;
        }

        $curl = new \Neigou\Curl();
        $curl->SetHeader('Content-Type', 'application/x-www-form-urlencoded');
        $curl->save_log = true;

        $url = config('neigou.KUAIDI100_DOMAIN').'/autonumber/auto?num='.$expressNo.'&key='.config('neigou.KUAIDI100_KEY');

        $return = $curl->Get($url);

        \Neigou\Logger::Debug('kuaidi100',
            ['action' => 'autonumber', 'expressNo' => $expressNo, 'return' => $return]);

        return json_decode($return, true);
    }

    /**
     * 处理空物流信息函数
     * 物流信息是空值， 物流无手机号， 参数有手机号。 则更新手机号
     * status : 100  代表该订单还没有开始从快递100拉取。 需要club脚本 拉数据。然后改status
     */
    private function dealEmptyDataExpress($expressDetail, $express_mobile)
    {
        //更新物流信息必须要素：物流无手机号，传入参数有手机号，必须是快递100，物流状态必须等于0在途(已完成不要注册回调)。
        if ($expressDetail['mobile'] || empty($express_mobile) || $expressDetail['is_kuaidi100'] != 1 || $expressDetail['status'] > 0) {
            return;
        }

        //更新参数手机号到物流信息中
        $status = config('neigou.KUAIDI100_STATUS_100');
        (new ExpressModel())->updateExpressMobileStatus($expressDetail['id'], $express_mobile, $status);
        return;
    }


    // --------------------------------------------------------------------

    /**
     * 保存物流信息
     */
    public function saveExpress($expressData, &$errMsg = '')
    {
        $expressModel = new ExpressModel();

        $expressDetail = $expressModel->getExpressDetail($expressData['company'], $expressData['num']);

        if (empty($expressDetail)) {

            $res = $expressModel->addExpress($expressData['company'], $expressData['num'], $expressData['is_kuaidi100'], $expressData['status'], $expressData['data'], $expressData['mobile']);

            if (!$res) {
                $errMsg = '新增物流失败';
                return false;
            }

        } else {
            $update = array();
            foreach ($expressData as $k => $v) {
                if ($expressDetail[$k] != $v) {
                    $update[$k] = $v;
                }
            }
            if (!empty($update)) {
                $res = $expressModel->updateExpressById($expressDetail['id'], $update);
                if (!$res) {
                    $errMsg = '修改物流失败';
                    return false;
                }
            }
        }

        return true;
    }
}
