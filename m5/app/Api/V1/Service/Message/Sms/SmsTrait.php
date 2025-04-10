<?php
namespace App\Api\V1\Service\Message\Sms;

use App\Api\Model\Message\MessageTemplate;

trait SmsTrait
{
    public function checkChildParam($param)
    {
        $templatePlatformData = [];
        if ($param['receiver']) {
            foreach ($param['receiver'] as $mobile) {
                if (!$this->isMobile($mobile)) {
                    return '手机号错误';
                }
            }
            if (isset($templatePlatformData[$this->channelId][$param['template_id']])) {
                $templateRow = $templatePlatformData[$this->channelId][$param['template_id']];
            } else {
                $templateModel = new MessageTemplate();
                $templateRow = $templateModel->getPlatformTemplate($param['template_id'], $this->channelId);
                $templatePlatformData[$this->channelId][$param['template_id']] = $templateRow;
            }
            if (empty($templateRow)) {
                return "模板id{$param['template_id']}不存在";
            }

        }
        return true;
    }

    private function isMobile($mobile)
    {
        return is_numeric($mobile) && strlen($mobile) == 11;
    }

}
