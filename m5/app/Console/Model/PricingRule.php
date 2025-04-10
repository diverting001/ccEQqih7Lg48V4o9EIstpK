<?php
namespace App\Console\Model;
use App\Api\Model\BaseModel ;

class PricingRule  extends  BaseModel
{

    protected  $table = 'server_pricing_rule' ;

    /*
     *  获取商品所有生效规则
     */
    public function GetAllRule($status=0,$end_time='', $type=2){
        $where  = "type={$type} ";
        if(!empty($status)) $where .= ' and status = '.intval($status);
        if(!empty($end_time)) $where .= ' and ((end_time >= '.intval($end_time).") or (end_time = 0))";
        $sql = "select * from server_pricing_rule where  {$where}";
        $range_list = $this->select($sql);
        return $range_list;
    }

    /*
     * @todo 获取价格规则版本号
     */
    public function GetRuleVersion(){
        $sql    = "select update_time from server_pricing_rule order by update_time desc limit 1";
        $rule_list    = $this->select($sql);
        return $rule_list[0]['update_time'];
    }


    /*
     * @todo 删除规则
     */
    public function DelRule($rule_id) {
        if(empty($rule_id)) return false;
         return  $this->baseDelete(['rule_id' =>intval($rule_id) ]) ;
    }

    /*
     * @todo 获取单条规则信息
     */
    public function GetRuleData($rule_id){
        if(empty($rule_id)) return false;
        $sql = "select * from `server_pricing_rule` where rule_id = {$rule_id} limit 1";
        $rule_list  = $this->db->select($sql);
        if(!empty($rule_list)){
            //获取规则信息
            $rule_list[0]['range']  = app::get('b2c')->model('pricing_range')->GetRangeData($rule_list[0]['range_id']);
            $rule_list[0]['stage']  = app::get('b2c')->model('pricing_stage')->GetStageData($rule_list[0]['stage_id']);
            return $rule_list[0];
        }else{
            return array();
        }
    }

    /*
     * @todo 规则更新
     */
    public function UpRule($rule_id,$rule_data){
        if(empty($rule_data) || empty($rule_id)) return false;
        $set_data = array();
        foreach ($rule_data as $k=>$v){
            $set_data[]    = "`{$k}`='{$v}'";
        }
        $sql = "update `server_pricing_rule` set ".implode(',',$set_data)." where rule_id = {$rule_id}";
        return $this->db->exec($sql);
    }

}
