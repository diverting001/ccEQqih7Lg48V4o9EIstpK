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
    public static function getVoucherName()
    {
       return ConfigModel::findValueByKey('voucher_name');
    }

    /**
     * 昵称
     *
     * @return string
     */
    public static function getWebNickname()
    {
       return ConfigModel::findValueByKey('web_nickname');
    }
}
