<?php

namespace App\Api\Model\Company;

use App\Api\Model\BaseModel;

class ClubCompany extends  BaseModel
{

    protected $connection = 'neigou_club' ;

    protected $table = 'club_third_company';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * @todo   查询公司渠道
     *
     * @param  integer $companyId
     * @return [type]
     */
    public function getCompanyRealChannel($companyId = 0)
    {
        $sql = "SELECT channel FROM club_third_company WHERE internal_id = $companyId and channel != 'aiguanhuai' limit 1";
        $row = $this->selectrow($sql);
        return $row ? $row['channel'] : 'aiguanhuai';
    }


   public function getChannelByCompanyId($internal_id = ''){
        if(!$internal_id) return false;
        $sql = "select * from club_third_company where internal_id = {$internal_id}";
        return  $this->selectrow($sql);
    }


    public function getChannelAndCompanyScope($key,$channel,$companyId){
        $sql = "select * from club_global_scope_config where `key` = '{$key}' AND ((`scope` = 'channel' AND `scope_value` = '{$channel}') OR (`scope` = 'company' AND `scope_value` = {$companyId}))";
        return $this->select($sql);
    }

    public function getScopeByWhereSqlStr($whereSql)
    {
        $sql = "SELECT `scope`, `scope_value`, `key`, `key_value` FROM club_global_scope_config WHERE " . $whereSql;
        return $this->select($sql);
    }


    public function getGcorpIdByCompanyId($companyId)
    {
        $sql = "SELECT `gcorp_id` FROM club_cas_company WHERE company_id=" . $companyId;
        return $this->selectrow($sql);
    }

}
