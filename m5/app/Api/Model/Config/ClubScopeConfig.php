<?php
namespace App\Api\Model\Config;

use App\Api\Model\BaseModel;
use App\Api\Model\Order\ClubCompany;

class ClubScopeConfig extends BaseModel
{
    protected $primaryKey = 'id';
    protected $connection = 'neigou_club';
    protected $table = 'club_global_scope_config';

    //获取公司配置
    public function getScopeCompanySetByKey($companyId, $key)
    {
        if(empty($companyId) || empty($key)){
            return false;
        }

        $sql = "select * from `club_global_scope_config` where (`scope` = 'company' and `scope_value` = '{$companyId}' and `key` = '{$key}') ";
        $return = $this->selectrow($sql);

        return $return;
    }

    //获取渠道配置
    public function getScopeChannelSetByKey($channel, $key)
    {
        if(empty($channel) || empty($key)){
            return false;
        }

        $sql = "select * from `club_global_scope_config` where (`scope` = 'channel' and `scope_value` = '{$channel}' and `key` = '{$key}') ";

        $return = $this->selectrow($sql);

        return $return;
    }

    //获取默认配置
    public function getScopeAllSetByKey($key)
    {
        if(empty($key)){
            return false;
        }

        $sql = "select * from `club_global_scope_config` where (`scope` = 'all' and `scope_value` = 'all' and `key` = '{$key}') ";
        $return = $this->selectrow($sql);

        return $return;
    }
}
