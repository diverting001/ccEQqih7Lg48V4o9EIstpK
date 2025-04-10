<?php


namespace App\Api\Model\Order;

use App\Api\Model\BaseModel;

class ClubCompany extends  BaseModel
{
    protected $primaryKey = 'id' ;
    protected $connection = 'neigou_club' ;
    protected $table ='club_third_company' ;

    /**
     * @todo   查询公司渠道
     *
     * @param  integer $companyId
     * @return [type]
     */
    public function getCompanyRealChannel($companyId = 0)
    {
        $sql = "SELECT channel FROM club_third_company WHERE internal_id = $companyId and channel != 'aiguanhuai'";
        $row = $this->selectrow($sql);
        return $row ? $row['channel'] : 'aiguanhuai';
    }


    // 获取公司平台
    public function getCompanyPlatform($companyId)
    {
        $return = defined('PSR_PLATFORM') ? PSR_PLATFORM : 'neigou';
        if (empty($companyId))
        {
            return $return;
        }

        $channel = $this->getCompanyRealChannel($companyId);

        $sql = "SELECT * FROM club_global_scope_config where `key` = 'platform' AND ((`scope` = 'channel' AND `scope_value` = '{$channel}') OR (`scope` = 'company' AND `scope_value` = {$companyId}))";

        $platformInfo = $this->selectrow($sql);

        if ($platformInfo)
        {
            $return = $platformInfo['key_value'];
        }

        return $return;
    }
}
