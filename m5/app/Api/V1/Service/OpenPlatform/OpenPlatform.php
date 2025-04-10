<?php

namespace App\Api\V1\Service\OpenPlatform;

interface OpenPlatform
{
    //获取access_token
    public function GetAccessToken($paramData);

    //获取公众号用于调用微信JS接口的临时票据
    public function GetTicket($paramData);

    //获取网页授权access_token
    public function GetOauth2AccessToken($paramData);
}
