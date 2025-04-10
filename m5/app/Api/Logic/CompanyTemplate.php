<?php
namespace App\Api\Logic;

use App\Api\Model\Config\ClubScopeConfig as ClubScopeConfigModel;
use App\Api\Model\Company\ClubCompany as ClubCompanyModel;
use App\Api\Model\Message\Channel as ChannelModel;

class CompanyTemplate
{
    /**
     * Notes:获取公司短信模板配置
     * User: mazhenkang
     * Date: 2024/8/26 下午1:59
     */
    public function getBusinessTemplateByCompany($companyId, $businessKey = array(), $isScopeFill = 0)
    {
        if (empty($companyId) || empty($businessKey)) {
            return [];
        }

        $global_key = 'message_center_template';
        $clubScopeConfigModel = new ClubScopeConfigModel;
        $configInfo = $clubScopeConfigModel->getScopeCompanySetByKey($companyId,$global_key);
        $templateConfig = json_decode($configInfo['key_value'], true);

        $channelTemplateConfig = $defaultTemplateConfig = [];
        if ($isScopeFill == 1) {
            $clubCompanyModel = new ClubCompanyModel();
            $companyChannel = $clubCompanyModel->getCompanyRealChannel($companyId);
            $channelConfigInfo = $clubScopeConfigModel->getScopeChannelSetByKey($companyChannel,$global_key);
            $channelTemplateConfig = json_decode($channelConfigInfo['key_value'], true);

            $defaultConfigInfo = $clubScopeConfigModel->getScopeAllSetByKey($global_key);
            $defaultTemplateConfig = json_decode($defaultConfigInfo['key_value'], true);
        }

        $return = [];
        foreach ($businessKey as $k) {
            $configItem = $templateConfig[$k] ?: null;
            if(empty($configItem) && $isScopeFill == 1) {
                $configItem = $channelTemplateConfig[$k] ?: null;

                if(empty($configItem)){
                    $configItem = $defaultTemplateConfig[$k] ?: null;
                }
            }
            if (empty($configItem['channel_name']) && !empty($configItem['channel_id'])) {
                $channelModel = new ChannelModel();
                $channelInfo = $channelModel->findChannelRows(['channel.id' => $configItem['channel_id']]);
                $configItem['channel_name'] = $channelInfo->channel;
                $configItem['channel_type'] = $channelInfo->type;
            }
            $return[$k] = $configItem;
        }

        return $return;
    }

    public function getBasicTemplateByBusinessKey($businessKey = array())
    {
        if (empty($businessKey)) {
            return [];
        }
        $global_key = 'message_center_template';
        $clubScopeConfigModel = new ClubScopeConfigModel;

        $configInfo = $clubScopeConfigModel->getScopeAllSetByKey($global_key);
        $templateConfig = json_decode($configInfo['key_value'], true);
        if (empty($templateConfig) || !is_array($templateConfig)) {
            return [];
        }

        $return = [];
        $channelIds = [];
        foreach ($businessKey as $k) {
            $configItem = $templateConfig[$k] ?: null;
            if (empty($configItem['channel_name']) && !empty($configItem['channel_id'])) {
                $channelIds[] = $configItem['channel_id'];
            }

            $return[$k] = $configItem;
        }

        if (!empty($channelIds)) {
            $channelModel = new ChannelModel();
            $channels = $channelModel->findList(['channel.id' =>
                ['in' => $channelIds]]);
            $channelsByKey = array_column($channels->toArray(), null, 'id');
            foreach ($return as $k => &$v) {
                if (isset($channelsByKey[$v['channel_id']])) {
                    $v['channel_name'] = $channelsByKey[$v['channel_id']]->channel;
                    $v['channel_type'] = $channelsByKey[$v['channel_id']]->type;
                }
            }
        }

        return $return;
    }
}
