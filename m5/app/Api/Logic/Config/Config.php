<?php

namespace App\Api\Logic\Config;
use App\Api\Model\Config\Config as ConfigModel;

class Config
{
    /**
     * 券名称
     *
     * @return string
     */
    public static function getVoucherName($platform = 'all')
    {
       return ConfigModel::findValueByKey('voucher_name', $platform);
    }

    /**
     * 昵称
     *
     * @return string
     */
    public static function getWebNickname($platform  = 'all')
    {
       return ConfigModel::findValueByKey('web_nickname', $platform);
    }

   /**
     * 定位
     *
     * @return string
     */
    public static function getLocation($platform  = 'all')
    {
       return ConfigModel::findValueByKey('service_location', $platform);
    }
}
