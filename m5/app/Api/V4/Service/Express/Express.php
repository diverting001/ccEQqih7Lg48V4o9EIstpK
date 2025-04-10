<?php


namespace App\Api\V4\Service\Express;

use App\Api\Common\Common;
use App\Api\Logic\Mq;
use App\Api\Logic\Openapi;
use App\Api\Model\Express\channel as channelModel;
use App\Api\Model\Express\CompanyMapping as CompanyMappingModel;
use App\Api\Model\Express\Dlycorp as ExpressDlycorpModel;
use App\Api\Model\Express\V2\Express as ExpressModel;
use App\Api\Model\Express\AutoNumber as ExpressAutoNumberModel;
use Neigou\Logger;

/**
 * 物流 Service
 *
 * @package     api
 * @category    Logic
 * @author        xupeng
 */
class Express
{

    // 无物流配送
    const WU_WU_LIU_LOGISTICS = 'WWLPS';
    // 自配物流
    const SELF_LOGISTICS = 'SDFH';
    // 无物流配送
    const ZITI_LOGISTICS = 'ZITI';
    // 包裹多物流配送
    const PRESENT_LOGISTICS = 'PRESENT';
    //自提券码
    const  COUPON_LOGISTICS = 'COUPON';
    //其他物流
    const OTHER_LOGISTICS = 'OTHER';

    /**
     * @param $expressCom  sdb_b2c_dlycorp 中的corp_code
     * @param $expressNo
     * @return array
     */
    public function getExpressDetail($expressCom, $expressNo, $expressMobile = '')
    {
        $return = array();

        if (empty($expressCom) or empty($expressNo)) {
            return $return;
        }

        //去除空格
        $expressNo = $this->_trimSpace($expressNo);

        // 获取物流详情
        $expressDetail = ExpressModel::getExpressDetail($expressCom, $expressNo);
        if (empty($expressDetail)) {
            //写入
            $this->saveExpress(array(
                'express_com' => $expressCom,
                'express_no' => $expressNo,
                'express_mobile' => $expressMobile,
                'is_external_channel' => 1
            ));

            //再次获取
            $expressDetail = ExpressModel::getExpressDetail($expressCom, $expressNo);
            if (empty($expressDetail)) {
                return $return;
            }
        }

        //修改物流手机号
        if (!empty($expressMobile) && empty($expressDetail['mobile'])) {
            ExpressModel::updateExpressById($expressDetail['id'], array('mobile' => $expressMobile));
        }

        $data = unserialize($expressDetail['data']);

        $content = !empty($data['data']) ? $data['data'] : array();

        //查公司名称
        $companyInfo = ExpressDlycorpModel::getDlycorpByCode($expressCom);

        $return = array(
            'express_com' => $expressDetail['company'],
            'express_channel_com' => $expressDetail['channel_company'],
            'express_name' => !empty($companyInfo) ? $companyInfo['name'] : '',
            'express_no' => $expressDetail['num'],
            'status' => $expressDetail['status'],
            'status_msg' => ExpressModel::$statusMsg[$expressDetail['status']],
            'create_time' => $expressDetail['add_time'] ? date('Y-m-d H:i:s', $expressDetail['add_time']) : '',
            'update_time' => $expressDetail['update_time'] ? date('Y-m-d H:i:s', $expressDetail['update_time']) : '',
            'content' => $content,
        );

        return $return;
    }

    /**
     * @param $expressData
     * @param $errMsg
     * @return bool
     */
    public function saveExpress($expressParams, &$errMsg = '')
    {
        if (empty($expressParams['express_com']) or empty($expressParams['express_no'])) {
            $errMsg = '物流公司或者物流单号有误';
            return false;
        }

        $saveDatas = array();
        $expressData = $this->_formatSaveData($expressParams);

        //多包裹物流需要分别保存物流信息
        if ($expressParams['express_com'] == self::PRESENT_LOGISTICS && !empty($expressParams['express_data'])) {
            $originalCompany = array();
            $originalNum = array();

            //拆分多个物流
            $subExpressData = is_array($expressParams['express_data']) ? $expressParams['express_data'] : json_decode($expressParams['express_data'], true);

            //组装子物流信息并保存
            foreach ($subExpressData['data'] as $data) {
                $saveDatas[] = $this->_formatSaveData(array(
                    'express_com' => $data['logi_code'],
                    'express_no' => $data['logi_no'],
                    'express_mobile' => $expressParams['express_mobile'],
                    'status' => $expressParams['status'],
                    'is_external_channel' => $expressParams['is_external_channel'],
                ));
                $originalCompany[] = $data['logi_name'];
                $originalNum[] = $data['logi_no'];
            }
            $expressData['original_company'] = implode(',', $originalCompany);
            $expressData['original_num'] = implode(',', $originalNum);
        }

        $saveDatas[] = $expressData;

        //保存物流信息
        $errs = array();

        foreach ($saveDatas as $saveData) {

            $saveErrMsg = '';
            $res = $this->saveExpressDetail($saveData, $saveErrMsg);

            if (!$res) {
                $errs[] = $saveData['express_no'] . ':' . $saveErrMsg;
            }
        }

        if (!empty($errs)) {
            $errMsg = implode(',', $errs);
            Logger::Debug('service.express.v4.save.failed', array(
                'params' => $expressParams,
                'save_datas' => $saveDatas,
                'msg' => $errMsg,
            ));
            return false;
        }

        return true;
    }

    /**
     * @param $expressParams
     * @return array
     */
    private function _formatSaveData($expressParams)
    {

        //保存物流信息
        $saveData = array(
            'express_com' => $expressParams['express_com'],
            'express_channel_com' => $expressParams['express_channel_com'] ?? '',
            'express_no' => $expressParams['express_no'],
            'express_mobile' => $expressParams['express_mobile'] ?? '',
            'is_external_channel' => $expressParams['is_external_channel']
        );

        //特殊物流公司编码 使用内部渠道
        if (in_array($expressParams['express_com'], array(self::PRESENT_LOGISTICS, self::WU_WU_LIU_LOGISTICS, self::ZITI_LOGISTICS, self::COUPON_LOGISTICS, self::SELF_LOGISTICS, self::OTHER_LOGISTICS))) {
            $saveData['is_external_channel'] = 0;
        }

        //内部渠道,无需订阅、主动拉取,物流状态、内容根据传递为主
        if ($saveData['is_external_channel'] == 0) {
            $data = $expressParams['express_data'] ?? '';
            $saveData['status'] = $expressParams['status'] ?? ExpressModel::STATUS_EMPTY;
            $saveData['express_data'] = is_array($data) ? serialize($data) : $data;
            $saveData['is_pull'] = ExpressModel::NO_NEED_PULL;
            $saveData['is_subscribe'] = ExpressModel::NO_NEED_SUBSCRIBE;
        } else {
            $saveData['is_pull'] = ExpressModel::NEED_PULL;
            $saveData['is_subscribe'] = ExpressModel::NEED_SUBSCRIBE;
        }

        return $saveData;
    }

    /**
     * @param $expressData
     * @return bool
     */
    public function saveExpressDetail($expressData, &$errMsg = '')
    {
        if ((empty($expressData['express_com']) && empty($expressData['express_channel_com'])) or empty($expressData['express_no'])) {
            $errMsg = '物流公司或者物流单号有误';
            return false;
        }

        //过滤物流单号
        $expressData['express_no'] = $this->_trimSpace($expressData['express_no']);

        if (strlen($expressData['express_no']) > 100) {
            $errMsg = '物流单号长度有误';
            return false;
        }

        // 获取物流详情
        if (!empty($expressData['express_com'])) {
            $expressDetail = ExpressModel::getExpressDetail($expressData['express_com'], $expressData['express_no']);
        } else {
            $expressDetail = ExpressModel::getChannelExpressDetail($expressData['express_channel_com'], $expressData['express_no']);
        }

        //如果没有则需要新增
        if (empty($expressDetail)) {

            $res = $this->addExpressDetail($expressData, $errMsg);

        } else {
            $res = $this->updateExpressDetail($expressDetail, $expressData, $errMsg);
        }

        return $res;
    }

    /**
     * @param $expressData
     * @param $errMsg
     * @return bool
     */
    public function addExpressDetail($expressData, &$errMsg = '') {
        //匹配物流公司编码 和渠道
        $result = $this->_matchExpressChannelAndCom($expressData['express_com'], $expressData['express_channel_com'], $expressData['express_channel'] ?? '', $expressData['is_external_channel'] ? 1 : 0, $errMsg);
        if (!$result) {
            return false;
        }

        $data = $expressData['express_data'] ?? '';
        $insertData = array(
            'company' => $result['express_com'],
            'channel_company' => $result['express_channel_com'],
            'num' => $expressData['express_no'],
            'status' => isset($expressData['status']) ? $expressData['status'] : ExpressModel::STATUS_EMPTY,
            'channel' => $result['express_channel'],
            'is_pull' => isset($expressData['is_pull']) ? $expressData['is_pull'] : ExpressModel::NO_NEED_PULL,
            'is_subscribe' => isset($expressData['is_subscribe']) ? $expressData['is_subscribe'] : ExpressModel::NO_NEED_SUBSCRIBE,
            'original_company' => isset($expressData['original_company']) ? $expressData['original_company'] : '',
            'original_num' => isset($expressData['original_num']) ? $expressData['original_num'] : '',
            'data' => is_array($data) ? serialize($data) : $data,
            'collect_time' => $expressData['collect_time'] ?? null,
            'add_time' => $expressData['add_time'] ?? time(),
            'update_time' => $expressData['update_time'] ?? time(),
            'mobile' => $expressData['express_mobile']
        );

        //如果是内部渠道则无需拉取订阅
        if ($result['is_external_channel'] == 0) {
            $insertData['is_pull'] = ExpressModel::NO_NEED_PULL;
            $insertData['is_subscribe'] = ExpressModel::NO_NEED_SUBSCRIBE;
        }
        $res = ExpressModel::addExpress($insertData);
        if (!$res) {
            $errMsg = '新增物流失败';
            return false;
        }

        return true;
    }

    /**
     * 修改物流
     * @param $expressDetail
     * @param $expressData
     * @param $errMsg
     * @return bool
     */
    public function updateExpressDetail($expressDetail, $expressData, &$errMsg = '') {

        $updateData = array();

        if (isset($expressData['status']) && $expressData['status'] != 0) {
            $updateData['status'] = $expressData['status'];
        }

        if (isset($expressData['is_subscribe'])) {
            $updateData['is_subscribe'] = $expressData['is_subscribe'];
        }

        if (isset($expressData['is_pull'])) {
            $updateData['is_pull'] = $expressData['is_pull'];
        }

        if (isset($expressData['express_mobile'])) {
            $updateData['mobile'] = $expressData['express_mobile'];
        }

        if (isset($expressData['collect_time'])) {
            $updateData['collect_time'] = $expressData['collect_time'];
        }

        if (isset($expressData['express_data']) && !empty($expressData['express_data']) && $expressData['express_data'] != 'a:0:{}') {
            $updateData['data'] = is_array($expressData['express_data']) ? serialize($expressData['express_data']) : $expressData['express_data'];
        }

        if (isset($expressData['original_company'])) {
            $updateData['original_company'] = $expressData['original_company'];
        }

        if (isset($expressData['original_num'])) {
            $updateData['original_num'] = $expressData['original_num'];
        }

        //物流超过1个月则不再进行订阅拉取
        if ($expressDetail['add_time'] < time() - 86400 * 30) {
            $updateData['is_pull'] = ExpressModel::NO_NEED_PULL;
            $updateData['is_subscribe'] = ExpressModel::NO_NEED_SUBSCRIBE;
        }
        //物流10天没有拉取到数据则不再进行订阅拉取
        if ($expressDetail['status'] == ExpressModel::STATUS_EMPTY && $expressData['status'] == ExpressModel::STATUS_EMPTY && $expressDetail['add_time'] < time() - 86400 * 10) {
            $updateData['is_pull'] = ExpressModel::NO_NEED_PULL;
            $updateData['is_subscribe'] = ExpressModel::NO_NEED_SUBSCRIBE;
        }

        //对比需要修改的数据
        foreach ($updateData as $k => $v) {
            if ($v == $expressDetail[$k]) {
                unset($updateData[$k]);
            }
        }

        if (empty($updateData)) {
            return true;
        }

        //如果物流状态变更则发送消息
        if (isset($updateData['status'])) {
            Mq::ExpressUpdate($expressDetail['company'], $expressDetail['num']);
        }

        $res = ExpressModel::updateExpressById($expressDetail['id'], $updateData);
        if (!$res) {
            $errMsg = '保存物流失败';
            return false;
        }

        return true;
    }

    /**
     * 匹配物流渠道和物流公司编码
     * @param $expressCom
     * @param $expressChannelCom
     * @param $expressChannel
     * @param $isExternalChannel
     * @param $errMsg
     * @return array|false|mixed|string[]
     */
    private function _matchExpressChannelAndCom($expressCom = '', $expressChannelCom = '', $expressChannel = '', $isExternalChannel = '', &$errMsg = '')
    {
        //必须2者存一
        if (empty($expressCom) && empty($expressChannelCom)) {
            $errMsg = '物流公司编码不能为空';
            return false;
        }

        //标准、渠道物流
        if (!empty($expressCom) && !empty($expressChannelCom)) {
            //未指定物流渠道
            if (empty($expressChannel)) {
                $errMsg = '物流公司渠道不能为空';
                return false;
            }

            //检查物流渠道
            $config = channelModel::getConfigByChannel($expressChannel);
            if (empty($config)) {
                $errMsg = '物流公司渠道有误';
                return false;
            }

            $isExternalChannel = $config['is_external'];

        } else if (!empty($expressChannelCom) && empty($expressCom)) {
            //未指定物流渠道
            if (empty($expressChannel)) {
                $errMsg = '物流公司渠道不能为空';
                return false;
            }

            //检查物流渠道
            $config = channelModel::getConfigByChannel($expressChannel);
            if (empty($config)) {
                $errMsg = '物流公司渠道有误';
                return false;
            }

            //获取指定映射
            $mapping = CompanyMappingModel::getCompanyMapping($expressChannelCom, $expressChannel, 'channel');
            if (empty($mapping['corp_code'])) {
                $errMsg = '物流公司未匹配:' . $expressChannelCom . '-' . $expressChannel;
                return false;
            }

            $expressCom = $mapping['corp_code'];

            $isExternalChannel = $config['is_external'];

        } else if (!empty($expressCom) && empty($expressChannelCom)) {
            //获取渠道
            $configs = channelModel::getChannelListByFilter(array('is_external' => $isExternalChannel));
            if (empty($configs)) {
                $errMsg = '物流公司渠道有误';
                return false;
            }
            $configs = Common::array_rebuild($configs, 'channel');

            //获取指定渠道的映射
            $mappings = CompanyMappingModel::getCompanyMappingList($expressCom, array_keys($configs));
            if (empty($mappings)) {
                $errMsg = '物流公司未匹配:' . $expressCom;
                return false;
            }

            //遍历映射并匹配渠道
            foreach ($mappings as $mapping) {

                $expressChannel = $mapping['channel'];
                $expressChannelCom = $mapping['logi_code'];

                //优先匹配默认的
                if (!empty($mapping['logi_code']) && isset($configs[$expressChannel]) && $configs[$expressChannel]['is_default'] == 1) {
                    break;
                }
            }
        }

        $result = array(
            'express_com' => $expressCom,
            'express_channel_com' => $expressChannelCom,
            'express_channel' => $expressChannel,
            'is_external_channel' => $isExternalChannel,
        );

        return $result;
    }

    /**
     * @param $callbackData
     * @return bool
     */
    public function expressCallback($callbackData, &$errMsg = '')
    {
        //批量保存物流数据
        $errs = array();

        foreach ($callbackData as $saveData) {

            $saveErrMsg = '';
            $res = $this->saveExpressDetail($saveData, $saveErrMsg);

            if (!$res) {
                $errs[] = $saveData['express_no'] . ':' . $saveErrMsg;
            }
        }

        if (!empty($errs)) {
            $errMsg = implode(',', $errs);
            Logger::Debug('service.express.v4.callback.failed', array(
                'callback_data' => $callbackData,
                'msg' => $errMsg,
            ));
            return false;
        }

        return true;
    }

    /**
     * @param string $expressNo
     * @return array|bool|mixed|string
     */
    public function getAutonumber(string $expressNo, &$errMsg = '')
    {
        $return = array();

        if (empty($expressNo)) {
            return $return;
        }

        //获取单号识别缓存
        $autoNumber = ExpressAutoNumberModel::getAutoNumberByNum($expressNo);
        if (!empty($autoNumber)) {
            return explode(',', $autoNumber['corp_codes']);
        }

        //默认渠道
        $defaultChannelConfig = channelModel::getDefaultChannel(1);

        if (empty($defaultChannelConfig)) {
            $errMsg = '渠道有误';
            return $return;
        }

        $result = array(
            'express_no' => $expressNo,
            'express_channel' => $defaultChannelConfig['channel']
        );
        $result = $this->_request('/ChannelInterop/V1/Express/Express/getAutonumber', $result);
        if (!isset($result['Result']) || $result['Result'] != 'true') {
            return $return;
        }

        //物流映射
        $list = CompanyMappingModel::getCompanyMappingList($result['Data'], $defaultChannelConfig['channel'], 'channel');

        $corpCodes = array_column($list, 'corp_code');

        //保存单号识别缓存
        ExpressAutoNumberModel::addAutoNumber($expressNo, implode(',', $corpCodes));

        return $corpCodes;
    }

    /**
     * @param $path
     * @param $request
     * @return false|void
     */
    private function _request($path, $requestData)
    {
        if (empty($path) or empty($requestData)) {
            return false;
        }

        $openapi_logic = new Openapi();

        $result = $openapi_logic->QueryV2($path, $requestData);
        return $result;
    }

    /**
     * 订阅
     * @param $express
     * @return void
     */
    public function expressSubscribe($express)
    {

        if (empty($express['channel_company']) || empty($express['num']) || empty($express['channel'])) {
            return false;
        }

        //订阅
        $request = array(
            'express_channel_com' => $express['channel_company'],
            'express_no' => $express['num'],
            'express_mobile' => $express['mobile'],
            'express_channel' => $express['channel']
        );
        return $this->_request('/ChannelInterop/V1/Express/Express/subscribe', $request);
    }

    /**
     * 拉取
     * @param $express
     * @return array|mixed
     */
    public function expressPull($express)
    {
        if (empty($express['channel_company']) || empty($express['num']) || empty($express['channel'])) {
            return false;
        }

        //拉取
        $request = array(
            'express_channel_com' => $express['channel_company'],
            'express_no' => $express['num'],
            'express_mobile' => $express['mobile'],
            'express_channel' => $express['channel']
        );
        return $this->_request('/ChannelInterop/V1/Express/Express/pull', $request);
    }
    /**
     * 获取渠道公司列表
     *
     * @param $channel string channel kuaidi100 kdniao supplier
     * @param $filter array keys:corp_code logi_name logi_code channel
     */
    //channel:kuaidi100 kdniao supplier
    public function getChannelCompanyList($channel, $filter = array())
    {
        $return = array();
        if (empty($channel)) {
            return array();
        }

        return CompanyMappingModel::getChannelCompanyList($channel, $filter);
    }

    /**
     * 去除空格
     * @param $str
     * @return string
     */
    private function _trimSpace($str) {
        if (preg_match('/[\x{4e00}-\x{9fa5}]/u', $str)) {
            $str = trim($str, " \t\n\r\x0B");
        } else {
            $str = trim($str, " \t\n\r\0\x0B\xc2\xa0");
        }
        return $str;
    }
}
