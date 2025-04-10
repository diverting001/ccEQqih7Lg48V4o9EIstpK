<?php

namespace App\Api\Model\Message;

use App\Api\V1\Service\Message\Isoftstone\IsoftstoneMessage;
use App\Api\V1\Service\Message\Qywx\QywxPic;
use App\Api\V1\Service\Message\Qywx\QywxText;
use App\Api\V1\Service\Message\Sms\AliyunSms;
use App\Api\V1\Service\Message\Sms\JuHeSms;
use App\Api\V1\Service\Message\Sms\MongateSms;
use App\Api\V1\Service\Message\Mail\MailerMail;
use App\Api\V1\Service\Message\Sms\ShlianluSms;
use App\Api\V1\Service\Message\Sms\WomaiSms;
use App\Api\V1\Service\Message\Kuaishou\KuaishouMessage;
use App\Api\V1\Service\Message\Sms\ChuangLanSms;
use App\Api\V1\Service\Message\Neigou\NeigouMessage;
use App\Api\V1\Service\Message\Sms\ZhaoShangSms;
use App\Api\V1\Service\Message\Shuidifeishu\ShuidifeishuMessage;

class Platform
{
    private $dataCollect;
    /**
     * @var  array $data [
     *          id  手动定义自增不可修改
     *          name  平台名称
     *          message_type 消息类型 1.短信2.邮箱3.软通消息4.企业微信5.站内
     *          class 相关处理类
     * ]
     */
    private $data = [
        ['id' => 1, 'name'=>AliyunSms::PLATFORM_NAME,'class'=>AliyunSms::class,'message_type'=>1],
        ['id' => 2, 'name'=>MongateSms::PLATFORM_NAME,'class'=>MongateSms::class,'message_type'=>1],
        ['id' => 3, 'name'=>MailerMail::PLATFORM_NAME,'class'=>MailerMail::class,'message_type'=>2],
        ['id' => 4, 'name'=>IsoftstoneMessage::PLATFORM_NAME,'class'=>IsoftstoneMessage::class,'message_type'=>3],
        ['id' => 5, 'name'=>WomaiSms::PLATFORM_NAME,'class'=>WomaiSms::class,'message_type'=>3],
        ['id' => 6, 'name'=>JuHeSms::PLATFORM_NAME,'class'=>JuHeSms::class,'message_type'=>1],
        ['id' => 7, 'name'=>QywxPic::PLATFORM_NAME,'class'=>QywxPic::class,'message_type'=>4],
        ['id' => 12, 'name'=>QywxText::PLATFORM_NAME,'class'=>QywxText::class,'message_type'=>4],
        ['id' => 16, 'name' => KuaishouMessage::PLATFORM_NAME, 'class' => KuaishouMessage::class, 'message_type' => 3],
        ['id' => 17, 'name' => ChuangLanSms::PLATFORM_NAME, 'class' => ChuangLanSms::class, 'message_type' => 1],
        ['id' => 18, 'name' => NeigouMessage::PLATFORM_NAME, 'class' => NeigouMessage::class, 'message_type' => 3],
        ['id' => 19, 'name' => ZhaoShangSms::PLATFORM_NAME, 'class' => ZhaoShangSms::class, 'message_type' => 1],
        ['id' => 20, 'name' => ShuidifeishuMessage::PLATFORM_NAME, 'class' =>
            ShuidifeishuMessage::class, 'message_type' => 3],
        ['id' => 21, 'name'=>ShlianluSms::PLATFORM_NAME,'class'=>ShlianluSms::class,'message_type'=>1],

    ];

    public function __construct()
    {
        $this->dataCollect = collect($this->data);
    }

    public function findPlatformById($id)
    {
        return $this->dataCollect->firstWhere('id', $id);
    }

    public function updateConfigAccess($messageType, $conf)
    {
        $db = app('api_db');
        $platform = $this->dataCollect->firstWhere('message_type', $messageType);

        return $db->table('server_message_platform_config')
            ->where('platform_id', $platform['id'])
            ->update(['platform_config'=>$conf]);
    }
}
