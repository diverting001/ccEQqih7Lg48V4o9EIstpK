<?php
/**
 *Create by PhpStorm
 *User:liangtao
 *Date:2021-7-14
 */

namespace App\Api\Logic\Member\Scope;

use App\Api\Logic\Member\Scope\Channel\KS;
use App\Api\Logic\Member\Scope\Channel\NG;

class ScopeAdapter implements ScopeAdapterInterface
{
    private $scopeAdapter;

    public function __construct( $channel = '' )
    {
        switch ( strtoupper( $channel ) )
        {
            case 'NG':
                $this->scopeAdapter = new NG();
                break;
            case 'KS':
                $this->scopeAdapter = new KS();
                break;
            default:
                throw new \Exception( '未找到数据对象源' );
        }
    }

    public function create( $channel = '', $identifyData = [] )
    {
        return $this->scopeAdapter->create( $channel, $identifyData );
    }

    public function update( $thirdUniqueCode = '', $params = [] )
    {
        return $this->scopeAdapter->update( $thirdUniqueCode, $params );
    }

    public function getScopeIdentifyListByRuleBn( $thirdUniqueCode = '', $page = 1, $limit = 20 )
    {
        return $this->scopeAdapter->getScopeIdentifyListByRuleBn( $thirdUniqueCode, $page, $limit );
    }

    public function getScopeByRuleBnsAndIdentify( $thirdUniqueCodes = [], $identifyData = [] )
    {
        return $this->scopeAdapter->getScopeByRuleBnsAndIdentify( $thirdUniqueCodes, $identifyData );
    }
}