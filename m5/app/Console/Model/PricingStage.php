<?php
namespace App\Console\Model;
use App\Api\Model\BaseModel ;

/**
 * @todo 商品定价-访问场景
 */
class PricingStage extends  BaseModel {

    protected $table = 'server_pricing_stage' ;
    protected $primaryKey = 'stage_id' ;

    /*
     * @todo 获取访问场景信息
     */
    public function GetStageInfo($stage_id){
        if(empty($stage_id)) return array();
        return $this->getInfoRow(['stage_id' => intval($stage_id)]) ;
    }

    public function AddStage($stage_data){
        if(empty($stage_data)) return false;
        //开启事务
        $this->beginTransaction() ;
        $time   = time();
        $sql = "INSERT INTO `server_pricing_stage` (`type`,`persistence`,`title`,`create_time`,`update_time`)VALUES ('{$stage_data['type']}','{$stage_data['title']}','{$stage_data['persistence']}',{$time},{$time})";
        $stage_id = $this->exec($sql);
        if($stage_id){
            switch ($stage_data['type']){
                case 1:
                    $res    = true;
                    break;
                case 3:
                    $res = $this->AddStageChannel($stage_id,$stage_data['value']);
                    break;
                case 4:
                    $res = $this->AddStageCompany($stage_id,$stage_data['value']);
                    break;
                case 5:
                    $res = $this->AddStageCompanyTag($stage_id,$stage_data['value']);
                    break;
            }
        }
        if(!$res){
            $this->rollBack() ;
            return false;
        }else{
            $this->commit() ;
            return $stage_id;
        }
    }


    /*
     * @todo 获取范围信息
     */
    public function GetStageData($stage_id){
        if(empty($stage_id)) return false;
        $stage_info = $this->GetStageInfo($stage_id);
        if(empty($stage_info)) return array();
        $stage_data = array();
        switch ($stage_info['type']){
            case 3:
                $data   = $this->GetStageChannel($stage_id);
                if(!empty($data)){
                    foreach ($data as $k=>$v){
                        $stage_data[]   = $v['channel_type'];
                    }
                }
                break;
            case 4:
                $data   = $this->GetStageCompany($stage_id);
                if(!empty($data)){
                    foreach ($data as $k=>$v){
                        $stage_data[]   = $v['company_id'];
                    }
                }
                break;
            case 5:
                $data   = $this->GetStageCompanyTag($stage_id);
                if(!empty($data)){
                    foreach ($data as $k=>$v){
                        $stage_data[]   = $v['company_tag'];
                    }
                }
                break;
        }
        $return_data    = array(
            'type'  => $stage_info['type'],
            'value' => $stage_data
        );
        return $return_data;
    }

    /*
     * @todo 删除范围
     */
    public function DelStage($stage_id){
        $stage_id   = intval($stage_id);
        if(empty($stage_id)) return false;
        $stage_info = $this->GetStageInfo($stage_id);
        if(empty($stage_info)) return false;
        //开启事务
        $this->beginTransaction() ;
        $sql = "delete from `server_pricing_stage` where stage_id = {$stage_id}";
        $res = $this->exec($sql);
        if($res){
            switch ($stage_info['type']){
                case 3:
                    $res = $this->DelStageChannel($stage_id);
                    break;
                case 4:
                    $res = $this->DelStageCompany($stage_id);
                    break;
                case 5:
                    $res = $this->DelStageCompanyTag($stage_id);
                    break;
            }
        }
        if(!$res){
            $this->rollBack() ;
        }else{
            $this->commit() ;
        }
        return $res;
    }

    /*
     * @todo 修改场景
     */
    public function UpStage($stage_id,$save_data){
        if(empty($stage_id ) || empty($save_data)) return false;
        $stage_info = $this->GetStageInfo($stage_id);
        if(empty($stage_info)) return false;
        //开启事务
        $this->beginTransaction() ;
        if(!isset($save_data['update_time'])) $save_data['update_time'] = time();
        $value_data = $save_data['value'];
        unset($save_data['value']);
        $set_data   = array();
        foreach ($save_data as $k=>$v){
            $set_data[] = "{$k} = '{$v}'";
        }
        $sql = "update `server_pricing_stage` set ".implode(',',$set_data).' where stage_id = '.$stage_id;
        $res = $this->exec($sql);
        if($res){
            switch ($stage_info['type']){
                case 3:
                    $res = $this->DelStageChannel($stage_id);
                    if($res) $res = $this->AddStageChannel($stage_id,$value_data);
                    break;
                case 4:
                    $res = $this->DelStageCompany($stage_id);
                    if($res) $res = $this->AddStageCompany($stage_id,$value_data);
                    break;
                case 5:
                    $res = $this->DelStageCompanyTag($stage_id);
                    if($res) $res = $this->AddStageCompanyTag($stage_id,$value_data);
                    break;
            }
        }
        if(!$res){
            $this->rollBack() ;
        }else{
            $this->commit() ;
        }
        return $res;
    }

    /*====================================公司=======================*/

    /*
     * @todo 保存公司场景
     */
    private function AddStageCompany($stage_id,$save_data){
        if(empty($save_data) || empty($stage_id)) return false;
        $time   = time();
        foreach ($save_data as $k=>$v){
            $save_data_list[]  = "('{$v}',{$stage_id},{$time},{$time})";
        }
        $sql = "INSERT INTO `server_pricing_stage_company` (`company_id`,`stage_id`,`create_time`,`update_time`) VALUES ".implode(',',$save_data_list);
        $res = $this->exec($sql);
        return $res;
    }

    /*
     * @todo 获取访问场景关联公司
     */
    public function GetStageCompany($stage_id){
        if(empty($stage_id)) return array();
        $sql = "select * from server_pricing_stage_company where stage_id = '".intval($stage_id)."'";
        $coimpany_list = $this->select($sql);
        return $coimpany_list;
    }

    /**
     * @todo 通过场景ID数组批量获取访问场景关联公司
     */
    public function GetStageCompanyByStageIds($stage_ids){
        if(empty($stage_ids)) return array();
        return app('api_db')->connection('neigou_store')
            ->table('server_pricing_stage_company')
            ->whereIn('stage_id',$stage_ids)
            ->get()->map(function ($value){
                return (array)$value;
            })->toArray();
//        $sql = "select * from server_pricing_stage_company where stage_id = '".intval($stage_id)."'";
//        $coimpany_list = $this->select($sql);
//        return $coimpany_list;
    }

    /*
     * @todo 删除公司场景
     */
    private function DelStageCompany($stage_id){
        $stage_id   = intval($stage_id);
        if(empty($stage_id)) return false;
        $sql = "delete from `server_pricing_stage_company` where stage_id = {$stage_id}";
        $res = $this->exec($sql);
        return $res;
    }

    /*====================================渠道=======================*/

    /*
     * @todo 保存渠道场景
     */
    private function AddStageChannel($stage_id,$save_data){
        if(empty($save_data) || empty($stage_id)) return false;
        $time   = time();
        foreach ($save_data as $k=>$v){
            $save_data_list[]  = "('{$v}',{$stage_id},{$time},{$time})";
        }
        $sql = "INSERT INTO `server_pricing_stage_channel` (`channel_type`,`stage_id`,`create_time`,`update_time`) VALUES ".implode(',',$save_data_list);
        $res = $this->exec($sql);
        return $res;
    }

    /*
     * @todo 获取访问场景关联渠道
     */
    public function GetStageChannel($stage_id){
        if(empty($stage_id)) return array();
        $sql = "select * from server_pricing_stage_channel where stage_id = '".intval($stage_id)."'";
        $channel_list = $this->select($sql);
        return $channel_list;
    }

    /**
     * @todo 通过场景ID数组批量获取访问场景关联渠道
     */
    public function GetStageChannelByStageIds($stage_ids){
        if(empty($stage_ids)) return array();
        return app('api_db')->connection('neigou_store')
            ->table('server_pricing_stage_channel')
            ->whereIn('stage_id',$stage_ids)
            ->get()->map(function ($value){
                return (array)$value;
            })->toArray();
//        $sql = "select * from server_pricing_stage_channel where stage_id = '".intval($stage_id)."'";
//        $channel_list = $this->select($sql);
//        return $channel_list;
    }

    /*
     * @todo 删除公司渠道
     */
    private function DelStageChannel($stage_id){
        $stage_id   = intval($stage_id);
        if(empty($stage_id)) return false;
        $sql = "delete from `server_pricing_stage_channel` where stage_id = {$stage_id}";
        $res = $this->exec($sql);
        return $res;
    }


    /*====================================公司标签=======================*/
    /*
     * @todo 获取访问场景关联公司标签
     */
    public function GetStageCompanyTag($stage_id){
        if(empty($stage_id)) return array();
        $sql = "select * from server_pricing_stage_company_tag where stage_id = '".intval($stage_id)."'";
        $coimpany_list = $this->select($sql);
        return $coimpany_list;
    }
    /**
     * @todo 通过场景ID数组批量获取访问场景关联公司标签
     */
    public function GetStageCompanyTagByStageIds($stage_ids){
        if(empty($stage_ids)) return array();
        return app('api_db')->connection('neigou_store')
            ->table('server_pricing_stage_company_tag')
            ->whereIn('stage_id',$stage_ids)
            ->get()->map(function ($value){
                return (array)$value;
            })->toArray();
//        $sql = "select * from server_pricing_stage_company_tag where stage_id = '".intval($stage_id)."'";
//        $coimpany_list = $this->select($sql);
//        return $coimpany_list;
    }

    /**
     * 基于场景id数组批量查询场景信息
     * @param array $stage_ids
     * @return array
     */
    public function GetStageInfoByStageIds(array $stage_ids)
    {
        if(empty($stage_ids)) return array();
        return $this->query()->whereIn('stage_id',$stage_ids)->get()->toArray();
    }

    /*
     * @todo 保存公司标签场景
     */
    private function AddStageCompanyTag($stage_id,$save_data){
        if(empty($save_data) || empty($stage_id)) return false;
        $time   = time();
        foreach ($save_data as $k=>$v){
            $save_data_list[]  = "('{$v}',{$stage_id},{$time},{$time})";
        }
        $sql = "INSERT INTO `server_pricing_stage_company_tag` (`company_tag`,`stage_id`,`create_time`,`update_time`) VALUES ".implode(',',$save_data_list);
        $res = $this->exec($sql);
        return $res;
    }

    /*
     * @todo 删除公司标签
     */
    private function DelStageCompanyTag($stage_id){
        $stage_id   = intval($stage_id);
        if(empty($stage_id)) return false;
        $sql = "delete from `server_pricing_stage_company_tag` where stage_id = {$stage_id}";
        $res = $this->exec($sql);
        return $res;
    }

}
