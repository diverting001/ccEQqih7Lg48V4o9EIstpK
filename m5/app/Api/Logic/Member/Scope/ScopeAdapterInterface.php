<?php
/**
 *Create by PhpStorm
 *User:liangtao
 *Date:2021-7-14
 */

namespace App\Api\Logic\Member\Scope;


interface ScopeAdapterInterface
{
    public function create( $channel, $identifyData );

    public function update( $thirdUniqueCode, $params );

    public function getScopeIdentifyListByRuleBn( $thirdUniqueCode, $page, $limit );

    public function getScopeByRuleBnsAndIdentify( $thirdUniqueCode, $identifyData );

}